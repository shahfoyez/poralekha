<?php
/**
 * Embeddable Checkout addon.
 *
 * Adds cross-origin embedding to the now-core Quick Checkout. External sites
 * load the slim `embed-sdk.js` from /se-embed/v1/sdk.js and authorise checkout
 * sessions via per-key origin allow-lists. Storefronts that don't need
 * external embedding can leave this addon disabled — the same-origin Quick
 * Checkout button + shortcode work without it.
 */

namespace StoreEngine\Addons\EmbeddableCheckout;

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EmbeddableCheckout extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'embeddable-checkout';

	public function define_constants() {
		define( 'STOREENGINE_EMBEDDABLE_CHECKOUT_VERSION', '1.0.0' );
		// Bump when the embed_keys table schema changes to trigger a re-sync
		// via the central Addons schema manager.
		define( 'STOREENGINE_EMBEDDABLE_CHECKOUT_DB_VERSION', '1.0.0' );
		define( 'STOREENGINE_EMBEDDABLE_CHECKOUT_PATH', STOREENGINE_ADDONS_DIR_PATH . 'embeddable-checkout/' );
		define( 'STOREENGINE_EMBEDDABLE_CHECKOUT_URL', plugins_url( 'storeengine/addons/embeddable-checkout/' ) );
	}

	public function init_addon() {
		// Hard dependency on Instant Checkout — without it the filter hooks this
		// addon registers have nothing to fire on. Surface a notice instead of
		// silently no-op'ing so the admin understands why nothing's happening.
		if ( ! Helper::get_addon_active_status( 'instant-checkout' ) ) {
			add_action( 'admin_notices', [ $this, 'render_missing_dependency_notice' ] );
			return;
		}

		Settings::init();
		Routes::init();
		Api\EmbedAuth::init();
		Api\EmbedKeys::init();
	}

	public function render_missing_dependency_notice(): void {
		echo '<div class="notice notice-warning"><p><strong>' .
			esc_html__( 'Embeddable Checkout requires the Instant Checkout addon.', 'storeengine' ) .
			'</strong> ' .
			esc_html__( 'Activate Instant Checkout under StoreEngine → Addons to enable cross-origin embedding.', 'storeengine' ) .
			'</p></div>';
	}

	public function get_db_version(): string {
		return STOREENGINE_EMBEDDABLE_CHECKOUT_DB_VERSION;
	}

	public function install_tables(): void {
		Models\EmbedKey::create_table();
	}

	public function addon_activation_hook() {
		Settings::init()->save_default_settings();
		Models\EmbedKey::create_table();
		try {
			Models\EmbedKey::find_or_create_system_key();
		} catch ( \Throwable $e ) {
			// Non-fatal; admin can create a key from settings.
		}
		// Force a rewrite flush so /se-embed/v1/ resolves immediately.
		delete_option( Routes::REWRITE_OPT );
	}

	public function addon_deactivation_hook() {
		flush_rewrite_rules( false );
	}
}
