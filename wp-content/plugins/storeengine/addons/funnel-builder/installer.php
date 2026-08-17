<?php
/**
 * Funnel Builder schema installer.
 *
 * Owns the three funnel-builder tables. Called by the central addon schema
 * manager (idempotent dbDelta) whenever DB_VERSION changes, and on activation.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Addons\FunnelBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Installer {

	public static function funnels_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'storeengine_funnels';
	}

	public static function steps_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'storeengine_funnel_steps';
	}

	public static function stats_table(): string {
		global $wpdb;

		return $wpdb->prefix . 'storeengine_funnel_stats';
	}

	public static function install_tables(): void {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$charset_collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';
		$funnels         = self::funnels_table();
		$steps           = self::steps_table();
		$stats           = self::stats_table();

		// A funnel is a flow. trigger_type controls how shoppers enter it:
		// global_checkout (replaces the default checkout flow), product (attached
		// to specific product/price ids in trigger_data) or manual (direct link).
		dbDelta( "CREATE TABLE IF NOT EXISTS {$funnels} (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`name` varchar(255) NOT NULL DEFAULT '',
			`slug` varchar(200) NOT NULL DEFAULT '',
			`status` varchar(20) NOT NULL DEFAULT 'draft',
			`type` varchar(20) NOT NULL DEFAULT 'sales',
			`trigger_type` varchar(30) NOT NULL DEFAULT 'manual',
			`trigger_data` longtext NULL,
			`settings` longtext NULL,
			`created_at` datetime DEFAULT NULL,
			`updated_at` datetime DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `slug` (`slug`),
			KEY `status` (`status`),
			KEY `trigger_type` (`trigger_type`)
		) {$charset_collate};" );

		// A step renders a WP page (page_id) built with aBlocks. step_order keeps
		// the linear sequence; settings (JSON) carries per-step config: the offer
		// price_id/quantity/discount for checkout & upsell steps, redirect rules,
		// the Pro A/B variant group and the Pro display-condition rule tree.
		dbDelta( "CREATE TABLE IF NOT EXISTS {$steps} (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`funnel_id` bigint(20) unsigned NOT NULL,
			`page_id` bigint(20) unsigned DEFAULT NULL,
			`type` varchar(20) NOT NULL DEFAULT 'landing',
			`name` varchar(255) NOT NULL DEFAULT '',
			`step_order` int(11) NOT NULL DEFAULT 0,
			`ab_variant_group` varchar(60) DEFAULT NULL,
			`settings` longtext NULL,
			`created_at` datetime DEFAULT NULL,
			`updated_at` datetime DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `funnel_id` (`funnel_id`),
			KEY `page_id` (`page_id`),
			KEY `type` (`type`),
			KEY `funnel_order` (`funnel_id`,`step_order`)
		) {$charset_collate};" );

		// One row per tracked funnel event. Aggregated for the analytics report;
		// session_id ties views to conversions, order_id/revenue attribute money.
		dbDelta( "CREATE TABLE IF NOT EXISTS {$stats} (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`funnel_id` bigint(20) unsigned NOT NULL,
			`step_id` bigint(20) unsigned DEFAULT NULL,
			`session_id` varchar(64) NOT NULL DEFAULT '',
			`order_id` bigint(20) unsigned DEFAULT NULL,
			`event` varchar(30) NOT NULL DEFAULT 'view',
			`revenue` decimal(19,4) NOT NULL DEFAULT 0.0000,
			`created_at` datetime DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `funnel_id` (`funnel_id`),
			KEY `step_id` (`step_id`),
			KEY `event` (`event`),
			KEY `session_id` (`session_id`),
			KEY `order_id` (`order_id`),
			KEY `created_at` (`created_at`)
		) {$charset_collate};" );
	}
}
