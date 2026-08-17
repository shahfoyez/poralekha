<?php
/**
 * Order Payment Failed Email.
 *
 * Fires when an order transitions to `failed` payment status on initial
 * purchase. Skips subscription/installment renewal orders — those have
 * dedicated emails (SubscriptionCancelled / InstallmentPaymentFailed /
 * LicenseRenewalFailed) so we don't double-email the customer.
 */

namespace StoreEngine\Addons\Email\order;

use StoreEngine\Addons\Email\Traits\Email;
use StoreEngine\Classes\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PaymentFailed {

	use Email {
		Email::__construct as private __EmailConstruct;
	}

	const SETTINGS_KEY = 'order_payment_failed';

	public function __construct() {
		$this->__EmailConstruct( self::SETTINGS_KEY );

		if ( ! is_array( $this->settings ) || empty( $this->settings ) ) {
			$this->settings = self::default_template();
		}

		add_action( 'storeengine/order/payment_status_changed', [ $this, 'on_payment_status_changed' ], 10, 3 );
	}

	public static function register_defaults( array $defaults ): array {
		if ( ! isset( $defaults[ self::SETTINGS_KEY ] ) ) {
			$defaults[ self::SETTINGS_KEY ] = self::default_template();
		}
		return $defaults;
	}

	public static function default_template(): array {
		return [
			'customer' => [
				'is_enable'     => true,
				'email_subject' => __( 'Your payment for order #{order_id} couldn\'t be completed', 'storeengine' ),
				'email_heading' => __( 'Payment failed for your order', 'storeengine' ),
				'email_content' => __(
					'<p>Hi {user_display_name},</p><p>We weren\'t able to process the payment for your order #{order_id} placed on {order_created_date}. The order is on hold until payment is received.</p><p>You can complete payment at any time from your account.</p><p>Order Items:</p><p>{order_items}</p><p>Order Totals:</p><p>{order_totals}</p><p>If you have questions, please reply to this email.</p>',
					'storeengine'
				),
			],
			'admin' => [
				'is_enable'     => true,
				'email_subject' => __( 'Payment failed for order #{order_id}', 'storeengine' ),
				'email_heading' => __( 'Payment failure on order #{order_id}', 'storeengine' ),
				'email_content' => __(
					'<p>Payment failed for order #{order_id} from {user_display_name}.</p><p>Order Items:</p><p>{order_items}</p><p>Order Totals:</p><p>{order_totals}</p>',
					'storeengine'
				),
			],
		];
	}

	public function on_payment_status_changed( $order, $new_status, $old_status ): void {
		if ( ! is_a( $order, Order::class ) || 'failed' !== $new_status ) {
			return;
		}

		// Skip renewal/installment-followup orders — they have their own email.
		// `parent` is the only original-purchase variant for subscriptions, so
		// emails fire only on first-purchase failures here.
		if ( class_exists( '\StoreEngine\Addons\Subscription\Classes\SubscriptionCollection' ) ) {
			if ( \StoreEngine\Addons\Subscription\Classes\SubscriptionCollection::order_contains_subscription( $order->get_id(), [ 'renewal' ] ) ) {
				return;
			}
		}

		$this->send_customer( $order );
		$this->send_admin( $order );
	}

	protected function send_customer( Order $order ): void {
		$settings = $this->get_settings( 'customer' );
		if ( ! is_array( $settings ) || empty( $settings['is_enable'] ) ) {
			return;
		}

		$to = $order->get_billing_email();
		if ( ! $to ) {
			return;
		}

		$this->send( $settings, $to, $order, false );
	}

	protected function send_admin( Order $order ): void {
		$settings = $this->get_settings( 'admin' );
		if ( ! is_array( $settings ) || empty( $settings['is_enable'] ) ) {
			return;
		}

		$this->send( $settings, get_option( 'admin_email' ), $order, true );
	}

	protected function send( array $settings, string $to, Order $order, bool $is_admin ): void {
		$subject = $this->get_email_subject( $order, $settings['email_subject'] );
		list( $headers, $body ) = $this->get_the_email_body( $settings, 'email/order-status-customer.php' );
		$body = $this->get_order_email_body( $order, $body );

		// Admin notices: route Reply back to the customer so admins can
		// respond directly to the order's billing contact instead of bouncing
		// the reply to the site's own admin_email.
		if ( $is_admin ) {
			$headers = $this->with_customer_reply_to( $headers, $order );
		}

		$this->mail_send( $to, $subject, $body, $headers, [ 'order_id' => $order->get_id() ] );
	}
}
