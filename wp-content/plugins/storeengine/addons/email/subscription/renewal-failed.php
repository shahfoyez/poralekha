<?php
/**
 * Subscription Renewal Payment Failed Email.
 *
 * Fires when an automatic renewal payment fails and the subscription is put on
 * hold (before any max-retry cancellation). The generic order PaymentFailed
 * email deliberately skips renewal orders, so without this the customer is
 * never told their renewal failed or how to fix it — the single most common
 * cause of involuntary churn. This email gives the customer a direct one-click
 * link to retry the payment from the order-pay page.
 */

namespace StoreEngine\Addons\Email\subscription;

use StoreEngine\Addons\Email\Traits\Email;
use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RenewalFailed {

	use Email {
		Email::__construct as private __EmailConstruct;
	}

	const SETTINGS_KEY = 'subscription_renewal_failed';

	public function __construct() {
		$this->__EmailConstruct( self::SETTINGS_KEY );

		if ( ! is_array( $this->settings ) || empty( $this->settings ) ) {
			$this->settings = self::default_template();
		}

		add_action( 'storeengine/subscription/renewal_payment_failed', [ $this, 'on_renewal_failed' ], 20, 2 );
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
				// On by default: a failed renewal that the customer never hears
				// about is the most common cause of involuntary churn.
				'is_enable'     => true,
				'email_subject' => __( 'Action needed: your subscription renewal payment failed (order #{order_id})', 'storeengine' ),
				'email_heading' => __( 'Your renewal payment didn\'t go through', 'storeengine' ),
				'email_content' => __(
					'<p>Hi {user_display_name},</p><p>We tried to renew your subscription but the payment for order #{order_id} couldn\'t be completed, so your subscription is now on hold.</p><p>To keep your subscription active, please retry the payment:</p><p>{retry_button}</p><p>Or open this link in your browser: {retry_url}</p><p>Order Items:</p><p>{order_items}</p><p>Order Totals:</p><p>{order_totals}</p><p>If you\'ve already paid or need help, just reply to this email.</p>',
					'storeengine'
				),
			],
		];
	}

	public function on_renewal_failed( $subscription, $renewal_order ): void {
		if ( ! is_a( $subscription, Subscription::class ) || ! is_a( $renewal_order, Order::class ) ) {
			return;
		}

		$settings = $this->get_settings( 'customer' );
		if ( ! is_array( $settings ) || empty( $settings['is_enable'] ) ) {
			return;
		}

		$to = $renewal_order->get_billing_email() ?: $subscription->get_billing_email();
		if ( ! $to ) {
			return;
		}

		$subject = $this->get_email_subject( $renewal_order, $settings['email_subject'] );
		list( $headers, $body ) = $this->get_the_email_body( $settings, 'email/order-status-customer.php' );
		$body = $this->get_order_email_body( $renewal_order, $body );

		// Inject the one-click retry link. Order::needs_payment() counts a
		// payment_failed order, so the order-pay page accepts this link and lets
		// the customer re-attempt the charge. Placeholders are replaced after the
		// CSS inliner has run, so the URL is injected as plain text (not inside an
		// href in the template) to avoid the inliner percent-encoding the braces.
		$retry_url    = $renewal_order->get_checkout_payment_url();
		$retry_button = sprintf(
			'<a href="%s" style="display:inline-block;padding:12px 24px;background:#2563eb;color:#ffffff;text-decoration:none;border-radius:4px;font-weight:600;">%s</a>',
			esc_url( $retry_url ),
			esc_html__( 'Retry payment', 'storeengine' )
		);

		$body = str_replace(
			[ '{retry_button}', '{retry_url}' ],
			[ $retry_button, esc_url( $retry_url ) ],
			$body
		);

		$this->mail_send( $to, $subject, $body, $headers, [
			'subscription_id' => $subscription->get_id(),
			'order_id'        => $renewal_order->get_id(),
		] );
	}
}
