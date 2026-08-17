<?php

namespace StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Order;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;
use WP_Error;

class ThankyouOrderInfo {
	public function __construct() {
		add_shortcode( 'storeengine_thankyou_order_info', [ $this, 'render' ] );
	}

	public function render( $atts ) {
		$attributes  = shortcode_atts( [
			'dummy' => false,
		], $atts );
		$dummy_order = Formatting::string_to_bool( $attributes['dummy'] );

		if ( ! $dummy_order ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_hash = isset( $_GET['order_hash'] ) ? sanitize_text_field( wp_unslash( $_GET['order_hash'] ) ) : '';
			$order      = Helper::get_order_by_key( $order_hash );
			$order      = $order instanceof WP_Error ? false : $order;

			// Fall back to sample content when there's no real order to show —
			// e.g. the page is opened directly, previewed, or rendered in the
			// block editor — so the thank-you page never renders blank.
			if ( ! $order ) {
				$dummy_order = true;
			}
		}

		if ( $dummy_order ) {
			$order = new Order();
			$order->set_id( 124 );
			$order->set_status( 'completed' );
			$order->set_billing_email( 'support@storeengine.pro' );
			$order->set_order_placed_date_gmt();
			$order->set_order_placed_date();
			$order->set_date_created_gmt( current_time( 'mysql', 1 ) );
		}

		ob_start();
		Template::get_template( 'shortcode/thankyou-order-info.php', [
			'order' => $order,
		] );

		return ob_get_clean();
	}
}
