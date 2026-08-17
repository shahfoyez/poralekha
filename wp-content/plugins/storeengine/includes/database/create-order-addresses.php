<?php
namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateOrderAddresses {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_order_addresses';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`order_id` bigint(20) unsigned NOT NULL,
			`address_type` varchar(20) DEFAULT NULL,
			`first_name` text,
			`last_name` text,
			`company` text,
			`address_1` text,
			`address_2` text,
			`city` text,
			`state` text,
			`postcode` text,
			`country` text,
			`email` varchar(320) DEFAULT NULL,
			`phone` varchar(100) DEFAULT NULL,
			PRIMARY KEY (`id`),
			UNIQUE KEY `address_type_order_id` (`address_type`,`order_id`),
			KEY `order_id` (`order_id`),
			KEY `email` (`email`(19))
		) $charset_collate;";

		dbDelta( $sql );
	}
}

