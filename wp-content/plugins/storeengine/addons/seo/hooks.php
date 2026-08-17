<?php
/**
 * SEO Hooks.
 *
 * Resolves which output channel is active (bridge vs standalone) and lets the
 * chosen provider register its own hooks. The REST/headless channel is wired
 * separately in the addon entry (always on).
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo;

use StoreEngine\Addons\Seo\Providers\ProviderDetector;
use StoreEngine\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {
	use Singleton;

	protected function __construct() {
		// Resolve the provider once the rest of the SEO plugins have had a
		// chance to load (plugins_loaded fires before init). Detection +
		// registration happen on `wp_loaded` so every 3rd-party plugin's
		// constants/classes are defined.
		add_action( 'wp_loaded', [ $this, 'register_provider' ], 5 );
	}

	public function register_provider(): void {
		$provider = ProviderDetector::resolve();

		if ( $provider ) {
			$provider->register();
		}
	}
}

// End of file hooks.php.
