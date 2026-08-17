<?php

namespace StoreEngine\Addons\Couriers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Database {

	const DB_VERSION = '1.2.0';

	public static function shipments_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'storeengine_courier_shipments';
	}

	/**
	 * Create/upgrade the shipments table.
	 *
	 * No per-addon version bookkeeping: the core addon manager
	 * (\StoreEngine\Addons::sync_schema_for) compares this addon's
	 * get_db_version() against the shared `storeengine_addons_db_version` map and
	 * calls install_tables() (→ here) only on a mismatch, then records the
	 * version itself. dbDelta is idempotent, so activation can call this directly.
	 */
	public static function install(): void {
		global $wpdb;
		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		$cc = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

		$shipments = self::shipments_table();

		dbDelta( "CREATE TABLE IF NOT EXISTS {$shipments} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT(20) UNSIGNED NOT NULL,
			vendor_id BIGINT(20) UNSIGNED DEFAULT NULL,
			provider VARCHAR(40) NOT NULL,
			tracking_id VARCHAR(190) DEFAULT NULL,
			consignment_id VARCHAR(190) DEFAULT NULL,
			status VARCHAR(40) NOT NULL DEFAULT 'created',
			internal_status VARCHAR(40) NOT NULL DEFAULT 'created',
			label_url VARCHAR(500) DEFAULT NULL,
			tracking_url VARCHAR(500) DEFAULT NULL,
			cost DECIMAL(20,4) DEFAULT NULL,
			cod_amount DECIMAL(20,4) DEFAULT NULL,
			weight_kg DECIMAL(10,3) DEFAULT NULL,
			payload TEXT DEFAULT NULL,
			response TEXT DEFAULT NULL,
			last_status_check DATETIME DEFAULT NULL,
			delivered_at DATETIME DEFAULT NULL,
			date_created DATETIME NOT NULL,
			date_modified DATETIME NOT NULL,
			PRIMARY KEY (id),
			KEY order_id (order_id),
			KEY vendor_id (vendor_id),
			KEY provider (provider),
			KEY status (status),
			KEY internal_status (internal_status),
			KEY tracking_id (tracking_id)
		) {$cc};" );
	}
}
