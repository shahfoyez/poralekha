<?php
namespace ABlocks\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds every `font-family` value aBlocks emits.
 *
 * A picker stores a bare family name ("Roboto") because that name is also the
 * key used to download/self-host the font (see FontCollector + FontLoadLocally).
 * The bare name must never reach CSS on its own: if the web font is slow, blocked
 * or missing, the browser falls back to its default serif and the design breaks.
 *
 * So the stack is assembled at emit time instead:
 *
 *     "Roboto", "Roboto Fallback", sans-serif
 *      |         |                  |
 *      |         |                  generic, from the family's category
 *      |         metric-adjusted local face - same footprint as the web font,
 *      |         so swapping in the real font shifts nothing (CLS)
 *      properly quoted family name
 *
 * @package ABlocks
 */
class FontStack {

	/**
	 * Suffix for the generated metric-adjusted fallback face.
	 */
	const FALLBACK_SUFFIX = ' Fallback';

	/**
	 * family => generic category. Loaded once per request.
	 *
	 * @var array|null
	 */
	protected static $categories = null;

	/**
	 * family => 'local face|size-adjust|ascent|descent|line-gap'.
	 *
	 * Stored pipe-joined rather than as nested arrays: 1,950 nested arrays cost
	 * ~2.5x more to parse on every request, and only the two or three families a
	 * page actually uses ever need splitting.
	 *
	 * @var array|null
	 */
	protected static $metrics = null;

	/**
	 * Memoised family => category lookups, so the case-insensitive fallback scan
	 * in get_category() runs at most once per family per request.
	 *
	 * @var array
	 */
	protected static $category_cache = [];

	/**
	 * Generic CSS families a fallback override may use verbatim.
	 *
	 * @var string[]
	 */
	const GENERIC_FAMILIES = [
		'serif',
		'sans-serif',
		'monospace',
		'cursive',
		'fantasy',
		'system-ui',
		'ui-serif',
		'ui-sans-serif',
		'ui-monospace',
		'ui-rounded',
		'math',
		'emoji',
		'fangsong',
	];

	/**
	 * Google's font categories mapped onto CSS generic families.
	 *
	 * @var array
	 */
	const CATEGORY_TO_GENERIC = [
		'sans-serif'  => 'sans-serif',
		'serif'       => 'serif',
		'monospace'   => 'monospace',
		'display'     => 'sans-serif',
		'handwriting' => 'cursive',
	];

	/**
	 * Category lookup table.
	 *
	 * @return array
	 */
	public static function categories() {
		if ( null === self::$categories ) {
			$file = ABLOCKS_BLOCKS_DIR_PATH . 'fonts.php';
			self::$categories = is_readable( $file ) ? (array) include $file : [];
		}
		return self::$categories;
	}

	/**
	 * Metric table for the generated fallback faces.
	 *
	 * @return array
	 */
	public static function metrics() {
		if ( null === self::$metrics ) {
			$file = ABLOCKS_BLOCKS_DIR_PATH . 'font-metrics.php';
			self::$metrics = is_readable( $file ) ? (array) include $file : [];
		}
		return self::$metrics;
	}

	/**
	 * A family's category ('serif', 'display', …), or '' when unknown.
	 *
	 * @param string $family Font family name.
	 * @return string
	 */
	public static function get_category( $family ) {
		if ( isset( self::$category_cache[ $family ] ) ) {
			return self::$category_cache[ $family ];
		}

		$categories = self::categories();
		$category   = isset( $categories[ $family ] ) ? $categories[ $family ] : '';

		if ( '' === $category ) {
			// Tolerate case drift between a saved value and the catalog. This walks
			// ~2,000 entries, so the result is memoised — build() is called once per
			// font-family declaration and a page can have dozens.
			foreach ( $categories as $name => $value ) {
				if ( 0 === strcasecmp( $name, $family ) ) {
					$category = $value;
					break;
				}
			}
		}

		$category = (string) apply_filters( 'ablocks/font_category', $category, $family );

		self::$category_cache[ $family ] = $category;

		return $category;
	}

	/**
	 * Whether metric-adjusted fallback faces are switched on.
	 *
	 * @return bool
	 */
	public static function metric_fallback_enabled() {
		return (bool) apply_filters(
			'ablocks/font_metric_fallback_enabled',
			\ABlocks\Helper::get_settings( 'font_metric_fallback', true )
		);
	}

