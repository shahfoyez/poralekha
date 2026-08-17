<?php
/**
 * StoreEngine License Management Client SDK bootstrap for aBlocks (free).
 *
 * aBlocks (free) OWNS the shared, version-managed SDK — it is the Composer
 * package `storeengine/wordpress-sdk`, loaded via `vendor/autoload.php` in
 * `ABlocks::load_dependency()`. Here we simply register the free product so the
 * SDK provides:
 *
 *  - The deactivation-reason popup + anonymous opt-in (`init_insights`). This
 *    replaces aBlocks' previous homegrown Insights dialog (includes/admin/insights.php).
 *
 * `use_update` is intentionally OFF: aBlocks (free) is distributed through
 * wp.org, so the SDK is used here only for uninstall-tracking/analytics, not to
 * serve updates. aBlocks Pro reuses this same SDK for its own license + updater.
 *
 * @package ABlocks
 */

namespace ABlocks\Admin;

use SE_License_SDK_Client;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StoreLicense {

	/**
	 * aBlocks (free) product ID on https://store.kodezen.com/.
	 */
	const PRODUCT_ID = 331;

	protected static ?SE_License_SDK_Client $sdk_client = null;

	public static function init(): void {
		if ( ! did_action( 'plugins_loaded' ) ) {
			add_action( 'plugins_loaded', [ __CLASS__, 'load_sdk' ] );
		} else {
			self::load_sdk();
		}
	}

	public static function load_sdk(): void {
		if ( null !== self::$sdk_client ) {
			return;
		}

		// The shared SDK is loaded by aBlocks (free) itself via load_dependency().
		// Bail quietly if it is somehow unavailable rather than fataling.
		if ( ! function_exists( 'se_license_init' ) ) {
			return;
		}

		// The SDK requires a valid product ID to construct its client (it fatals
		// otherwise). Guard against a misconfigured/empty ID so the site never
		// white-screens.
		if ( self::PRODUCT_ID <= 0 ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'ABlocks: StoreEngine SDK not initialised — set StoreLicense::PRODUCT_ID to the free aBlocks product ID.' );
			}
			return;
		}

		$first_install = get_option( 'ablocks_first_install_time' );
		if ( ! $first_install ) {
			$first_install = time();
			add_option( 'ablocks_first_install_time', $first_install, '', false );
		}

		self::$sdk_client = se_license_init( [
			'package_file'        => ABLOCKS_ROOT_DIR_PATH . 'ablocks.php',
			'package_name'        => 'aBlocks',
			'product_id'          => self::PRODUCT_ID,
			'is_free'             => true,
			// wp.org-distributed: SDK is used only for insights/opt-in here, not
			// as an update channel. Flip to true only if free aBlocks is deployed
			// through the StoreEngine store instead of wp.org.
			'use_update'          => false,
			'slug'                => ABLOCKS_PLUGIN_SLUG,
			'basename'            => ABLOCKS_PLUGIN_BASENAME,
			'package_type'        => 'plugin',
			'package_version'     => ABLOCKS_VERSION,
			// Keep local activation allowed so the deactivation popup + opt-in are
			// not suppressed on local/dev sites (is_local_request() short-circuits
			// to false when allow_local is true).
			'allow_local'         => true,
			'init_insights'       => true,
			'license_server'      => 'https://store.kodezen.com/',
			'purchase_url'        => 'https://store.kodezen.com/product/ablocks-pro/',
			'product_logo'        => defined( 'ABLOCKS_ASSETS_URL' ) ? ABLOCKS_ASSETS_URL . 'images/logo-shape.svg' : '',
			'store_dashboard_url' => 'https://store.kodezen.com/dashboard/license-keys/',
			'terms_url'           => 'https://kodezen.com/terms-and-conditions/',
			'privacy_policy_url'  => 'https://store.kodezen.com/privacy-policy/',
			'support_url'         => 'https://community.kodezen.com/tickets',
			'ticket_recipient'    => 'support@kodezen.com',
			'primary_color'       => '#13191B',
			'first_install_time'  => $first_install,
			'optin_notice_delay'  => 3 * DAY_IN_SECONDS,
		] );
	}

	public static function get_client(): ?SE_License_SDK_Client {
		return self::$sdk_client;
	}
}
