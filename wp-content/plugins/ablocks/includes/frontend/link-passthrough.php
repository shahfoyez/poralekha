<?php
/**
 * Query Param Passthrough.
 *
 * Carries incoming affiliate / UTM query parameters (e.g. ?ref=xyz) onto
 * "tagged" links on the page so a visitor who lands with a tracking param keeps
 * it as they click through the site. It is fully inert unless a tracked param
 * exists in the current request URL AND a tagged link is present on the page.
 *
 * Because tagged links then carry the param forward, it persists across page
 * views without any cookie.
 *
 * @package ABlocks
 */

namespace ABlocks\Frontend;

use ABlocks\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class LinkPassthrough {

	public static function init() {
		$self = new self();
		add_action( 'wp_enqueue_scripts', [ $self, 'enqueue_assets' ] );
	}

	/**
	 * The class a link (or a wrapping block) must carry to receive the params.
	 *
	 * @return string
	 */
	public function get_link_class() : string {
		$class = (string) Helper::get_settings( 'param_passthrough_class', 'aff-link' );
		$class = trim( $class );
		// Keep it a safe, single CSS class token.
		$class = preg_replace( '/[^A-Za-z0-9_-]/', '', $class );

		return '' === $class ? 'aff-link' : $class;
	}

	/**
	 * Which links receive the params: 'class', 'all' or 'keyword'.
	 *
	 * @return string
	 */
	public function get_match_mode() : string {
		$mode = (string) Helper::get_settings( 'param_passthrough_match', 'class' );

		return in_array( $mode, [ 'class', 'all', 'keyword' ], true ) ? $mode : 'class';
	}

	/**
	 * Lower-cased list of words a link URL must contain in 'keyword' mode.
	 *
	 * @return string[]
	 */
	public function get_keywords() : array {
		$raw = (string) Helper::get_settings( 'param_passthrough_keyword', '' );

		$words = array_filter(
			array_map(
				static function ( $word ) {
					return strtolower( trim( $word ) );
				},
				explode( ',', $raw )
			)
		);

		return array_values( array_unique( $words ) );
	}

	/**
	 * Sanitized list of query-parameter keys to carry.
	 *
	 * @return string[]
	 */
	public function get_param_keys() : array {
		$raw = (string) Helper::get_settings(
			'param_passthrough_keys',
			'ref,utm_source,utm_medium,utm_campaign,utm_term,utm_content'
		);

		$keys = array_filter(
			array_map(
				static function ( $key ) {
					// Query-string keys are word-ish; strip anything unexpected.
					return preg_replace( '/[^A-Za-z0-9_\-.]/', '', trim( $key ) );
				},
				explode( ',', $raw )
			)
		);

		return array_values( array_unique( $keys ) );
	}

	public function enqueue_assets() {
		if ( ! (bool) Helper::get_settings( 'param_passthrough_enabled', false ) ) {
			return;
		}

		// Nothing to do in the admin / editor context.
		if ( is_admin() ) {
			return;
		}

		$keys = $this->get_param_keys();
		if ( empty( $keys ) ) {
			return;
		}

		$path = ABLOCKS_ASSETS_PATH . 'js/link-passthrough.js';
		if ( ! file_exists( $path ) ) {
			return;
		}

		// The payload is sub-1KB gzipped and already needs an inline config
		// block, so it's printed inline rather than as an extra HTTP request.
		// The readable source file stays the single source of truth.
		$handle = 'ablocks-link-passthrough';
		wp_register_script( $handle, false, [], ABLOCKS_VERSION, [ 'in_footer' => true ] );
		wp_enqueue_script( $handle );

		$config = [
			'keys'      => $keys,
			'mode'      => $this->get_match_mode(),
			'linkClass' => $this->get_link_class(),
			'keywords'  => $this->get_keywords(),
			'persist'   => (bool) Helper::get_settings( 'param_passthrough_persist', true ),
			'cookieDays' => max( 0, (int) Helper::get_settings( 'param_passthrough_cookie_days', 30 ) ),
		];

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$body = (string) file_get_contents( $path );

		wp_add_inline_script(
			$handle,
			'window.ABlocksLinkPassthrough = ' . wp_json_encode( $config ) . ";\n" . $body,
			'after'
		);
	}
}
