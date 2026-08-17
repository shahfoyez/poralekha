<?php

namespace StoreEngine\Addons\MultiVendor\Classes\Webhooks;

use StoreEngine\Addons\Webhooks\Classes\AbstractListener;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CommissionReversed extends AbstractListener {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action(
			'storeengine/multi_vendor/commission_reversed',
			static function ( $order_id, $vendor_id, $amount ) use ( $deliver_callback, $webhook ) {
				call_user_func_array( $deliver_callback, [ $webhook, [
					'order_id'    => (int) $order_id,
					'vendor_id'   => (int) $vendor_id,
					'amount'      => (float) $amount,
					'event'       => 'commission_reversed',
					'occurred_at' => gmdate( 'c' ),
				] ] );
			},
			10,
			3
		);
	}
}
