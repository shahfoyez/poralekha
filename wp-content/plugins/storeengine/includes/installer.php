<?php

namespace StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use StoreEngine\Admin\Notices;
use StoreEngine\Admin\Settings;
use StoreEngine\Classes\PayoutsRepository;
use StoreEngine\Classes\Role;
use StoreEngine\Utils\Helper;

class Installer {
	protected $storeengine_version;

	public function __construct() {
		$this->storeengine_version = get_option( 'storeengine_version' );
	}

	public static function init() {
		add_action( 'init', [ __CLASS__, 'check_version' ], 5 );
	}

	/**
	 * Check version & run installer if needed.
	 *
	 * @since 1.6.4
	 * @return void
	 */
	public static function check_version() {
		$current_version    = get_option( 'storeengine_version' );
		$current_db_version = get_option( 'storeengine_db_version' );
		$requires_update    = version_compare( $current_version, STOREENGINE_VERSION, '<' )
			|| version_compare( $current_db_version, STOREENGINE_DB_VERSION, '<' );

		if ( ! defined( 'IFRAME_REQUEST' ) && $requires_update ) {
			define( 'STOREENGINE_UPDATING', true );
			// Run the installer.
			self::install();

			/**
			 * Run after StoreEngine has been updated.
			 *
			 * @since 1.6.4
			 */
			do_action( 'storeengine/updated' );
		}
	}

	public static function install() {
		if ( ! is_blog_installed() ) {
			return;
		}

		// Check if we are not already running this routine.
		if ( 'yes' === get_transient( 'storeengine_installing' ) ) {
			return;
		}

		// If we made it till here nothing is running yet, lets set the transient now.
		set_transient( 'storeengine_installing', 'yes', MINUTE_IN_SECONDS * 10 );

		define( 'STOREENGINE_INSTALLING', true );

		global $wpdb;
		$wpdb->hide_errors();

		try {
			Database::init()->wpdb_table_fix();
			Notices::remove_all_notices();
			Database::create_initial_custom_table();
			Settings::save_settings();
			self::add_role();
			// Copy any rows from legacy affiliate/vendor payout tables into the unified ledger.
			PayoutsRepository::migrate_legacy_tables();
			// Create files.
			self::create_base_secure_directory();
			// Need for flush rewrite rules.
			PermalinkRewrite::init();
			Database::init()->register_product_post_type();

			if ( ! self::is_new_install() && 'yes' !== get_option( 'storeengine_has_redirect_to_setup_wizard', 'no' ) ) {
				// Don't redirect old users to setup-wizard upon update.
				update_option( 'storeengine_has_redirect_to_setup_wizard', 'yes', false );
			}

			update_option( 'storeengine_version', STOREENGINE_VERSION );
		} catch ( \Exception $e ) {
			// NoOp.
			Helper::log_error( $e );
		} finally {
			delete_transient( 'storeengine_installing' );
		}

		if ( ! get_option( 'storeengine_first_install_time' ) ) {
			add_option( 'storeengine_first_install_time', Helper::get_time() );
		}

		// Force a flush of rewrite rules even if the corresponding hook isn't initialized yet.
		if ( ! has_action( 'storeengine/flush_rewrite_rules' ) ) {
			flush_rewrite_rules();
		}

		/**
		 * Flush the rewrite rules after install or update.
		 */
		do_action( 'storeengine/flush_rewrite_rules' );

		/**
		 * Run after StoreEngine has been installed or updated.
		 *
		 * @since 1.6.4
		 */
		do_action( 'storeengine/installed' );
	}

	/**
	 * Is this a brand-new StoreEngine installation?
	 *
	 * A brand-new installation has no version yet. Also treat empty installations as 'new'.
	 *
	 * @return boolean
	 */
	public static function is_new_install(): bool {
		return
			is_null( get_option( 'storeengine_version', null ) ) ||
			is_null( get_option( 'storeengine_first_install_time', null ) ) ||
			(
				! Helper::get_settings( 'shop_page' ) &&
				0 === array_sum( (array) wp_count_posts( Helper::PRODUCT_POST_TYPE ) )
			);
	}

	public static function add_role() {
		// customer role
		Role::add_customer_role();
		// shop manager role
		Role::add_shop_manager_role();
		// One-shot back-fill for caps added in v2 (manage_storeengine_settings, edit_storeengine_orders).
		Role::migrate_caps_v2();
	}

