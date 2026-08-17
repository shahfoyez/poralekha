<?php

namespace StoreEngine\Addons\MultiVendor;

use StoreEngine\Addons\MultiVendor\Api\Vendor as VendorApi;
use StoreEngine\Addons\MultiVendor\Api\Vendors as VendorsApi;
use StoreEngine\Addons\MultiVendor\Api\ProductPermissions;
use StoreEngine\Addons\MultiVendor\Api\Withdrawals as WithdrawalsApi;
use StoreEngine\Addons\MultiVendor\Api\Fulfillment as FulfillmentApi;
use StoreEngine\Addons\MultiVendor\Api\Settings as SettingsApi;
use StoreEngine\Addons\MultiVendor\Classes\WebhookEvents;
use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MultiVendor extends AbstractAddon {

	use Singleton;

	protected string $addon_name = 'multi-vendor';

	public function define_constants() {
		define( 'STOREENGINE_MULTI_VENDOR_VERSION', '1.0.0' );
		// Bump on any schema change under multi-vendor/database/ to trigger a
		// re-sync via the central Addons schema manager.
		define( 'STOREENGINE_MULTI_VENDOR_DB_VERSION', '1.0.1' );
		define( 'STOREENGINE_MULTI_VENDOR_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'multi-vendor/' );
		define( 'STOREENGINE_MULTI_VENDOR_ASSETS_URI', STOREENGINE_PLUGIN_ROOT_URI . 'addons/multi-vendor/assets/' );
		// Templates now live in core `templates/multi-vendor/` (same
		// convention as the email addon). Loaders use Helper::get_template
		// with the `multi-vendor/` prefix so themes can override via
		// `<theme>/storeengine/multi-vendor/...`.
		define( 'STOREENGINE_MULTI_VENDOR_SETTINGS_NAME', 'storeengine_multi_vendor_settings' );
	}

	public function init_addon() {
		// Table creation/migration is handled centrally by the Addons schema
		// manager (see Addons::sync_addon_schemas) via get_db_version() +
		// install_tables() below — both run on `init` priority 4.
		add_action( 'init', [ Role::class, 'add_vendor_role' ] );
		add_action( 'init', [ OwnerVendor::class, 'ensure' ], 20 );
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		$this->dispatch_hooks();
	}

	public function dispatch_hooks() {
		Hooks::init();
		Shortcode::init();
		Admin::init();
		VendorApi::init();
		VendorsApi::init();
		WithdrawalsApi::init();
		FulfillmentApi::init();
		SettingsApi::init();
		ProductPermissions::init();
		CommissionCalculator::init();
		TemplateLoader::init();
		StorePage::init();
		ShopFilter::init();
		WebhookEvents::init();
	}

	public function enqueue_assets() {
		// Load on the dashboard page (Helper::is_dashboard returns true) or on any page
		// containing the signup shortcode. Cheap to load — single small CSS file.
		$css = STOREENGINE_MULTI_VENDOR_DIR_PATH . 'assets/multi-vendor.css';
		if ( ! file_exists( $css ) ) {
			return;
		}

		wp_enqueue_style(
			'storeengine-multi-vendor',
			STOREENGINE_MULTI_VENDOR_ASSETS_URI . 'multi-vendor.css',
			[],
			filemtime( $css )
		);

		// Fulfilment Save handler for the vendor "Store orders" page. Tiny,
		// delegated on document, and no-ops when the page isn't present.
		$js = STOREENGINE_MULTI_VENDOR_DIR_PATH . 'assets/fulfillment.js';
		if ( file_exists( $js ) ) {
			wp_enqueue_script(
				'storeengine-multi-vendor-fulfillment',
				STOREENGINE_MULTI_VENDOR_ASSETS_URI . 'fulfillment.js',
				[],
				filemtime( $js ),
				true
			);
			wp_localize_script( 'storeengine-multi-vendor-fulfillment', 'StoreEngineVendorFulfillment', [
				'i18n' => [
					'saving'        => __( 'Saving…', 'storeengine' ),
					'saved'         => __( 'Saved.', 'storeengine' ),
					'pick_status'   => __( 'Pick a status first.', 'storeengine' ),
					'error'         => __( 'Could not update. Please try again.', 'storeengine' ),
					'network_error' => __( 'Network error. Please try again.', 'storeengine' ),
				],
			] );
		}
	}

	public function get_db_version(): string {
		return STOREENGINE_MULTI_VENDOR_DB_VERSION;
	}

	public function install_tables(): void {
		Database::init();
	}

	public function addon_activation_hook() {
		Database::init();
		Role::add_vendor_role();

		// Persist defaults to the option on first activation so the React
		// Settings tab shows checked boxes that match the runtime behaviour.
		if ( false === get_option( Settings::OPTION ) ) {
			Settings::save( Settings::defaults() );
		}

		// Spin up the owner / default vendor right now so the admin can hit
		// the dashboard immediately without waiting for the next `init`.
		OwnerVendor::ensure();

		// Raise StoreEngine's existing flush flag — Database::maybe_flush_rewrite_rules()
		// picks this up on the next admin page load and runs flush_rewrite_rules() once,
		// which makes /dashboard/vendor-withdraw/, /vendor-payment-method/, and the
		// other new vendor endpoints reachable without a manual permalink save.
		Helper::flush_rewire_rules();
	}

	public function addon_deactivation_hook() {
		// Vendor role + caps stay on deactivate so existing vendor accounts
		// keep their role. Use Role::remove_vendor_role() on full uninstall
		// when the admin chooses to wipe all data.
		Helper::flush_rewire_rules();
	}
}
