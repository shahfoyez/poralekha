<?php

namespace StoreEngine\Addons\Paddle;

use StoreEngine;
use StoreEngine\Classes\Cart;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Interfaces\GatewayAdapterInterface;
use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\Utils\ArrayUtil;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;

class GatewayPaddle extends PaymentGateway implements GatewayAdapterInterface {

	public function __construct() {
		$this->setup();

		$this->init_admin_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
	}

	protected function setup() {
		$this->id                 = 'paddle';
		$this->icon               = apply_filters( 'storeengine/paddle_icon', Helper::get_assets_url( 'images/payment-methods/paddle.svg' ) );
		$this->method_title       = __( 'Paddle', 'storeengine' );
		$this->method_description = __( 'Paddle’s checkout securely processes your payment.', 'storeengine' );
		$this->has_fields         = true;
		$this->verify_config      = true;
		$this->supports           = [
			'products',
			'refunds',
			'subscriptions',
			'multiple_subscriptions',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_date_changes',
			'subscription_payment_method_change_admin',
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change',
		];
	}

	/**
	 * Build the Paddle webhook REST URL safely.
	 *
	 * Admin fields are built while StoreEngine boots on `plugins_loaded` (via the
	 * settings AJAX handler), which is BEFORE WordPress instantiates the global
	 * `$wp_rewrite`. Calling rest_url() then fatals with
	 * "Call to a member function using_index_permalinks() on null". Instantiating
	 * `$wp_rewrite` on demand makes rest_url() safe; core replaces it later in
	 * wp-settings.php with an identical instance, so this has no side effects.
	 */
	protected function get_webhook_url(): string {
		if ( empty( $GLOBALS['wp_rewrite'] ) ) {
			$GLOBALS['wp_rewrite'] = new \WP_Rewrite(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		return esc_url_raw( rest_url( STOREENGINE_PLUGIN_SLUG . '/v1/payment/paddle/webhook' ) );
	}

	protected function init_admin_fields() {
		$webhook_url = $this->get_webhook_url();

		$this->admin_fields = [
			'title'                => [
				'label'    => __( 'Title', 'storeengine' ),
				'type'     => 'safe_text',
				'tooltip'  => __( 'Payment method title that the customer will see on your checkout.', 'storeengine' ),
				'default'  => __( 'Paddle', 'storeengine' ),
				'priority' => 0,
			],
			'description'          => [
				'label'    => __( 'Description', 'storeengine' ),
				'type'     => 'textarea',
				'tooltip'  => __( 'Payment method description that the customer will see on your website.', 'storeengine' ),
				'default'  => __( 'Process payments securely with Paddle.', 'storeengine' ),
				'priority' => 0,
			],
			'is_production'        => [
				'label'    => __( 'Is Live Mode?', 'storeengine' ),
				'tooltip'  => __( 'Enable Stripe Live (Production) Mode.', 'storeengine' ),
				'type'     => 'checkbox',
				'default'  => true,
				'priority' => 0,
			],
			'client_token'         => [
				'label'        => __( 'Client Side Token', 'storeengine' ),
				'type'         => 'text',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => true ],
				'description'  => __( 'This can\'t be verify through saving, Verification only happen through checkout process.', 'storeengine' ),
				'autocomplete' => 'none',
				'required'     => true,
			],
			'api_key'              => [
				'label'        => __( 'API Key', 'storeengine' ),
				'type'         => 'password',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => true ],
				'autocomplete' => 'none',
				'required'     => true,
			],
			'test_client_token'    => [
				'label'        => __( 'Client Side Token (Sandbox)', 'storeengine' ),
				'type'         => 'text',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => false ],
				'description'  => __( 'This can\'t be verify through saving, Verification only happen through checkout process.', 'storeengine' ),
				'autocomplete' => 'none',
				'required'     => true,
			],
			'test_api_key'         => [
				'label'        => __( 'API Key (Sandbox)', 'storeengine' ),
				'type'         => 'password',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => false ],
				'autocomplete' => 'none',
				'required'     => true,
			],
			'webhook_url'          => [
				'label'       => __( 'Webhook URL', 'storeengine' ),
				'type'        => 'copy_text',
				'value'       => $webhook_url,
				'priority'    => 0,
				'description' => __( 'Add this URL as a notification destination in your Paddle dashboard, then subscribe it to the transaction.completed, transaction.paid, subscription.canceled and subscription.past_due events.', 'storeengine' ),
			],
			'webhook_secret'       => [
				'label'        => __( 'Webhook Secret', 'storeengine' ),
				'type'         => 'password',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => true ],
				'description'  => __( 'Optional but recommended. Paste the signing secret from your Paddle (live) notification destination to verify incoming webhooks.', 'storeengine' ),
				'autocomplete' => 'none',
				'required'     => false,
			],
			'test_webhook_secret'  => [
				'label'        => __( 'Webhook Secret (Sandbox)', 'storeengine' ),
				'type'         => 'password',
				'priority'     => 0,
				'dependency'   => [ 'is_production' => false ],
				'description'  => __( 'Optional but recommended. Paste the signing secret from your Paddle sandbox notification destination to verify incoming webhooks.', 'storeengine' ),
				'autocomplete' => 'none',
				'required'     => false,
			],
			'default_tax_category' => [
				'label'        => __( 'Default Tax Category', 'storeengine' ),
				'type'         => 'select',
				'priority'     => 0,
				'description'  => __( 'Select the default tax category for products(if tax category not selected) sold through this payment method. Selected tax category must be enabled on your Paddle account.', 'storeengine' ),
				'options'      => [
					[
						'label' => __( 'Digital Goods', 'storeengine' ),
						'value' => 'digital-goods',
					],
					[
						'label' => __( 'eBooks', 'storeengine' ),
						'value' => 'ebooks',
					],
					[
						'label' => __( 'Implementation Services', 'storeengine' ),
						'value' => 'implementation-services',
					],
					[
						'label' => __( 'Professional Services', 'storeengine' ),
						'value' => 'professional-services',
					],
					[
						'label' => __( 'SaaS', 'storeengine' ),
						'value' => 'saas',
					],
					[
						'label' => __( 'Software Programming Services', 'storeengine' ),
						'value' => 'software-programming-services',
					],
					[
						'label' => __( 'Standard', 'storeengine' ),
						'value' => 'standard',
					],
					[
						'label' => __( 'Training Services', 'storeengine' ),
						'value' => 'training-services',
					],
					[
						'label' => __( 'Website Hosting', 'storeengine' ),
						'value' => 'website-hosting',
					],
				],
				'default'      => 'digital-goods',
				'autocomplete' => 'none',
				'required'     => true,
			],
			'tax_mode'             => [
				'label'    => __( 'Prices Tax Mode', 'storeengine' ),
				'type'     => 'select',
				'priority' => 0,
				'options'  => [
					[
						'label' => __( 'Internal', 'storeengine' ),
						'value' => 'internal',
					],
					[
						'label' => __( 'External', 'storeengine' ),
						'value' => 'external',
					],
					[
						'label' => __( 'Account Setting', 'storeengine' ),
						'value' => 'account_setting',
					],
				],
				'default'  => 'internal',
				'required' => true,
			],
			'checkout_theme'       => [
				'label'    => __( 'Paddle Theme', 'storeengine' ),
				'type'     => 'select',
				'priority' => 0,
				'options'  => [
					[
						'label' => __( 'Light', 'storeengine' ),
						'value' => 'light',
					],
					[
						'label' => __( 'Dark', 'storeengine' ),
						'value' => 'dark',
					],
				],
				'default'  => 'light',
				'required' => true,
			],
			'checkout_variant'     => [
				'label'    => __( 'Checkout Variant', 'storeengine' ),
				'type'     => 'select',
				'priority' => 0,
				'options'  => [
					[
						'label' => __( 'One page', 'storeengine' ),
						'value' => 'one-page',
					],
					[
						'label' => __( 'Multi page', 'storeengine' ),
						'value' => 'multi-page',
					],
				],
				'default'  => 'one-page',
				'required' => true,
			],
		];
	}

	/**
	 * Verify Config.
	 *
	 * @param array $config
	 *
	 * @return void
	 * @throws StoreEngineException
	 */
	public function verify_config( array $config ) {
		$is_production = $config['is_production'] ?? true;
		if ( $is_production ) {
			$client_token = $config['client_token'] ?? '';
			$api_key      = $config['api_key'] ?? '';
		} else {
			$client_token = $config['test_client_token'] ?? '';
			$api_key      = $config['test_api_key'] ?? '';
		}

		if ( ! $client_token ) {
			throw new StoreEngineException( esc_html__( 'Paddle Client Side Token is required.', 'storeengine' ), 'client-token-is-required', 400 );
		}

		if ( ! $api_key ) {
			throw new StoreEngineException( esc_html__( 'Paddle API Key is required.', 'storeengine' ), 'api-key-is-required', 400 );
		}

		if ( ! $this->is_currency_supported() ) {
			throw new StoreEngineException(
				sprintf(
				/* translators: %1$s the shop currency, %2$s the Paddle currency support page link opening HTML tag, %3$s the link ending HTML tag. */
					esc_html__(
						'Attention: Your current StoreEngine store currency (%1$s) is not supported by Paddle. Please update your store currency to one that is supported by Paddle to ensure smooth transactions. Visit the %2$sPaddle currency support page%3$s for more information on supported currencies.',
						'storeengine'
					),
					esc_html( Formatting::get_currency() ),
					'<a href="' . esc_url( 'https://developer.paddle.com/concepts/sell/supported-currencies#supported-payments' ) . '" target="_blank">',
					'</a>'
				),
				'currency-not-supported',
				null,
				400
			);
		}

		$result = PaddleService::validate_api_key( $api_key, $is_production );

		if ( ! $result ) {
			throw new StoreEngineException( esc_html__( 'Paddle API Key Is Invalid! Please update API key.', 'storeengine' ), 'paddle-api-key-is-invalid', 400 );
		}
	}

	public function is_currency_supported( string $currency = null ): bool {
		if ( ! $currency ) {
			$currency = Formatting::get_currency();
		}

		return in_array( $currency, PaddleService::get_supported_currencies(), true );
	}

	public function is_available(): bool {
		if ( Helper::is_add_payment_method_page() && ! $this->saved_cards ) {
			return false;
		}

		if ( Helper::is_request( 'admin' ) && Helper::is_request( 'ajax' ) ) {
			return parent::is_available();
		}

		if ( Helper::is_request( 'ajax' ) && isset( $_REQUEST['payment_method'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return parent::is_available();
		}

		if ( ! Helper::is_dashboard() && StoreEngine::init()->cart ) {
			/**
			 * Paddle doesn't allow payment for physical products.
			 *
			 * @link https://paddle.com/help/start/intro-to-paddle/what-am-i-not-allowed-to-sell-on-paddle
			 */
			if ( ! $this->is_currency_supported() || StoreEngine::init()->cart->needs_shipping() ) {
				return false;
			}
		}

		return parent::is_available();
	}

	/**
	 * @throws StoreEngineException
	 */
	public function validate_minimum_order_amount( $order ) {
		if ( $order->get_total() * 100 < PaddleService::get_minimum_amount() ) {
			throw new StoreEngineException(
				wp_kses_post(
					sprintf(
					/* translators: %1$s. Minimum allowed amount (including currency symbol) by the gateway. */
						__( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'storeengine' ),
						Formatting::price( PaddleService::get_minimum_amount() / 100 )
					)
				),
				'did-not-meet-minimum-amount'
			);
		}
	}

	public function process_payment( Order $order ) {
		$transaction_id = $order->get_transaction_id();

		if ( ! $transaction_id ) {
			return [];
		}

		$transaction = PaddleService::init()->get_transaction( $transaction_id );

		if ( is_wp_error( $transaction ) ) {
			return [];
		}

		return $this->complete_order_from_transaction( $order, $transaction );
	}

	/**
	 * Verify a Paddle transaction, reconcile its line items onto the order, and
	 * advance the order to a paid state. Shared by the client-side
	 * process_payment() flow and the server-to-server webhook reconciliation
	 * (StoreEngine\Addons\Paddle\API::handle_webhook).
	 *
	 * Idempotent: returns a success result without re-processing if the order is
	 * already paid (guards against duplicate `transaction.completed` /
	 * `transaction.paid` webhook deliveries).
	 *
	 * @param Order  $order       The order to complete.
	 * @param object $transaction Paddle transaction data (->status, ->items, ->custom_data, ->subscription_id).
	 *
	 * @return array { result: 'success', redirect: string } on success, or [] when the
	 *               transaction does not authorize this order.
	 */
	public function complete_order_from_transaction( Order $order, $transaction, bool $trusted = false ): array {
		// Idempotency — already paid, nothing to do.
		if ( $order->is_paid() ) {
			return [
				'result'   => 'success',
				'redirect' => $order->get_checkout_order_received_url(),
			];
		}

		$transaction_statuses = [ 'completed', 'paid' ];

		// Paddle returns custom_data values as strings; cast both sides so a
		// string transaction id never silently fails against the int order id.
		$merchant_order_id = isset( $transaction->custom_data->merchant_order_id )
			? (int) $transaction->custom_data->merchant_order_id
			: 0;

		if ( ! in_array( $transaction->status, $transaction_statuses, true ) ) {
			return [];
		}

		// Ownership guard: the transaction must reference this order via
		// custom_data.merchant_order_id — UNLESS the caller already established the
		// link another way (e.g. the webhook's email fallback), where that field is
		// legitimately absent.
		if ( ! $trusted && $merchant_order_id !== (int) $order->get_id() ) {
			return [];
		}

		$order_items = [];

		foreach ( $order->get_items() as $order_item ) {
			$order_items[ $order_item->get_price_id() ] = [
				'item_id'                  => $order_item->get_id(),
				'quantity'                 => $order_item->get_quantity(),
				'product_id'               => $order_item->get_product_id(),
				'variation_id'             => $order_item->get_variation_id() ?? null,
				'is_digital_auto_complete' => $order_item->get_digital_auto_complete(),
				'paid'                     => false,
			];
		}

		foreach ( $transaction->items as $transaction_item ) {
			$price_id = $transaction_item->price->custom_data->merchant_price_id ?? null;
			if ( ! $price_id || ! isset( $order_items[ $price_id ] ) ) {
				continue;
			}

			$selected_order_item              = $order_items[ $price_id ];
			$order_items[ $price_id ]['paid'] = true;

			if ( $transaction_item->quantity !== $selected_order_item['quantity'] ) {
				$order_item = $order->get_items()[ $selected_order_item['item_id'] ];
				$order_item->set_quantity( $transaction_item->quantity );
				$order_item->set_subtotal( $transaction_item->quantity * $order_item->get_total() );
				$order_item->set_total( $transaction_item->quantity * $order_item->get_total() );
				$order_item->save();
			}
		}

		foreach ( $order_items as $order_item ) {
			if ( $order_item['paid'] ) {
				continue;
			}
			$order->remove_item( $order_item['item_id'] );
			unset( $order_items[ $order_item['item_id'] ] );
		}

		// Persist the Paddle subscription id (when this transaction created one)
		// so recurring-renewal webhooks can find the originating subscription.
		if ( ! empty( $transaction->subscription_id ) ) {
			$order->update_meta_data( '_paddle_subscription_id', $transaction->subscription_id );
		}

		// Mark paid + advance status. mark_as_paid_force() handles whichever state
		// the order is in — `pending_payment` (client-side place ran) or `draft`
		// (stranded order reconciled by webhook) — and runs the full flow.
		$order->mark_as_paid_force( _x( 'Payment already done.', 'Paddle payment method', 'storeengine' ) );
		$order->calculate();
		$order->save();

		return [
			'result'   => 'success',
			'redirect' => $order->get_checkout_order_received_url(),
		];
	}

	/**
	 * Process a refund through Paddle's adjustments API.
	 *
	 * Submits a refund adjustment against the order's Paddle transaction. Note:
	 * Paddle reviews refunds — the adjustment is usually created `pending_approval`
	 * and the money moves once Paddle/the seller approves it. Returning true means
	 * the refund was successfully *requested* (so StoreEngine records it).
	 *
	 * @param int        $order_id
	 * @param float|null $amount  Amount to refund; null/≥ total = full refund.
	 * @param string     $reason
	 *
	 * @return bool|WP_Error
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = Helper::get_order( $order_id );

		if ( is_wp_error( $order ) || ! $order ) {
			return new WP_Error( 'paddle_refund_invalid_order', __( 'Invalid order.', 'storeengine' ) );
		}

		$transaction_id = $order->get_transaction_id() ?: $order->get_meta( '_paddle_transaction_id' );

		if ( ! $transaction_id ) {
			return new WP_Error( 'paddle_refund_no_transaction', __( 'No Paddle transaction is linked to this order.', 'storeengine' ) );
		}

		$service     = PaddleService::init();
		$transaction = $service->get_transaction( $transaction_id );

		if ( is_wp_error( $transaction ) ) {
			return $transaction;
		}

		$line_items = $transaction->details->line_items ?? [];

		if ( empty( $line_items ) ) {
			return new WP_Error( 'paddle_refund_no_items', __( 'The Paddle transaction has no refundable items.', 'storeengine' ) );
		}

		$order_total = (float) $order->get_total();
		$amount      = ( null === $amount ) ? $order_total : (float) $amount;
		$is_full     = $amount >= $order_total;

		$items = [];

		if ( $is_full ) {
			foreach ( $line_items as $li ) {
				$items[] = [ 'item_id' => $li->id, 'type' => 'full' ];
			}
		} else {
			// Partial: allocate the requested amount (in cents) across line items.
			$remaining = PaddleService::get_paddle_amount( $amount, $order->get_currency() );

			foreach ( $line_items as $li ) {
				if ( $remaining <= 0 ) {
					break;
				}
				$li_total = (int) ( $li->totals->total ?? 0 );
				$take     = min( $remaining, $li_total );
				if ( $take <= 0 ) {
					continue;
				}
				$items[]    = [ 'item_id' => $li->id, 'type' => 'partial', 'amount' => (string) $take ];
				$remaining -= $take;
			}
		}

		if ( empty( $items ) ) {
			return new WP_Error( 'paddle_refund_nothing', __( 'Nothing to refund on the Paddle transaction.', 'storeengine' ) );
		}

		$adjustment = $service->create_refund_adjustment( $transaction_id, $items, $reason );

		if ( is_wp_error( $adjustment ) ) {
			return $adjustment;
		}

		if ( ! empty( $adjustment->id ) ) {
			$order->update_meta_data( '_paddle_refund_adjustment_id', $adjustment->id );
			$order->add_order_note( sprintf(
			/* translators: %s: Paddle adjustment id. */
				__( 'Paddle refund requested (adjustment %s). Paddle will process it after approval.', 'storeengine' ),
				$adjustment->id
			) );
			$order->save();
		}

		return true;
	}

	/**
	 * GatewayAdapterInterface — pre-confirmation client-side handshake.
	 *
	 * Creates (or reuses) a Paddle transaction for the given order so the
	 * client-side adapter can hand its id to Paddle.Checkout.open(). The
	 * resulting transaction id is also written onto the order so that
	 * GatewayPaddle::process_payment() can verify it after the overlay
	 * completes — same flow the legacy AJAX endpoints used.
	 *
	 * @return array|WP_Error  { intent_id: string, customer_email?: string }
	 */
	public function create_intent( Order $order, Cart $cart ) {
		$service = PaddleService::init();

		// Two call sites converge here:
		//   1. New checkout — order is a fresh draft without line items yet,
		//      so the cart is authoritative for the selection.
		//   2. Pay-for-existing-order (failed / scheduled / installment retry)
		//      — the order already has line items and the cart is empty.
		// Prefer the cart when it has contents; fall back to the order's own
		// line items otherwise so the pay-order flow doesn't 422 with
		// "Cart is empty.".
		if ( ! $cart->is_cart_empty() ) {
			$items = $service->prepare_items_from_cart( $cart, $order );
		} else {
			$items = $service->prepare_items_from_order( $order );
		}

		if ( empty( $items ) ) {
			return new WP_Error(
				'storeengine_paddle_no_items',
				__( 'No items to charge for this order.', 'storeengine' ),
				[ 'status' => 400 ]
			);
		}

		// Coupons → Paddle discount (optional). Mirrors the legacy AJAX path.
		$discount_id = null;
		$discounts   = [];
		foreach ( $order->get_coupons() as $coupon_item ) {
			$discounts[ $coupon_item->get_code() ] = $coupon_item->get_discount();
		}
		if ( ! empty( $discounts ) ) {
			$discount_response = $service->create_discount( $order->get_id(), $discounts );
			if ( ! is_wp_error( $discount_response ) ) {
				$discount_id = $discount_response->data->id;
				$order->update_meta_data( '_paddle_discount_id', $discount_id );
			}
		}

		// Reuse the existing transaction id if Paddle still has it; otherwise create.
		$transaction_id = $order->get_transaction_id();
		if ( $transaction_id && 'paddle' === $order->get_payment_method() ) {
			$update = $service->update_transaction( $transaction_id, [
				'discount_id' => $discount_id,
				'items'       => $items,
				'custom_data' => [ 'merchant_order_id' => $order->get_id() ],
			] );
			if ( is_wp_error( $update ) ) {
				// Fall through to create a fresh transaction below.
				$transaction_id = '';
			}
		}

		if ( ! $transaction_id ) {
			$response = $service->create_transaction( $order->get_id(), $items, $discount_id );
			if ( is_wp_error( $response ) ) {
				return $response;
			}
			$transaction_id = $response->data->id;
		}

		// Persist the transaction id so process_payment() can verify it.
		$order->set_payment_method( 'paddle' );
		$order->set_transaction_id( $transaction_id );
		$order->save();

		return [
			'intent_id'      => $transaction_id,
			'customer_email' => $order->get_billing_email() ?: '',
		];
	}
}
