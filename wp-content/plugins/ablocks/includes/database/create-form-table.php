<?php
namespace ABlocks\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateFormTable {
	public static function up( $prefix, $charset_collate ) {
		global $wpdb;
		$table_entries = $prefix . ABLOCKS_PLUGIN_SLUG . '_form_entries';
		$table_meta = $prefix . ABLOCKS_PLUGIN_SLUG . '_form_meta';

		$sql_entries = "CREATE TABLE $table_entries (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            form_type VARCHAR(50) NOT NULL,

            post_id VARCHAR(100) NULL,  -- for post id
            ip VARCHAR(100) NULL,  -- for user ip
            is_in_trash VARCHAR(20) DEFAULT 'no',  
            user_agent VARCHAR(250) NULL,  -- for user browser agent

            block_id VARCHAR(100) NOT NULL,
            user_email VARCHAR(100) NOT NULL,
            status VARCHAR(50) DEFAULT 'unread',
            is_email_verified VARCHAR(20) DEFAULT 'no',
            expire INT(20) NULL,
            email_verification_token VARCHAR(100) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id)
        ) $charset_collate;";

		// SQL to create the custom_form_meta table
		$sql_meta = "CREATE TABLE $table_meta (
            meta_id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            entry_id BIGINT(20) UNSIGNED NOT NULL,
            meta_key VARCHAR(255) NOT NULL,
            meta_value TEXT NOT NULL,
            PRIMARY KEY (meta_id),
            FOREIGN KEY (entry_id) REFERENCES $table_entries(id) ON DELETE CASCADE ON UPDATE CASCADE
        ) $charset_collate;";

		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared 
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_entries}'" ) !== $table_entries ) {
			dbDelta( $sql_entries );
		}
		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared 
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_meta}'" ) !== $table_meta ) {
			dbDelta( $sql_meta );
		}

	}
	public static function down( $prefix ) {
		global $wpdb;

		$table_entries = $prefix . ABLOCKS_PLUGIN_SLUG . '_form_entries';
		$table_meta = $prefix . ABLOCKS_PLUGIN_SLUG . '_form_meta';

		$sql = "DROP TABLE IF EXISTS $table_meta, $table_entries;";
		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching,  WordPress.DB.PreparedSQL.NotPrepared 
		$wpdb->query( $sql );
	}
}
