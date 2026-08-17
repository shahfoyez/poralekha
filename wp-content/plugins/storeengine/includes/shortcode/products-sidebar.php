<?php

namespace StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductsSidebar {

	public function __construct() {
		add_shortcode( 'storeengine_products_sidebar', [ $this, 'render' ] );
	}

	public function render() {
		ob_start();
		do_action( 'storeengine/templates/archive_product_sidebar' );
		return ob_get_clean();
	}

}
