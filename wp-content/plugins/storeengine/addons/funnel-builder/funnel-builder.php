<?php
/**
 * Funnel Builder (free) addon.
 *
 * Visual sales-funnel builder for StoreEngine. Funnels are ordered sequences of
 * steps (landing, opt-in, checkout, upsell, downsell, thank-you); each step
 * renders a WordPress page built with the active block/page builder (aBlocks).
 *
 * The free addon owns the data layer (funnels, steps, stats tables), the admin
 * editor, the REST API, frontend routing and the base step types. The Pro addon
 * (storeengine-pro/addons/funnel-builder) shares the SAME slug `funnel-builder`,
 * so the single `storeengine_addons['funnel-builder']` flag activates both at
 * once — there is no separate Pro toggle. Pro layers one-click upsells, A/B
 * testing and conditional offers on top, storing its config inside the JSON
 * `settings` columns this addon defines (Pro owns no tables of its own).
 *
 * @version 1.0.0
 */

namespace StoreEngine\Addons\FunnelBuilder;

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class FunnelBuilder extends AbstractAddon {

	use Singleton;

	protected string $addon_name = 'funnel-builder';

	const DB_VERSION = '1.0.0';

	public function define_constants() {
		define( 'STOREENGINE_FUNNEL_BUILDER_VERSION', '1.0.0' );
		define( 'STOREENGINE_FUNNEL_BUILDER_DB_VERSION', self::DB_VERSION );
		define( 'STOREENGINE_FUNNEL_BUILDER_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'funnel-builder/' );
		define( 'STOREENGINE_FUNNEL_BUILDER_URI', plugins_url( 'addons/funnel-builder/', STOREENGINE_PLUGIN_FILE ) );
		define( 'STOREENGINE_FUNNEL_BUILDER_ASSETS_URI', STOREENGINE_FUNNEL_BUILDER_URI . 'assets/' );
	}

	/**
	 * The free addon owns every table the funnel builder needs. Bump DB_VERSION
	 * whenever the schema in Installer changes so the central manager
	 * (\StoreEngine\Addons::sync_schema_for) reruns install_tables().
	 */
	public function get_db_version(): string {
		return self::DB_VERSION;
	}

	public function install_tables(): void {
		Installer::install_tables();
	}

	public function addon_activation_hook() {
		Installer::install_tables();
	}

	public function init_addon() {
		Database::init();
		Api::init();
		Hooks::init();
		Frontend::init();
		StoreCheckout::init();
		ShortcodeBlocks::init();

		/**
		 * Lets the Pro addon (and third parties) boot once the free funnel
		 * builder is fully loaded. Pro hangs its upsell engine here.
		 */
		do_action( 'storeengine/funnel-builder/loaded' );
	}
}
