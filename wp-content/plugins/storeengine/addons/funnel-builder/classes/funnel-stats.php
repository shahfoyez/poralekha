<?php
/**
 * Funnel stats recorder + aggregator.
 *
 * Writes one row per tracked event and reads back per-step / per-funnel
 * summaries for the analytics screen.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Addons\FunnelBuilder\Classes;

use StoreEngine\Addons\FunnelBuilder\Installer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FunnelStats {

	const EVENTS = [ 'view', 'optin', 'purchase', 'upsell_accepted', 'upsell_rejected', 'skipped' ];

	public static function record( int $funnel_id, ?int $step_id, string $event, array $args = [] ): void {
		global $wpdb;

		if ( ! in_array( $event, self::EVENTS, true ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Insert into a custom StoreEngine stats table; no cache layer applies.
		$wpdb->insert(
			Installer::stats_table(),
			[
				'funnel_id'  => $funnel_id,
				'step_id'    => $step_id,
				'session_id' => $args['session_id'] ?? self::session_id(),
				'order_id'   => $args['order_id'] ?? null,
				'event'      => $event,
				'revenue'    => isset( $args['revenue'] ) ? (float) $args['revenue'] : 0,
				'created_at' => current_time( 'mysql', true ),
			]
		);
	}

	/**
	 * A stable-per-visitor id used to thread views → conversions. Cookie based,
	 * falls back to a transient-free random token when cookies are unavailable.
	 */
	public static function session_id(): string {
		$cookie = 'storeengine_funnel_sid';
		if ( ! empty( $_COOKIE[ $cookie ] ) ) {
			return sanitize_text_field( wp_unslash( $_COOKIE[ $cookie ] ) );
		}

		$sid = wp_generate_uuid4();
		if ( ! headers_sent() ) {
			setcookie( $cookie, $sid, time() + DAY_IN_SECONDS, COOKIEPATH ? COOKIEPATH : '/', COOKIE_DOMAIN, is_ssl(), true );
		}

		return $sid;
	}

	/**
	 * Per-step roll-up for a funnel: views, conversions and revenue. Optional
	 * date range via $args['from'] / $args['to'] (Y-m-d).
	 */
	public static function summary( int $funnel_id, array $args = [] ): array {
		global $wpdb;
		$table = Installer::stats_table();

		$sql    = 'SELECT step_id, event, COUNT(*) AS total, SUM(revenue) AS revenue FROM %i WHERE funnel_id = %d';
		$params = [ $table, $funnel_id ];
		if ( ! empty( $args['from'] ) ) {
			$sql     .= ' AND created_at >= %s';
			$params[] = gmdate( 'Y-m-d 00:00:00', strtotime( $args['from'] ) );
		}
		if ( ! empty( $args['to'] ) ) {
			$sql     .= ' AND created_at <= %s';
			$params[] = gmdate( 'Y-m-d 23:59:59', strtotime( $args['to'] ) );
		}
		$sql .= ' GROUP BY step_id, event';

		// Safe: %i (identifier) + %d/%s placeholders via prepare(); $sql is built from literals only.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregated analytics over a custom stats table; not cacheable per request.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ) ?: [];

		$by_step = [];
		$totals  = [
			'views'         => 0,
			'unique_views'  => 0,
			'conversions'   => 0,
			'orders'        => 0,
			'revenue'       => 0.0,
			'offer_revenue' => 0.0,
		];
		foreach ( $rows as $row ) {
			$sid = (int) $row['step_id'];
			if ( ! isset( $by_step[ $sid ] ) ) {
				$by_step[ $sid ] = [ 'views' => 0, 'conversions' => 0, 'revenue' => 0.0 ];
			}
			$count = (int) $row['total'];
			if ( 'view' === $row['event'] ) {
				$by_step[ $sid ]['views'] += $count;
				$totals['views']          += $count;
			} else {
				$by_step[ $sid ]['conversions'] += $count;
				$totals['conversions']          += $count;
			}
			if ( 'purchase' === $row['event'] ) {
				$totals['orders'] += $count;
			}
			if ( 'upsell_accepted' === $row['event'] ) {
				$totals['offer_revenue'] += (float) $row['revenue'];
			}
			$by_step[ $sid ]['revenue'] += (float) $row['revenue'];
			$totals['revenue']          += (float) $row['revenue'];
		}

		// Unique visitors (distinct sessions that viewed any step).
		$uv_sql    = 'SELECT COUNT(DISTINCT session_id) FROM %i WHERE funnel_id = %d';
		$uv_params = [ $table, $funnel_id ];
		if ( ! empty( $args['from'] ) ) {
			$uv_sql     .= ' AND created_at >= %s';
			$uv_params[] = gmdate( 'Y-m-d 00:00:00', strtotime( $args['from'] ) );
		}
		if ( ! empty( $args['to'] ) ) {
			$uv_sql     .= ' AND created_at <= %s';
			$uv_params[] = gmdate( 'Y-m-d 23:59:59', strtotime( $args['to'] ) );
		}
		$uv_sql .= " AND event = 'view'";
		// Safe: %i (identifier) + %d/%s placeholders via prepare(); $uv_sql is built from literals only.
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregated analytics over a custom stats table; not cacheable per request.
		$totals['unique_views'] = (int) $wpdb->get_var( $wpdb->prepare( $uv_sql, $uv_params ) );

		$totals['aov']  = $totals['orders'] ? round( $totals['revenue'] / $totals['orders'], 2 ) : 0.0;
		$totals['rpuv'] = $totals['unique_views'] ? round( $totals['revenue'] / $totals['unique_views'], 2 ) : 0.0;

		return [
			'funnel_id' => $funnel_id,
			'totals'    => $totals,
			'by_step'   => $by_step,
		];
	}
}
