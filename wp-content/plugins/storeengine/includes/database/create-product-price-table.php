<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateProductPriceTable {

	public static function up( $prefix, $charset_collate ) {
		global $wpdb;
		$table_name = $prefix . 'storeengine_product_price';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			price_name VARCHAR(255) NOT NULL,
			price_type VARCHAR(255) NOT NULL,
			price VARCHAR(255) NOT NULL,
			compare_price VARCHAR(255) DEFAULT NULL,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			settings TEXT DEFAULT NULL,
			`order` INT(11) UNSIGNED DEFAULT 0,
			PRIMARY KEY (id),
			KEY product_id (product_id)
		) $charset_collate;";

		dbDelta( $sql );
	}
}
