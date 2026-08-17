<?php
namespace ABlocks\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;

/**
 * Determines which fonts a page actually uses by reading saved block
 * attributes (deterministic, design-time truth) instead of discovering them
 * by scanning generated CSS at render time.
 *
 * - Per-post fonts are stored in post meta and refreshed on save.
 * - Global typography fonts are stored in an option and refreshed when the
 *   plugin's global settings are saved.
 * - Self-hosting (local download) is the default loading model.
 *
 * See docs/FONT-MANAGEMENT-PLAN.md.
 */
class FontCollector {

	const POST_META_KEY      = '_ablocks_fonts';
	const GLOBAL_OPTION_NAME = 'ablocks_global_fonts';
	const SITE_OPTION_NAME   = 'ablocks_site_fonts';

	/**
	 * Recursively collect [ family => [weights] ] from a list of parsed blocks.
	 *
	 * @param array $blocks Parsed blocks (from parse_blocks()).
	 * @param array $fonts  Accumulator.
	 * @return array
	 */
	public static function collect_from_blocks( $blocks, &$fonts = [] ) {
		if ( ! is_array( $blocks ) ) {
			return $fonts;
		}

		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			// Reusable blocks / synced patterns referenced by id.
			if ( 'core/block' === $block['blockName'] && ! empty( $block['attrs']['ref'] ) ) {
				$ref_post = get_post( (int) $block['attrs']['ref'] );
				if ( $ref_post instanceof \WP_Post ) {
					self::collect_from_blocks( parse_blocks( $ref_post->post_content ), $fonts );
				}
			}

			if ( ! empty( $block['attrs'] ) ) {
				self::collect_from_attributes( $block['attrs'], $fonts );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				self::collect_from_blocks( $block['innerBlocks'], $fonts );
			}
		}

