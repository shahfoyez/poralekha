<?php
/**
 * Account Mover.
 *
 * Non-destructively transfers a customer's StoreEngine data (orders, subscriptions,
 * downloads, payment tokens, etc.) from one WordPress user to another. Neither user
 * account is deleted — only the ownership of the records changes.
 *
 * The transfer is additive: records from the source user are merged into the target,
 * and any records the target already owns are preserved.
 *
 * @package StoreEngine\Classes
 */

namespace StoreEngine\Classes;

use StoreEngine\SqlTransaction;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AccountMover {

	/**
	 * Source user ID (data is moved FROM here).
	 *
	 * @var int
	 */
	protected int $from;

	/**
	 * Target user ID (data is moved TO here).
	 *
	 * @var int
	 */
	protected int $to;

	/**
	 * @param int $from Source user ID.
	 * @param int $to   Target user ID.
	 */
	public function __construct( int $from, int $to ) {
		$this->from = $from;
		$this->to   = $to;
	}

	/**
	 * Core (free) tables owned by a customer, keyed by the customer column.
	 *
	 * Each entry: table suffix (without $wpdb->prefix) => owner column.
	 * Pro addons register their own tables via the `storeengine/account/moveable_tables`
	 * filter and act on the `storeengine/account/moved` hook.
	 *
	 * NOTE: POS operator tables, vendor/affiliate identities, api_keys and the
	 * session-bound cart are intentionally excluded — they are not customer purchases.
	 *
	 * @return array<string, string>
	 */
	protected function get_moveable_tables(): array {
		$tables = [
			'storeengine_orders'                            => 'customer_id',
			'storeengine_order_product_lookup'              => 'customer_id',
			'storeengine_subscriptions'                     => 'user_id',
			'storeengine_downloadable_product_permissions'  => 'user_id',
			'storeengine_download_log'                       => 'user_id',
			'storeengine_payment_tokens'                    => 'user_id',
			'storeengine_email_log'                          => 'customer_id',
		];

		/**
		 * Filters the list of tables whose ownership is transferred on account move.
		 *
		 * @param array<string, string> $tables Map of table suffix => owner column.
		 * @param int                   $from   Source user ID.
		 * @param int                   $to     Target user ID.
		 */
		$tables = apply_filters( 'storeengine/account/moveable_tables', $tables, $this->from, $this->to );

		// Only operate on tables that actually exist (some are addon-created).
		return array_filter( $tables, [ $this, 'table_exists' ], ARRAY_FILTER_USE_KEY );
	}

	/**
	 * Whether a StoreEngine table exists.
	 *
	 * @param string $table Table suffix (without $wpdb->prefix).
	 */
	protected function table_exists( string $table ): bool {
		global $wpdb;

		$full = $wpdb->prefix . $table;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (bool) $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $full ) ) );
	}

	/**
	 * Validate that the move can be performed.
	 *
	 * @return true|WP_Error
	 */
	public function validate() {
		if ( $this->from === $this->to ) {
			return new WP_Error( 'storeengine_account_move_same_user', __( 'Source and target accounts must be different.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$from_user = get_userdata( $this->from );
		$to_user   = get_userdata( $this->to );

		if ( ! $from_user instanceof WP_User ) {
			return new WP_Error( 'storeengine_account_move_invalid_source', __( 'Source account does not exist.', 'storeengine' ), [ 'status' => 404 ] );
		}

		if ( ! $to_user instanceof WP_User ) {
			return new WP_Error( 'storeengine_account_move_invalid_target', __( 'Target account does not exist.', 'storeengine' ), [ 'status' => 404 ] );
		}

		return true;
	}

	/**
	 * Preview how many records would move, per table.
	 *
	 * @return array<string, int>|WP_Error Map of table suffix => row count.
	 */
	public function preview() {
		$valid = $this->validate();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		global $wpdb;
		$counts = [];

		foreach ( $this->get_moveable_tables() as $table => $column ) {
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared (%d) count on a custom StoreEngine table; table/column names are internal map keys, not user input.
			$counts[ $table ] = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$wpdb->prefix}{$table} WHERE `{$column}` = %d",
					$this->from
				)
			);
			// phpcs:enable
		}

		/**
		 * Filters the account-move preview counts. Pro addons append their own entries.
		 *
		 * @param array<string, int> $counts Map of table suffix => row count for the source user.
		 * @param int                $from   Source user ID.
		 * @param int                $to     Target user ID.
		 */
		return apply_filters( 'storeengine/account/move_preview', $counts, $this->from, $this->to );
	}

	/**
	 * Perform the move inside a single DB transaction.
	 *
	 * @param array $options Reserved for future use (e.g. selective entities).
	 *
	 * @return array|WP_Error Result summary: [ 'moved' => [table => rows], 'from' => id, 'to' => id ].
	 */
	public function move( array $options = [] ) {
		$valid = $this->validate();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		global $wpdb;

		// Capture subscription order IDs before the move so downstream listeners
		// (e.g. the subscription addon) can flag them for payment re-authorization —
		// the moved payment tokens belong to the source user's gateway customer.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$subscription_ids = array_map( 'intval', (array) $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}storeengine_orders WHERE customer_id = %d AND type = 'subscription'",
				$this->from
			)
		) );

		$transaction = new SqlTransaction();
		$transaction->start();

		try {
			$moved = [];

			foreach ( $this->get_moveable_tables() as $table => $column ) {
				// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared (%d) update on a custom StoreEngine table; table/column names are internal map keys, not user input.
				$result = $wpdb->query(
					$wpdb->prepare(
						"UPDATE {$wpdb->prefix}{$table} SET `{$column}` = %d WHERE `{$column}` = %d",
						$this->to,
						$this->from
					)
				);
				// phpcs:enable

				if ( false === $result ) {
					throw new \RuntimeException(
						/* translators: %s: database table name. */
						sprintf( __( 'Failed to move records for table %s.', 'storeengine' ), $table )
					);
				}

				$moved[ $table ] = (int) $result;
			}

			$result = [
				'from'             => $this->from,
				'to'               => $this->to,
				'moved'            => $moved,
				'subscription_ids' => $subscription_ids,
				'options'          => $options,
			];

			/**
			 * Fires while an account move is in progress, inside the transaction.
			 *
			 * Pro addons (license-management, returns, subscription re-auth flagging, etc.)
			 * should move their own records here. Throwing rolls back the entire move.
			 *
			 * @param int   $from   Source user ID.
			 * @param int   $to     Target user ID.
			 * @param array $result Running result summary (passed by reference).
			 */
			do_action_ref_array( 'storeengine/account/moving', [ $this->from, $this->to, &$result ] );

			// Recount derived customer stats for both users.
			$this->recount_customer_stats( $this->from );
			$this->recount_customer_stats( $this->to );

			$transaction->commit();
		} catch ( \Throwable $e ) {
			$transaction->rollback();

			return new WP_Error( 'storeengine_account_move_failed', $e->getMessage(), [ 'status' => 500 ] );
		}

		$this->log_move( $result );

		/**
		 * Fires after an account move has been committed.
		 *
		 * @param int   $from   Source user ID.
		 * @param int   $to     Target user ID.
		 * @param array $result Final result summary.
		 */
		do_action( 'storeengine/account/moved', $this->from, $this->to, $result );

		return $result;
	}

	/**
	 * Recompute total_orders / total_spent user meta from the orders table.
	 *
	 * @param int $user_id User whose stats to recount.
	 */
	protected function recount_customer_stats( int $user_id ) {
		global $wpdb;

		$paid_statuses = Helper::get_order_paid_statuses();
		$placeholders  = implode( ', ', array_fill( 0, count( $paid_statuses ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total_orders, COALESCE(SUM(total_amount), 0) AS total_spent
				FROM {$wpdb->prefix}storeengine_orders
				WHERE customer_id = %d AND type != 'subscription' AND status IN ( {$placeholders} )",
				array_merge( [ $user_id ], $paid_statuses )
			)
		);
		// phpcs:enable

		update_user_meta( $user_id, 'storeengine_total_orders', (int) ( $stats->total_orders ?? 0 ) );
		update_user_meta( $user_id, 'storeengine_total_spent', (float) ( $stats->total_spent ?? 0 ) );
	}

	/**
	 * Append an audit entry to both users' move logs.
	 *
	 * @param array $result Result summary.
	 */
	protected function log_move( array $result ) {
		$entry = [
			'from'       => $this->from,
			'to'         => $this->to,
			'moved'      => $result['moved'] ?? [],
			'by'         => get_current_user_id(),
			'created_at' => current_time( 'mysql', true ),
		];

		foreach ( [ $this->from, $this->to ] as $user_id ) {
			$log   = get_user_meta( $user_id, 'storeengine_account_move_log', true );
			$log   = is_array( $log ) ? $log : [];
			$log[] = $entry;
			update_user_meta( $user_id, 'storeengine_account_move_log', $log );
		}
	}
}
