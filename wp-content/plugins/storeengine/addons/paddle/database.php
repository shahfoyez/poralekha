<?php

namespace StoreEngine\Addons\Paddle;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Database {

	public static function init() {
		add_action( 'init', [ __CLASS__, 'register_product_meta' ] );
	}

	public static function register_product_meta() {
		register_meta( 'post', '_storeengine_paddle_tax_category', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
		] );
	}
}
