<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateReservedStockTable {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_reserved_stock';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			order_id BIGINT(20) UNSIGNED NOT NULL,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			variation_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			stock_quantity INT(11) NOT NULL,
			expires DATETIME NOT NULL,
			PRIMARY KEY (order_id, product_id, variation_id),
			KEY expires (expires)
		) $charset_collate;";

		dbDelta( $sql );
	}
}
