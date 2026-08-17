<?php
/**
 * SeopressProvider — bridge into SEOPress.
 *
 * SEOPress has no stable single schema-merge filter for a custom CPT, so we
 * use the additive-JSON-LD strategy: SEOPress keeps owning <title>/canonical/
 * OG, and we add our commerce Product + BreadcrumbList nodes as a separate
 * JSON-LD block plus a portable noindex on transactional pages.
 *
 * TODO (Sprint 2): verify SEOPress's JSON-LD / titles filters
 * (`seopress_titles_title`, `seopress_titles_desc`, `seopress_schemas_*`) for
 * a tighter integration.
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo\Providers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SeopressProvider extends AbstractProvider {

	public static function is_active(): bool {
		return defined( 'SEOPRESS_VERSION' ) || function_exists( 'seopress_init' );
	}

	public function register(): void {
		add_action( 'wp_head', [ $this, 'emit_jsonld' ], 99 );

		// SEOPress renders its own robots meta tag independently of the
		// portable `wp_robots` filter AbstractProvider::apply_core_robots()
		// relies on. Forcing noindex through `wp_robots` there merely adds a
		// second, contradictory tag (SEOPress keeps that filter's array empty
		// on its own, so nothing renders from it until we push data in) —
		// force it through SEOPress's own robots-attributes filter instead.
		add_filter( 'seopress_titles_robots_attrs', [ $this, 'filter_robots' ] );
	}

	/**
	 * @param array $attrs SEOPress robots directive strings, e.g. ['index, follow', 'max-snippet:-1, max-image-preview:large, max-video-preview:-1'].
	 *
	 * @return array
	 */
	public function filter_robots( $attrs ) {
		$data = $this->current_data();
		if ( ! is_array( $attrs ) || ! $data || $data->robots['index'] ) {
			return $attrs;
		}

		// Drop any directive string asserting indexability, then force noindex.
		$attrs = array_filter( $attrs, static function ( $attr ) {
			return false === strpos( (string) $attr, 'index' ) || false !== strpos( (string) $attr, 'noindex' );
		} );

		array_unshift( $attrs, 'noindex' );

		return array_values( $attrs );
	}
}

// End of file seopress-provider.php.
