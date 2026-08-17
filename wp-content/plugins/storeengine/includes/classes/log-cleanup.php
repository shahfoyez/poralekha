<?php
namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LogCleanup {

	/**
	 * main cleanup logic
	 */
	public static function execute_cleanup() {
		global $wpdb;
		$table = $wpdb->prefix . 'storeengine_logs';

		// admin setting
		$settings = get_option( 'storeengine_log_settings',[
			'retention_days'   => 30, 
			'cleanup_statuses' => [ 'success' ] 
		] );

		$days     = (int) ( $settings['retention_days'] ?? 30 );
		$statuses = $settings['cleanup_statuses'] ?? [ 'success' ];

		if ( $days <= 0 || empty( $statuses ) || ! is_array( $statuses ) ) {
			return;
		}

		//  date calculation — UTC, matching the UTC timestamps the logger writes.
		$threshold_date = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		//  SQL query — table via %i identifier placeholder, all values via prepare().
		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Dynamic IN() list on a custom StoreEngine log table: %i identifier + every value (%s) bound through prepare(); $placeholders holds only literal "%s" tokens. The static analyzer cannot count the array_merge() replacement args.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM %i WHERE date < %s AND status IN ($placeholders)",
				array_merge( [ $table, $threshold_date ], $statuses )
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber
	}
}