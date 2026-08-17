<?php

namespace StoreEngine\Addons\Paddle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;

final class Paddle extends AbstractAddon {

	use Singleton;

	protected string $addon_name = 'paddle';

	public function define_constants() {
		define( 'STOREENGINE_PADDLE_VERSION', '1.0' );
		define( 'STOREENGINE_PADDLE_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'paddle/' );
	}

	public function init_addon() {
		add_filter( 'storeengine/payment_gateways', [ $this, 'add_gateway' ] );

		Database::init();
		add_action( 'storeengine/gateway/paddle/init', static function ( $gateway ) {
			PaddleService::init( $gateway );
			Hooks::init();
			Assets::init( $gateway );
			API::init( $gateway );
		} );
	}

	public function add_gateway( array $gateways ): array {
		$gateways[] = GatewayPaddle::class;

		return $gateways;
	}
}
