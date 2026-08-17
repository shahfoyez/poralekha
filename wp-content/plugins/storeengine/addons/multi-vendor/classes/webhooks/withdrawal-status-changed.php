<?php

namespace StoreEngine\Addons\MultiVendor\Classes\Webhooks;

use StoreEngine\Addons\Webhooks\Classes\AbstractListener;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WithdrawalStatusChanged extends AbstractListener {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action(
			'storeengine/multi_vendor/withdrawal_status_changed',
			static function ( $withdrawal_id, $status, $vendor_id = 0 ) use ( $deliver_callback, $webhook ) {
				call_user_func_array( $deliver_callback, [ $webhook, [
					'withdrawal_id' => (int) $withdrawal_id,
					'status'        => (string) $status,
					'vendor_id'     => (int) $vendor_id,
					'event'         => 'withdrawal_status_changed',
					'occurred_at'   => gmdate( 'c' ),
				] ] );
			},
			10,
			3
		);
	}
}
