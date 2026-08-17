<?php
/**
 * Plugin Name:       aBlocks
 * Description:       The WordPress plugin for creating beautiful and functional websites using the Gutenberg editor, with a variety of customizable blocks to design website pages.
 * Requires at least: 6.8
 * Requires PHP:      7.4
 * Version:           2.11.0
 * Author:            Kodezen LLC
 * Author URI:        https://ablocks.pro/
 * License:           GPL-3.0+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       ablocks
 * Domain Path:     /languages/
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ABlocks {
	public function __construct() {
		// define constants
		$this->define_constants();
		$this->load_dependency();
		// Register the free product with the shared StoreEngine SDK (loaded in
		// load_dependency) so the SDK owns the deactivation popup + opt-in.
		\ABlocks\Admin\StoreLicense::init();
		$this->load_cli();
		register_activation_hook( __FILE__, [ $this, 'activate' ] );
		register_deactivation_hook( __FILE__, [ $this, 'deactivate' ] );
		$this->set_global_settings();
		add_action( 'plugins_loaded', [ $this, 'on_plugins_loaded' ] );
		add_action( 'ablocks_loaded', [ $this, 'init_plugin' ] );
	}

	public static function init() {
		static $instance = false;
		if ( ! $instance ) {
			$instance = new self();
		}
		return $instance;
	}

	/**
	 * Define the plugin constants
	 */
	private function define_constants() {
		define( 'ABLOCKS_VERSION', '2.11.0' );
		define( 'ABLOCKS_PLUGIN_SLUG', 'ablocks' );
		define( 'ABLOCKS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
		define( 'ABLOCKS_ROOT_URL', plugin_dir_url( __FILE__ ) );
		define( 'ABLOCKS_ASSETS_URL', ABLOCKS_ROOT_URL . 'assets/' );
		define( 'ABLOCKS_ROOT_DIR_PATH', plugin_dir_path( __FILE__ ) );
		define( 'ABLOCKS_ASSETS_PATH', ABLOCKS_ROOT_DIR_PATH . 'assets/' );
		define( 'ABLOCKS_INCLUDES_DIR_PATH', ABLOCKS_ROOT_DIR_PATH . 'includes/' );
		define( 'ABLOCKS_BLOCKS_DIR_PATH', ABLOCKS_ROOT_DIR_PATH . 'includes/blocks/' );
		define( 'ABLOCKS_ADDONS_DIR_PATH', ABLOCKS_ROOT_DIR_PATH . 'addons/' );
		define( 'ABLOCKS_BLOCKS_VISIBILITY_SETTINGS_NAME', 'ablocks_blocks' );
		define( 'ABLOCKS_FONTS_SETTINGS_NAME', 'ablocks_fonts' );
		define( 'ABLOCKS_REST_NAMESPACE', 'ablocks/v1' );
		define( 'ABLOCKS_SETTINGS_NAME', 'ablocks_settings' );
		define( 'ABLOCKS_FRONTEND_DASHBOARD_SUB_PAGES_SETTINGS_NAME', 'ablocks_frontend_dashboard_sub_pages' );
		define( 'ABLOCKS_ADDONS_SETTINGS_NAME', 'ablocks_addons' );
		define( 'ABLOCKS_TEMPLATE_LIB_HOST', 'template-kits.com' );
	}

	public function load_dependency() {
		require_once ABLOCKS_INCLUDES_DIR_PATH . 'autoload.php';

		// Load the shared StoreEngine License Management Client SDK. aBlocks (free)
		// owns this version-managed library (Composer package
		// `storeengine/wordpress-sdk`); aBlocks Pro (and any add-on) reuses the
		// already-loaded SDK rather than bundling its own copy.
		if ( file_exists( ABLOCKS_ROOT_DIR_PATH . 'vendor/autoload.php' ) ) {
			require_once ABLOCKS_ROOT_DIR_PATH . 'vendor/autoload.php';
		}

		// Require the SDK bootstrap DIRECTLY, not only through Composer's files
		// autoload. Composer de-dupes the SDK's init.php by a package-stable hash,
		// so when several active plugins each bundle this SDK only the first one's
		// autoloader includes it and the rest never register their version. Loading
		// it here guarantees aBlocks' bundled SDK version always joins the
		// "newest version wins" election regardless of plugin load order. Requiring
		// init.php registers this SDK version and, on `plugins_loaded`, loads
		// functions.php (defining se_license_init()) and the SDK class autoloader.
		if ( file_exists( ABLOCKS_ROOT_DIR_PATH . 'vendor/storeengine/wordpress-sdk/init.php' ) ) {
			require_once ABLOCKS_ROOT_DIR_PATH . 'vendor/storeengine/wordpress-sdk/init.php';
		}
	}
	public function load_cli() {
		if ( file_exists( ABLOCKS_ROOT_DIR_PATH . 'dev-cli.php' ) ) {
			require_once ABLOCKS_ROOT_DIR_PATH . 'dev-cli.php';
		}
	}

	public function set_global_settings() {
		$GLOBALS['ablocks_fonts'] = json_decode( get_option( ABLOCKS_FONTS_SETTINGS_NAME, '{}' ), true );
		$GLOBALS['ablocks_blocks'] = json_decode( get_option( ABLOCKS_BLOCKS_VISIBILITY_SETTINGS_NAME, '{}' ) );
		$GLOBALS['ablocks_settings'] = json_decode( get_option( ABLOCKS_SETTINGS_NAME, '{}' ) );
		$GLOBALS['ablocks_addons'] = json_decode( get_option( ABLOCKS_ADDONS_SETTINGS_NAME, '{}' ) );
	}

	/**
	 * When WP has loaded all plugins, trigger the `ablocks_loaded` hook.
	 *
	 * This ensures `ablocks_loaded` is called only after all other plugins
	 * are loaded, to avoid issues caused by plugin directory naming changing
	 *
	 * @since 1.0.0
	 */
	public function on_plugins_loaded() {
		do_action( 'ablocks_loaded' );
	}

	public function init_plugin() {
		ABlocks\Migration::init();
		ABlocks\PermalinkRewrite::init();
		ABlocks\Addons::init();
		ABlocks\Blocks::init();
		ABlocks\Assets::init();
		ABlocks\Classes\CoreFontRegistry::init();
		ABlocks\Performance\Optimizations::init();
		ABlocks\Performance\DelayJs::init();
		ABlocks\Performance\DeferJs::init();
		ABlocks\Performance\ImageOptimizer::init();
		ABlocks\Performance\LcpPreload::init();
		ABlocks\Performance\TouchTargets::init();
		ABlocks\Performance\PageCache::init();
		ABlocks\Performance\StyleConsolidator::init();
		ABlocks\Performance\FragmentCache::init();
		ABlocks\Performance\TemplateCache::init();
		ABlocks\Performance\ImageTools::init();
		ABlocks\Classes\Images\UploadGuard::init();
		ABlocks\Ajax::init();
		ABlocks\API::init();
		if ( is_admin() ) {
			ABlocks\Admin::init();
		}
		ABlocks\Frontend::init();

	}

	public function activate() {
		ABlocks\Installer::init();
	}

	/**
	 * Leave no scheduled work behind when the plugin is switched off.
	 *
	 * Cached files are deliberately left in place: deactivation is often
	 * temporary, and re-activating should not mean rebuilding the whole cache.
	 * They are removed on uninstall, or on demand from the Performance tab.
	 */
	public function deactivate() {
		ABlocks\Classes\PageCache\Scheduler::clear_events();
	}
}

/**
 * Kickoff
*/

ABlocks::init();


