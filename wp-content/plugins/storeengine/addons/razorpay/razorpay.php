<?php
/**
 * Stripe Payment Addon.
 *
 * @version 1.5.0
 */

namespace StoreEngine\Addons\Razorpay;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Stripe\PaymentTokens\StripePaymentTokens;
use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;

final class Razorpay extends AbstractAddon {

	use Singleton;

	protected string $addon_name = 'razorpay';

	public function define_constants() {
		define( 'STOREENGINE_RAZORPAY_VERSION', '1.0' );
		define( 'STOREENGINE_RAZORPAY_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'razorpay/' );
	}

	public function init_addon() {
		add_filter( 'storeengine/payment_gateways', [ $this, 'add_gateway' ] );

		add_action( 'storeengine/gateway/razorpay/init', static function ( $gateway ) {
			RazorpayService::init( $gateway );
			Assets::init( $gateway );
		} );
	}

	public function add_gateway( array $gateways ): array {
		$gateways[] = GatewayRazorpay::class;

		return $gateways;
	}
}
