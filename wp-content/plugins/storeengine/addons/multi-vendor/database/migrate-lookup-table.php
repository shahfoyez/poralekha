<?php

namespace StoreEngine\Addons\MultiVendor\Database;

use StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MigrateLookupTable {

	const BACKFILL_BATCH_SIZE = 200;
	const BACKFILL_HOOK       = 'storeengine/multi_vendor/backfill_lookup_vendor_id';

	public static function migrate_vendors_table() {
		global $wpdb;
		$table = $wpdb->prefix . 'storeengine_vendors';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Schema migration on a custom StoreEngine table; identifier passed via %i to prepare(), no cache layer applies.
		$has_visible = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM %i LIKE %s", $table, 'is_visible' ) );
		if ( ! $has_visible ) {
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `is_visible` TINYINT(1) NOT NULL DEFAULT 1", $table ) );
		}

		$has_default = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM %i LIKE %s", $table, 'is_default' ) );
		if ( ! $has_default ) {
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `is_default` TINYINT(1) NOT NULL DEFAULT 0", $table ) );
		}

		$has_badges = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM %i LIKE %s", $table, 'badges' ) );
		if ( ! $has_badges ) {
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `badges` TEXT NULL", $table ) );
		}

		$has_payment = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM %i LIKE %s", $table, 'payment_method' ) );
		if ( ! $has_payment ) {
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `payment_method` VARCHAR(50) NOT NULL DEFAULT 'paypal'", $table ) );
		}

		$has_payment_data = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM %i LIKE %s", $table, 'payment_data' ) );
		if ( ! $has_payment_data ) {
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `payment_data` TEXT NULL", $table ) );
		}

		$has_auto_approve_products = $wpdb->get_var( $wpdb->prepare( "SHOW COLUMNS FROM %i LIKE %s", $table, 'auto_approve_products' ) );
		if ( ! $has_auto_approve_products ) {
			$wpdb->query( $wpdb->prepare( "ALTER TABLE %i ADD COLUMN `auto_approve_products` TINYINT(1) NOT NULL DEFAULT 0", $table ) );
		}
		// phpcs:enable
	}

	public static function schedule_backfill() {
		if ( ! StoreEngine::init()->queue() ) {
			return;
		}

		if ( ! StoreEngine::init()->queue()->get_next( self::BACKFILL_HOOK ) ) {
			StoreEngine::init()->queue()->schedule_single( time() + 5, self::BACKFILL_HOOK, [ 0 ] );
		}

		// Idempotent listener registration. Safe to re-add.
		add_action( self::BACKFILL_HOOK, [ __CLASS__, 'run_backfill' ] );
	}

	/**
	 * Walks lookup rows where vendor_id IS NULL in batches and fills it from product post_author.
	 *
	 * @param int $offset
	 */
	public static function run_backfill( $offset = 0 ) {
		global $wpdb;

		$table = $wpdb->prefix . 'storeengine_order_product_lookup';
		$batch = (int) self::BACKFILL_BATCH_SIZE;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared (%i/%d) query on a custom StoreEngine table; batch backfill, no cache layer applies.
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT order_item_id, product_id FROM %i WHERE vendor_id IS NULL OR vendor_id = 0 LIMIT %d",
			$table,
			$batch
		) );
		// phpcs:enable

		if ( empty( $rows ) ) {
			return;
		}

		foreach ( $rows as $row ) {
			$author = (int) get_post_field( 'post_author', (int) $row->product_id );

			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$wpdb->update(
				$table,
				[ 'vendor_id' => $author > 0 ? $author : null ],
				[ 'order_item_id' => (int) $row->order_item_id ],
				[ '%d' ],
				[ '%d' ]
			);
			// phpcs:enable
		}

		// Schedule next batch if more rows remain.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared (%i) count on a custom StoreEngine table; no cache layer applies.
		$remaining = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE vendor_id IS NULL", $table ) );
		// phpcs:enable

		if ( $remaining > 0 && \StoreEngine::init()->queue() ) {
			\StoreEngine::init()->queue()->schedule_single( time() + 5, self::BACKFILL_HOOK, [ $offset + $batch ] );
		}
	}
}
