<?php

namespace StoreEngine\Addons\MultiVendor\Classes\Webhooks;

use StoreEngine\Addons\Webhooks\Classes\AbstractListener;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VendorProductsAssigned extends AbstractListener {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action(
			'storeengine/multi_vendor/products_assigned',
			static function ( $vendor_id, $product_ids ) use ( $deliver_callback, $webhook ) {
				call_user_func_array( $deliver_callback, [ $webhook, [
					'vendor_id'    => (int) $vendor_id,
					'product_ids'  => array_map( 'intval', (array) $product_ids ),
					'event'        => 'vendor_products_assigned',
					'occurred_at'  => gmdate( 'c' ),
				] ] );
			},
			10,
			2
		);
	}
}
