<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateStockMovementsTable {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_stock_movements';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			variation_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			location_id BIGINT(20) UNSIGNED DEFAULT NULL,
			vendor_id BIGINT(20) UNSIGNED DEFAULT NULL,
			type VARCHAR(32) NOT NULL,
			reason VARCHAR(64) DEFAULT NULL,
			qty_change INT(11) NOT NULL,
			qty_before INT(11) DEFAULT NULL,
			qty_after INT(11) DEFAULT NULL,
			note TEXT DEFAULT NULL,
			user_id BIGINT(20) UNSIGNED DEFAULT NULL,
			order_id BIGINT(20) UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY product_id (product_id),
			KEY variation_id (variation_id),
			KEY location_id (location_id),
			KEY vendor_id (vendor_id),
			KEY type (type),
			KEY created_at (created_at)
		) $charset_collate;";

		dbDelta( $sql );
	}
}
