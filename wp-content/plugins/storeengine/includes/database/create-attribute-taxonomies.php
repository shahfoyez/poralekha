<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateAttributeTaxonomies {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_attribute_taxonomies';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
	        `attribute_id` BIGINT(20) unsigned NOT NULL AUTO_INCREMENT,
	        `attribute_name` VARCHAR(200) NOT NULL,
	        `attribute_label` VARCHAR(200) NULL,
	        `attribute_type` VARCHAR(20) NOT NULL,
	        `attribute_orderby` VARCHAR(20) NOT NULL,
	        `attribute_public` INT(1) NOT NULL DEFAULT 1,
	        PRIMARY KEY  (`attribute_id`)
    	) $charset_collate;";

		dbDelta( $sql );
	}
}
