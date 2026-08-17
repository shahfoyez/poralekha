<?php

namespace StoreEngine\Addons\Affiliate\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateCommission {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'affiliate_commissions';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
            `commission_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
            `affiliate_id` INT(11) UNSIGNED NOT NULL,
            `order_id` INT(11) UNSIGNED NOT NULL,
            `commission_amount` DECIMAL(10,2) NOT NULL,
            `status` ENUM('pending', 'approved', 'paid', 'rejected') NOT NULL,
            `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`commission_id`),
    		KEY `affiliate_id` (`affiliate_id`),
    		KEY `order_id` (`order_id`)
        ) $charset_collate;";
		dbDelta( $sql );

		// dbDelta will not widen an existing ENUM, so apply the 'rejected'
		// state (used for refund/cancellation reversals) explicitly. Idempotent:
		// re-running MODIFY with the same definition is a no-op.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifier passed via %i to prepare(); reading column definition on a custom StoreEngine table during migration.
		$column = $wpdb->get_row( $wpdb->prepare( 'SHOW COLUMNS FROM %i LIKE %s', $table_name, 'status' ), ARRAY_A );
		if ( $column && false === strpos( (string) $column['Type'], 'rejected' ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifier passed via %i to prepare(); one-off ENUM widen migration on a custom StoreEngine table.
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i MODIFY COLUMN `status` ENUM('pending', 'approved', 'paid', 'rejected') NOT NULL", $table_name ) );
		}
	}
}
