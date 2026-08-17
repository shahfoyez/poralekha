<?php

namespace StoreEngine\Shortcode;

use StoreEngine\Utils\Helper;

class SingleProductComments {

	public function __construct() {
		add_shortcode( 'storeengine_single_product_comments', [ $this, 'render' ] );
	}

	public function render() {
		$enable_product_comments = (bool) Helper::get_settings( 'enable_product_comments', false );

		if ( ! $enable_product_comments ) {
			return '';
		}

		ob_start();
		if ( comments_open() || get_comments_number() ) {
			comments_template();
		}

		return ob_get_clean();
	}

}
