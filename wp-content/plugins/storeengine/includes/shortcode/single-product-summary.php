<?php

namespace StoreEngine\Shortcode;

use StoreEngine\Utils\Helper;

class SingleProductSummary {

	public function __construct() {
		add_shortcode( 'storeengine_single_product_summary', [ $this, 'render' ] );
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

		$GLOBALS['product'] = $product; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		ob_start();
		Helper::get_template( 'shortcode/single-product-summary.php', [
			'product' => $product,
		] );

		return ob_get_clean();
	}

}