		return $fonts;
	}

	/**
	 * Walk an attribute tree and record any typography object that carries a
	 * non-empty fontFamily. Attribute-shape driven, so it works for every block
	 * without a per-block schema (typography attrs are objects with fontFamily).
	 *
	 * @param mixed $node  Attributes (array) or sub-node.
	 * @param array $fonts Accumulator.
	 */
	protected static function collect_from_attributes( $node, &$fonts ) {
		if ( ! is_array( $node ) ) {
			return;
		}

		if ( isset( $node['fontFamily'] ) && is_string( $node['fontFamily'] ) ) {
			$family = trim( $node['fontFamily'] );
			// 'Default' sets no font and 'inherit' defers to the theme; neither is
			// a downloadable family. A stored stack came from a custom value, so
			// its files are the author's responsibility.
			if ( '' !== $family && 'Default' !== $family && 'inherit' !== strtolower( $family ) && false === strpos( $family, ',' ) ) {
				$weight = '400';
				if ( isset( $node['weight'] ) && '' !== $node['weight'] && 'Default' !== $node['weight'] ) {
					$weight = (string) $node['weight'];
				}
				self::add( $fonts, $family, $weight );
			}
		}

		foreach ( $node as $value ) {
			if ( is_array( $value ) ) {
				self::collect_from_attributes( $value, $fonts );
			}
		}
	}

	/**
	 * Add a family/weight pair to the accumulator without duplicates.
	 */
	protected static function add( &$fonts, $family, $weight ) {
		if ( ! isset( $fonts[ $family ] ) ) {
			$fonts[ $family ] = [];
		}
		if ( ! in_array( $weight, $fonts[ $family ], true ) ) {
			$fonts[ $family ][] = $weight;
		}
	}

	/**
	 * Union two [family => weights] maps.
	 */
	public static function merge( $a, $b ) {
		$a = is_array( $a ) ? $a : [];
		$b = is_array( $b ) ? $b : [];
		foreach ( $b as $family => $weights ) {
			foreach ( (array) $weights as $weight ) {
				self::add( $a, $family, (string) $weight );
			}
		}
		return $a;
	}

	/* -------------------------------------------------------------------------
	 * Per-post
	 * ---------------------------------------------------------------------- */

	/**
	 * Collect fonts directly from a post's saved content.
	 */
	public static function collect_from_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return [];
		}
		return self::collect_from_blocks( parse_blocks( $post->post_content ) );
	}

	/**
	 * Recompute and store a post's fonts, then self-host them. Called on save.
	 */
	public static function save_post_fonts( $post_id ) {
		$fonts = self::collect_from_post( $post_id );
		update_post_meta( $post_id, self::POST_META_KEY, $fonts );
		self::download( $fonts );
		return $fonts;
	}

	/**
	 * Get a post's fonts, computing + caching on first access (lazy backfill for
	 * content saved before this system existed).
	 */
	public static function get_post_fonts( $post_id ) {
		$fonts = get_post_meta( $post_id, self::POST_META_KEY, true );
		if ( ! is_array( $fonts ) ) {
			$fonts = self::save_post_fonts( $post_id );
		}
		return is_array( $fonts ) ? $fonts : [];
	}

	/* -------------------------------------------------------------------------
	 * Global typography
	 * ---------------------------------------------------------------------- */

	/**
	 * Collect global typography fonts from the plugin's global settings.
	 */
	public static function collect_from_globals() {
		$fonts = [];
		$keys  = [
			'global_typography',
			'global_body_typography',
			'global_link_typography',
			'global_link_hover_typography',
			'global_h1_typography',
			'global_h2_typography',
			'global_h3_typography',
			'global_h4_typography',
			'global_h5_typography',
			'global_h6_typography',
		];
		foreach ( $keys as $key ) {
			$setting = Helper::get_settings( $key, [] );
			// Normalise stdClass → array so the recursive walk works uniformly.
			$normalised = json_decode( wp_json_encode( $setting ), true );
			self::collect_from_attributes( $normalised, $fonts );
		}
		return $fonts;
	}

	/**
	 * Recompute and store global fonts, then self-host. Called on settings save.
	 */
	public static function update_global_fonts() {
		$fonts = self::collect_from_globals();
		update_option( self::GLOBAL_OPTION_NAME, $fonts );
		self::download( $fonts );
		return $fonts;
	}

	/**
	 * Get global fonts, computing + caching on first access.
	 */
	public static function get_global_fonts() {
		$fonts = get_option( self::GLOBAL_OPTION_NAME, null );
		if ( ! is_array( $fonts ) ) {
			$fonts = self::update_global_fonts();
		}
		return is_array( $fonts ) ? $fonts : [];
	}

	/* -------------------------------------------------------------------------
	 * Site-wide content (templates, template parts, theme builder layouts)
	 * ---------------------------------------------------------------------- */

	/**
	 * Post types whose content is rendered outside the queried post: block theme
	 * templates and template parts (header/footer/archive markup) and aBlocks
	 * theme builder layouts.
	 *
	 * These are not `is_singular()` on the front end, so their fonts were never
	 * requested — a header built with aBlocks got `font-family: X` in CSS with no
	 * font-face rule and no Google stylesheet behind it.
	 *
	 * @return string[]
	 */
	public static function get_site_font_post_types() {
		return (array) apply_filters(
			'ablocks/site_font_post_types',
			[ 'wp_template', 'wp_template_part', 'ablocks_tb' ]
		);
	}

	/**
	 * Collect fonts from every template / template part / theme builder layout.
	 *
	 * Which template WordPress will render is not known while `wp_head` runs, so
	 * the union of them is used. The result is cached in an option and only
	 * recomputed when one of those posts is saved, so the front end pays a single
	 * option read. Self-hosted faces are lazy — declaring one the page does not
	 * use costs a line of CSS, not a download.
	 */
	public static function collect_from_site_content() {
		$fonts = [];

		$post_ids = get_posts(
			[
				'post_type'              => self::get_site_font_post_types(),
				'post_status'            => [ 'publish', 'draft', 'auto-draft', 'inherit' ],
				'posts_per_page'         => 200,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'suppress_filters'       => true,
			]
		);

		foreach ( $post_ids as $post_id ) {
			$fonts = self::merge( $fonts, self::collect_from_post( $post_id ) );
		}

		return $fonts;
	}

	/**
	 * Recompute and store the site-wide font set, then self-host it.
	 */
	public static function update_site_fonts() {
		$fonts = self::collect_from_site_content();
		update_option( self::SITE_OPTION_NAME, $fonts );
		self::download( $fonts );
		return $fonts;
	}

	/**
	 * Get the site-wide font set, computing + caching on first access.
	 *
	 * Self-hosting downloads font files over the network, which must not happen
	 * inside a visitor's request. On the front end the set is collected and cached
	 * immediately (so the fonts do load, remotely) and the download is handed to a
	 * one-off cron event.
	 */
	public static function get_site_fonts() {
		$fonts = get_option( self::SITE_OPTION_NAME, null );
		if ( is_array( $fonts ) ) {
			return $fonts;
		}

		if ( is_admin() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return self::update_site_fonts();
		}

		$fonts = self::collect_from_site_content();
		update_option( self::SITE_OPTION_NAME, $fonts );

		if ( ! empty( $fonts ) && ! wp_next_scheduled( 'ablocks/download_site_fonts' ) ) {
			wp_schedule_single_event( time() + 30, 'ablocks/download_site_fonts' );
		}

		return $fonts;
	}

	/**
	 * Drop the cached site-wide font set (a template/part/layout changed).
	 */
	public static function flush_site_fonts() {
		delete_option( self::SITE_OPTION_NAME );
	}

	/* -------------------------------------------------------------------------
	 * Frontend
	 * ---------------------------------------------------------------------- */

	/**
	 * The complete set of fonts the current request needs: global typography and
	 * site-wide content (templates, parts, theme builder layouts) on every page,
	 * plus the queried post's own fonts. Cached per request.
	 */
	public static function get_page_fonts() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$fonts = self::merge( self::get_global_fonts(), self::get_site_fonts() );

		if ( is_singular() ) {
			$post_id = get_queried_object_id();
			if ( $post_id ) {
				$fonts = self::merge( $fonts, self::get_post_fonts( $post_id ) );
			}
		}

		$cache = (array) apply_filters( 'ablocks/page_fonts', $fonts );
		return $cache;
	}

	/* -------------------------------------------------------------------------
	 * Self-hosting
	 * ---------------------------------------------------------------------- */

	/**
	 * Download any missing font files locally (self-host is the default model).
	 */
	protected static function download( $fonts ) {
		if ( empty( $fonts ) ) {
			return;
		}
		( new FontLoadLocally() )->process_font_queue( $fonts );
	}
}
