<?php

namespace StoreEngine\Addons\Stripe;

use StoreEngine\Addons\Stripe\Constants\StripePaymentMethods;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusTransitionException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Stripe\PaymentIntent;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\PaymentUtil;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class Hooks {

	protected static ?Hooks $instance = null;

	protected static GatewayStripe $gateway;

	public static function init( GatewayStripe $gateway ): ?Hooks {
		if ( null === self::$instance ) {
			self::$gateway  = &$gateway;
			self::$instance = new self();

			add_filter( 'storeengine/frontend_scripts_payment_method_data', [ __CLASS__, 'gateway_javascript_params' ] );
			add_filter( 'storeengine/payment_method/display_my_payment_method', [ __CLASS__, 'maybe_render_order_payment_method' ], 10, 2 );
			add_action( 'storeengine/checkout/before_place_order_payload/stripe', [ __CLASS__, 'remap_react_payment_payload' ] );
			add_action( 'storeengine/checkout/before_pay_order_payload/stripe', [ __CLASS__, 'remap_react_payment_payload_for_pay_order' ], 10, 2 );
			add_filter( 'storeengine/checkout/gateway/stripe/data', [ __CLASS__, 'expose_saved_cards' ], 10, 2 );
		}

		return self::$instance;
	}

	/**
	 * Expose the logged-in shopper's saved Stripe payment tokens to the
	 * React Quick Checkout. Mirrors the saved-cards UI on the legacy
	 * /checkout/ page (which is also Stripe-only).
	 */
	public static function expose_saved_cards( array $data, $gateway ): array {
		if ( ! is_user_logged_in() ) {
			return $data;
		}
		$tokens = \StoreEngine\Classes\PaymentTokens\PaymentTokens::get_customer_tokens( get_current_user_id(), 'stripe' );
		if ( empty( $tokens ) ) {
			return $data;
		}

		$saved = [];
		foreach ( $tokens as $token ) {
			if ( ! is_object( $token ) ) {
				continue;
			}
			$saved[] = [
				'id'         => (int) $token->get_id(),
				'brand'      => method_exists( $token, 'get_card_type' ) ? (string) $token->get_card_type() : '',
				'last4'      => method_exists( $token, 'get_last4' ) ? (string) $token->get_last4() : '',
				'expiry'     => method_exists( $token, 'get_expiry_month' ) && method_exists( $token, 'get_expiry_year' )
					? sprintf( '%02d/%s', (int) $token->get_expiry_month(), substr( (string) $token->get_expiry_year(), -2 ) )
					: '',
				'is_default' => method_exists( $token, 'is_default' ) ? (bool) $token->is_default() : false,
			];
		}

		$data['saved_cards'] = $saved;

		return $data;
	}

	/**
	 * React adapter sends `stripe_payment_intent_id` in payment_payload.
	 * Mirror it to the legacy `payment_intent_id` $_POST key the Stripe
	 * gateway looks for, and persist as order meta (preferred lookup).
	 */
	public static function remap_react_payment_payload( array $payment_data ) {
		if ( empty( $payment_data['stripe_payment_intent_id'] ) ) {
			return;
		}
		$intent_id                     = sanitize_text_field( (string) $payment_data['stripe_payment_intent_id'] );
		$_POST['payment_intent_id']    = $intent_id;
		$_REQUEST['payment_intent_id'] = $intent_id;
		$draft = \StoreEngine\Utils\Helper::get_recent_draft_order( get_current_user_id(), null, true );
		if ( $draft ) {
			$draft->update_meta_data( '_stripe_intent_id', $intent_id );
			$draft->save();
		}
	}

	/**
	 * Pay-order variant of the React-payload remap. The order already exists
	 * (re-paying a failed/pending order), so persist the intent id on it
	 * directly rather than scanning for a draft.
	 */
	public static function remap_react_payment_payload_for_pay_order( array $payment_data, Order $order ) {
		if ( empty( $payment_data['stripe_payment_intent_id'] ) ) {
			return;
		}
		$intent_id                     = sanitize_text_field( (string) $payment_data['stripe_payment_intent_id'] );
		$_POST['payment_intent_id']    = $intent_id;
		$_REQUEST['payment_intent_id'] = $intent_id;
		$order->update_meta_data( '_stripe_intent_id', $intent_id );
		$order->save();
	}

	public static function gateway_javascript_params( $payment_method ) {
		$is_production = self::$gateway->get_option( 'is_production', true );
		$key_type      = $is_production ? '' : 'test_';

		// Stripe Params.
		$payment_method['stripe'] = [
			'is_production'             => $is_production,
			'publishable_key'          => self::$gateway->get_option( $key_type . 'publishable_key' ),
			// Native Stripe Billing: the checkout JS must collect the card via a
			// SetupIntent (no up-front charge) for subscription carts.
			'use_native_subscriptions' => self::$gateway->is_native_subscriptions_enabled(),
		];

		return $payment_method;
	}

	/**
	 * Render the payment method used for a order in the "My orders" table
	 *
	 * @param string $payment_method_to_display the default payment method text to display
	 * @param Order $order the order details
	 *
	 * @return string the order payment method
	 */
	public static function maybe_render_order_payment_method( string $payment_method_to_display, Order $order ): string {
		$customer_user = $order->get_customer_id();
		// bail for other payment methods
		if ( $order->get_payment_method() !== self::$gateway->id || ! $customer_user ) {
			return $payment_method_to_display;
		}

		$stripe_source_id = $order->get_meta( '_stripe_source_id', true );

		$stripe_customer    = new StripeCustomer();
		$stripe_customer_id = $order->get_meta( '_stripe_customer_id', true );

		// If we couldn't find a Stripe customer linked to the order, fallback to the user meta data.
		if ( ! $stripe_customer_id || ! is_string( $stripe_customer_id ) ) {
			$user_id            = $customer_user;
			$stripe_customer_id = get_user_option( '_stripe_customer_id', $user_id );
			$stripe_source_id   = get_user_option( '_stripe_source_id', $user_id );
		}

		// If we couldn't find a Stripe customer linked to the account, fallback to the order meta data.
		if ( ( ! $stripe_customer_id || ! is_string( $stripe_customer_id ) ) && false !== $order->get_parent() ) {
			$parent_order       = $order->get_parent_order();
			$stripe_customer_id = $parent_order ? $parent_order->get_meta( '_stripe_customer_id', true ) : '';
			$stripe_source_id   = $parent_order ? $parent_order->get_meta( '_stripe_source_id', true ) : '';
		}

		if ( $stripe_customer_id ) {
			$stripe_customer->set_id( $stripe_customer_id );
		}

		$payment_method_to_display = '';

		try {
			// Retrieve all possible payment methods for orders.
			foreach ( StripeCustomer::STRIPE_PAYMENT_METHODS as $payment_method_type ) {
				foreach ( $stripe_customer->get_payment_methods( $payment_method_type ) as $source ) {
					if ( $source->id !== $stripe_source_id ) {
						continue;
					}

					switch ( $source->type ) {
						case StripePaymentMethods::CARD:
							/* translators: 1) card brand 2) last 4 digits */
							$payment_method_to_display = sprintf( __( 'Via %1$s card ending in %2$s', 'storeengine' ), ( isset( $source->card->brand ) ? PaymentUtil::get_credit_card_type_label( $source->card->brand ) : __( 'N/A', 'storeengine' ) ), $source->card->last4 );
							break 3;
						case StripePaymentMethods::SEPA_DEBIT:
							/* translators: 1) last 4 digits of SEPA Direct Debit */
							$payment_method_to_display = sprintf( __( 'Via SEPA Direct Debit ending in %1$s', 'storeengine' ), $source->sepa_debit->last4 );
							break 3;
						case StripePaymentMethods::CASHAPP_PAY:
							/* translators: 1) Cash App Cashtag */
							$payment_method_to_display = sprintf( __( 'Via Cash App Pay (%1$s)', 'storeengine' ), $source->cashapp->cashtag );
							break 3;
						case StripePaymentMethods::LINK:
							/* translators: 1) email address associated with the Stripe Link payment method */
							$payment_method_to_display = sprintf( __( 'Via Stripe Link (%1$s)', 'storeengine' ), $source->link->email );
							break 3;
						case StripePaymentMethods::ACH:
							$payment_method_to_display = sprintf(
							/* translators: 1) account type (checking, savings), 2) last 4 digits of account. */
								__( 'Via %1$s Account ending in %2$s', 'storeengine' ),
								ucfirst( $source->us_bank_account->account_type ),
								$source->us_bank_account->last4
							);
							break 3;
						case StripePaymentMethods::BECS_DEBIT:
							$payment_method_to_display = sprintf(
							/* translators: last 4 digits of account. */
								__( 'BECS Direct Debit ending in %s', 'storeengine' ),
								$source->au_becs_debit->last4
							);
							break 3;
						case StripePaymentMethods::ACSS_DEBIT:
							$payment_method_to_display = sprintf(
							/* translators: 1) bank name, 2) last 4 digits of account. */
								__( 'Via %1$s ending in %2$s', 'storeengine' ),
								$source->acss_debit->bank_name,
								$source->acss_debit->last4
							);
							break 3;
						case StripePaymentMethods::BACS_DEBIT:
							/* translators: 1) the Bacs Direct Debit payment method's last 4 numbers */
							$payment_method_to_display = sprintf( __( 'Via Bacs Direct Debit ending in (%1$s)', 'storeengine' ), $source->bacs_debit->last4 );
							break 3;
						case StripePaymentMethods::AMAZON_PAY:
							/* translators: 1) the Amazon Pay payment method's email */
							$payment_method_to_display = sprintf( __( 'Via Amazon Pay (%1$s)', 'storeengine' ), $source->billing_details->email ?? '' );
							break 3;
					}
				}
			}
		} catch ( \Exception $e ) {
			Helper::log_error( $e );
		}

		return $payment_method_to_display;
	}

	/**
	 * @param Order $order Order Object.
	 *
	 * @return void
	 *
	 * @throws StoreEngineInvalidOrderStatusException Throws if order status is unsupported.
	 * @throws StoreEngineInvalidOrderStatusTransitionException Throws if order status transition is invalid.
	 * @throws StoreEngineException
	 */
	public function add_stripe_meta( Order $order ) {
		// maybe deprecated...
		if ( 'stripe' !== $order->get_payment_method() ) {
			return;
		}

		$stripe_payment_intent_id = $order->get_meta( '_stripe_intent_id', true, 'edit' );
		if ( ! $stripe_payment_intent_id ) {
			return;
		}

		$stripe_service               = StripeService::init();
		$stripe_payment_intent_object = $stripe_service->get_payment_intent( $stripe_payment_intent_id );
		if ( ! $stripe_payment_intent_object instanceof PaymentIntent ) {
			return;
		}

		$order->set_transaction_id( $stripe_payment_intent_id );
		$order->add_meta_data( '_stripe_customer_id', $stripe_payment_intent_object->customer, true );
		$order->add_meta_data( '_stripe_payment_method_id', $stripe_payment_intent_object->payment_method, true );

		$order_context = new OrderContext( $order->get_status() );
		// update order status to processing
		$order_context->proceed_to_next_status( 'payment_initiate', $order );

		if ( $stripe_payment_intent_object->cancellation_reason ) {
			// translators: %s contains the reason of cancellation.
			$order->add_order_note( sprintf( __( 'Payment cancellation reason: %s', 'storeengine' ), $stripe_payment_intent_object->cancellation_reason ) );
			$order_context->proceed_to_next_status( 'payment_fail', $order );
		}

		$order->save();

		/**
		 * Fires after adding stripe metadata on Order.
		 *
		 * @param Order $order Order object.
		 */
		do_action( 'storeengine/stripe/after_add_stripe_meta', $order );
	}

	/**
	 * @param Order $order Order object.
	 *
	 * @return void
	 * @throws StoreEngineInvalidOrderStatusException
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 * @throws StoreEngineException
	 */
	public function verify_payment_update_order_status( Order $order ): void {
		// Check if the payment method is stripe.
		if ( 'stripe' !== $order->get_payment_method() ) {
			return;
		}

		$stripe_service               = StripeService::init();
		$stripe_payment_intent_object = $stripe_service->get_payment_intent( $order->get_transaction_id() );
		if ( ! $stripe_payment_intent_object instanceof PaymentIntent ) {
			return;
		}

		$order_context = new OrderContext( $order->get_status() );
		if ( 'succeeded' === $stripe_payment_intent_object->status ) {
			$order_context->proceed_to_next_status( 'payment_confirm', $order );
			if ( is_user_logged_in() ) {
				update_user_meta( get_current_user_id(), 'storeengine_stripe_customer_pm', '' );
			}
		} else {
			$order_context->proceed_to_next_status( 'payment_fail', $order );
		}

		$order->save();
	}
}
