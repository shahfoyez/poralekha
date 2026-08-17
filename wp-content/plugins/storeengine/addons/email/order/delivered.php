<?php
/**
 * Order Delivered Email.
 *
 * Fires from storeengine/order/item_shipped — the same shipment lifecycle hook
 * ItemShipped uses — but only when the new shipment status is 'delivered'.
 * ItemShipped skips the delivered status so the customer gets exactly one
 * "delivered" email, worded for arrival rather than dispatch.
 */

namespace StoreEngine\Addons\Email\order;

use StoreEngine\Addons\Email\Traits\Email;
use StoreEngine\Utils\Constants;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Delivered {

	use Email {
		Email::__construct as private __EmailConstruct;
	}

	const SETTINGS_KEY = 'order_delivered';

	public function __construct() {
		$this->__EmailConstruct( self::SETTINGS_KEY );

		if ( ! is_array( $this->settings ) || empty( $this->settings ) ) {
			$this->settings = self::default_template();
		}

		add_action( 'storeengine/order/item_shipped', [ $this, 'on_item_shipped' ], 10, 5 );
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
				'email_subject' => __( 'Your item from order #{order_id} was delivered', 'storeengine' ),
				'email_heading' => __( 'Your item has arrived', 'storeengine' ),
				'email_content' => __(
					'<p>Hi {user_display_name},</p><p>Great news — “{shipped_item_name}” from your order #{order_id} has been delivered.</p><p><strong>Courier:</strong> {shipment_courier}<br/><strong>Tracking number:</strong> {shipment_tracking_number}</p><p>We hope you love it. If anything isn\'t right, just reply to this email and we\'ll help.</p><p>Thank you for shopping with us.</p>',
					'storeengine'
				),
			],
		];
	}

	/**
	 * @param int    $order_id
	 * @param int    $order_item_id
	 * @param int    $product_id
	 * @param array  $shipment   courier/tracking_number/tracking_url/…
	 * @param string $new_status
	 */
	public function on_item_shipped( $order_id, $order_item_id, $product_id, $shipment, $new_status ): void {
		if ( Constants::DELIVERED !== (string) $new_status ) {
			return;
		}

		$settings = $this->get_settings( 'customer' );
		if ( ! is_array( $settings ) || empty( $settings['is_enable'] ) ) {
			return;
		}

		$order = Helper::get_order( (int) $order_id );
		if ( is_wp_error( $order ) ) {
			return;
		}
		$to = $order->get_billing_email();
		if ( ! $to ) {
			return;
		}

		$shipment  = is_array( $shipment ) ? $shipment : [];
		$item_name = get_the_title( (int) $product_id ) ?: ( '#' . (int) $product_id );

		$subject                = $this->get_email_subject( $order, $settings['email_subject'] );
		list( $headers, $body ) = $this->get_the_email_body( $settings, 'email/order-status-customer.php' );
		$body                   = $this->get_order_email_body( $order, $body );

		$courier   = (string) ( $shipment['courier'] ?? '' );
		$tracking  = (string) ( $shipment['tracking_number'] ?? '' );
		$track_url = (string) ( $shipment['tracking_url'] ?? '' );
		$link      = $track_url
			? '<a href="' . esc_url( $track_url ) . '">' . esc_html__( 'View shipment details', 'storeengine' ) . '</a>'
			: '';

		$tokens = [
			'{shipped_item_name}'        => esc_html( $item_name ),
			'{shipment_status}'          => esc_html( Constants::get_shipping_status_label( (string) $new_status ) ),
			'{shipment_courier}'         => $courier ? esc_html( $courier ) : esc_html__( 'N/A', 'storeengine' ),
			'{shipment_tracking_number}' => $tracking ? esc_html( $tracking ) : esc_html__( 'N/A', 'storeengine' ),
			'{shipment_tracking_url}'    => esc_url( $track_url ),
			'{shipment_tracking_link}'   => $link,
		];

		$subject = str_replace( array_keys( $tokens ), array_values( $tokens ), $subject );
		$body    = str_replace( array_keys( $tokens ), array_values( $tokens ), $body );

		$this->mail_send( $to, $subject, $body, $headers, [ 'order_id' => $order->get_id() ] );
	}

}
