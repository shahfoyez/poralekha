<?php
/**
 * Couriers / Shipping Partners addon (Pro).
 *
 * Provides a thin abstraction over courier APIs (Pathao, Steadfast,
 * Shiprocket, ...). Stores per-order shipments with tracking IDs and
 * status-cache. Keys live in the storeengine_courier_settings option (one
 * config blob per provider). Sync runs on demand via REST + Action
 * Scheduler poll for in-flight shipments.
 */

namespace StoreEngine\Addons\Couriers;

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Couriers extends AbstractAddon {

	use Singleton;

	protected string $addon_name = 'couriers';

	public function define_constants(): void {
		define( 'STOREENGINE_COURIERS_VERSION', '1.0.0' );
		define( 'STOREENGINE_COURIERS_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'couriers/' );
	}

	public function init_addon(): void {
		// Table creation is driven by the core addon manager, which calls
		// install_tables() on a get_db_version() mismatch (see Database::install).
		Api::init();
		Classes\Scheduler::init();
		Classes\AutoPush::init();
		Classes\OrderStatusSync::init();

		// Side-menu + SPA route. Used to live in the free plugin's
		// Menu::inject_retail_menu_items(); it now ships with the addon so
		// disabling Couriers removes both the menu and its route. Only runs
		// when the addon is active (AbstractAddon::run() gate).
		add_filter( 'storeengine/admin_menu_list', [ __CLASS__, 'register_menu_items' ] );
	}

	/**
	 * Inject the Couriers top-level menu into the React admin shell.
	 * (Provider settings live in the main Settings → Couriers tab.)
	 *
	 * @param array $menu Existing menu list.
	 * @return array
	 */
	public static function register_menu_items( array $menu ): array {
		// Single "Shipments" entry (folded under the Orders group). The page
		// slug stays `-couriers` (addon key, route, REST all unchanged); only
		// the visible label reads "Shipments". No sub-item — a lone empty-slug
		// child would just duplicate the parent row onto the same screen.
		$menu[ STOREENGINE_PLUGIN_SLUG . '-couriers' ] = [
			'title'      => __( 'Shipments', 'storeengine' ),
			'capability' => 'manage_options',
			'priority'   => 29,
		];

		return $menu;
	}

	public function get_db_version(): string {
		return Database::DB_VERSION;
	}

	public function install_tables(): void {
		Database::install();
	}

	public function addon_activation_hook(): void {
		Database::install();
	}

	public function addon_deactivation_hook(): void {
		Classes\Scheduler::clear();
	}
}
