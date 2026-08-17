<?php

namespace StoreEngine\Shortcode;

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

class SingleProductCartNotice {

	public function __construct() {
		add_shortcode( 'storeengine_single_product_cart_notice', [ $this, 'render' ] );
	}

	public function render( array $attrs ) {
		$attributes = shortcode_atts( [
			'product_id' => 0,
		], $attrs );

		$cart = Helper::cart();
		do_action( 'storeengine/cart/check_items' );

		$product_id = absint( $attributes['product_id'] );
		if ( ! $product_id ) {
			$product_id = get_the_ID();
		}
		if ( ! $product_id ) {
			return '';
		}

		ob_start();
		$cart_item = $cart->get_cart_items_by_product( $product_id );
		if ( $cart_item ) {
			$total_quantity_in_cart = array_sum( array_column( $cart_item, 'quantity' ) );
			$num_prices_in_cart     = count( $cart_item );
			Template::get_template( 'notice/view-cart.php', [
				'total_quantity_in_cart' => $total_quantity_in_cart,
				'num_prices_in_cart'     => $num_prices_in_cart,
			] );
		}

		return ob_get_clean();
	}

}
