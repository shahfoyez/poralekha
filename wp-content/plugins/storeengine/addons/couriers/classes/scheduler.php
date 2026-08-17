<?php

namespace StoreEngine\Addons\Couriers\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Polls in-flight shipments via Action Scheduler.
 *
 * Frequency is intentionally conservative (15 minutes) since most BD/IN
 * couriers don't push webhooks reliably and we cap to 50 shipments per run.
 */
final class Scheduler {

	public const ACTION = 'storeengine/couriers/poll_in_flight';

	public static function init(): void {
		add_action( 'init', [ __CLASS__, 'maybe_schedule' ] );
		add_action( self::ACTION, [ __CLASS__, 'run' ] );
	}

	public static function maybe_schedule(): void {
		if ( ! function_exists( 'as_next_scheduled_action' ) || ! function_exists( 'as_schedule_recurring_action' ) ) {
			return;
		}

		if ( false === as_next_scheduled_action( self::ACTION ) ) {
			as_schedule_recurring_action( time() + 60, 15 * MINUTE_IN_SECONDS, self::ACTION, [], 'storeengine' );
		}
	}

	public static function clear(): void {
		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( self::ACTION );
		}
	}

	public static function run(): void {
		foreach ( ShipmentsService::in_flight_ids( 50 ) as $id ) {
			ShipmentsService::refresh_status( $id );
		}
	}
}
