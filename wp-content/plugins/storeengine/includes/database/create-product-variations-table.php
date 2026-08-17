<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateProductVariationsTable {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_product_variations';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			sku VARCHAR(255) NOT NULL,
			barcode VARCHAR(64) DEFAULT NULL,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			price decimal(26,8),
			compare_price decimal(26,8),
			cost_price decimal(26,8) DEFAULT NULL,
			manage_stock TINYINT(1) NOT NULL DEFAULT 0,
			stock_quantity INT(11) DEFAULT NULL,
			stock_status VARCHAR(32) NOT NULL DEFAULT 'instock',
			backorders VARCHAR(16) NOT NULL DEFAULT 'no',
			low_stock_threshold INT(11) DEFAULT NULL,
			reorder_point INT(11) DEFAULT NULL,
			reorder_qty INT(11) DEFAULT NULL,
			preferred_supplier_id BIGINT(20) UNSIGNED DEFAULT NULL,
			PRIMARY KEY (id),
			KEY product_id (product_id),
			KEY stock_status (stock_status),
			KEY sku (sku),
			KEY barcode (barcode),
			KEY preferred_supplier_id (preferred_supplier_id)
		) $charset_collate;";

		dbDelta( $sql );
	}
}
