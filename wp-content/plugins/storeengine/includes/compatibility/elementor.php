<?php

namespace StoreEngine\Compatibility;

use StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Elementor {

	public static function init() {
		$self = new self();

		add_action( 'elementor/editor/init', [ $self, 'load_storeengine_cart' ] );
	}

	public function load_storeengine_cart() {
		StoreEngine::init()->load_cart();
	}
}
