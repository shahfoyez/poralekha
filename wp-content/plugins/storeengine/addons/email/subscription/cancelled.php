<?php
/**
 * Subscription Cancelled Email.
 *
 * Fires when a subscription transitions to `cancelled` — either by the
 * customer, by an admin, or automatically after max_failed_payments
 * exhausts the retry cycle.
 */

namespace StoreEngine\Addons\Email\subscription;

use StoreEngine\Addons\Email\Traits\Email;
use StoreEngine\Addons\Subscription\Classes\Subscription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cancelled {

	use Email {
		Email::__construct as private __EmailConstruct;
	}

	const SETTINGS_KEY = 'subscription_cancelled';

	public function __construct() {
		$this->__EmailConstruct( self::SETTINGS_KEY );

		if ( ! is_array( $this->settings ) || empty( $this->settings ) ) {
			$this->settings = self::default_template();
		}

		add_action( 'storeengine/subscription/status_updated', [ $this, 'on_status_updated' ], 20, 3 );
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
				'email_subject' => __( 'Your subscription #{order_id} has been cancelled', 'storeengine' ),
				'email_heading' => __( 'Subscription cancelled', 'storeengine' ),
				'email_content' => __(
					'<p>Hi {user_display_name},</p><p>Your subscription (#{order_id}) has been cancelled and will not renew. You won\'t be charged again.</p><p>If this was a mistake or you\'d like to resubscribe, you can do so from your account at any time.</p><p>Order Items:</p><p>{order_items}</p><p>Thanks for being a customer at {site_title}.</p>',
					'storeengine'
				),
			],
		];
	}

	public function on_status_updated( $subscription, $new_status, $old_status ): void {
		if ( 'cancelled' !== $new_status ) {
			return;
		}

		if ( ! is_a( $subscription, Subscription::class ) ) {
			return;
		}

		$settings = $this->get_settings( 'customer' );
		if ( ! is_array( $settings ) || empty( $settings['is_enable'] ) ) {
			return;
		}

		$to = $subscription->get_billing_email();
		if ( ! $to ) {
			return;
		}

		$subject = $this->get_email_subject( $subscription, $settings['email_subject'] );
		list( $headers, $body ) = $this->get_the_email_body( $settings, 'email/order-status-customer.php' );
		$body = $this->get_order_email_body( $subscription, $body );

		$this->mail_send( $to, $subject, $body, $headers, [ 'subscription_id' => $subscription->get_id() ] );
	}
}
