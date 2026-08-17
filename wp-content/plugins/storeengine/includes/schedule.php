<?php
namespace StoreEngine;

use StoreEngine\Schedules\Order;
use StoreEngine\Utils\Geolocation;
use StoreEngine\Classes\LogCleanup;
use StoreEngine\Classes\EmailLogCleanup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Schedule {

	public static function init() {
		Order::init();

		add_action( 'action_scheduler_ensure_recurring_actions', [ __CLASS__, 'register_recurring_actions' ] );
		add_action( 'storeengine/geolocation/maxmind/db-update', [ Geolocation::class, 'update_maxmind_db' ] );
		add_action( 'storeengine/logs/auto_cleanup', [ LogCleanup::class, 'execute_cleanup' ] );
		add_action( 'storeengine/email_log/auto_cleanup', [ EmailLogCleanup::class, 'execute_cleanup' ] );
	}

	public static function register_recurring_actions() {
		if ( ! function_exists( 'as_schedule_recurring_action' ) || ! function_exists( 'as_schedule_single_action' ) ) {
			return;
		}

		$gmt_offset   = get_option( 'gmt_offset' );
		$offset_hours = ( $gmt_offset > 0 ? '-' : '+' ) . absint( $gmt_offset ) . ' hours';
		$tomorrow_6am = strtotime( 'tomorrow 06:00 am ' . $offset_hours );

		$tomorrow_2am = strtotime( 'tomorrow 02:00 am ' . $offset_hours );
		$tomorrow_3am = strtotime( 'tomorrow 03:00 am ' . $offset_hours );

		as_schedule_recurring_action( $tomorrow_6am, 15 * DAY_IN_SECONDS, 'storeengine/geolocation/maxmind/db-update', [], 'storeengine', true );

		as_schedule_recurring_action( $tomorrow_2am, DAY_IN_SECONDS, 'storeengine/logs/auto_cleanup',[], 'storeengine', true );
		as_schedule_recurring_action( $tomorrow_3am, DAY_IN_SECONDS, 'storeengine/email_log/auto_cleanup', [], 'storeengine', true );
	}
}
