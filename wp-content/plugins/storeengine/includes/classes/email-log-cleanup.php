<?php
/**
 * Daily cleanup for the email log table.
 *
 * Separate from LogCleanup (which manages storeengine_logs) because the
 * retention policy is different: customer-communication history wants longer
 * retention for failures (deliverability debugging) and shorter for routine
 * successes. Both clean up independently.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EmailLogCleanup {

	const DEFAULT_RETENTION_SENT   = 90;
	const DEFAULT_RETENTION_FAILED = 365;

	public static function execute_cleanup() {
		$settings = self::get_settings();

		$days_sent   = (int) $settings['retention_days_sent'];
		$days_failed = (int) $settings['retention_days_failed'];

		if ( $days_sent > 0 ) {
			self::purge( 'sent', $days_sent );
		}

		if ( $days_failed > 0 ) {
			self::purge( 'failed', $days_failed );
		}

		// 'queued' rows older than 1 day are almost always stuck — wp_mail
		// didn't fire the success/fail action, or the request died. Purge them
		// after the same window as sent so they don't accumulate.
		if ( $days_sent > 0 ) {
			self::purge( 'queued', $days_sent );
		}
	}

	public static function get_settings(): array {
		$defaults = [
			'retention_days_sent'   => self::DEFAULT_RETENTION_SENT,
			'retention_days_failed' => self::DEFAULT_RETENTION_FAILED,
		];

		$stored = get_option( 'storeengine_email_log_settings', [] );

		if ( ! is_array( $stored ) ) {
			$stored = [];
		}

		return array_merge( $defaults, $stored );
	}

	protected static function purge( string $status, int $days ) {
		global $wpdb;
		$table     = $wpdb->prefix . 'storeengine_email_log';
		$threshold = gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared (%i/%s) delete on a custom StoreEngine log table; retention cleanup, not cacheable.
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM %i WHERE status = %s AND sent_at_gmt < %s',
				$table,
				$status,
				$threshold
			)
		);
	}
}
