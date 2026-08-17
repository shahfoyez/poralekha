<?php
/**
 * Stripe Payment Addon.
 *
 * @version 1.5.0
 */

namespace StoreEngine\Addons\Stripe;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Stripe\PaymentTokens\StripePaymentTokens;
use StoreEngine\Addons\Stripe\Tax\StripeTaxBridge;
use StoreEngine\Addons\Stripe\Tax\StripeTaxRest;
use StoreEngine\Admin\Notices;
use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

final class Stripe extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'stripe';

	public function define_constants() {
		define( 'STOREENGINE_STRIPE_VERSION', '1.0' );
		define( 'STOREENGINE_STRIPE_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'stripe/' );
	}

	public function init_addon() {
		add_filter( 'storeengine/payment_gateways', [ $this, 'add_gateway' ] );

		// Settings + meta registration must work even when the Stripe gateway
		// itself is disabled — otherwise tax settings can't be saved.
		StripeTaxBridge::bootstrap();

		add_action( 'storeengine/gateway/stripe/init', static function ( $gateway ) {
			Hooks::init( $gateway );
			StripePaymentTokens::get_instance();
			StripeService::init( $gateway );
			Api::init();
			Assets::init( $gateway );
			StripeTaxBridge::init();
			StripeTaxRest::init();

			// Native Stripe Billing (real Stripe subscriptions). The webhook
			// receiver + subscription sync are inert unless the gateway's
			// "Use native Stripe subscriptions" setting is enabled, except the
			// webhook endpoint which always listens (and no-ops for non-native).
			WebhookHandler::init( $gateway );
			SubscriptionSync::init( $gateway );
		} );
	}

	public function add_gateway( array $gateways ): array {
		$gateways[] = GatewayStripe::class;

		return $gateways;
	}
}
