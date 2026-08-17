<?php
/**
 * Inventory addon (free).
 *
 * Single-location stock management for physical stores. Owns the admin UI
 * and REST endpoints over the legacy free-core columns
 * (`variations.stock_quantity`, `low_stock_threshold`, `stock_status`,
 * `backorders`) and the existing `storeengine_stock_movements` table.
 *
 * Multi-location, transfers, and per-location dashboards live in the Pro
 * `inventory-pro` addon.
 */

namespace StoreEngine\Addons\Inventory;

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Inventory extends AbstractAddon {

	use Singleton;

	protected string $addon_name = 'inventory';

	public function define_constants() {
		define( 'STOREENGINE_INVENTORY_VERSION', '1.0.0' );
		define( 'STOREENGINE_INVENTORY_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'inventory/' );
	}

	public function init_addon() {
		Api::init();
	}
}
