<?php
/**
 * Plugin Name:     StoreEngine
 * Plugin URI:      https://storeengine.pro
 * Description:     Powerful WordPress eCommerce Plugin for Payments, Memberships, Affiliates, Sales & More
 * Version:         2.2.0
 * Author:          Kodezen
 * Author URI:      http://kodezen.com
 * License:         GPL-3.0+
 * License URI:     http://www.gnu.org/licenses/gpl-3.0.txt
 * Text Domain:     storeengine
 * Domain Path:     /i18n/languages/
 *
 * Requires PHP: 7.4
 * Requires at least: 6.5
 * Tested up to: 7.0
 *
 * @package StoreEngine
 */

use StoreEngine\ActionQueue;
use StoreEngine\Admin\Settings;
use StoreEngine\Classes\Cart;
use StoreEngine\Classes\Customer;
use StoreEngine\Classes\DownloadHandler;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Logger;
use StoreEngine\Classes\StockManager;
use StoreEngine\Classes\Tax;
use StoreEngine\Classes\UrlPresigner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StoreEngine {

	protected static ?StoreEngine $instance = null;

	public ?Customer $customer = null;
	public ?Cart $cart = null;

	private function __construct() {
		$this->define_constants();
		// If a failed/interrupted update left core files missing, bail with an
		// admin notice instead of fataling with a white screen.
		if ( ! $this->load_dependency() ) {
			return;
		}

		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
		register_shutdown_function( [ $this, 'log_errors' ] );

		Settings::load_settings();
		StoreEngine\Installer::init();

		add_action( 'activated_plugin', array( $this, 'activated_redirect' ), 10, 2 );
		// Translations are loaded automatically by WordPress from the plugin
		// header's `Text Domain` / `Domain Path` declarations (since 4.6), so
		// we don't call load_plugin_textdomain ourselves. WP 6.7+ will throw
		// "Translation loading triggered too early" if any code below calls
		// __() before the `init` action fires — if that happens, fix the
		// offending call site (defer to init), not by re-adding a loader here.
		add_action( 'plugins_loaded', array( $this, 'on_plugins_loaded' ) );
		add_action( 'storeengine_loaded', array( $this, 'init_plugin' ) );
		add_action( 'init', array( $this, 'dispatch_post_hooks' ) );
	}

	public static function init(): StoreEngine {

		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function define_constants() {
		/**
		 * Defines CONSTANTS for Whole plugins.
		 */
		define( 'STOREENGINE_VERSION', '2.2.0' );
		define( 'STOREENGINE_DB_VERSION', '1.3' );
		define( 'STOREENGINE_PLUGIN_FILE', __FILE__ );
		define( 'STOREENGINE_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		define( 'STOREENGINE_PLUGIN_SLUG', 'storeengine' );
		define( 'STOREENGINE_SETTINGS_NAME', 'storeengine_settings' );
		define( 'STOREENGINE_ADDONS_SETTINGS_NAME', 'storeengine_addons' );
		define( 'STOREENGINE_PAYMENTS_SETTINGS_NAME', 'storeengine_payments_settings' );
		define( 'STOREENGINE_PLUGIN_ROOT_URI', plugins_url( '/', __FILE__ ) );
		/** @define "STOREENGINE_ROOT_DIR_PATH" "./" */
		define( 'STOREENGINE_ROOT_DIR_PATH', plugin_dir_path( __FILE__ ) );
		/** @define "STOREENGINE_INCLUDES_DIR_PATH" "./includes/" */
		define( 'STOREENGINE_INCLUDES_DIR_PATH', STOREENGINE_ROOT_DIR_PATH . 'includes/' );
		define( 'STOREENGINE_ASSETS_DIR_PATH', STOREENGINE_ROOT_DIR_PATH . 'assets/' );
		define( 'STOREENGINE_ASSETS_URI', STOREENGINE_PLUGIN_ROOT_URI . 'assets/' );
		define( 'STOREENGINE_ADDONS_DIR_PATH', STOREENGINE_ROOT_DIR_PATH . 'addons/' );
		define( 'STOREENGINE_LIBRARY_PATH', STOREENGINE_ROOT_DIR_PATH . 'libraries/' );
		define( 'STOREENGINE_TEMPLATE_DEBUG_MODE', false );
		/** @define "STOREENGINE_TEMPLATE_PATH" "./templates/" */
		define( 'STOREENGINE_TEMPLATE_PATH', STOREENGINE_ROOT_DIR_PATH . 'templates/' );
		define( 'STOREENGINE_BLOCK_TEMPLATES_DIR_PATH', STOREENGINE_ROOT_DIR_PATH . 'templates/block-templates/' );

		$upload_dir = wp_get_upload_dir();
		/** @define "STOREENGINE_SECURE_UPLOADS_DIR" "./../../uploads/storeengine_uploads" */
		define( 'STOREENGINE_SECURE_UPLOADS_DIR', $upload_dir['basedir'] . '/storeengine_uploads' );
		define( 'STOREENGINE_LOGS_DIR', $upload_dir['basedir'] . '/storeengine-logs' );
	}

	public function log_errors() {
		$error          = error_get_last();
		$allowed_errors = [ E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR ];

		// Catch only severe errors that halt execution.
		if ( $error !== null && in_array( $error['type'], $allowed_errors, true ) ) {

			// Map fatal error types to readable names.
			$error_names = [
				E_ERROR         => 'Fatal Error',
				E_PARSE         => 'Parse Error',
				E_CORE_ERROR    => 'Core Error',
				E_COMPILE_ERROR => 'Compile Error',
				E_USER_ERROR    => 'User Error',
			];
			$error_type  = $error_names[ $error['type'] ] ?? 'Fatal Error';
			$error_copy  = $error;
			$message     = $error_copy['message'];
			unset( $error_copy['message'] );

			$context = [ 'error' => $error_copy ];

			if ( false !== strpos( $message, 'Stack trace:' ) ) {
				$segments  = explode( 'Stack trace:', $message );
				$message   = str_replace( PHP_EOL, ' ', trim( $segments[0] ) );
				$backtrace = array_map( 'trim', explode( PHP_EOL, $segments[1] ) );

				$context['backtrace'] = $backtrace;
			} else {
				$context['backtrace'] = true;
			}

			// Format a descriptive title, e.g., Critical Crash: PHP Fatal Error.
			$title = sprintf( 'Critical Crash: PHP %s', $error_type );

			$content = [
				'message' => $message,
				'file'    => $error['file'],
				'line'    => $error['line'],
				'context' => $context,
			];

			Logger::log( $title, $content, Logger::CRITICAL, 'system' );

			/**
			 * Action triggered when there are errors during shutdown.
			 *
			 * @since 1.8.0
			 */
			do_action( 'storeengine_shutdown_error', $error );
		}
	}

	/**
	 * When WP has loaded all plugins, trigger the `storeengine_loaded` hook.
	 *
	 * This ensures `storeengine_loaded` is called only after all other plugins
	 * are loaded, to avoid issues caused by plugin directory naming changing
	 */
	public function on_plugins_loaded() {
		/**
		 * When WP has loaded all plugins, trigger the storeengine_loaded hook.
		 */
		do_action( 'storeengine_loaded' );
	}

	/**
	 * Initialize the plugin
	 *
	 * @return void
	 */
	public function init_plugin() {
		//$non_existent_variable->method();
		/**
		 * Before the plugin initialization.
		 */
		do_action( 'storeengine/before_init' );
		StoreEngine\Utils\Caching::init();
		$this->load_addons();
		StoreEngine\Payment_Gateways::init();
		$this->dispatch_hooks();
		/**
		 * Initialize the plugin.
		 */
		do_action( 'storeengine_init' );
	}

	public function dispatch_hooks() {
		DownloadHandler::init();
		UrlPresigner::init();
		StoreEngine\Cli::init();
		StoreEngine\Hooks::init();
		StoreEngine\Database::init();
		StoreEngine\PermalinkRewrite::init();
		StoreEngine\Shipping\Shipping::init();
		StoreEngine\API::init();
		StoreEngine\Ajax::init();
		StoreEngine\Assets::init();
		StoreEngine\Shortcode::init();
		StoreEngine\Integrations::init();
		StoreEngine\Migration::init();
		StoreEngine\Backup\BackupManager::init();
		StoreEngine\Miscellaneous::init();
		StoreEngine\Schedule::init();
		StockManager::init();
		Tax::init();

		if ( is_admin() ) {
			StoreEngine\Admin::init();
		}

		StoreEngine\Frontend::init();
	}

	public function dispatch_post_hooks() {
		StoreEngine\Post::init();
	}

	public function load_addons() {
		StoreEngine\Addons::init();
	}

	/**
	 * Load global settings.
	 *
	 * @deprecated
	 * @see \StoreEngine\Admin\Settings::load_settings()
	 */
	public function set_global_settings() {
		$GLOBALS['storeengine_settings'] = apply_filters( 'storeengine_settings', json_decode( get_option( STOREENGINE_SETTINGS_NAME, '{}' ) ) );
		$GLOBALS['storeengine_addons']   = json_decode( get_option( STOREENGINE_ADDONS_SETTINGS_NAME, '{}' ) );
	}

	public function load_dependency(): bool {
		// Internal Autoload — required; without it nothing else can load.
		if ( ! self::require_or_notice( STOREENGINE_INCLUDES_DIR_PATH . 'autoload.php', 'StoreEngine' ) ) {
			return false;
		}

		// Autoload prefixed / composer packages. Optional — keep the existing
		// tolerant behavior (don't abort if absent).
		self::require_or_notice( STOREENGINE_ROOT_DIR_PATH . 'vendor/prefixed/autoload.php', 'StoreEngine', false );
		self::require_or_notice( STOREENGINE_ROOT_DIR_PATH . 'vendor/autoload.php', 'StoreEngine', false );

		// StoreEngine License Management SDK — loaded here (not by the free
		// satellite plugins) so the whole ecosystem can share one copy. It is a
		// version-managed shared library (Action Scheduler pattern): its init.php
		// registers on `plugins_loaded` pri 0/1, and the highest version across
		// every active plugin (core, Pro, …) wins. This must run during plugin
		// load — before `plugins_loaded` fires — which it does, since core is
		// instantiated at file load. Requiring it here (rather than via composer's
		// autoload-files) means it works even before `composer install` is run.
		// The free StoreEngine Payments / Connectors plugins reuse this copy via
		// the global `se_license_init()` for their automatic store updates.
		self::require_or_notice( STOREENGINE_ROOT_DIR_PATH . 'vendor/storeengine/wordpress-sdk/init.php', 'StoreEngine', false );

		// Frontend Template and Hooks — hard-required.
		return self::require_or_notice( STOREENGINE_INCLUDES_DIR_PATH . 'frontend/functions.php', 'StoreEngine' )
			&& self::require_or_notice( STOREENGINE_INCLUDES_DIR_PATH . 'frontend/hooks.php', 'StoreEngine' );
	}

	/**
	 * Require a bootstrap file, or — if it is missing (e.g. an interrupted
	 * update left the plugin folder incomplete) — register an admin notice and
	 * signal the caller to abort booting instead of fataling with a white
	 * screen. Must not depend on any autoloaded class.
	 *
	 * @param string $path   Absolute path to the file to require.
	 * @param string $label  Human-readable plugin name for the notice.
	 * @param bool   $notify Whether to show an admin notice when missing.
	 *                       Pass false for optional files.
	 *
	 * @return bool True if required successfully, false if the file is missing.
	 */
	private static function require_or_notice( string $path, string $label, bool $notify = true ): bool {
		if ( is_readable( $path ) ) {
			require_once $path;

			return true;
		}

		if ( $notify ) {
			add_action( 'admin_notices', static function () use ( $label ) {
				echo '<div class="notice notice-error"><p>';
				echo esc_html( sprintf(
				/* translators: %s: plugin name. */
					__( '%s core files are missing or incomplete — please reinstall the plugin.', 'storeengine' ),
					$label
				) );
				echo '</p></div>';
			} );
		}

		return false;
	}

	/**
	 * Initialize and load the cart functionality.
	 */
	public function load_cart() {
		if ( ! did_action( 'storeengine/before_init' ) || doing_action( 'storeengine/before_init' ) ) {
			/* translators: 1: initialize_session 2: storeengine/before_init */
			_doing_it_wrong( __FUNCTION__, sprintf( esc_html__( '%1$s should not be called before the %2$s action.', 'storeengine' ), 'storeengine_load_cart', 'storeengine/before_init' ), '1.0.0' );

			return;
		}

		$this->initialize_cart();
	}

	/**
	 * Initialize the customer and cart objects and setup customer saving on shutdown.
	 *
	 * Note, storeengine()->customer is session based. Changes to customer data via this property are not persisted to the database automatically.
	 *
	 * @return void
	 */
	public function initialize_cart() {
		if ( did_action( 'storeengine/cart/initialized' ) && $this->customer && $this->cart ) {
			return;
		}

		if ( ! $this->customer instanceof Customer ) {
			$this->customer = new Customer( get_current_user_id(), true );
		}

		if ( ! $this->cart instanceof Cart ) {
			$this->cart = Cart::init();
		}

		/**
		 * Fires after cart initialization finished.
		 */
		do_action( 'storeengine/cart/initialized' );
	}

	/**
	 * @return ?Cart
	 */
	public function get_cart(): ?Cart {
		return $this->cart;
	}

	/**
	 * @return ?Customer
	 */
	public function get_customer(): ?Customer {
		return $this->customer;
	}

	/**
	 * Get queue instance.
	 *
	 * @return ActionQueue
	 * @throws StoreEngineException
	 */
	public function queue(): ActionQueue {
		return ActionQueue::get_instance();
	}

	public function activate() {
		StoreEngine\Installer::install();
	}

	public function deactivate() {
		StoreEngine\Installer::uninstall();
	}

	public function activated_redirect( $plugin, $network_wide = null ) {
		if ( $network_wide || class_exists( \WP_CLI::class, false ) || STOREENGINE_PLUGIN_BASENAME !== $plugin ) {
			return;
		}

		if ( 'yes' === get_option( 'storeengine_has_redirect_to_setup_wizard', 'no' ) ) {
			return;
		}

		update_option( 'storeengine_has_redirect_to_setup_wizard', 'yes', false );
		wp_safe_redirect( admin_url( 'admin.php?page=storeengine-setup' ) );
		exit;
	}
}

/**
 * Initializes the main plugin
 *
 * @return StoreEngine
 */
function storeengine(): StoreEngine {
	return StoreEngine::init();
}

/**
 * Initializes the main plugin
 *
 * @return StoreEngine
 * @deprecated in favor of storeengine()
 * @see storeengine()
 */
function storeengine_start(): StoreEngine {
	return storeengine();
}

// Plugin Start
storeengine();
