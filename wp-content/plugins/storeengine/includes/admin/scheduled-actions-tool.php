<?php

namespace StoreEngine\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Installer;

/**
 * Backend service for inspecting and resetting StoreEngine's Action Scheduler
 * queue. The user-facing UI lives in the React "Action Scheduler" tab on the
 * Tools page; this class exposes the data + cancel operations to the AJAX
 * handler and a WP-CLI command.
 */
class ScheduledActionsTool {

	public static function init() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'storeengine reset-actions', [ __CLASS__, 'cli_reset' ] );
		}
	}

	/**
	 * Cancel every pending/failed action for a hook. Returns count cancelled.
	 */
	public static function cancel_hook( string $hook ): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared (%s) count against the Action Scheduler queue table; live queue state, not cacheable.
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions WHERE hook = %s AND status IN ('pending','failed')",
			$hook
		) );

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( $hook );
		}

		// Belt-and-braces: hard delete remaining failed/canceled rows for this hook.
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared (%s) cleanup delete on the Action Scheduler queue table; no cache layer applies.
		$wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}actionscheduler_actions WHERE hook = %s AND status IN ('pending','failed','canceled')",
			$hook
		) );

		return $count;
	}

	public static function truncate_logs() {
		global $wpdb;
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}actionscheduler_logs" );
	}

	/**
	 * Pending/failed counts grouped by hook for the known StoreEngine set.
	 *
	 * @return array<int, array{hook:string, pending:int, failed:int}>
	 */
	public static function get_hook_counts(): array {
		global $wpdb;

		$hooks  = self::get_known_hooks();
		$result = [];
		$table  = $wpdb->prefix . 'actionscheduler_actions';
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared (%s) existence probe for the Action Scheduler table; not cacheable.
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );

		if ( ! $exists ) {
			foreach ( $hooks as $hook ) {
				$result[] = [ 'hook' => $hook, 'pending' => 0, 'failed' => 0 ];
			}
			return $result;
		}

		foreach ( $hooks as $hook ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared (%s) pending count on the Action Scheduler queue table; live state, not cacheable.
			$pending = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions WHERE hook = %s AND status = 'pending'",
				$hook
			) );
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared (%s) failed count on the Action Scheduler queue table; live state, not cacheable.
			$failed  = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->prefix}actionscheduler_actions WHERE hook = %s AND status = 'failed'",
				$hook
			) );
			$result[] = [ 'hook' => $hook, 'pending' => $pending, 'failed' => $failed ];
		}

		usort( $result, fn( $a, $b ) => $b['pending'] <=> $a['pending'] );
		return $result;
	}

	/**
	 * The full list of StoreEngine-owned action hooks. Filter to add hooks
	 * registered by third-party StoreEngine addons.
	 *
	 * @return string[]
	 */
	public static function get_known_hooks(): array {
		$hooks = Installer::get_known_scheduled_action_hooks();
		return apply_filters( 'storeengine/scheduled_actions/known_hooks', $hooks );
	}

	/**
	 * WP-CLI: wp storeengine reset-actions [--hook=<hook>] [--all] [--logs]
	 *
	 * @param array $args
	 * @param array $assoc
	 */
	public static function cli_reset( $args, $assoc ) {
		$hooks = [];

		if ( ! empty( $assoc['all'] ) ) {
			$hooks = self::get_known_hooks();
		} elseif ( ! empty( $assoc['hook'] ) ) {
			$hooks = [ $assoc['hook'] ];
		} else {
			\WP_CLI::error( 'Specify --hook=<hook> or --all.' );
		}

		$known     = array_flip( self::get_known_hooks() );
		$cancelled = 0;

		foreach ( $hooks as $hook ) {
			if ( ! isset( $known[ $hook ] ) ) {
				\WP_CLI::warning( "Skipping unknown hook: $hook" );
				continue;
			}
			$n = self::cancel_hook( $hook );
			\WP_CLI::log( "Cancelled $n actions for $hook" );
			$cancelled += $n;
		}

		if ( ! empty( $assoc['logs'] ) ) {
			self::truncate_logs();
			\WP_CLI::log( 'Truncated actionscheduler_logs.' );
		}

		\WP_CLI::success( "Cancelled $cancelled actions total." );
	}
}
