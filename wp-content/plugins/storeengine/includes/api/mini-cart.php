<?php

namespace StoreEngine\API;

use StoreEngine\Classes\Countries;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\TaxUtil;
use StoreEngine\Utils\Template;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;


// no aliasing required

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MiniCart extends WP_REST_Controller {

	public static function init() {
		$self            = new self();
		$self->namespace = STOREENGINE_PLUGIN_SLUG . '/v1';
		$self->rest_base = 'mini-cart';

		add_action( 'rest_api_init', array( $self, 'register_routes' ) );
	}

	/**
	 * Register the routes for the objects of the controller.
	 */
	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/current', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_mini_cart_info' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'render' => [
					'type'              => 'boolean',
					'default'           => true,
				],
			],
		] );
	}

	public function get_mini_cart_info( WP_REST_Request $request ) {
		$cart   = Helper::cart();

		$response = apply_filters( 'storeengine/api/mini_cart_response', [
			'is_empty'         => $cart->is_cart_empty(),
			'tax_itemized'     => 'itemized' === Helper::get_settings( 'tax_total_display' ),
			'subtotal'         => (float) $cart->get_subtotal(),
			'total'            => (float) $cart->get_total( 'edit' ),
			'taxes_total'      => (float) $cart->get_taxes_total(),
			'tax_or_vat'       => Countries::get_instance()->tax_or_vat(),
			'tax_label_suffix' => TaxUtil::get_tax_label_suffix(),
			'tax_totals'       => $cart->get_tax_totals(),
			'totals'           => $cart->get_totals(),
			'fees'             => $cart->get_fees(),
			'cart_items_count' => $cart->get_count(),
			'cart_url'         => Helper::get_cart_url(),
			'checkout_url'     => Helper::get_checkout_url(),
			'shop_url'         => Helper::get_shop_url(),
			'cart_items'       => $cart->get_cart_items(),
			'cart_items_html'  => $request->get_param( 'render' ) ? Template::get_template_content( 'cart/cart-list-table.php' ) : null,
		] );

		return rest_ensure_response( $response );
	}

}
