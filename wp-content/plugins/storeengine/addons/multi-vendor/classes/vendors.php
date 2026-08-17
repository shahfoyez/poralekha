<?php

namespace StoreEngine\Addons\MultiVendor\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Vendors {

	protected static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'storeengine_vendors';
	}

	/**
	 * @param array $args { status?: string, search?: string, limit?: int, offset?: int }
	 * @return Vendor[]
	 */
	public static function query( array $args = [] ): array {
		global $wpdb;

		$args = wp_parse_args( $args, [
			'status' => '',
			'search' => '',
			'limit'  => 50,
			'offset' => 0,
		] );

		$where  = [];
		$values = [];

		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$values[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(store_name LIKE %s OR store_slug LIKE %s)';
			$like     = '%' . $wpdb->esc_like( (string) $args['search'] ) . '%';
			$values[] = $like;
			$values[] = $like;
		}

		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$values[]  = (int) $args['limit'];
		$values[]  = (int) $args['offset'];

		// Prepend the table identifier so it can be bound via the %i placeholder.
		array_unshift( $values, self::table() );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where_sql built from literal clauses only; identifier via %i and every value bound through prepare()'s $values array (dynamic placeholder count is not statically countable).
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i {$where_sql} ORDER BY date_registered DESC LIMIT %d OFFSET %d",
				$values
			),
			ARRAY_A
		);
		// phpcs:enable

		$out = [];
		foreach ( (array) $rows as $row ) {
			$vendor = new Vendor();
			// Use reflection-style: rely on internal setter via load(). We've already got the row,
			// so seed via the public API path: instantiate with user_id which triggers load().
			$vendor = new Vendor( (int) $row['user_id'] );
			if ( $vendor->exists() ) {
				$out[] = $vendor;
			}
		}

		return $out;
	}

	public static function get_by_slug( string $slug ): ?Vendor {
		global $wpdb;

		$slug = sanitize_title( $slug );
		if ( ! $slug ) {
			return null;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$user_id = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT user_id FROM %i WHERE store_slug = %s',
			self::table(),
			$slug
		) );
		// phpcs:enable

		if ( ! $user_id ) {
			return null;
		}

		$vendor = new Vendor( $user_id );
		return $vendor->exists() ? $vendor : null;
	}

	public static function count_by_status( string $status ): int {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE status = %s', self::table(), $status )
		);
		// phpcs:enable
	}

	public static function slug_exists( string $slug, int $exclude_user_id = 0 ): bool {
		global $wpdb;

		$slug = sanitize_title( $slug );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$count = (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE store_slug = %s AND user_id <> %d',
				self::table(),
				$slug,
				$exclude_user_id
			)
		);
		// phpcs:enable

		return $count > 0;
	}

	public static function unique_slug( string $base, int $user_id = 0 ): string {
		$base = sanitize_title( $base );
		if ( ! $base ) {
			$base = 'vendor-' . max( 1, $user_id );
		}

		$slug = $base;
		$i    = 2;
		while ( self::slug_exists( $slug, $user_id ) ) {
			$slug = $base . '-' . $i;
			$i++;
		}
		return $slug;
	}

	/**
	 * Resolve the global default commission rate from addon settings.
	 *
	 * @return array{ rate: float, type: string }
	 */
	public static function get_global_commission(): array {
		$opt = get_option( STOREENGINE_MULTI_VENDOR_SETTINGS_NAME, [] );
		if ( ! is_array( $opt ) ) {
			$opt = [];
		}
		return [
			'rate' => isset( $opt['commission_rate'] ) ? (float) $opt['commission_rate'] : 10.0,
			'type' => isset( $opt['commission_type'] ) && 'flat' === $opt['commission_type'] ? 'flat' : 'percent',
		];
	}
}
