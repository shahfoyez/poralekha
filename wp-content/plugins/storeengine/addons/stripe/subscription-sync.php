<?php
/**
 * Native Stripe Billing — subscription sync.
 *
 * Bridges StoreEngine subscriptions and real Stripe (Billing) subscriptions when
 * native mode is enabled:
 *  - flags new StoreEngine subscriptions as externally-managed (so the scheduler
 *    never off-session-charges them — Stripe bills them);
 *  - creates the matching Stripe subscriptions at checkout;
 *  - cancels the Stripe subscription when the StoreEngine one is cancelled.
 *
 * Mirrors the Paddle externally-managed pattern (addons/paddle/hooks.php).
 *
 * NOTE: the checkout *charge* path (process_native_subscription_payment in
 * GatewayStripe) is the part that must be validated in a Stripe sandbox before
 * production use — see the plan's "Risks" section.
 */

namespace StoreEngine\Addons\Stripe;

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Addons\Subscription\Classes\SubscriptionCollection;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SubscriptionSync {

	protected static GatewayStripe $gateway;

	/** Guards against a webhook→StoreEngine→Stripe cancel feedback loop. */
	protected static bool $syncing_from_webhook = false;

	public static function init( GatewayStripe $gateway ): void {
		self::$gateway = $gateway;

		// Flag StoreEngine subscriptions as externally-managed (native mode).
		add_action( 'storeengine/subscription/checkout_subscription_created', [ __CLASS__, 'link_stripe_subscription' ], 10, 2 );

		// Cancel the Stripe subscription when the StoreEngine one is cancelled.
		add_action( 'storeengine/subscription/status_cancelled', [ __CLASS__, 'on_subscription_cancelled' ] );
	}

	public static function is_native(): bool {
		return self::$gateway->is_native_subscriptions_enabled();
	}

	/**
	 * On subscription creation: when paid via Stripe in native mode, flag the
	 * subscription as manual-renewal so StoreEngine's scheduler never charges it
	 * (Stripe does). The actual Stripe subscription is created by
	 * create_for_order() during payment.
	 *
	 * @param Subscription $subscription
	 * @param Order        $order
	 */
	public static function link_stripe_subscription( $subscription, $order ): void {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_payment_method' ) || 'stripe' !== $order->get_payment_method() ) {
			return;
		}
		if ( ! self::is_native() ) {
			return;
		}
		if ( method_exists( $subscription, 'set_requires_manual_renewal' ) ) {
			$subscription->set_requires_manual_renewal( true );
			$subscription->save();
		}
	}

	/**
	 * Create a native Stripe subscription for every StoreEngine subscription on
	 * the order, charging each first invoice off-session with the saved default
	 * payment method. Stores `_stripe_subscription_id` on each subscription and
	 * the order.
	 *
	 * @param Order  $order              The parent order.
	 * @param string $customer_id        Stripe customer id (cus_…).
	 * @param string $payment_method_id  Stripe payment method id (pm_…).
	 * @param array  $extra_invoice_items One-time items to bill on the FIRST
	 *                                    subscription's first invoice — each
	 *                                    ['name','amount','quantity'].
	 *
	 * @return array{created:string[], error:?\WP_Error}
	 */
	public static function create_for_order( Order $order, string $customer_id, string $payment_method_id, array $extra_invoice_items = [] ): array {
		$created = [];

		if ( ! class_exists( Subscription::class ) ) {
			return [ 'created' => $created, 'error' => new \WP_Error( 'subscriptions_unavailable', __( 'Subscriptions addon unavailable.', 'storeengine' ) ) ];
		}

		$subscriptions = SubscriptionCollection::get_subscriptions_for_order( $order->get_id(), 'parent' );
		$currency      = $order->get_currency();
		$first         = true;

		foreach ( $subscriptions as $subscription ) {
			// Already linked (idempotency on retry).
			if ( $subscription->get_meta( '_stripe_subscription_id' ) ) {
				continue;
			}

			$items = self::build_items( $subscription );
			if ( empty( $items ) ) {
				continue;
			}

			$args = [
				'currency' => $currency,
				'metadata' => [
					'storeengine_subscription_id' => $subscription->get_id(),
					'storeengine_order_id'        => $order->get_id(),
				],
			];

			$trial_days = (int) ( method_exists( $subscription, 'get_trial_days' ) ? $subscription->get_trial_days() : 0 );
			if ( $subscription->get_trial() && $trial_days > 0 ) {
				$args['trial_period_days'] = $trial_days;
			}

			// Bill any one-time items on the first subscription's first invoice.
			if ( $first && ! empty( $extra_invoice_items ) ) {
				$args['add_invoice_items'] = $extra_invoice_items;
			}

			try {
				$stripe_subscription = StripeService::init()->create_subscription( $customer_id, $items, $payment_method_id, $args );
			} catch ( \Throwable $e ) {
				Helper::log_error( $e );

				return [ 'created' => $created, 'error' => new \WP_Error( 'stripe_subscription_failed', $e->getMessage() ) ];
			}

			$subscription->update_meta_data( '_stripe_subscription_id', $stripe_subscription->id );
			$subscription->update_meta_data( '_stripe_customer_id', $customer_id );
			$subscription->update_meta_data( '_stripe_source_id', $payment_method_id );
			$subscription->set_requires_manual_renewal( true );
			$subscription->save();

			// The first record found by resolve_subscription() is via the order;
			// also stamp the order so the webhook can map back.
			$order->update_meta_data( '_stripe_subscription_id', $stripe_subscription->id );

			$created[] = $stripe_subscription->id;
			$first     = false;
		}

		$order->save();

		return [ 'created' => $created, 'error' => null ];
	}

	/**
	 * Build inline Stripe price_data items from a StoreEngine subscription's
	 * product line items + billing schedule.
	 */
	protected static function build_items( Subscription $subscription ): array {
		$interval       = $subscription->get_payment_duration_type();
		$interval_count = (int) $subscription->get_payment_duration();
		$items          = [];

		foreach ( $subscription->get_line_product_items() as $line_item ) {
			$quantity = max( 1, (int) $line_item->get_quantity() );
			// Recurring unit amount = line subtotal / qty.
			$subtotal = (float) ( method_exists( $line_item, 'get_subtotal' ) ? $line_item->get_subtotal() : $line_item->get_total() );
			$unit     = $quantity > 0 ? round( $subtotal / $quantity, 4 ) : $subtotal;

			$items[] = [
				'name'           => $line_item->get_name(),
				'amount'         => $unit,
				'interval'       => $interval,
				'interval_count' => $interval_count,
				'quantity'       => $quantity,
			];
		}

		return $items;
	}

	/**
	 * Build the one-time invoice-item line for a subscription's setup/signup fee.
	 *
	 * Native Stripe subscriptions bill the CLEAN recurring price — the setup fee
	 * is stripped from the recurring cart (Subscription\Hooks::
	 * set_subscription_prices_for_calculation() returns 0 for the fee under the
	 * `recurring_total` calculation), so it never rides along on the recurring
	 * amount. Unlike order-total gateways (Razorpay/PayPal), that means the fee
	 * is NEVER collected unless we add it explicitly to the subscription's first
	 * invoice via `add_invoice_items`. This builds that line.
	 *
	 * The fee is stored per-unit, so it's charged `quantity` times to match the
	 * order subtotal the customer saw at checkout (the same rule the Paddle
	 * adapter follows). Returns null when there's no fee to bill.
	 *
	 * @param bool   $has_setup_fee   Whether the line item carries a setup fee.
	 * @param float  $setup_fee_price Per-unit setup fee amount.
	 * @param string $setup_fee_name  Display name (falls back to "Setup Fee").
	 * @param int    $quantity        Line quantity.
	 *
	 * @return array{name:string,amount:float,quantity:int}|null
	 */
	public static function build_setup_fee_invoice_item( bool $has_setup_fee, float $setup_fee_price, string $setup_fee_name, int $quantity ): ?array {
		if ( ! $has_setup_fee || $setup_fee_price <= 0 ) {
			return null;
		}

		return [
			'name'     => '' !== $setup_fee_name ? $setup_fee_name : __( 'Setup Fee', 'storeengine' ),
			'amount'   => round( $setup_fee_price, 4 ),
			'quantity' => max( 1, $quantity ),
		];
	}

	/**
	 * Cancel the Stripe subscription when the StoreEngine subscription is
	 * cancelled (unless this cancellation originated from a Stripe webhook).
	 *
	 * @param Subscription $subscription
	 */
	public static function on_subscription_cancelled( $subscription ): void {
		if ( self::$syncing_from_webhook ) {
			return;
		}
		if ( ! is_object( $subscription ) || ! method_exists( $subscription, 'get_meta' ) ) {
			return;
		}

		$stripe_subscription_id = $subscription->get_meta( '_stripe_subscription_id' );
		if ( ! $stripe_subscription_id ) {
			return;
		}

		try {
			StripeService::init()->cancel_subscription( $stripe_subscription_id );
		} catch ( \Throwable $e ) {
			Helper::log_error( $e );
		}
	}

	/** Called by the webhook handler to suppress the cancel-sync feedback loop. */
	public static function set_syncing_from_webhook( bool $value ): void {
		self::$syncing_from_webhook = $value;
	}
}
