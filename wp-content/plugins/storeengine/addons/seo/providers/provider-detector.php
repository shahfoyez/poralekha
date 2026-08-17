<?php
/**
 * ProviderDetector — pick the single active SEO provider for this request.
 *
 * Honors the `mode` setting:
 *   - standalone : always StandaloneProvider.
 *   - auto       : first active 3rd-party plugin in priority order, else
 *                  StandaloneProvider (own it when nothing else will).
 *   - bridge     : first active 3rd-party plugin in priority order, else
 *                  null — never falls back to StandaloneProvider. The
 *                  merchant explicitly asked for "never output standalone",
 *                  so with no bridge plugin active the addon stays silent
 *                  on the frontend (no title/meta/robots/schema output).
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo\Providers;

use StoreEngine\Addons\Seo\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProviderDetector {

	/**
	 * Bridge adapters in deterministic priority order.
	 *
	 * @return string[]
	 */
	protected static function bridge_providers(): array {
		return apply_filters( 'storeengine/seo/bridge_providers', [
			YoastProvider::class,
			RankMathProvider::class,
			AioseoProvider::class,
			SeopressProvider::class,
		] );
	}

	public static function resolve(): ?SeoProvider {
		$mode = Settings::init()->get_settings( 'mode', 'auto' );

		if ( 'standalone' === $mode ) {
			return new StandaloneProvider();
		}

		// auto / bridge: first active 3rd-party plugin wins.
		foreach ( self::bridge_providers() as $class ) {
			if ( is_callable( [ $class, 'is_active' ] ) && $class::is_active() ) {
				return new $class();
			}
		}

		// Bridge-only: no 3rd-party plugin found. The merchant opted out of
		// the standalone fallback, so the addon does nothing on the frontend.
		if ( 'bridge' === $mode ) {
			return null;
		}

		// auto: nothing else is handling SEO — own it.
		return new StandaloneProvider();
	}
}

// End of file provider-detector.php.
