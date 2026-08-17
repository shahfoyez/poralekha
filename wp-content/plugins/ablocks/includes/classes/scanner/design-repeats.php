<?php
namespace ABlocks\Classes\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\DesignAudit;
use ABlocks\Helper;

/**
 * Find design values typed in over and over instead of being made a global.
 *
 * {@see DesignAudit} answers "which blocks carry a hand-picked value?" — useful
 * for the design-system lock, which only cares whether a violation exists. This
 * answers a different question: *which value*, and *how often*.
 *
 * That difference is the whole point. One block with a custom colour is a
 * choice. The same hex typed into thirty blocks is an unnamed design token: it
 * behaves like a brand colour, it just cannot be changed in one place. Finding
 * those is what turns "you have custom colours" into "#3858e9 appears 30 times
 * and is not one of your presets — make it one".
 *
 * A value is only counted when the block has no matching `<name>Global`
 * sibling, so anything already wired to a preset is invisible here.
 */
class DesignRepeats {

	/**
	 * Times a value must appear before it is worth naming.
	 *
	 * Below this it is genuinely a one-off; above it, changing your mind means
	 * editing every occurrence by hand.
	 */
	const MIN_REPEATS = 3;

	/**
	 * Posts examined. Bounded because this parses block content.
	 */
	const MAX_POSTS = 300;

	/**
	 * Scan site content for repeated colours and typography.
	 *
	 * @return array{colors:array, fonts:array, posts:int}
	 */
	public static function scan() {
		$post_ids = get_posts(
			[
				'post_type'              => array_merge(
					[ 'post', 'page' ],
					\ABlocks\Classes\FontCollector::get_site_font_post_types()
				),
				'post_status'            => [ 'publish', 'draft', 'pending', 'private' ],
				'posts_per_page'         => self::MAX_POSTS,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		$colors = [];
		$fonts  = [];
		$seen   = 0;

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post instanceof \WP_Post || false === strpos( (string) $post->post_content, 'wp:ablocks' ) ) {
				continue;
			}

			$seen++;
			self::walk( parse_blocks( $post->post_content ), $colors, $fonts );
		}

		return [
			'colors' => self::rank( $colors ),
			'fonts'  => self::rank( $fonts ),
			'posts'  => $seen,
		];
	}

	/**
	 * Walk parsed blocks, tallying literal values.
	 *
	 * @param array $blocks Parsed blocks.
	 * @param array $colors Colour tally, by reference.
	 * @param array $fonts  Font tally, by reference.
	 */
	private static function walk( $blocks, &$colors, &$fonts ) {
		if ( ! is_array( $blocks ) ) {
			return;
		}

		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			if ( 0 === strpos( $block['blockName'], 'ablocks/' ) && ! empty( $block['attrs'] ) ) {
				self::tally( $block['attrs'], $colors, $fonts );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				self::walk( $block['innerBlocks'], $colors, $fonts );
			}
		}
	}

	/**
	 * Tally one block's attributes.
	 *
	 * @param array $attrs  Block attributes.
	 * @param array $colors Colour tally, by reference.
	 * @param array $fonts  Font tally, by reference.
	 */
	private static function tally( $attrs, &$colors, &$fonts ) {
		foreach ( $attrs as $name => $value ) {
			// A global reference is the thing we want people to have, not a finding.
			if ( self::ends_with( $name, 'Global' ) ) {
				continue;
			}
			if ( ! empty( $attrs[ $name . 'Global' ] ) ) {
				continue;
			}

			if ( DesignAudit::is_typography( $value ) ) {
				$key = self::font_key( $value );
				if ( $key ) {
					$fonts[ $key ] = isset( $fonts[ $key ] ) ? $fonts[ $key ] + 1 : 1;
				}
				continue;
			}

			if ( DesignAudit::is_custom_color( $value ) ) {
				$key = self::color_key( $value );
				if ( $key ) {
					$colors[ $key ] = isset( $colors[ $key ] ) ? $colors[ $key ] + 1 : 1;
				}
			}
		}//end foreach
	}

	/**
	 * Normalise a colour so the same colour written differently counts once.
	 *
	 * `#FFF`, `#ffffff` and `#FFFFFF` are one colour to a designer and three
	 * strings to a computer; counting them separately would hide exactly the
	 * repetition this is looking for.
	 *
	 * @param mixed $value Attribute value.
	 * @return string
	 */
	private static function color_key( $value ) {
		if ( ! is_string( $value ) ) {
			return '';
		}

		$value = strtolower( trim( $value ) );

		if ( preg_match( '/^#([0-9a-f]{3})$/', $value, $m ) ) {
			$value = '#' . $m[1][0] . $m[1][0] . $m[1][1] . $m[1][1] . $m[1][2] . $m[1][2];
		}

		// Whitespace inside rgb()/rgba() is meaningless, so remove it.
		$value = preg_replace( '/\s+/', '', $value );

		return $value;
	}

	/**
	 * Describe a typography object by the parts a person would recognise.
	 *
	 * Deliberately ignores units and responsive variants: two headings differing
	 * only in tablet line-height are the same decision, and splitting them would
	 * bury the repeat.
	 *
	 * @param array $value Typography attribute.
	 * @return string
	 */
	private static function font_key( $value ) {
		$family = isset( $value['fontFamily'] ) ? trim( (string) $value['fontFamily'] ) : '';
		$size   = isset( $value['fontSize'] ) ? trim( (string) $value['fontSize'] ) : '';
		$weight = isset( $value['weight'] ) ? trim( (string) $value['weight'] ) : '';

		if ( 'Default' === $family ) {
			$family = '';
		}

		$parts = array_filter( [ $family, $size ? $size . 'px' : '', $weight ] );

		return empty( $parts ) ? '' : implode( ' · ', $parts );
	}

	/**
	 * Keep values seen often enough to be worth naming, biggest first.
	 *
	 * @param array $tally value => count.
	 * @return array
	 */
	private static function rank( $tally ) {
		$min = (int) apply_filters( 'ablocks/scanner/min_repeats', self::MIN_REPEATS );

		$tally = array_filter(
			$tally,
			function ( $count ) use ( $min ) {
				return $count >= $min;
			}
		);

		arsort( $tally );

		$out = [];
		foreach ( array_slice( $tally, 0, 10, true ) as $value => $count ) {
			$out[] = [
				'value' => (string) $value,
				'count' => (int) $count,
			];
		}

		return $out;
	}

	/**
	 * Colours already defined as global presets, normalised for comparison.
	 *
	 * @return string[]
	 */
	public static function global_colors() {
		$presets = Helper::get_settings( 'global_color', [] );
		$out     = [];

		foreach ( (array) $presets as $preset ) {
			$value = is_array( $preset ) && isset( $preset['value'] ) ? $preset['value'] : null;
			if ( ! is_string( $value ) ) {
				continue;
			}
			$key = self::color_key( $value );
			if ( $key ) {
				$out[] = $key;
			}
		}

		return $out;
	}

	/**
	 * Does a string end with a suffix?
	 *
	 * @param string $haystack Subject.
	 * @param string $needle   Suffix.
	 * @return bool
	 */
	private static function ends_with( $haystack, $needle ) {
		$length = strlen( $needle );
		return 0 !== $length && substr( $haystack, -$length ) === $needle;
	}
}
