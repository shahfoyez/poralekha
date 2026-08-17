<?php
/**
 * Funnel Builder REST API bootstrap.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Addons\FunnelBuilder;

use StoreEngine\Addons\FunnelBuilder\Api\AnalyticsController;
use StoreEngine\Addons\FunnelBuilder\Api\FunnelsController;
use StoreEngine\Addons\FunnelBuilder\Api\StepsController;
use StoreEngine\Addons\FunnelBuilder\Api\StoreCheckoutController;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Api {

	public static function init() {
		add_action( 'rest_api_init', static function () {
			( new FunnelsController() )->register_routes();
			( new StepsController() )->register_routes();
			( new AnalyticsController() )->register_routes();
			( new StoreCheckoutController() )->register_routes();

			/**
			 * Lets the Pro addon register its own funnel-builder routes (upsell
			 * accept/reject, A/B, conditions) on the same namespace.
			 */
			do_action( 'storeengine/funnel-builder/rest_api_init' );
		} );
	}
}
