<?php
/**
 * Subscription Renewed Email — receipt-style notice when an auto-renewal
 * succeeds. Default OFF; some merchants want it, some find it noisy.
 */

namespace StoreEngine\Addons\Email\subscription;

use StoreEngine\Addons\Email\Traits\Email;
use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Renewed {

	use Email {
		Email::__construct as private __EmailConstruct;
	}

	const SETTINGS_KEY = 'subscription_renewed';

	public function __construct() {
		$this->__EmailConstruct( self::SETTINGS_KEY );

		if ( ! is_array( $this->settings ) || empty( $this->settings ) ) {
			$this->settings = self::default_template();
		}

		add_action( 'storeengine/subscription/renewal_payment_complete', [ $this, 'on_renewal_complete' ], 20, 2 );
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
				// Opt-in: most stores don't need a per-renewal receipt and the
				// gateway already sends one. Enable only if you want the extra
				// touchpoint.
				'is_enable'     => false,
				'email_subject' => __( 'Subscription renewal receipt — order #{order_id}', 'storeengine' ),
				'email_heading' => __( 'Your subscription was renewed', 'storeengine' ),
				'email_content' => __(
					'<p>Hi {user_display_name},</p><p>Your subscription has been renewed successfully. Here\'s a receipt for the renewal payment:</p><p>Order #{order_id} ({order_created_date})</p><p>Order Items:</p><p>{order_items}</p><p>Order Totals:</p><p>{order_totals}</p><p>You don\'t need to do anything — your subscription will continue as normal.</p>',
					'storeengine'
				),
			],
		];
	}

	public function on_renewal_complete( $subscription, $renewal_order ): void {
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

		$this->mail_send( $to, $subject, $body, $headers, [
			'subscription_id' => $subscription->get_id(),
			'order_id'        => $renewal_order->get_id(),
		] );
	}
}
