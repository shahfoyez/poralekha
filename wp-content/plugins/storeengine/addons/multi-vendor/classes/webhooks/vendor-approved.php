<?php

namespace StoreEngine\Addons\MultiVendor\Classes\Webhooks;

use StoreEngine\Addons\Webhooks\Classes\AbstractListener;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VendorApproved extends AbstractListener {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action(
			'storeengine/multi_vendor/vendor_approved',
			static function ( $vendor_id ) use ( $deliver_callback, $webhook ) {
				call_user_func_array( $deliver_callback, [ $webhook, [
					'vendor_id'   => (int) $vendor_id,
					'event'       => 'vendor_approved',
					'occurred_at' => gmdate( 'c' ),
				] ] );
			}
		);
	}
}
