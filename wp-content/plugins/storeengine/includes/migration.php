<?php
/**
 * DB Migration
 *
 * @package StoreEngine
 */

namespace StoreEngine;

use StoreEngine;
use StoreEngine\Admin\Settings\Base;
use StoreEngine\Database\CreateEmailLog;
use StoreEngine\Database\CreateShippingZoneLocations;
use StoreEngine\Utils\Fs;
use StoreEngine\Utils\Helper;
use StoreEngine\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Migration {

	public static function init() {
		$self = new self();
		add_action( 'init', [ $self, 'run_migration' ] );
	}

	public function run_migration() {
		// @TODO refactor this with:
		//       - Update the original schema and remove the alter queries as db-delta can
		//         generate alter query when needed.
		//       - Progressive data migration with background task queue.
		//       - Run migration on update from installer (not on every init).

		$this->update_admin_role();

		$storeengine_version = get_option( 'storeengine_version' );

		if ( ! get_option( 'storeengine_db_version' ) ) {
			Database::create_initial_custom_table();
			Settings::save_settings();
			update_option( 'storeengine_version', STOREENGINE_VERSION );

			return;
		}

		// Force update existence templates with new shortcodes.
		$this->migrate_1_beta_5_1( $storeengine_version );
		$this->migrate_1_beta_6( $storeengine_version );
		$this->migrate_stable_1( $storeengine_version );
		$this->migrate_1_0_2( $storeengine_version );
		$this->migrate_1_2_1( $storeengine_version );
		$this->migrate_1_3_0( $storeengine_version );
		$this->migrate_1_3_3( $storeengine_version );
		$this->migrate_1_5_0( $storeengine_version );
		$this->migrate_1_5_2( $storeengine_version );
		$this->migrate_1_5_5( $storeengine_version );
		$this->migrate_1_8_0( $storeengine_version );
		$this->migrate_1_10_0( $storeengine_version );

		// Flag-gated, so it must stay above the save_settings() call below.
		$this->migrate_sticky_add_to_cart_key();
		$this->migrate_coupon_end_timestamp();

		if ( STOREENGINE_VERSION !== $storeengine_version ) {
			Settings::save_settings();
			update_option( 'storeengine_version', STOREENGINE_VERSION );
		}
	}

	public function update_admin_role() {
		// Current user have administrator role and not have storeengine_shop_manager role then assign storeengine_shop_manager role
		if ( ! is_user_logged_in() || ! get_current_user_id() ) {
			return;
		}

		$user = wp_get_current_user();

		if ( in_array( 'administrator', $user->roles, true ) && ! in_array( 'storeengine_shop_manager', $user->roles, true ) ) {
			$user->add_role( 'storeengine_shop_manager' );
		}
	}

	public function migrate_1_beta_5_1( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.0.0-beta-5.1', '<' ) ) {
			StoreEngine::init()->load_cart();
			Helper::create_initial_pages();

			$pages = [ 'cart_page', 'checkout_page', 'thankyou_page', 'dashboard_page' ];
			foreach ( $pages as $page ) {
				$page_id = Helper::get_settings( $page );
				if ( $page_id ) {
					$page_content_path = STOREENGINE_TEMPLATE_PATH . 'page-content/' . str_replace( '_page', '', $page ) . '.php';
					if ( file_exists( $page_content_path ) ) {
						wp_update_post( [
							'ID'           => $page_id,
							'post_content' => Fs::render_template( $page_content_path ),
						] );
					}
				}
			}
		}
	}

	// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.SlowDBQuery.slow_db_query_meta_value, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key

	public function migrate_1_beta_6( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.0.0-beta-6', '<' ) ) {
			global $wpdb;
			$method_table = $wpdb->prefix . 'storeengine_shipping_zone_methods';
			$zone_table   = $wpdb->prefix . 'storeengine_shipping_zones';

			$wpdb->query( "ALTER TABLE $method_table DROP COLUMN `type`, DROP COLUMN `cost`, DROP COLUMN `tax`" );
			$wpdb->query( "ALTER TABLE $method_table
				ADD COLUMN `method_id` varchar(200) NOT NULL AFTER `zone_id`,
				ADD COLUMN `settings` longtext NULL AFTER `description`,
				ADD COLUMN `method_order` bigint(20) UNSIGNED NOT NULL AFTER `method_id`" );
			$wpdb->query( "ALTER TABLE $zone_table DROP COLUMN `region`" );
			$wpdb->query( "ALTER TABLE $zone_table ADD COLUMN `zone_order` BIGINT(20) UNSIGNED NOT NULL AFTER `zone_name`" );

			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			CreateShippingZoneLocations::up( $wpdb->prefix, $wpdb->get_charset_collate() );

			$page    = 'thankyou_page';
			$page_id = Helper::get_settings( $page );

			if ( $page_id ) {
				$page_content_path = STOREENGINE_TEMPLATE_PATH . 'page-content/' . str_replace( '_page', '', $page ) . '.php';
				if ( file_exists( $page_content_path ) ) {
					wp_update_post( [
						'ID'           => $page_id,
						'post_content' => Fs::render_template( $page_content_path ),
					] );
				}
			}
		}
	}

	public function migrate_stable_1( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.0.0', '<' ) ) {
			$settings = get_option( STOREENGINE_SETTINGS_NAME );
			$settings = json_decode( $settings, true );

			if ( 'leftWithSpace' === $settings['store_currency_position'] ) {
				$settings['store_currency_position'] = 'left_space';
			}
			if ( 'rightWithSpace' === $settings['store_currency_position'] ) {
				$settings['store_currency_position'] = 'right_space';
			}

			if ( isset( $settings['store_zip'] ) ) {
				unset( $settings['store_zip'] );
			}

			update_option( STOREENGINE_SETTINGS_NAME, wp_json_encode( $settings ) );
		}
	}

	public function migrate_1_0_2( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.0.2', '<' ) ) {
			global $wpdb;
			$users_metadata = $wpdb->get_results( "SELECT * FROM $wpdb->usermeta WHERE meta_key = '_storeengine_purchased_membership_ids'" );

			foreach ( $users_metadata as $user_meta ) {
				$membership_ids = maybe_unserialize( $user_meta->meta_value );
				if ( is_array( $membership_ids ) ) {
					continue;
				}
				$membership_ids = maybe_unserialize( $membership_ids );
				update_user_meta( $user_meta->user_id, '_storeengine_purchased_membership_ids', $membership_ids );
			}

			if ( ! Helper::get_settings( 'product_archive_filters' ) ) {
				Settings::save_settings();
				Base::save_settings( [
					'product_archive_filters' => [
						[
							'slug'   => 'search',
							'status' => true,
							'order'  => 0,
						],
						[
							'slug'   => 'category',
							'status' => true,
							'order'  => 1,
						],
						[
							'slug'   => 'tags',
							'status' => true,
							'order'  => 2,
						],
					],
				] );
			}
		}
	}

	public function migrate_1_2_1( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.2.1', '<' ) ) {
			Settings::save_settings();
			Base::save_settings( [
				'product_archive_filters' => [
					'search'   => [
						'status' => true,
						'order'  => 0,
					],
					'category' => [
						'status' => true,
						'order'  => 1,
					],
					'tags'     => [
						'status' => true,
						'order'  => 2,
					],
				],
				'auth_redirect_type'      => 'storeengine',
				'auth_redirect_url'       => '',
			] );
		}
	}

	public function migrate_1_3_0( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.3.0', '<' ) ) {
			global $wpdb;

			// Update taxonomy terms for new attribute taxonomy prefix.
			$wpdb->query( "UPDATE $wpdb->term_taxonomy SET `taxonomy` = REGEXP_REPLACE(`taxonomy`, '^storeengine_pa_', 'se_pa_') WHERE taxonomy LIKE 'storeengine_pa_%'" );

			// Update attribute order data with new attribute taxonomy prefix.
			$results = $wpdb->get_results( "SELECT meta_id, meta_value FROM $wpdb->postmeta WHERE meta_key = '_storeengine_product_attributes_order' AND meta_value LIKE '%storeengine_pa_%'" );

			foreach ( $results as $meta ) {
				$meta_value = maybe_unserialize( $meta->meta_value );
				if ( ! is_array( $meta_value ) ) {
					$meta_value = json_decode( $meta->meta_value, true );
				}

				foreach ( $meta_value as $k => $v ) {
					/** @noinspection RegExpRedundantEscape */
					$meta_value[ $k ] = Helper::get_attribute_taxonomy_name( preg_replace( '/^storeengine_pa\_/', '', $v ) );
				}

				$wpdb->update(
					$wpdb->postmeta,
					[ 'meta_value' => maybe_serialize( $meta_value ) ],
					[ 'meta_id' => $meta->meta_id ]
				);
			}
		}
	}

	public function migrate_1_3_3( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.3.3', '<' ) ) {
			global $wpdb;
			wp_cache_flush();

			// Missing migration for PR#803
			$orders_meta = "{$wpdb->prefix}storeengine_orders_meta";
			$orders      = "{$wpdb->prefix}storeengine_orders";
			$wpdb->query( "ALTER TABLE $orders_meta DROP INDEX meta_key_value;" );
			$wpdb->query( "ALTER TABLE $orders_meta ADD INDEX meta_key_value (meta_key(100), meta_value(73));" );
			$wpdb->query( "ALTER TABLE $orders_meta ADD INDEX order_id_meta_key_meta_value (order_id, meta_key(100), meta_value(73));" );
			$wpdb->query( "ALTER TABLE $orders DROP INDEX customer_id_billing_email;" );
			$wpdb->query( "ALTER TABLE $orders ADD INDEX customer_id_billing_email (customer_id, billing_email(171));" );
			$wpdb->query( "ALTER TABLE $orders DROP INDEX billing_email;" );
			$wpdb->query( "ALTER TABLE $orders ADD INDEX INDEX billing_email (billing_email(191));" );
			// End Missing migration for PR#803

			// Alter meta key index length.
			$wpdb->query( "ALTER TABLE $orders_meta DROP INDEX meta_key_index;" );
			$wpdb->query( "ALTER TABLE $orders_meta ADD INDEX meta_key_index (meta_key_index(191));" );

			// Add missing product-id index in price & variation table.
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}storeengine_product_price ADD INDEX product_id (product_id);" );
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}storeengine_product_variations ADD INDEX product_id (product_id);" );

			// Rename variation_meta variation-id index.
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}storeengine_product_variation_meta DROP INDEX order_id_index;" );
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}storeengine_product_variation_meta ADD INDEX variation_id_index (variation_id);" );

			// Update variation_meta index length
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}storeengine_product_variation_meta DROP INDEX meta_key_index;" );
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}storeengine_product_variation_meta ADD INDEX meta_key_index (meta_key(191));" );
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}storeengine_product_variation_meta DROP INDEX meta_value_index;" );
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}storeengine_product_variation_meta ADD INDEX meta_value_index (meta_value(191));" );

			// Added missing zone_id & method_id key in variation_term_relations
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}storeengine_shipping_zone_methods ADD INDEX zone_id (zone_id);" );
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}storeengine_shipping_zone_methods ADD INDEX method_id (method_id);" );

			// Added missing variation_id & term_id key in shipping_zone_methods
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}storeengine_shipping_zone_methods ADD INDEX term_id (term_id);" );
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}storeengine_shipping_zone_methods ADD INDEX variation_id (variation_id);" );

			// Change api table last-access into timestamp & add created-at timestamp
			$wpdb->query( "ALTER TABLE {$wpdb->prefix}storeengine_api_keys MODIFY `last_access` TIMESTAMP NULL DEFAULT NULL, ADD `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP;" );
		}
	}

	public function migrate_1_5_0( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.5.0', '<' ) ) {
			global $wpdb;
			wp_cache_flush();
			$wpdb->query( "DELETE FROM $wpdb->usermeta WHERE meta_key = '_storeengine_memberships'" );
		}
	}

	public function migrate_1_5_2( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.5.2', '<' ) ) {
			if ( ! Helper::get_settings( 'product_archive_multi_price_display' ) ) {
				Settings::save_settings();
				Base::save_settings( [ 'product_archive_multi_price_display' => 'dropdown' ] );
			}

			// update email settings
			$email_settings = get_option( 'storeengine_email_settings' );
			if ( ! $email_settings ) {
				return;
			}
			$email_settings = json_decode( $email_settings, true );
			if ( ! is_array( $email_settings ) || isset( $email_settings['new_user_notification'] ) ) {
				return;
			}

			$email_settings['new_user_notification'] = StoreEngine\Addons\Email\Admin\Settings::get_settings_default_data()['new_user_notification'];
			update_option( 'storeengine_email_settings', wp_json_encode( $email_settings ) );
		}
	}

	public function migrate_1_5_5( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.5.5', '<' ) ) {
			global $wpdb;
			// Migrate old meta-key to new meta-key.
			$wpdb->query( "UPDATE $wpdb->postmeta SET meta_key = '_storeengine_coupon_customer_usage_limit' WHERE meta_key = '_storeengine_coupon_total_usage_limit';" );

			// Remove unused metadata.
			$wpdb->delete( "$wpdb->postmeta", [ 'meta_key' => '_storeengine_coupon_is_one_usage_per_user' ] );
			$wpdb->delete( "$wpdb->postmeta", [ 'meta_key' => '_storeengine_coupon_is_total_usage_limit' ] );
			$wpdb->delete( "$wpdb->postmeta", [ 'meta_key' => '_storeengine_per_user_coupon_usage_limit' ] );
			$wpdb->delete( "$wpdb->postmeta", [ 'meta_key' => '_storeengine_coupon_customer_usage_limit_type' ] );
		}
	}

	public function migrate_1_8_0( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.8.0', '<' ) ) {
			global $wpdb;
			$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}storeengine_log");
		}
	}

	/**
	 * Create the storeengine_email_log table that the customer-communication
	 * audit (EmailLogger) writes into. dbDelta is idempotent so re-running on
	 * an existing install is safe; the version gate keeps it from firing on
	 * every page load.
	 */
	public function migrate_1_10_0( $storeengine_version ) {
		if ( version_compare( $storeengine_version, '1.10.0', '<' ) ) {
			global $wpdb;
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			CreateEmailLog::up( $wpdb->prefix, $wpdb->get_charset_collate() );
		}
	}

	/**
	 * `sticky_add_to_cart_mobile` → `sticky_add_to_cart`.
	 *
	 * The bar is no longer phone-only, so the `_mobile` suffix was a lie.
	 *
	 * Gated on its own flag rather than on `version_compare()`: the rename ships
	 * within 2.2.0, so any install already running a 2.2.0 build has that
	 * version stored and a version gate would skip it — silently dropping the
	 * merchant's choice back to the new key's default (enabled). It also has to
	 * run before run_migration()'s Settings::save_settings(), which merges
	 * defaults into the saved blob and would otherwise write the new key first
	 * and shadow the old value.
	 */
	public function migrate_sticky_add_to_cart_key() {
		if ( get_option( 'storeengine_sticky_atc_key_migrated' ) ) {
			return;
		}

		$saved = Base::get_settings_saved_data();

		// Only carry the value across when the merchant actually had the old key
		// and has not already been moved onto the new one.
		if ( array_key_exists( 'sticky_add_to_cart_mobile', $saved ) && ! array_key_exists( 'sticky_add_to_cart', $saved ) ) {
			$saved['sticky_add_to_cart'] = (bool) $saved['sticky_add_to_cart_mobile'];
		}

		if ( array_key_exists( 'sticky_add_to_cart_mobile', $saved ) ) {
			unset( $saved['sticky_add_to_cart_mobile'] );
			update_option( STOREENGINE_SETTINGS_NAME, wp_json_encode( $saved ) );
		}

		update_option( 'storeengine_sticky_atc_key_migrated', 1, false );
	}

	/**
	 * Backfill the queryable coupon end-timestamp mirror.
	 *
	 * The admin coupon list filters "Expired" / "No end date" against
	 * `_storeengine_coupon_end_ts` (see API\Coupon), which is written on every
	 * save going forward. Existing coupons have never been re-saved, so seed the
	 * mirror once here. Flag-gated rather than version-gated so it still runs on
	 * installs already on this build (which would otherwise skip a version
	 * bump). `0` means "never expires".
	 */
	public function migrate_coupon_end_timestamp() {
		if ( get_option( 'storeengine_coupon_end_ts_migrated' ) ) {
			return;
		}

		$coupon_ids = get_posts( [
			'post_type'      => Helper::COUPON_POST_TYPE,
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		] );

		foreach ( $coupon_ids as $coupon_id ) {
			$end_date_time = get_post_meta( $coupon_id, '_storeengine_coupon_end_date_time', true );
			update_post_meta( $coupon_id, \StoreEngine\API\Coupon::END_TS_META, \StoreEngine\API\Coupon::compute_end_timestamp( $end_date_time ) );
		}

		update_option( 'storeengine_coupon_end_ts_migrated', 1, false );
	}

	// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.SlowDBQuery.slow_db_query_meta_value, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key
}
