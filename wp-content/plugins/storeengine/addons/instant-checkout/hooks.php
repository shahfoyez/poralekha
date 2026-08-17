<?php

namespace StoreEngine\Addons\InstantCheckout;

use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {
	use Singleton;

	protected function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'register_bundles' ], 5 );
		add_action( 'wp_enqueue_scripts', [ $this, 'localize_launcher_config' ], 20 );
	}

	/**
	 * Register the React app bundle so the iframe template + shortcode can
	 * `wp_enqueue_script()` it by handle. The launcher SDK ships inside core
	 * `frontend.js` (always loaded) — no separate bundle.
	 */
	public function register_bundles(): void {
		$asset_file = STOREENGINE_ASSETS_DIR_PATH . sprintf( 'build/instant-checkout-app.%s.asset.php', STOREENGINE_VERSION );
		$deps       = [ 'wp-element', 'wp-i18n' ];
		$ver        = STOREENGINE_VERSION;
		if ( is_readable( $asset_file ) ) {
			$asset = include $asset_file;
			$deps  = array_unique( array_merge( $deps, (array) ( $asset['dependencies'] ?? [] ) ) );
			$ver   = $asset['version'] ?? $ver;
		}

		wp_register_script(
			'storeengine-instant-checkout-app',
			STOREENGINE_ASSETS_URI . sprintf( 'build/instant-checkout-app.%s.js', STOREENGINE_VERSION ),
			$deps,
			$ver,
			true
		);
		wp_register_style(
			'storeengine-instant-checkout-app',
			STOREENGINE_ASSETS_URI . 'build/instant-checkout-app.css',
			[],
			STOREENGINE_VERSION
		);
	}

	/**
	 * Make storeUrl + REST nonce available to the launcher whenever the
	 * addon itself is active. The launcher reads
	 * `window.StoreEngineInstantCheckout.{storeUrl,nonce}` and stays dormant
	 * when the global is absent — so when the addon is off the bundled
	 * launcher loads but does not bind any click handlers.
	 *
	 * Note: this used to gate on the global `enable_direct_checkout` setting,
	 * which incorrectly tied the modal flow to the legacy direct-checkout
	 * flow. Instant Checkout is independent — it should light up whenever
	 * its addon is active, regardless of the legacy setting.
	 *
	 * `direct_checkout_attrs()` also primes the global at template-render time
	 * so a Buy Now button rendered through the shortcode (or any custom path
	 * outside the typical storefront surfaces) is still wired up correctly.
	 */
	public function localize_launcher_config(): void {
		if ( ! Helper::get_addon_active_status( 'instant-checkout' ) ) {
			return;
		}

		self::ensure_launcher_config();
	}

	/**
	 * Whether storefront templates should render a Buy Now button so the
	 * Instant Checkout launcher can decorate it for the modal flow.
	 *
	 * Returns true only when the addon is active AND the per-surface visibility
	 * toggle (`show_on_single` / `show_on_archive` in the addon settings) is on.
	 *
	 * Templates use this in addition to the global `enable_direct_checkout`
	 * gate so the modal button can appear independently of the legacy flow.
	 *
	 * @param string $surface 'single' or 'archive'.
	 */
	public static function should_show_modal_button( string $surface = 'single' ): bool {
		if ( ! Helper::get_addon_active_status( 'instant-checkout' ) ) {
			return false;
		}

		$key = 'archive' === $surface ? 'show_on_archive' : 'show_on_single';

		return (bool) Settings::init()->get_settings( $key, true );
	}

	/**
	 * Idempotently emit the launcher config global. Safe to call from any
	 * render path (shortcode, dynamic block) that prints a Quick Checkout
	 * launcher button outside the normal storefront surfaces.
	 */
	public static function ensure_launcher_config(): void {
		static $printed = false;
		if ( $printed ) {
			return;
		}
		$printed = true;

		$config = wp_json_encode( [
			'storeUrl' => home_url( '/' ),
			'rest_url' => esc_url_raw( rest_url() ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
		] );
		wp_add_inline_script(
			'storeengine-frontend-scripts',
			'window.StoreEngineInstantCheckout=Object.assign(window.StoreEngineInstantCheckout||{},' . $config . ');',
			'before'
		);

		// The launcher hand-off relies on the modal app + its stylesheet being
		// on the page. `register_bundles()` only registers them; without an
		// explicit enqueue here they never reach archive/single product pages
		// and the iframe handshake silently fails.
		wp_enqueue_script( 'storeengine-instant-checkout-app' );
		wp_enqueue_style( 'storeengine-instant-checkout-app' );
	}

	/**
	 * Build the data attributes that decorate a core Buy Now (`data-action="buy_now"`)
	 * button so the launcher can hijack the click and open the Quick Checkout modal
	 * instead of running the direct-checkout REST flow.
	 *
	 * Returns a leading-space-prefixed string (or empty) safe to echo inside an
	 * existing `<button …>` opening tag without further escaping.
	 *
	 * @param mixed $product Product instance (must expose get_id() and get_prices()).
	 */
	public static function direct_checkout_attrs( $product ): string {
		// PSR autoload makes this class loadable even when the addon is
		// deactivated, so `class_exists()` at the call site can't be the
		// gate. Check the live activation flag instead — when the addon is
		// off we must return empty so the legacy direct-checkout REST flow
		// (add to cart → redirect to checkout) keeps working unchanged.
		if ( ! Helper::get_addon_active_status( 'instant-checkout' ) ) {
			return '';
		}

		if ( ! $product || ! method_exists( $product, 'get_id' ) || ! method_exists( $product, 'get_prices' ) ) {
			return '';
		}

		$prices = $product->get_prices();
		if ( empty( $prices ) ) {
			return '';
		}

		$first_price_id = (int) reset( $prices )->get_id();

		// Whenever a Buy Now button is decorated for the modal launcher, make
		// sure the launcher config global is emitted on this page — otherwise
		// the bundled launcher stays dormant (per its own `isLauncherEnabled()`
		// gate) and the cart.js bail leaves the click without a handler.
		self::ensure_launcher_config();

		return sprintf(
			' data-instant-checkout="modal" data-product-id="%d" data-price-id="%d"',
			(int) $product->get_id(),
			$first_price_id
		);
	}

	/**
	 * Render the Quick Checkout (modal) launcher button for a product. Used by
	 * the shortcode's `mode="modal"` path. Returns false when the product has
	 * no purchasable price.
	 */
	public function print_button_for_product( int $product_id ): bool {
		$product = Helper::get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		$prices = method_exists( $product, 'get_prices' ) ? $product->get_prices() : [];
		if ( empty( $prices ) ) {
			return false;
		}
		$first_price_id = (int) reset( $prices )->get_id();

		// Shortcode modal mode runs on arbitrary pages (any post/page that drops
		// the [storeengine_instant_checkout mode="modal"] shortcode). Those
		// surfaces aren't covered by `localize_launcher_config`'s page gate, so
		// emit the config inline here to wake the bundled launcher up.
		self::ensure_launcher_config();

		$label = (string) Settings::init()->get_settings( 'button_label_modal', __( 'Quick Checkout', 'storeengine' ) );
		printf(
			'<div class="storeengine-add-to-cart-buttons"><button type="button" class="storeengine-btn storeengine-btn--preset-blue storeengine-instant-checkout storeengine-instant-checkout--modal" data-product-id="%1$d" data-price-id="%2$d">%3$s</button></div>',
			esc_attr( $product_id ),
			esc_attr( $first_price_id ),
			esc_html( $label )
		);

		return true;
	}
}
