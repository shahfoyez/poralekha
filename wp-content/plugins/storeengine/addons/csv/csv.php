<?php

namespace StoreEngine\Addons\Csv;

use StoreEngine\Addons\Csv\Ajax\Export;
use StoreEngine\Addons\Csv\Ajax\Import;
use StoreEngine\Addons\Csv\ThirdParty\Rcp;
use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Csv extends AbstractAddon {

	use Singleton;

	protected string $addon_name = 'csv';

	public function define_constants() {
		define( 'STOREENGINE_CSV_VERSION', '1.0' );
		define( 'STOREENGINE_CSV_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'csv/' );
	}

	public function init_addon() {
		Hooks::init();

		// ajax actions.
		( new Export() )->dispatch_actions();
		( new Import() )->dispatch_actions();
		( new Rcp() )->dispatch_actions();
	}

}
