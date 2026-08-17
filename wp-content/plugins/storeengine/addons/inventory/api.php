<?php

namespace StoreEngine\Addons\Inventory;

use StoreEngine\Addons\Inventory\Api\AdjustController;
use StoreEngine\Addons\Inventory\Api\MovementsController;
use StoreEngine\Addons\Inventory\Api\StockController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Api {

	public static function init() {
		StockController::init();
		MovementsController::init();
		AdjustController::init();
	}
}
