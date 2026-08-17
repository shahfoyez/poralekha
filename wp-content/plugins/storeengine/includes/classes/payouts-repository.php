<?php

namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * CRUD + migration for the unified `wp_storeengine_payouts` ledger.
 *
 * Each addon writes through this repository with a `payee_type`
 * discriminator (`affiliate`, `vendor`, …). Reads filter on the same
 * column via the (payee_type, payee_id) index.
 */
final class PayoutsRepository {

	const TYPE_AFFILIATE = 'affiliate';
	const TYPE_VENDOR    = 'vendor';

	const MIGRATION_OPTION = 'storeengine_payouts_migration_v1';

	const STATUS_PENDING    = 'pending';
	const STATUS_PROCESSING = 'processing';
	const STATUS_PAID       = 'paid';
	const STATUS_FAILED     = 'failed';
	const STATUS_CANCELLED  = 'cancelled';

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'storeengine_payouts';
	}

	/**
	 * @return int Inserted payout id, or 0 on failure.
	 */
	public static function create( string $payee_type, int $payee_id, array $data ): int {
		global $wpdb;

		$row = [
			'payee_type'     => $payee_type,
			'payee_id'       => $payee_id,
			'amount'         => isset( $data['amount'] ) ? (float) $data['amount'] : 0.0,
			'payment_method' => isset( $data['payment_method'] ) ? (string) $data['payment_method'] : null,
			'reference'      => isset( $data['reference'] ) ? (string) $data['reference'] : '',
			'status'         => isset( $data['status'] ) ? (string) $data['status'] : self::STATUS_PENDING,
			'notes'          => $data['notes'] ?? null,
			'meta_json'      => isset( $data['meta_json'] )
				? ( is_string( $data['meta_json'] ) ? $data['meta_json'] : wp_json_encode( $data['meta_json'] ) )
				: null,
			'created_at'     => $data['created_at'] ?? current_time( 'mysql', 1 ),
			'paid_at'        => $data['paid_at'] ?? null,
		];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->insert( self::table(), $row );
		// phpcs:enable

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	public static function update( int $id, array $data ): bool {
		if ( $id <= 0 ) return false;

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->update( self::table(), $data, [ 'id' => $id ] );
		// phpcs:enable
		return false !== $ok;
	}

	public static function get( int $id ): ?object {
		if ( $id <= 0 ) return null;

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
		$row = $wpdb->get_row( $wpdb->prepare(
			'SELECT * FROM %i WHERE id = %d',
			self::table(),
			$id
		) );
		// phpcs:enable
		return $row ?: null;
	}

	/**
	 * @param array $filters status, search, per_page, page, count
	 */
	public static function find_for( string $payee_type, int $payee_id = 0, array $filters = [] ): array {
		global $wpdb;

		$where  = [ 'payee_type = %s' ];
		$values = [ $payee_type ];

		if ( $payee_id > 0 ) {
			$where[]  = 'payee_id = %d';
			$values[] = $payee_id;
		}

		if ( ! empty( $filters['status'] ) && 'any' !== $filters['status'] ) {
			$where[]  = 'status = %s';
			$values[] = (string) $filters['status'];
		}

		$where_sql = ' WHERE ' . implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $where_sql built from literal clauses only; identifier via %i and every value bound through prepare()'s spread $values (dynamic placeholder count is not statically countable).
		if ( ! empty( $filters['count'] ) ) {
			return [ (int) $wpdb->get_var( $wpdb->prepare(
				'SELECT COUNT(*) FROM %i' . $where_sql,
				self::table(),
				...$values
			) ) ];
		}

		$per_page = max( 1, (int) ( $filters['per_page'] ?? 50 ) );
		$page     = max( 1, (int) ( $filters['page'] ?? 1 ) );
		$offset   = ( $page - 1 ) * $per_page;

		$values[] = $per_page;
		$values[] = $offset;

		$rows = $wpdb->get_results( $wpdb->prepare(
			'SELECT * FROM %i' . $where_sql . ' ORDER BY created_at DESC LIMIT %d OFFSET %d',
			self::table(),
			...$values
		) );
		// phpcs:enable

		return is_array( $rows ) ? $rows : [];
	}

	/**
	 * Delete a payout — used by the "rollback" admin action only.
	 */
	public static function delete( int $id ): bool {
		if ( $id <= 0 ) return false;
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ok = $wpdb->delete( self::table(), [ 'id' => $id ] );
		// phpcs:enable
		return (bool) $ok;
	}

	/**
	 * One-shot copy of legacy `wp_affiliate_payouts` and `wp_vendor_payouts`
	 * (and their accidental `storeengine_*` siblings, if present) into the
	 * unified ledger. Idempotent via `storeengine_payouts_migration_v1`.
	 */
	public static function migrate_legacy_tables(): void {
		if ( get_option( self::MIGRATION_OPTION ) ) {
			return;
		}

		global $wpdb;
		$prefix = $wpdb->prefix;

		// Try both naming variants — older code accidentally created tables
		// under both `affiliate_payouts` and `storeengine_affiliate_payouts`.
		$affiliate_sources = [ $prefix . 'storeengine_affiliate_payouts', $prefix . 'affiliate_payouts' ];
		foreach ( $affiliate_sources as $src ) {
			if ( ! self::table_exists( $src ) ) continue;

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( "SELECT * FROM {$src}", ARRAY_A );
			// phpcs:enable
			if ( ! is_array( $rows ) ) continue;

			foreach ( $rows as $r ) {
				self::create( self::TYPE_AFFILIATE, (int) ( $r['affiliate_id'] ?? 0 ), [
					'amount'         => (float) ( $r['payout_amount'] ?? 0 ),
					'payment_method' => isset( $r['payment_method'] ) ? self::normalize_payment_method( (string) $r['payment_method'] ) : null,
					'reference'      => (string) ( $r['transaction_id'] ?? '' ),
					'status'         => self::map_legacy_status( (string) ( $r['status'] ?? 'pending' ) ),
					'created_at'     => $r['created_at'] ?? null,
				] );
			}
			// First source that yielded rows wins; don't double-import.
			break;
		}

		$vendor_sources = [ $prefix . 'storeengine_vendor_payouts', $prefix . 'vendor_payouts' ];
		foreach ( $vendor_sources as $src ) {
			if ( ! self::table_exists( $src ) ) continue;

			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared
			$rows = $wpdb->get_results( "SELECT * FROM {$src}", ARRAY_A );
			// phpcs:enable
			if ( ! is_array( $rows ) ) continue;

			foreach ( $rows as $r ) {
				self::create( self::TYPE_VENDOR, (int) ( $r['user_id'] ?? 0 ), [
					'amount'     => (float) ( $r['amount'] ?? 0 ),
					'reference'  => (string) ( $r['reference'] ?? '' ),
					'status'     => (string) ( $r['status'] ?? 'pending' ),
					'notes'      => $r['notes'] ?? null,
					'created_at' => $r['created_at'] ?? null,
					'paid_at'    => $r['paid_at'] ?? null,
				] );
			}
			break;
		}

		update_option( self::MIGRATION_OPTION, 1, false );
	}

	protected static function table_exists( string $table ): bool {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		// phpcs:enable
		return $found === $table;
	}

	protected static function normalize_payment_method( string $raw ): string {
		// Affiliate's old ENUM values: 'PayPal','Bank Transfer','Stripe','Check Payment','E-Check'.
		$slug = strtolower( str_replace( [ ' ', '-' ], '_', trim( $raw ) ) );
		return preg_replace( '/[^a-z0-9_]/', '', $slug );
	}

	protected static function map_legacy_status( string $raw ): string {
		$s = strtolower( trim( $raw ) );
		if ( 'completed' === $s ) return self::STATUS_PAID;
		if ( in_array( $s, [ 'pending', 'paid', 'failed', 'cancelled', 'processing' ], true ) ) {
			return $s;
		}
		return self::STATUS_PENDING;
	}
}
