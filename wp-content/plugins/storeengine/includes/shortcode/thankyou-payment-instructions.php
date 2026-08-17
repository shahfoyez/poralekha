<?php

namespace StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;

class ThankyouPaymentInstructions {

	public function __construct() {
		add_shortcode( 'storeengine_thankyou_payment_instructions', [ $this, 'render' ] );
	}

	public function render( $atts ) {
		$attributes = shortcode_atts( [ 'dummy' => false ], $atts );
		$dummy      = Formatting::string_to_bool( $attributes['dummy'] );

		if ( ! $dummy ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$order_hash = isset( $_GET['order_hash'] ) ? sanitize_text_field( wp_unslash( $_GET['order_hash'] ) ) : '';
			$order      = Helper::get_order_by_key( $order_hash );
			$order      = $order instanceof WP_Error ? false : $order;

			// Fall back to sample instructions when there's no real order to show
			// (page opened directly, previewed, or rendered in the editor).
			if ( ! $order ) {
				$dummy = true;
			}
		}

		if ( $dummy ) {
			return '<p>' . __( 'Please send a check to <b>Store Name</b>, <b>Store Street</b>, <b>Store Town</b>, <b>Store State/County</b>, <b>Store Postcode</b>.', 'storeengine' ) . '</p>';
		}

		ob_start();

		do_action( 'storeengine/thankyou/' . $order->get_payment_method(), $order->get_id() );

		return ob_get_clean();
	}
}
