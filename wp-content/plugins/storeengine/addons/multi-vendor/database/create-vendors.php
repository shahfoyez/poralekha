<?php

namespace StoreEngine\Addons\MultiVendor\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateVendors {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'vendors';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			`user_id` BIGINT(20) UNSIGNED NOT NULL,
			`store_name` VARCHAR(255) NOT NULL DEFAULT '',
			`store_slug` VARCHAR(190) NOT NULL DEFAULT '',
			`status` ENUM('pending','approved','suspended') NOT NULL DEFAULT 'pending',
			`commission_rate` DECIMAL(5,2) NULL,
			`commission_type` ENUM('percent','flat') NOT NULL DEFAULT 'percent',
			`payout_email` VARCHAR(190) NOT NULL DEFAULT '',
			`first_name` VARCHAR(100) NOT NULL DEFAULT '',
			`last_name` VARCHAR(100) NOT NULL DEFAULT '',
			`phone` VARCHAR(50) NOT NULL DEFAULT '',
			`address` TEXT NULL,
			`store_description` TEXT NULL,
			`business_type` ENUM('individual','company') NOT NULL DEFAULT 'individual',
			`terms_accepted_at` DATETIME NULL,
			`date_registered` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`date_approved` DATETIME NULL,
			PRIMARY KEY (`user_id`),
			UNIQUE KEY `store_slug` (`store_slug`),
			KEY `status` (`status`)
		) $charset_collate;";

		dbDelta( $sql );
	}
}
