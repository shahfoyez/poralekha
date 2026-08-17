<?php

namespace StoreEngine\Addons\MultiVendor\Classes\Webhooks;

use StoreEngine\Addons\Webhooks\Classes\AbstractListener;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WithdrawalRequested extends AbstractListener {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action(
			'storeengine/multi_vendor/withdrawal_requested',
			static function ( $withdrawal_id, $vendor_id, $amount ) use ( $deliver_callback, $webhook ) {
				call_user_func_array( $deliver_callback, [ $webhook, [
					'withdrawal_id' => (int) $withdrawal_id,
					'vendor_id'     => (int) $vendor_id,
					'amount'        => (float) $amount,
					'event'         => 'withdrawal_requested',
					'occurred_at'   => gmdate( 'c' ),
				] ] );
			},
			10,
			3
		);
	}
}
