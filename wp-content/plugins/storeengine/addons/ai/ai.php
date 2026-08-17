<?php
/**
 * AI Addon (free core).
 *
 * Adds AI-assisted authoring to StoreEngine on top of WordPress's native AI
 * Client (WP 7.0 Connectors). Free scope:
 *
 *  - Product content generation — title, description, short description and image
 *    alt text, generated from the product editor sidebar.
 *  - Abilities — registers read-only StoreEngine operations as WP Abilities so
 *    external AI agents can query the store.
 *  - The SEO addon's meta generation already runs through the shared
 *    {@see \StoreEngine\AI\AiService}; this addon owns product copy.
 *
 * Bulk generation, multi-variant copy and write-capable abilities live in the
 * pro `ai` augmentation under the SAME slug (one toggle), mirroring how
 * membership/subscription/multi-vendor span free + pro.
 *
 * @since StoreEngine 1.7.0
 */

namespace StoreEngine\Addons\Ai;

use StoreEngine\Addons\Ai\Rest\RestProduct;
use StoreEngine\Addons\Ai\Rest\RestSettings;
use StoreEngine\Addons\Ai\Rest\RestStatus;
use StoreEngine\Addons\Ai\Rest\RestTerm;
use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Ai extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'ai';

	public function define_constants() {
		define( 'STOREENGINE_AI_VERSION', '1.0.0' );
		define( 'STOREENGINE_AI_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'ai/' );
	}

	public function init_addon() {
		Settings::init();
		Hooks::init();
		Abilities::init();

		RestStatus::init();
		RestSettings::init();
		RestProduct::init();
		RestTerm::init();
	}

	public function addon_activation_hook() {
		Settings::init()->save_default_settings();
	}
}

// End of file ai.php.
