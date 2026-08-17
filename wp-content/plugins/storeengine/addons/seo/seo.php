<?php
/**
 * SEO Addon.
 *
 * Dual-mode SEO engine for StoreEngine. Computes the SEO "truth" (title,
 * description, canonical, robots, Open Graph / Twitter, Product + Breadcrumb
 * JSON-LD) once per request, then renders it through three channels:
 *
 *  - BRIDGE     : when a 3rd-party SEO plugin (Yoast / Rank Math / AIOSEO /
 *                 SEOPress) is active, graft commerce data onto ITS schema
 *                 graph and let it keep owning <title>/canonical/OG. No
 *                 duplicate head output.
 *  - STANDALONE : when no SEO plugin is active, we own wp_head — meta,
 *                 canonical, robots, OG/Twitter and JSON-LD.
 *  - HEADLESS   : a computed `seo` object is attached to the product REST
 *                 response for the Next.js storefront. Always on.
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo;

use StoreEngine\Addons\Seo\Rest\RestSeo;
use StoreEngine\Addons\Seo\Rest\RestSettings;
use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Seo extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'seo';

	public function define_constants() {
		define( 'STOREENGINE_SEO_VERSION', '1.0.0' );
		define( 'STOREENGINE_SEO_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'seo/' );
	}

	public function init_addon() {
		Settings::init();
		Hooks::init();
		Classes\MetaBox::init();
		RestSeo::init();
		RestSettings::init();
		Rest\RestProduct::init();
	}

	public function addon_activation_hook() {
		Settings::init()->save_default_settings();
	}
}

// End of file seo.php.
