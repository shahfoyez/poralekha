<?php

namespace StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Exception;
use StoreEngine;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;
use StoreEngine\Utils\Validation;

class CartSubTotalTable {
	public function __construct() {
		add_shortcode( 'storeengine_cart_sub_total_table', array( $this, 'render_cart_sub_total_table' ) );
	}

	/**
	 * Calculate shipping for the cart.
	 */
	public static function calculate_shipping() {
		try {
			\StoreEngine\Shipping\Shipping::init()->reset_shipping();

			$address = [];

			// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Formatting::clean sanitizes the input
			$address['country']  = isset( $_POST['calc_shipping_country'] ) ? Formatting::clean( wp_unslash( $_POST['calc_shipping_country'] ) ) : ''; // WPCS: input var ok, CSRF ok, sanitization ok.
			$address['state']    = isset( $_POST['calc_shipping_state'] ) ? Formatting::clean( wp_unslash( $_POST['calc_shipping_state'] ) ) : ''; // WPCS: input var ok, CSRF ok, sanitization ok.
			$address['postcode'] = isset( $_POST['calc_shipping_postcode'] ) ? Formatting::clean( wp_unslash( $_POST['calc_shipping_postcode'] ) ) : ''; // WPCS: input var ok, CSRF ok, sanitization ok.
			$address['city']     = isset( $_POST['calc_shipping_city'] ) ? Formatting::clean( wp_unslash( $_POST['calc_shipping_city'] ) ) : ''; // WPCS: input var ok, CSRF ok, sanitization ok.
			// phpcs:enable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Formatting::clean sanitizes the input

			if ( $address['postcode'] ) {
				$address['postcode'] = Formatting::format_postcode( $address['postcode'], $address['country'] );
			}

			$address = apply_filters( 'storeengine/cart/calculate_shipping_address', $address );

			if ( $address['postcode'] && ! Validation::is_postcode( $address['postcode'], $address['country'] ) ) {
				throw new Exception( esc_html__( 'Please enter a valid postcode / ZIP.', 'storeengine' ) );
			}

			if ( $address['country'] ) {
				if ( ! StoreEngine::init()->customer->get_billing_first_name() ) {
					StoreEngine::init()->customer->set_billing_location( $address['country'], $address['state'], $address['postcode'], $address['city'] );
				}
				StoreEngine::init()->customer->set_shipping_location( $address['country'], $address['state'], $address['postcode'], $address['city'] );
			} else {
				StoreEngine::init()->customer->set_billing_address_to_base();
				StoreEngine::init()->customer->set_shipping_address_to_base();
			}

			StoreEngine::init()->customer->set_calculated_shipping( true );
			StoreEngine::init()->customer->save();

			storeengine_show_notice( __( 'Shipping costs updated.', 'storeengine' ), 'notice' );

			do_action( 'storeengine/cart/calculated_shipping' );

		} catch ( Exception $e ) {
			Helper::log_error( $e );
			storeengine_show_notice( $e->getMessage(), 'error' );
		}
	}

	public function render_cart_sub_total_table() {
		if ( Formatting::string_to_bool( get_query_var( 'order_pay' ) ) ) {
			return '';
		}

		if ( ! empty( $_POST['calc_shipping'] ) && ! empty( $_REQUEST['security'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_REQUEST['security'] ) ), 'storeengine_nonce' ) ) {
			self::calculate_shipping();

			// Update cart total.
			StoreEngine::init()->cart->calculate_totals();
		}

		ob_start();
		Template::get_template( 'shortcode/cart-sub-total-table.php' );
		return ob_get_clean();
	}
}
