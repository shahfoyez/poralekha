<?php
/**
 * AioseoProvider — bridge into All in One SEO.
 *
 * AIOSEO's schema graph filter names have churned across v4 minors, so rather
 * than risk an invented/outdated filter we use the safe additive-JSON-LD
 * strategy: AIOSEO keeps owning <title>/canonical/OG, and we add our commerce
 * Product + BreadcrumbList nodes as a separate JSON-LD block plus a portable
 * noindex on transactional pages.
 *
 * TODO (Sprint 2): verify the current AIOSEO schema filter (e.g.
 * `aioseo_schema_output`) and merge into its graph instead of appending, so
 * there is a single graph node.
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AioseoProvider extends AbstractProvider {

	public static function is_active(): bool {
		return function_exists( 'aioseo' ) || defined( 'AIOSEO_VERSION' );
	}

	public function register(): void {
		// Additive commerce schema, after AIOSEO has rendered its own.
		add_action( 'wp_head', [ $this, 'emit_jsonld' ], 99 );

		// AIOSEO strips every `wp_robots` filter on `wp_head` at priority -1
		// (Robots::disableWpRobotsCore()), so AbstractProvider's portable
		// `wp_robots` fallback never survives to render. Force noindex through
		// AIOSEO's own robots filter instead.
		add_filter( 'aioseo_robots_meta', [ $this, 'filter_robots' ] );
	}

	/**
	 * @param array $attributes AIOSEO robots attributes, e.g. ['noindex' => '', 'nofollow' => '', ...].
	 *
	 * @return array
	 */
	public function filter_robots( $attributes ) {
		$data = $this->current_data();
		if ( is_array( $attributes ) && $data && ! $data->robots['index'] ) {
			$attributes['noindex'] = 'noindex';
		}

		return $attributes;
	}
}

// End of file aioseo-provider.php.
