<?php

namespace StoreEngine\Addons\Affiliate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Affiliate\Integrations\Email;
use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Affiliate\Settings\Affiliate as AffiliateSettings;
use StoreEngine\Addons\Affiliate\models\Payout;
use StoreEngine\Addons\Affiliate\models\Affiliate as AffiliateModel;

final class Affiliate extends AbstractAddon {

	use Singleton;

	protected string $addon_name = 'affiliate';

	public function define_constants() {
		define( 'STOREENGINE_AFFILIATE_VERSION', '1.0' );
		// Bump on any schema change under affiliate/database/ to trigger a
		// re-sync via the central Addons schema manager.
		// 1.2 — widened the affiliates.status ENUM (added rejected, suspended).
		define( 'STOREENGINE_AFFILIATE_DB_VERSION', '1.2' );
		define( 'STOREENGINE_AFFILIATE_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'affiliate/' );
		define( 'STOREENGINE_AFFILIATE_ASSETS_DIR', STOREENGINE_PLUGIN_ROOT_URI . 'addons/affiliate/assets/' );
		define( 'STOREENGINE_AFFILIATE_TEMPLATE_DIR', STOREENGINE_AFFILIATE_DIR_PATH . 'templates/' );
		define( 'STOREENGINE_AFFILIATE_SETTINGS_NAME', 'storeengine_affiliate_settings' );
		define( 'STOREENGINE_AFFILIATE_COOKIE_KEY', 'storeengine_affiliate' );
	}

	public function init_addon() {
		add_action( 'init', [ Role::class, 'add_affiliate_role' ] );
		$this->dispatch_hooks();
	}

	public function dispatch_hooks() {
		Shortcode::init();
		Ajax::init();
		Post::init();
		CookieHandler::init();
		Hooks::init();
	}

	public function get_db_version(): string {
		return STOREENGINE_AFFILIATE_DB_VERSION;
	}

	public function install_tables(): void {
		Database::init();
	}

	public function addon_activation_hook() {
		Database::init();
		AffiliateSettings::save_settings();
		Helper::flush_rewire_rules();
	}

	public function addon_deactivation_hook() {
		// Affiliate role + its caps stay on deactivate so existing affiliate
		// accounts keep their role. Use Role::remove_affiliate_role() on full
		// uninstall when the admin chooses to wipe all data.
		Helper::flush_rewire_rules();
	}
}
