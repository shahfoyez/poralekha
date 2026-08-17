<?php
/**
 * Instant Checkout addon.
 *
 * Renders a one-page Quick Checkout modal on the storefront and an embeddable
 * inline checkout (via shortcode) so authors can drop a single-purchase form
 * + product details on any WP page. Cross-origin embedding lives in a
 * separate Embeddable Checkout addon that depends on this one.
 *
 * The launcher (.storeengine-instant-checkout button click handler + the
 * StoreEngine.Checkout class) is bundled into core's frontend.js for perf —
 * a single network request instead of two — and self-installs on every page.
 * It's inert when this addon is disabled because nothing renders the buttons.
 */

namespace StoreEngine\Addons\InstantCheckout;

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class InstantCheckout extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'instant-checkout';

	public function define_constants() {
		define( 'STOREENGINE_INSTANT_CHECKOUT_VERSION', '1.8.7' );
		define( 'STOREENGINE_INSTANT_CHECKOUT_PATH', STOREENGINE_ADDONS_DIR_PATH . 'instant-checkout/' );
		define( 'STOREENGINE_INSTANT_CHECKOUT_URL', plugins_url( 'storeengine/addons/instant-checkout/' ) );
	}

	public function init_addon() {
		Settings::init();
		FrontendHandler::init();
		Hooks::init();
		Shortcode::init();
		Api\Session::init();
		Api\CheckoutBridge::init();
	}

	public function addon_activation_hook() {
		Settings::init()->save_default_settings();
		// Force a rewrite flush so /?se_checkout=1 resolves immediately.
		delete_option( FrontendHandler::REWRITE_OPT );
	}

	public function addon_deactivation_hook() {
		flush_rewrite_rules( false );
	}
}