	/**
	 * Name of the generated metric-adjusted face for a family.
	 *
	 * @param string $family Font family name.
	 * @return string
	 */
	public static function fallback_face_name( $family ) {
		return $family . self::FALLBACK_SUFFIX;
	}

	/**
	 * Whether a family has metrics to build a fallback face from.
	 *
	 * @param string $family Font family name.
	 * @return bool
	 */
	public static function has_metrics( $family ) {
		$metrics = self::metrics();
		return isset( $metrics[ $family ] );
	}

	/**
	 * Quote a family name when CSS requires it.
	 *
	 * Unquoted family names must be a sequence of CSS identifiers, so anything
	 * with a digit-leading word ("42dot Sans") or punctuation has to be quoted.
	 * Quoting on any whitespace as well keeps the output unambiguous.
	 *
	 * @param string $family Font family name.
	 * @return string
	 */
	public static function quote( $family ) {
		$family = trim( (string) $family );
		if ( '' === $family ) {
			return '';
		}

		$needs_quotes = (bool) preg_match( '/[^a-zA-Z0-9_-]/', $family )
			|| (bool) preg_match( '/(^|\s)[0-9-]/', $family );

		if ( ! $needs_quotes ) {
			return $family;
		}

		return '"' . str_replace( [ '\\', '"' ], [ '\\\\', '\"' ], $family ) . '"';
	}

	/**
	 * Resolve the generic (or custom) tail of the stack.
	 *
	 * @param string $family   Font family name.
	 * @param string $fallback Author-supplied override: a generic keyword, a full
	 *                         custom stack, or '' to derive it from the category.
	 * @return string
	 */
	public static function resolve_fallback( $family, $fallback = '' ) {
		$fallback = trim( (string) $fallback );

		if ( '' !== $fallback ) {
			// A generic keyword, or a hand-written stack the author owns.
			if ( in_array( strtolower( $fallback ), self::GENERIC_FAMILIES, true ) ) {
				return strtolower( $fallback );
			}
			if ( 'none' === strtolower( $fallback ) ) {
				return '';
			}
			return $fallback;
		}

		$category = self::get_category( $family );

		if ( isset( self::CATEGORY_TO_GENERIC[ $category ] ) ) {
			$generic = self::CATEGORY_TO_GENERIC[ $category ];
		} else {
			// Unknown family (an uploaded or theme font). Fall back to the site-wide
			// default from Global Settings → Typography.
			$generic = trim( (string) \ABlocks\Helper::get_settings( 'global_font_family_fallback', 'sans-serif' ) );
			if ( '' === $generic ) {
				$generic = 'sans-serif';
			}
			if ( in_array( strtolower( $generic ), self::GENERIC_FAMILIES, true ) ) {
				$generic = strtolower( $generic );
			}
		}

		return (string) apply_filters( 'ablocks/font_generic_fallback', $generic, $family, $category );
	}

	/**
	 * Build the full font-family value for a stored family name.
	 *
	 * @param string $family   Family name as stored on the block attribute.
	 * @param string $fallback Optional author override for the generic tail.
	 * @return string Complete CSS value, or '' when nothing should be emitted.
	 */
	public static function build( $family, $fallback = '' ) {
		$family = trim( (string) $family );

		// "Default" means "don't set a font" - emitting it produced the invalid
		// declaration `font-family: Default`. Declaring nothing is also how the
		// theme's font wins: font-family inherits, so the theme's rules cascade
		// untouched. That is why the picker has no separate "inherit" entry.
		if ( '' === $family || 'Default' === $family ) {
			return '';
		}

		// Not offered in the picker (it takes the *parent's* font, which overrides
		// a theme's own h2/button rules), but honoured if set via a filter or left
		// on content from an earlier build.
		if ( 'inherit' === strtolower( $family ) ) {
			return 'inherit';
		}

		// Already a stack (legacy content, or a custom value typed by hand), or a
		// functional value such as var(--ablocks-heading-font-family). Either way
		// it is already a complete declaration - never quote or append to it.
		if ( false !== strpos( $family, ',' ) || false !== strpos( $family, '(' ) ) {
			return $family;
		}

		$stack = [ self::quote( $family ) ];

		if ( self::metric_fallback_enabled() && self::has_metrics( $family ) ) {
			$stack[] = self::quote( self::fallback_face_name( $family ) );
		}

		$generic = self::resolve_fallback( $family, $fallback );
		if ( '' !== $generic ) {
			$stack[] = $generic;
		}

		return (string) apply_filters(
			'ablocks/font_stack',
			implode( ', ', $stack ),
			$family,
			$fallback
		);
	}

