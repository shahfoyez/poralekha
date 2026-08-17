<?php

namespace StoreEngine\Shortcode;

use StoreEngine\Utils\Helper;

class SingleProductFaq {

	public function __construct() {
		add_shortcode( 'storeengine_single_product_faq', [ $this, 'render' ] );
	}

	public function render( $attrs ) {
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

		if ( ! (bool) Helper::get_settings( 'enable_faqs', true ) ) {
			return '';
		}

		$groups = storeengine_get_product_faqs( $product_id );
		if ( empty( $groups ) ) {
			return '';
		}

		ob_start();
		Helper::get_template( 'single-product/faq.php', [
			'product_id' => $product_id,
			'groups'     => $groups,
		] );

		if ( function_exists( 'storeengine_product_faq_schema' ) ) {
			storeengine_product_faq_schema( $product_id, $groups );
		}

		return ob_get_clean();
	}

}
