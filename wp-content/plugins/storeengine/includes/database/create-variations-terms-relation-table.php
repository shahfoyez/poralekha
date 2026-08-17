<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateVariationsTermsRelationTable {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_variation_term_relations';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            variation_id BIGINT UNSIGNED,
            term_id BIGINT UNSIGNED,
            term_order INT UNSIGNED DEFAULT 0,
            PRIMARY KEY (id),
            KEY term_id (term_id),
            KEY variation_id (variation_id)
        ) $charset_collate;";

		dbDelta( $sql );
	}
}
