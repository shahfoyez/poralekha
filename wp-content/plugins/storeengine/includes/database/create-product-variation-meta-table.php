<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateProductVariationMetaTable {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_product_variation_meta';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			variation_id BIGINT(20) UNSIGNED,
			meta_key VARCHAR(255),
			meta_value VARCHAR(9999),
			PRIMARY KEY (id),
			INDEX variation_id_index (variation_id),
			INDEX meta_key_index (meta_key(191)),
			INDEX meta_value_index (meta_value(191))
        ) $charset_collate;";

		dbDelta( $sql );
	}
}
