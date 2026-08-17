<?php

namespace StoreEngine\Addons\Affiliate\models;

use StoreEngine\Addons\Affiliate\Helper as HelperAddon;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\PayoutsRepository;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Payout {

	public static function get_payouts( $args = [] ) {
		global $wpdb;

		$defaults = [
			'payout_id' => null,
			'user_id'   => null,
			'offset'    => 0,
			'per_page'  => 10,
			'count'     => false,
			'status'    => 'any',
			'search'    => '',
		];

		$args  = wp_parse_args( $args, $defaults );
		$table = PayoutsRepository::table();

		// Single-row lookup: read straight from the unified ledger and shape
		// it back into the legacy associative format the UI consumes.
		if ( $args['payout_id'] ) {
			$row = PayoutsRepository::get( (int) $args['payout_id'] );
			if ( ! $row || PayoutsRepository::TYPE_AFFILIATE !== $row->payee_type ) {
				return [];
			}
			return self::shape_for_legacy_ui( $row );
		}

		if ( $args['count'] ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Identifier passed via %i to prepare(); COUNT over a custom StoreEngine ledger table.
			return (int) $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE payee_type = %s', $table, 'affiliate' )
			);
			// phpcs:enable
		}

		// Listing: join through affiliates → users to get display_name/email.
		// payee_id stores affiliate_id (matches the legacy semantics).
		$query = "SELECT
            p.id              AS payout_id,
            p.payee_id        AS affiliate_id,
            u.display_name,
            u.user_email,
            p.payment_method,
            p.amount          AS payout_amount,
            p.reference       AS transaction_id,
            p.created_at,
            p.status
        FROM
            {$table} p
        LEFT JOIN
            {$wpdb->prefix}storeengine_affiliates a ON p.payee_id = a.affiliate_id
        LEFT JOIN
            {$wpdb->users} u ON a.user_id = u.ID
        WHERE p.payee_type = 'affiliate'";

		if ( $args['user_id'] ) {
			$query .= $wpdb->prepare( ' AND a.user_id = %d', (int) $args['user_id'] );
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted table identifiers ($wpdb->prefix/users, repo table constant) interpolated; all user values bound via prepare(); custom StoreEngine tables.
			return $wpdb->get_results( $query, ARRAY_A ) ?: [];
			// phpcs:enable
		}

		if ( 'any' !== $args['status'] ) {
			$query .= $wpdb->prepare( ' AND p.status = %s AND u.display_name LIKE %s', $args['status'], '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		} else {
			$query .= $wpdb->prepare( ' AND u.display_name LIKE %s', '%' . $wpdb->esc_like( $args['search'] ) . '%' );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Trusted table identifiers interpolated; all user values bound via prepare(); custom StoreEngine tables.
		return $wpdb->get_results( $wpdb->prepare(
			"{$query} ORDER BY p.created_at DESC LIMIT %d, %d",
			(int) $args['offset'],
			(int) $args['per_page']
		), ARRAY_A ) ?: [];
		// phpcs:enable
	}

	public static function save( $args = [] ) {
		try {
			$id = PayoutsRepository::create(
				PayoutsRepository::TYPE_AFFILIATE,
				(int) ( $args['affiliate_id'] ?? 0 ),
				[
					'amount'         => (float) ( $args['payout_amount'] ?? 0 ),
					'payment_method' => isset( $args['payment_method'] ) ? sanitize_key( (string) $args['payment_method'] ) : null,
					'reference'      => HelperAddon::generate_random_code( 'payouts', 12 ),
					'status'         => PayoutsRepository::STATUS_PENDING,
				]
			);

			if ( ! $id ) {
				global $wpdb;
				return new WP_Error( 'failed-to-insert', esc_html( $wpdb->last_error ?: 'Unknown error' ) );
			}

			return self::get_payouts( [ 'payout_id' => $id ] );
		} catch ( StoreEngineException $e ) {
			return $e->toWpError();
		}
	}

	public static function update( int $id, array $args ) {
		$ok = PayoutsRepository::update( $id, $args );
		if ( ! $ok ) {
			global $wpdb;
			return new WP_Error( 'failed-to-update', $wpdb->last_error );
		}
		return $ok;
	}

	/**
	 * Shapes a unified-ledger row back into the column names the affiliate
	 * UI was built against, so the React table doesn't need to change.
	 */
	protected static function shape_for_legacy_ui( object $row ): array {
		global $wpdb;

		// Resolve display_name / user_email via affiliates → users.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$user = $wpdb->get_row( $wpdb->prepare(
			"SELECT u.display_name, u.user_email
			   FROM {$wpdb->prefix}storeengine_affiliates a
			   LEFT JOIN {$wpdb->users} u ON a.user_id = u.ID
			  WHERE a.affiliate_id = %d",
			(int) $row->payee_id
		), ARRAY_A );
		// phpcs:enable

		return [
			'payout_id'      => (int) $row->id,
			'affiliate_id'   => (int) $row->payee_id,
			'display_name'   => $user['display_name'] ?? '',
			'user_email'     => $user['user_email'] ?? '',
			'payment_method' => (string) ( $row->payment_method ?? '' ),
			'payout_amount'  => (float) $row->amount,
			'transaction_id' => (string) $row->reference,
			'created_at'     => (string) $row->created_at,
			'status'         => (string) $row->status,
		];
	}
}