	/**
	 * Create files/directories.
	 */
	public static function create_base_secure_directory() {
		// Install files and folders for uploading files and prevent hotlinking.
		// Directory is restricted and downloads must be proxied via script.
		$htaccess  = '<IfModule mod_authz_core.c>' . PHP_EOL;
		$htaccess .= "\tRequire all denied" . PHP_EOL;
		$htaccess .= '</IfModule>' . PHP_EOL;
		$htaccess .= '<IfModule !mod_authz_core.c>' . PHP_EOL;
		$htaccess .= "\tDeny from all" . PHP_EOL;
		$htaccess .= '</IfModule>';

		self::create_uploads_upload_directory_files( [
			[
				'base'    => STOREENGINE_SECURE_UPLOADS_DIR,
				'file'    => 'index.html', // Empty index, so server does not output auto-generated directory index.
				'content' => '',
			],
			[
				'base'    => STOREENGINE_SECURE_UPLOADS_DIR,
				'file'    => '.htaccess',
				'content' => $htaccess,
			],
			[
				'base'    => STOREENGINE_LOGS_DIR,
				'file'    => 'index.html',
				'content' => '',
			],
			[
				'base'    => STOREENGINE_LOGS_DIR,
				'file'    => '.htaccess',
				'content' => $htaccess,
			],
		] );

		// @TODO (In a separate function:private) Create a placeholder image in the media library (STOREENGINE_ASSETS_URI . images/thumbnail-placeholder.png).
		//       Add settings for user to change placeholder image.
		// @TODO Add image sizes for gallery, preview & thumbnails.
		//       Maybe add settings for user to change image sizes
		//       If image sizes changes or theme switched regenerate thumbnails.
	}

	public static function create_uploads_upload_directory_files( array $config ) {
		$upload_dir = wp_get_upload_dir();

		foreach ( $config as $file ) {
			$file = wp_parse_args( $file, [
				'base'    => '',
				'file'    => '',
				'content' => '',
			] );

			if ( empty( $file['base'] ) ) {
				continue;
			}

			if ( ! str_starts_with( $file['base'], $upload_dir['basedir'] ) ) {
				// Maybe out-of wp-content/uploads.
				continue;
			}

			$file['file'] = ltrim( rtrim( $file['file'], '/\\' ), '/\\' );

			// Check if file not exists first. If the file exists, the directory is already created.
			if ( ! file_exists( trailingslashit( $file['base'] ) . $file['file'] ) && wp_mkdir_p( $file['base'] ) && $file['file'] ) {
				$file_handle = @fopen( trailingslashit( $file['base'] ) . $file['file'], 'wb' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fopen

				if ( $file_handle ) {
					fwrite( $file_handle, $file['content'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fwrite, WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
					fclose( $file_handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_fclose, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
				}
			}
		}
	}

	public static function uninstall() {
		as_unschedule_all_actions( 'storeengine/geolocation/maxmind/db-update' );
		Role::remove_shop_manager_role();
		Role::remove_customer_role();
		flush_rewrite_rules();
	}

	/**
	 * Every Action Scheduler hook owned by StoreEngine core + bundled addons.
	 * Kept in one place so the "Reset Action Scheduler" admin tool and the
	 * uninstall hook stay in sync.
	 *
	 * @return string[]
	 */
	public static function get_known_scheduled_action_hooks(): array {
		return [
			// Core.
			'storeengine/geolocation/maxmind/db-update',
			'storeengine/logs/auto_cleanup',
			'storeengine/store_product_lookup',
			'storeengine/remove_product_lookup_data',

			// Webhooks addon.
			'storeengine/webhooks/async_delivery',

			// CSV addon.
			'storeengine/csv/clean_tmp_csv',

			// Invoice addon.
			'storeengine/mail/clean_tmp_invoices',

			// Multi-currency addon.
			'storeengine/multi_currency/refresh_rates',

			// Subscription addon.
			'storeengine/subscription/start',
			'storeengine/subscription/renewal',
			'storeengine/subscription/scheduled_payment',
			'storeengine/subscription/schedule_payment_retry',
			'storeengine/subscription/schedule_trial_end',
			'storeengine/subscription/schedule_expiration',
			'storeengine/subscription/schedule_end_of_prepaid_term',

			// Deprecated subscription hooks (kept for cleanup of legacy installs).
			'storeengine_subscription_renewal',
			'storeengine_create_renewal_order_schedule',

			// StoreEngine Pro — license management addon.
			'storeengine_pro/license/update_top_product_cache',
			'storeengine_pro/license/update_event_cc',
		];
	}
}
