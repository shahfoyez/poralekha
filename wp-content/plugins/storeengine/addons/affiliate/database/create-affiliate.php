<?php

namespace StoreEngine\Addons\Affiliate\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateAffiliate {

	/**
	 * Canonical order of the `status` ENUM. Keep in sync with
	 * {@see \StoreEngine\Addons\Affiliate\models\Affiliate::statuses()}.
	 */
	const STATUS_ENUM = [ 'active', 'inactive', 'pending', 'rejected', 'suspended' ];

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'affiliates';
		$enum       = "'" . implode( "', '", self::STATUS_ENUM ) . "'";
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			`affiliate_id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
			`user_id` BIGINT(20) UNSIGNED NOT NULL,
			`commission_type` ENUM('percentage', 'flat') NOT NULL,
			`commission_rate` INT(3) UNSIGNED NOT NULL,
			`status` ENUM({$enum}) NOT NULL,
			`created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (`affiliate_id`),
			UNIQUE KEY `user_id` (`user_id`)
        ) $charset_collate;";

		dbDelta( $sql );

		// dbDelta cannot alter an existing ENUM definition, so widen it explicitly
		// for stores created before `rejected`/`suspended` existed. Idempotent.
		self::ensure_status_enum( $table_name );
	}

	/**
	 * Bring an existing `status` column up to the full ENUM. Runs on the
	 * version-triggered schema re-sync; no-op once already widened.
	 */
	private static function ensure_status_enum( string $table_name ): void {
		global $wpdb;

		// $table_name is composed from $wpdb->prefix — no user input — so it is
		// safe to interpolate (identifiers can't be bound as placeholders).
		$column = $wpdb->get_row( "SHOW COLUMNS FROM `{$table_name}` LIKE 'status'" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange

		if ( ! $column || empty( $column->Type ) ) {
			return;
		}

		foreach ( self::STATUS_ENUM as $value ) {
			if ( false === strpos( $column->Type, "'{$value}'" ) ) {
				$enum = "'" . implode( "', '", self::STATUS_ENUM ) . "'";
				$wpdb->query( "ALTER TABLE `{$table_name}` MODIFY COLUMN `status` ENUM({$enum}) NOT NULL" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
				return;
			}
		}
	}
}
