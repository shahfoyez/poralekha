<?php

namespace StoreEngine\Shortcode;

use StoreEngine\Utils\Helper;

class SingleProductDescription {

	public function __construct() {
		add_shortcode( 'storeengine_single_product_description', [ $this, 'render' ] );
	}

	public function render( array $attrs ) {
		$attributes = shortcode_atts( [
			'product_id' => 0,
		], $attrs );
		$product_id = absint( $attributes['product_id'] );
		if ( ! $product_id ) {
			$product_id = get_the_ID();
		}
		if ( ! $product_id ) {
			return '';
		}

		$product = Helper::get_product( $product_id );
		if ( ! $product ) {
			return '';
		}

		ob_start();
		Helper::get_template( 'single-product/description.php', [
			'content' => $product->get_content()
		] );

		return ob_get_clean();
	}

}
