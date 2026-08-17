<?php
/**
 * SeoProvider — one output channel for the SEO engine.
 *
 * A provider is either a bridge into a 3rd-party SEO plugin or the standalone
 * fallback. Exactly one is active per request (chosen by ProviderDetector).
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface SeoProvider {

	/**
	 * Whether the backing SEO plugin is active. Standalone always returns true.
	 */
	public static function is_active(): bool;

	/**
	 * Attach this provider's WordPress hooks.
	 */
	public function register(): void;

	/**
	 * 'bridge' or 'standalone' — used for diagnostics/telemetry.
	 */
	public function mode(): string;
}

// End of file provider-interface.php.
