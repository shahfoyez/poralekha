<?php
/**
 * EU VAT Assets
 *
 * Enqueues the checkout-side JS that POSTs to the validate AJAX endpoint and
 * swaps progress messages in/out of the field's status box.
 *
 * @package StoreEngine\Addons\EuVat
 */

namespace StoreEngine\Addons\EuVat\Classes;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Assets {

	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue' ] );
		add_filter( 'storeengine/frontend_scripts_data', [ $this, 'inject_js_data' ] );

		// Admin order editor VAT field. Priority 20 so the core admin bundle
		// (storeengine-admin-scripts) is already registered when we check.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin' ], 20 );
	}

	public function enqueue(): void {
		if ( ! Helper::is_checkout() ) {
			return;
		}

		wp_enqueue_style(
			'storeengine-eu-vat',
			STOREENGINE_EU_VAT_URL . 'assets/css/eu-vat.css',
			[],
			STOREENGINE_EU_VAT_VERSION
		);

		wp_enqueue_script(
			'storeengine-eu-vat',
			STOREENGINE_EU_VAT_URL . 'assets/js/eu-vat-checkout.js',
			[ 'storeengine-frontend-scripts' ],
			STOREENGINE_EU_VAT_VERSION,
			true
		);
	}

	/**
	 * Enqueue the admin order-editor VAT field. Scoped to StoreEngine admin
	 * screens by piggy-backing on the core admin bundle: if that isn't enqueued,
	 * we're not on a StoreEngine page and there's nothing to inject into.
	 */
	public function enqueue_admin(): void {
		if ( ! wp_script_is( 'storeengine-admin-scripts', 'enqueued' ) ) {
			return;
		}

		wp_enqueue_style(
			'storeengine-eu-vat',
			STOREENGINE_EU_VAT_URL . 'assets/css/eu-vat.css',
			[],
			STOREENGINE_EU_VAT_VERSION
		);

		wp_enqueue_script(
			'storeengine-eu-vat-admin-order',
			STOREENGINE_EU_VAT_URL . 'assets/js/eu-vat-admin-order.js',
			array_merge(
				[ 'storeengine-admin-scripts', 'wp-element', 'wp-hooks', 'wp-i18n' ],
				// Depend on the JS-translation companion (the same free handle the
				// core admin bundle uses) so w.org language packs apply to this
				// addon's strings too. Guarded because the companion is only
				// registered when its build/i18n-strings.js file is present.
				wp_script_is( 'storeengine-i18n', 'registered' ) ? [ 'storeengine-i18n' ] : []
			),
			STOREENGINE_EU_VAT_VERSION,
			true
		);
		wp_set_script_translations(
			'storeengine-eu-vat-admin-order',
			'storeengine',
			STOREENGINE_ROOT_DIR_PATH . 'i18n/languages'
		);
	}

	/**
	 * Inject our settings into StoreEngineGlobal so the JS doesn't need a
	 * separate localize call.
	 */
	public function inject_js_data( $data ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}
		$data['eu_vat'] = [
			'messages' => (array) Settings::get( 'messages', [] ),
			'required' => Settings::get( 'field_required', 'optional' ),
		];
		return $data;
	}
}
