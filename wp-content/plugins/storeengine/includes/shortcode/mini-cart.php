<?php

namespace StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

class MiniCart {

	public function __construct() {
		add_shortcode( 'storeengine_mini_cart', array( $this, 'render' ) );
	}

	public function render( $atts ) {
		do_action( 'storeengine/cart/check_items' );

		return Template::get_template_content( 'shortcode/mini-cart.php', [
			'item_count' => Helper::cart()->get_count(),
			'cart_url'   => Helper::get_page_permalink( 'cart_page' ),
		] );
	}
}
