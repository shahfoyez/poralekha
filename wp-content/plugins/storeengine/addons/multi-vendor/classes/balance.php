<?php

namespace StoreEngine\Addons\MultiVendor\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Computes a vendor's available balance for withdrawals.
 *
 * Lifetime commission (positive + negative refund rows) MINUS any pending +
 * approved + paid withdrawal amounts. Pending requests count as held funds so
 * a vendor can't double-spend by stacking requests.
 */
class Balance {

	public static function for_vendor( int $user_id ): float {
		global $wpdb;

		$lookup       = $wpdb->prefix . 'storeengine_order_product_lookup';
		$withdrawals  = $wpdb->prefix . 'storeengine_vendor_withdrawals';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- %i identifier + %d value bound via prepare() on custom StoreEngine tables; per-vendor balance, not cacheable.
		$earned = (float) $wpdb->get_var( $wpdb->prepare(
			'SELECT COALESCE(SUM(commission_amount),0) FROM %i WHERE vendor_id = %d',
			$lookup,
			$user_id
		) );

		$held = (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(amount),0) FROM %i WHERE user_id = %d AND status IN ('pending','approved','paid')",
			$withdrawals,
			$user_id
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		return max( 0.0, round( $earned - $held, 2 ) );
	}

	/**
	 * Sum of amounts currently held against the vendor — pending + approved +
	 * paid withdrawals. Used to detect an overdraw (held > earned).
	 */
	public static function held_total( int $user_id ): float {
		global $wpdb;
		$withdrawals = $wpdb->prefix . 'storeengine_vendor_withdrawals';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(amount),0) FROM `{$withdrawals}` WHERE user_id = %d AND status IN ('pending','approved','paid')",
			$user_id
		) );
		// phpcs:enable
	}

	public static function lifetime_earned( int $user_id ): float {
		global $wpdb;
		$lookup = $wpdb->prefix . 'storeengine_order_product_lookup';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(commission_amount),0) FROM `{$lookup}` WHERE vendor_id = %d",
			$user_id
		) );
		// phpcs:enable
	}

	public static function paid_total( int $user_id ): float {
		global $wpdb;
		$withdrawals = $wpdb->prefix . 'storeengine_vendor_withdrawals';
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		return (float) $wpdb->get_var( $wpdb->prepare(
			"SELECT COALESCE(SUM(amount),0) FROM `{$withdrawals}` WHERE user_id = %d AND status = 'paid'",
			$user_id
		) );
		// phpcs:enable
	}
}