	/**
	 * @font-face rules for the metric-adjusted fallback faces of the given families.
	 *
	 * The face is a local() system font re-scaled to the web font's own metrics,
	 * so a line of text occupies the same box before and after the web font
	 * arrives. Nothing is downloaded - `local()` only ever matches installed fonts.
	 *
	 * @param array $families List of family names (or a family => weights map).
	 * @return string CSS, empty when there is nothing to emit.
	 */
	public static function get_fallback_face_css( $families ) {
		if ( empty( $families ) || ! self::metric_fallback_enabled() ) {
			return '';
		}

		// Accept both [ 'Roboto' => ['400'] ] and [ 'Roboto' ].
		$names = isset( $families[0] )
			? array_map( 'strval', array_values( $families ) )
			: array_map( 'strval', array_keys( $families ) );

		$metrics = self::metrics();
		$css     = '';

		foreach ( array_unique( $names ) as $family ) {
			if ( ! isset( $metrics[ $family ] ) ) {
				continue;
			}

			$face = explode( '|', $metrics[ $family ] );
			if ( 5 !== count( $face ) ) {
				continue;
			}
			list( $local, $size_adjust, $ascent, $descent, $line_gap ) = $face;

			$css .= sprintf(
				'@font-face{font-family:%s;src:local("%s"),local("%s");size-adjust:%s;ascent-override:%s;descent-override:%s;line-gap-override:%s;font-display:swap;}',
				self::quote( self::fallback_face_name( $family ) ),
				$local,
				self::local_alias( $local ),
				$size_adjust,
				$ascent,
				$descent,
				$line_gap
			);
		}

		return $css;
	}

	/**
	 * Font families WordPress itself already knows about: the theme's own
	 * theme.json fonts plus anything installed/activated through the Font Library
	 * (WP 6.5+). Core prints their @font-face rules, so aBlocks only has to offer
	 * them in the picker — nothing to download or self-host.
	 *
	 * @return array List of [ 'label' => 'Inter', 'value' => '"Inter", sans-serif', 'source' => 'theme' ].
	 */
	public static function get_theme_font_families() {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$cache = [];

		if ( ! class_exists( '\WP_Theme_JSON_Resolver' ) ) {
			return $cache;
		}

		$settings = \WP_Theme_JSON_Resolver::get_merged_data()->get_settings();
		$families = isset( $settings['typography']['fontFamilies'] )
			? $settings['typography']['fontFamilies']
			: [];

		// WP 6.6+ keys presets by origin ( theme / custom / default ); older
		// versions hand back a flat list.
		if ( isset( $families[0] ) ) {
			$families = [ 'theme' => $families ];
		}

		$seen = [];
		foreach ( (array) $families as $origin => $presets ) {
			foreach ( (array) $presets as $preset ) {
				if ( empty( $preset['fontFamily'] ) ) {
					continue;
				}
				$value = $preset['fontFamily'];
				if ( isset( $seen[ $value ] ) ) {
					continue;
				}
				$seen[ $value ] = true;

				$label = ! empty( $preset['name'] ) ? $preset['name'] : $value;

				$cache[] = [
					'label'  => $label,
					'value'  => $value,
					'source' => (string) $origin,
				];
			}
		}

		return (array) apply_filters( 'ablocks/theme_font_families', $cache );
	}

	/**
	 * Second local() candidate, so the face still resolves where the primary
	 * donor font is not installed (Arial is absent on most Linux boxes).
	 *
	 * @param string $local Primary local face name.
	 * @return string
	 */
	protected static function local_alias( $local ) {
		switch ( $local ) {
			case 'Arial':
				return 'Helvetica Neue';
			case 'Times New Roman':
				return 'Liberation Serif';
			case 'Courier New':
				return 'Liberation Mono';
			default:
				return $local;
		}
	}
}
