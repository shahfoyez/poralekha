<?php

namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateDownloadLog {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_download_log';
		$sql        = "CREATE TABLE IF NOT EXISTS $table_name (
			id bigint(20) unsigned NOT NULL auto_increment,
			user_id bigint(20) unsigned NOT NULL,
			permission_id bigint(20) unsigned NOT NULL,
			user_ip_address varchar(100) NOT NULL,
			`timestamp` DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id)
		) $charset_collate;";

		dbDelta( $sql );
	}
}
