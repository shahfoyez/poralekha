<?php
/**
 * Order Cancelled Email.
 *
 * Fires when an order transitions to a cancelled state via
 * storeengine/order/status_changed. Distinct from the generic Order Status
 * email so stores can send a purpose-written cancellation notice (and keep the
 * generic status email off, or scoped to other transitions).
 */

namespace StoreEngine\Addons\Email\order;

use StoreEngine\Classes\Order;
use StoreEngine\Addons\Email\Traits\Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cancelled {

	use Email {
		Email::__construct as private __EmailConstruct;
	}

	const SETTINGS_KEY = 'order_cancelled';

	/**
	 * Both spellings exist in Constants (ORDER_STATUS_CANCELED = 'canceled' and
	 * ORDER_STATUS_CANCELLED = 'cancelled'); match either so the email fires
	 * regardless of which the transition used.
	 */
	const CANCELLED_STATUSES = [ 'cancelled', 'canceled' ];

	public function __construct() {
		$this->__EmailConstruct( self::SETTINGS_KEY );

		if ( ! is_array( $this->settings ) || empty( $this->settings ) ) {
			$this->settings = self::default_template();
		}

		add_action( 'storeengine/order/status_changed', [ $this, 'send_mail' ], 10, 4 );
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
				'email_subject' => __( 'Your order #{order_id} has been cancelled', 'storeengine' ),
				'email_heading' => __( 'Order cancelled', 'storeengine' ),
				'email_content' => __(
					'<p>Hi {user_display_name},</p><p>Your order #{order_id} placed on {order_created_date} has been cancelled.</p><p>If a payment was taken, any eligible refund will be processed to your original payment method.</p><p>Order Totals:</p><p>{order_totals}</p><p>If you didn\'t request this or have any questions, just reply to this email.</p>',
					'storeengine'
				),
			],
		];
	}

	public function send_mail( $order_id, $old_status, $new_status, Order $order ) {
		if ( ! in_array( (string) $new_status, self::CANCELLED_STATUSES, true ) ) {
			return;
		}

		// Nothing meaningful to cancel if it never left draft.
		if ( 'draft' === $old_status ) {
			return;
		}

		$settings = $this->get_settings( 'customer' );
		if ( ! is_array( $settings ) || empty( $settings['is_enable'] ) ) {
			return;
		}

		$to = $order->get_billing_email();
		if ( ! $to ) {
			return;
		}

		$subject                = $this->get_email_subject( $order, $settings['email_subject'] );
		list( $headers, $body ) = $this->get_the_email_body( $settings, 'email/order-status-customer.php' );
		$body                   = $this->get_order_email_body( $order, $body );

		$this->mail_send( $to, $subject, $body, $headers, [ 'order_id' => $order->get_id() ] );
	}

}
