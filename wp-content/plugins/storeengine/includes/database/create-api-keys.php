<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateApiKeys {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_api_keys';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			`key_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`user_id` bigint(20) unsigned NOT NULL,
			`description` varchar(200) DEFAULT NULL,
			`permissions` varchar(10) NOT NULL,
			`consumer_key` char(64) NOT NULL,
			`consumer_secret` char(43) NOT NULL,
			`nonces` longtext,
			`truncated_key` char(7) NOT NULL,
			`last_access` TIMESTAMP DEFAULT NULL,
			`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`key_id`),
			KEY `consumer_key` (`consumer_key`),
			KEY `consumer_secret` (`consumer_secret`)
        ) $charset_collate;";

		dbDelta( $sql );
	}
}
