<?php

namespace StoreEngine\Addons\Couriers;

use StoreEngine\Addons\Couriers\Api\ProvidersController;
use StoreEngine\Addons\Couriers\Api\SettingsController;
use StoreEngine\Addons\Couriers\Api\ShipmentsController;
use StoreEngine\Addons\Couriers\Api\OAuthController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Api {

	public static function init(): void {
		ProvidersController::init();
		SettingsController::init();
		ShipmentsController::init();
		OAuthController::init();
	}
}
