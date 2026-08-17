<?php
namespace ABlocks\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Phase 2: hand font emission to WordPress core.
 *
 * Locally-hosted fonts the current page needs are injected into theme.json
 * (settings.typography.fontFamilies). Core's wp_print_font_faces() then prints
 * the @font-face rules automatically — no custom enqueue/discovery.
 *
 * Any font not yet self-hosted falls back to a Google Fonts <link>.
 *
 * Requires WP 6.5+ (the plugin requires 6.8). See docs/FONT-MANAGEMENT-PLAN.md.
 */
class CoreFontRegistry {

	public static function init() {
		$self = new self();
		// Inject the page's local fonts so core prints their @font-face. Register
		// on a single origin to avoid emitting duplicate @font-face rules.
		add_filter( 'wp_theme_json_data_theme', [ $self, 'register_page_fonts' ] );
		// Remote fallback for fonts not yet downloaded locally.
		add_action( 'wp_enqueue_scripts', [ $self, 'enqueue_remote_fallback' ], 999 );
		// Metric-adjusted local faces, so the pre-swap render is the right size.
		add_action( 'wp_enqueue_scripts', [ $self, 'enqueue_fallback_faces' ], 5 );
	}

	/**
	 * Emit the metric-adjusted fallback @font-face rules for this page's fonts.
	 *
	 * These reference locally installed system fonts via local() — nothing is
	 * downloaded. Their only job is to make the fallback render occupy exactly the
	 * space the real font will, so the swap doesn't shift the layout.
	 */
	public function enqueue_fallback_faces() {
		$fonts = $this->page_fonts();
		if ( empty( $fonts ) ) {
			return;
		}

		$css = FontStack::get_fallback_face_css( $fonts );
		if ( '' === $css ) {
			return;
		}

		wp_register_style( 'ablocks-font-fallbacks', false, [], ABLOCKS_VERSION );
		wp_enqueue_style( 'ablocks-font-fallbacks' );
		wp_add_inline_style( 'ablocks-font-fallbacks', $css );
	}

	/**
	 * Page fonts, resolved once per request.
	 */
	protected function page_fonts() {
		return FontCollector::get_page_fonts();
	}

	/**
	 * Register the current page's locally-hosted fonts into theme.json data so
	 * core emits their @font-face declarations.
	 *
	 * @param \WP_Theme_JSON_Data $theme_json
	 * @return \WP_Theme_JSON_Data
	 */
	public function register_page_fonts( $theme_json ) {
		if ( is_admin() || ! is_object( $theme_json ) || ! method_exists( $theme_json, 'get_data' ) ) {
			return $theme_json;
		}

		$fonts = $this->page_fonts();
		if ( empty( $fonts ) ) {
			return $theme_json;
		}

		$loader   = new FontLoadLocally();
		$families = [];

		foreach ( $fonts as $family => $weights ) {
			$local_faces = $loader->get_local_font_faces( $family, (array) $weights );
			if ( empty( $local_faces ) ) {
				continue; // not self-hosted yet → handled by the remote fallback
			}

			$font_faces = [];
			foreach ( $local_faces as $face ) {
				$font_faces[] = [
					'fontFamily'  => $family,
					'fontStyle'   => 'normal',
					'fontWeight'  => $face['weight'],
					'fontDisplay' => 'swap',
					'src'         => [ $face['src'] ],
				];
			}

			$families[] = [
				'fontFamily' => $family,
				'name'       => $family,
				'slug'       => sanitize_title( $family ),
				'fontFace'   => $font_faces,
			];
		}

		if ( empty( $families ) ) {
			return $theme_json;
		}

		$data = $theme_json->get_data();

		// WP 6.6+ keys typography.fontFamilies by origin ( [ 'theme' => [ …presets… ] ] );
		// older versions used a flat preset list. Append our families to the 'theme'
		// origin bucket. Merging a flat list onto the origin-keyed shape (the previous
		// behaviour) produced a malformed preset with no 'slug', tripping core's
		// get_settings_values_by_slug()/get_settings_slugs() (undefined array key "slug").
		$existing = $data['settings']['typography']['fontFamilies'] ?? [];
		if ( ! is_array( $existing ) ) {
			$existing = [];
		}
		if ( isset( $existing[0] ) ) { // flat list → normalise to the theme origin
			$existing = [ 'theme' => $existing ];
		}
		$existing['theme'] = array_merge(
			( isset( $existing['theme'] ) && is_array( $existing['theme'] ) ) ? $existing['theme'] : [],
			$families
		);
		$data['settings']['typography']['fontFamilies'] = $existing;

		// update_with() replaces the theme origin's preset list wholesale, so $existing
		// must already contain the theme's own fonts alongside ours.
		return $theme_json->update_with( $data );
	}

	/**
	 * Enqueue a Google Fonts <link> for any page font not available locally.
	 */
	public function enqueue_remote_fallback() {
		$fonts = $this->page_fonts();
		if ( empty( $fonts ) ) {
			return;
		}

		$missing = ( new FontLoadLocally() )->get_missing( $fonts );
		if ( empty( $missing ) ) {
			return;
		}

		$family_strings = [];
		foreach ( $missing as $family => $weights ) {
			$weights = array_unique( array_map( 'strval', (array) $weights ) );
			sort( $weights );
			$encoded          = str_replace( ' ', '+', $family );
			$family_strings[] = ! empty( $weights ) ? $encoded . ':wght@' . implode( ';', $weights ) : $encoded;
		}

		$url = 'https://fonts.googleapis.com/css2?family=' . implode( '&family=', $family_strings ) . '&display=swap';
		wp_enqueue_style( 'ablocks-google-fonts', esc_url( $url ), [], null );

		// Warm up the font connections early so the render-blocking stylesheet +
		// its font files resolve faster (helps FCP/LCP). Only added when a remote
		// font is actually loaded.
		add_filter( 'wp_resource_hints', [ $this, 'preconnect_google_fonts' ], 10, 2 );
	}

	/**
	 * Add preconnect hints for the Google Fonts origins.
	 *
	 * @param array  $hints    Resource hint URLs/attributes for this relation.
	 * @param string $relation Current relation type (preconnect, dns-prefetch, …).
	 * @return array
	 */
	public function preconnect_google_fonts( $hints, $relation ) {
		if ( 'preconnect' !== $relation ) {
			return $hints;
		}
		$hints[] = [ 'href' => 'https://fonts.googleapis.com' ];
		$hints[] = [
			'href'        => 'https://fonts.gstatic.com',
			'crossorigin' => 'anonymous',
		];
		return $hints;
	}
}
