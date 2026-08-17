<?php

namespace StoreEngine\Shortcode;

use StoreEngine\Utils\Template;

class SingleProductGallery {

	public function __construct() {
		add_shortcode( 'storeengine_single_product_gallery', [ $this, 'render' ] );
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

		// Custom vanilla-JS gallery — loaded only when the product has media.
		storeengine_enqueue_product_gallery_assets( $product_id );

		ob_start();
		Template::get_template( 'single-product/gallery.php', [
			'product_id' => $product_id,
		] );

		return ob_get_clean();
	}

}
