<?php

namespace StoreEngine\Shortcode;

use StoreEngine\Models\Product;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

class SingleProductReviews {

	public function __construct() {
		add_shortcode( 'storeengine_single_product_reviews', [ $this, 'render' ] );
	}

	public function render( $attrs ) {
		$attributes = shortcode_atts( [
			'product_id' => 0,
			'is_enabled' => 'default',
		], $attrs );
		$product_id = absint( $attributes['product_id'] );
		if ( ! $product_id ) {
			$product_id = get_the_ID();
		}
		if ( ! $product_id ) {
			return '';
		}

		if ( 'default' === $attributes['is_enabled'] ) {
			$enable_product_reviews = Helper::get_settings( 'enable_product_reviews' );
			if ( ! $enable_product_reviews ) {
				return '';
			}
		} else if ( ! Formatting::string_to_bool( $attributes['is_enabled'] ) ) {
			return '';
		}

		$rating = Product::get_product_rating( $product_id );
		ob_start();
		Helper::get_template( 'shortcode/single-product-reviews.php', [
			'rating'     => $rating,
			'product_id' => $product_id
		] );

		return ob_get_clean();
	}

}
