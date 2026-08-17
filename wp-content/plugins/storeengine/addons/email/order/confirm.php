<?php

namespace StoreEngine\Addons\Email\order;

use StoreEngine\Classes\Order;
use StoreEngine\Addons\Email\Traits\Email;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Confirm {

	use Email {
		Email::__construct as private __EmailConstruct;
	}

	public function __construct() {
		$this->__EmailConstruct( 'order_confirmation' );

		add_action( 'storeengine/checkout/after_place_order', [ $this, 'schedule_order_email' ], 99 );
		add_action( 'storeengine/email/send_order_confirmation', [ $this, 'send_order_email_by_id' ] );
	}

	/**
	 * Admin + customer emails can involve a slow SMTP handshake. Sending them
	 * inline here added that latency to the synchronous checkout/place-order
	 * request the customer's browser is waiting on — a contributor to gateways
	 * (e.g. Razorpay) occasionally surfacing an upstream-timeout error even
	 * though the order/payment already succeeded. Defer via Action Scheduler
	 * (already bundled/used elsewhere, e.g. the webhooks addon) so the
	 * checkout response returns immediately.
	 */
	public function schedule_order_email( Order $order ): void {
		// Fail safe: if Action Scheduler isn't available for any reason, never
		// silently drop the confirmation email — fall back to sending inline.
		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			$this->send_order_email( $order );

			return;
		}

		as_enqueue_async_action(
			'storeengine/email/send_order_confirmation',
			[ 'order_id' => $order->get_id() ],
			'storeengine-email'
		);
	}

	public function send_order_email_by_id( int $order_id ): void {
		$order = Helper::get_order( $order_id );
		if ( ! $order || is_wp_error( $order ) ) {
			return;
		}

		$this->send_order_email( $order );
	}

	public function send_order_email( Order $order ) {
		$is_for_admin    = $this->get_settings( 'admin' );
		$is_for_customer = $this->get_settings( 'customer' );

		if ( ( ! is_array( $is_for_admin ) && ! is_array( $is_for_customer ) ) ) {
			return;
		}

		if ( $order->get_new_order_email_sent() ) {
			// Bail, mail already sent, or system don't want to send the confirmation email.
			return;
		}

		if ( $is_for_admin['is_enable'] ) {
			$this->send_admin_mail( $order, $is_for_admin );
		}

		if ( $is_for_customer['is_enable'] ) {
			$this->send_customer_mail( $order, $is_for_customer );
		}

		$order->set_new_order_email_sent( true );
		$order->save();
	}

	private function send_admin_mail( Order $order, array $settings ) {
		$subject = $this->get_email_subject( $order, $settings['email_subject'] );
		// get email data.
		list( $headers, $body ) = $this->get_the_email_body( $settings, 'email/order-confirmation-admin.php' );

		$body = $this->get_order_email_body( $order, $body );

		// Route Reply back to the customer so the admin can respond directly
		// instead of bouncing replies to the site's own admin_email.
		$headers = $this->with_customer_reply_to( $headers, $order );

		$this->mail_send( get_option( 'admin_email' ), $subject, $body, $headers, [ 'order_id' => $order->get_id() ] );
	}

	private function send_customer_mail( Order $order, array $settings ) {
		$subject = $this->get_email_subject( $order, $settings['email_subject'] );
		// get email data.
		list( $headers, $body ) = $this->get_the_email_body( $settings, 'email/order-confirmation-customer.php' );

		$body = $this->get_order_email_body( $order, $body );

		$this->mail_send( $order->get_billing_email(), $subject, $body, $headers, [ 'order_id' => $order->get_id() ] );
	}

}
