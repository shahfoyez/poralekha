<?php

namespace StoreEngine\Addons\MultiVendor\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateWithdrawals {

	public static function up( $prefix, $charset_collate ) {
		$table = $prefix . 'vendor_withdrawals';
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			`user_id` BIGINT(20) UNSIGNED NOT NULL,
			`amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
			`status` ENUM('pending','approved','rejected','paid','cancelled') NOT NULL DEFAULT 'pending',
			`payment_method` VARCHAR(50) NOT NULL DEFAULT '',
			`payment_data` TEXT NULL,
			`vendor_note` TEXT NULL,
			`admin_note` TEXT NULL,
			`requested_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`processed_at` DATETIME NULL,
			`processed_by` BIGINT(20) UNSIGNED NULL,
			PRIMARY KEY (`id`),
			KEY `user_id` (`user_id`),
			KEY `status` (`status`),
			KEY `requested_at` (`requested_at`)
		) $charset_collate;";

		dbDelta( $sql );
	}
}
