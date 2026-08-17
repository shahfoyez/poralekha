<?php

namespace StoreEngine;

use StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


class API {

	public static function init() {
		$self = new self();

		API\Product::init();
		API\Reviews::init();
		API\Orders::init();
		API\cart::init();
		API\MiniCart::init();
		API\Payment::init();
		API\Analytics::init();
		API\ProductAnalytics::init();
		API\Customer::init();
		API\Settings::init();
		API\Taxes::init();
		API\Shipping::init();
		API\Coupon::init();
		API\Logs::init();
		API\Roles::init();
		API\EmailLog::init();
		API\Checkout::init();
		API\PaymentMethods::init();
		API\StorefrontAuth::init();
		API\Me::init();
		API\MeSubscriptions::init();
		API\BundleSync::init();


		add_filter( 'rest_api_init', [ $self, 'api_init' ] );
		add_filter( 'user_has_cap', array( $self, 'update_user_cap' ), 10, 3 );
	}

	public function api_init() {
		// @TODO move to specific api where cart is needed.
		StoreEngine::init()->load_cart();
	}

	public function update_user_cap( $all_caps, $cap, $args ) {
		if ( isset( $all_caps['edit_posts'] ) && $all_caps['edit_posts'] ) {
			$all_caps['edit_post_meta']   = true;
			$all_caps['delete_post_meta'] = true;
		}

		return $all_caps;
	}
}
