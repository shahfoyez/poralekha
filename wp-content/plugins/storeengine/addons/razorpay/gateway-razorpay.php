<?php

namespace StoreEngine\Addons\Razorpay;

use StoreEngine;
use StoreEngine\Addons\Stripe\StripeService;
use StoreEngine\Classes\Cart;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusTransitionException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Interfaces\GatewayAdapterInterface;
use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\Utils\ArrayUtil;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;

class GatewayRazorpay extends PaymentGateway implements GatewayAdapterInterface {

	protected int $index = 2;

	public function __construct() {
		$this->setup();
		$this->init_admin_fields();
		$this->init_settings();

		$this->title       = $this->get_option( 'title' );
		$this->description = $this->get_option( 'description' );
		$this->saved_cards = $this->get_option( 'saved_cards' );
	}

	protected function setup() {
		$this->id                 = 'razorpay';
		$this->icon               = apply_filters( 'storeengine/razorpay_icon', Helper::get_assets_url( 'images/payment-methods/razorpay.svg' ) );
		$this->method_title       = __( 'Razorpay', 'storeengine' );
		$this->method_description = __( 'Allows you to accept credit cards, debit cards, netbanking, wallet, and UPI payments in India and FPX, eWallets, Duitnow in Malaysia.', 'storeengine' );
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

	protected function init_admin_fields() {
		$this->admin_fields = [
			'title'       => [
				'label'    => __( 'Title', 'storeengine' ),
				'type'     => 'safe_text',
				'tooltip'  => __( 'Payment method description that the customer will see on your checkout.', 'storeengine' ),
				'default'  => __( 'Credit Card/Debit Card/NetBanking', 'storeengine' ),
				'priority' => 0,
			],
			'description' => [
				'label'    => __( 'Description', 'storeengine' ),
				'type'     => 'textarea',
				'default'  => 'Pay securely by Credit or Debit card or Internet Banking through Razorpay.',
				'tooltip'  => __( 'Payment method description that the customer will see on your website.', 'storeengine' ),
				'priority' => 0,
			],
			'key_id'      => [
				'label'        => __( 'Key ID', 'storeengine' ),
				'type'         => 'text',
				'priority'     => 0,
				'autocomplete' => 'none',
				'required'     => true,
			],
			'key_secret'  => [
				'label'        => __( 'Key Secret', 'storeengine' ),
				'type'         => 'password',
				'priority'     => 0,
				'autocomplete' => 'none',
				'required'     => true,
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
		$key_id     = $config['key_id'] ?? '';
		$key_secret = $config['key_secret'] ?? '';

		if ( ! $key_id ) {
			throw new StoreEngineException( esc_html__( 'Razorpay Key ID is required.', 'storeengine' ), 'key-id-is-required', 400 );
		}

		if ( ! $key_secret ) {
			throw new StoreEngineException( esc_html__( 'Razorpay Key Secret is required.', 'storeengine' ), 'secret-key-is-required', 400 );
		}

		if ( ! $this->is_currency_supported() ) {
			throw new StoreEngineException(
				sprintf(
				/* translators: %1$s the shop currency, %2$s the Razorpay currency support page link opening HTML tag, %3$s the link ending HTML tag. */
					esc_html__(
						'Attention: Your current StoreEngine store currency (%1$s) is not supported by Razorpay. Please update your store currency to one that is supported by Razorpay to ensure smooth transactions. Visit the %2$sRazorpay currency support page%3$s for more information on supported currencies.',
						'storeengine'
					),
					esc_html( Formatting::get_currency() ),
					'<a href="' . esc_url( 'https://razorpay.com/docs/payments/international-payments/#supported-currencies' ) . '" target="_blank">',
					'</a>'
				),
				'currency-not-supported',
				null,
				400
			);
		}

		$result = RazorpayService::validate_keys( $key_id, $key_secret );
		if ( ! $result ) {
			throw new StoreEngineException( esc_html__( 'Invalid keys combination.Please use correct keys!', 'storeengine' ), 'razorpay-key-is-invalid', 400 );
		}
	}

	public function is_currency_supported( string $currency = null ): bool {
		if ( ! $currency ) {
			$currency = Formatting::get_currency();
		}

		return in_array( $currency, StripeService::get_supported_currencies(), true );
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
			if ( ! $this->is_currency_supported() ) {
				return false;
			}
		}

		return parent::is_available();
	}

	public function validate_minimum_order_amount( $order ) {
		if ( $order->get_total() * 100 < RazorpayService::get_minimum_amount() ) {
			throw new StoreEngineException(
				wp_kses_post(
					sprintf(
					/* translators: %1$s. Minimum allowed amount (including currency symbol) by the gateway. */
						__( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'storeengine' ),
						Formatting::price( RazorpayService::get_minimum_amount() / 100 )
					)
				),
				'did-not-meet-minimum-amount'
			);
		}
	}

	/**
	 * Returns true if a payment is needed for the current cart or order.
	 * Pre-Orders and Subscriptions may not require an upfront payment, so we need to check whether
	 * or not the payment is necessary to decide for either a setup intent or a payment intent.
	 *
	 * @param Order $order The order ID being processed.
	 *
	 * @return bool Whether a payment is necessary.
	 */
	public function is_payment_needed( Order $order ): bool {
		return 0 < StripeService::get_stripe_amount( $this->get_total_payment( $order ), $order->get_currency() );
	}

	/**
	 * @param Order $order
	 *
	 * @return array
	 * @throws StoreEngineException
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 */
	public function process_payment( Order $order ) {
		$razorpay_order_id = $order->get_meta( '_razorpay_payment_id', true, 'edit' );
		if ( empty( $razorpay_order_id ) ) {
			return [];
		}

		$razorpay_payment = RazorpayService::init()->get_payment( $razorpay_order_id );
		if ( 'captured' !== $razorpay_payment['status'] ) {
			$razorpay_payment = RazorpayService::init()->capture_payment( $razorpay_order_id, $order->get_total( 'edit' ), $order->get_currency() );
			if ( is_wp_error( $razorpay_payment ) ) {
				wp_send_json_error( $razorpay_payment->get_error_message() );
			}
		}

		// Set order as paid
		$order->set_paid_status( 'paid' );
		$order_context = new OrderContext( $order->get_status() );
		$order_context->proceed_to_next_status( 'process_order', $order, [ 'note' => _x( 'Payment already done.', 'Razorpay payment method', 'storeengine' ) ] );
		$order->save();

		return [
			'result'   => 'success',
			'redirect' => $order->get_checkout_order_received_url(),
		];
	}

	/**
	 * Process refund.
	 *
	 * @param int $order_id Order ID.
	 * @param float|string|null $amount Refund amount.
	 * @param string $reason Refund reason.
	 *
	 * @return bool|WP_Error True or false based on success, or a WP_Error object.
	 */
	public function process_refund( int $order_id, $amount = null, string $reason = '' ) {
		$order = Helper::get_order( $order_id );

		if ( ! $order ) {
			return false;
		}

		if ( is_wp_error( $order ) ) {
			return $order;
		}

		// Refund without an amount is a no-op, but required to succeed
		if ( '0.00' === sprintf( '%0.2f', $amount ?? 0 ) ) {
			return true;
		}

		$refund_result = RazorpayService::init()->refund( $order->get_transaction_id(), $amount );

		if ( is_wp_error( $refund_result ) ) {
			return false;
		}

		if ( is_array( $refund_result ) ) {
			$order->add_meta_data( '_razorpay_refund_id', $refund_result['id'], true );
			$order->save();
		}

		return true;
	}

	/**
	 * GatewayAdapterInterface — create a Razorpay order so the client-side
	 * Checkout modal can collect payment for it. Returns the Razorpay order id
	 * + everything the modal config needs (key, currency, amount-in-paise).
	 *
	 * @return array|WP_Error
	 */
	public function create_intent( Order $order, Cart $cart ) {
		// Hydrate the draft order's currency + total so RazorpayService::create_order
		// uses the right amount. Pay-for-existing-order context has no cart —
		// trust the order's own totals rather than overwriting them with zeros.
		if ( ! $cart->is_cart_empty() ) {
			$order->set_currency( Formatting::get_currency() );
			$order->set_total( (float) $cart->get_total( 'edit' ) );
		}
		$order->set_payment_method( 'razorpay' );
		$order->save();

		$razorpay_order_id = RazorpayService::init()->create_order( $order );
		if ( is_wp_error( $razorpay_order_id ) ) {
			return $razorpay_order_id;
		}

		$order->update_meta_data( '_razorpay_order_id', $razorpay_order_id );
		$order->save();

		return [
			'intent_id'         => $razorpay_order_id,
			'razorpay_order_id' => $razorpay_order_id,
			'amount'            => RazorpayService::get_razorpay_amount( $order->get_total( 'edit' ), $order->get_currency() ),
			'currency'          => $order->get_currency(),
		];
	}
}
