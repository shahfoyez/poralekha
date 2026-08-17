<?php
/**
 * Membership stats on the main StoreEngine dashboard.
 *
 * Rather than a separate membership-analytics screen, we append membership
 * tiles to the existing Dashboard OverviewCards row via the
 * `storeengine/analytics/stats` filter — the same seam cost-profit uses. Tiles
 * appear only while the membership addon is active.
 *
 * @package StoreEngine\Addons\Membership
 */

namespace StoreEngine\Addons\Membership;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Analytics {

	public static function init() {
		$self = new self();
		add_filter( 'storeengine/analytics/stats', [ $self, 'append_membership_stats' ], 20 );
	}

	/**
	 * @param array $stats Existing OverviewCards tiles.
	 *
	 * @return array
	 */
	public function append_membership_stats( $stats ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $stats;
		}

		$stats[] = [
			'label' => __( 'Members', 'storeengine' ),
			'icon'  => 'users',
			'data'  => [
				'count'  => $this->count_members(),
				'format' => false,
				'rate'   => null,
			],
		];

		$stats[] = [
			'label' => __( 'Access Groups', 'storeengine' ),
			'icon'  => 'analytic-up',
			'data'  => [
				'count'  => $this->count_access_groups(),
				'format' => false,
				'rate'   => null,
			],
		];

		return $stats;
	}

	/**
	 * Distinct users holding at least one membership.
	 */
	protected function count_members(): int {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$count = $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT( DISTINCT user_id ) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value NOT IN ( '', 'a:0:{}' )",
			Access::PURCHASED_META
		) );

		return (int) $count;
	}

	/**
	 * Published access groups.
	 */
	protected function count_access_groups(): int {
		$counts = wp_count_posts( STOREENGINE_MEMBERSHIP_POST_TYPE );

		return isset( $counts->publish ) ? (int) $counts->publish : 0;
	}
}
