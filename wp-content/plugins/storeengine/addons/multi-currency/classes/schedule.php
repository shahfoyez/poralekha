<?php
/**
 * Multi-Currency Schedule
 *
 * Uses StoreEngine's existing ActionScheduler integration (same pattern as
 * the Geolocation MaxMind DB update in includes/schedule.php) to refresh
 * exchange rates on a configurable interval.
 *
 * @package StoreEngine\Addons\MultiCurrency
 */

namespace StoreEngine\Addons\MultiCurrency\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schedule {

	const ACTION_HOOK = 'storeengine/multi_currency/refresh_rates';

	public function __construct() {
		add_action( self::ACTION_HOOK, [ __CLASS__, 'run_refresh' ] );
		add_action( 'action_scheduler_ensure_recurring_actions', [ __CLASS__, 'register' ] );
	}

	/**
	 * Register the recurring action with ActionScheduler.
	 * Called on action_scheduler_ensure_recurring_actions — same as core geolocation.
	 * ActionScheduler deduplicates automatically (the last true param).
	 */
	public static function register(): void {
		if ( ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		$interval_hours = (int) Settings::get( 'refresh_interval_hours', 6 );
		$interval_secs  = $interval_hours * HOUR_IN_SECONDS;

		as_schedule_recurring_action(
			time() + $interval_secs,
			$interval_secs,
			self::ACTION_HOOK,
			[],
			'storeengine',
			true  // unique — only one instance queued at a time
		);
	}

	/**
	 * The cron callback — fetch fresh rates and cache in transient.
	 */
	public static function run_refresh(): void {
		ExchangeRates::refresh();
	}

	/**
	 * Remove all scheduled actions. Called on addon deactivation.
	 */
	public static function unschedule(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION_HOOK );
		}
	}
}
