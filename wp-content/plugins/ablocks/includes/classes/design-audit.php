<?php
namespace ABlocks\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds blocks that carry a hand-picked colour or typography value instead of a
 * reference to the site's global presets.
 *
 * The design system lock (Settings → Editor Options) stops new ones from being
 * created, but deliberately leaves saved content alone so switching it on cannot
 * restyle a live site. This is the other half: a list of what is already there,
 * so a team can clean it up on purpose.
 *
 * @package ABlocks
 */
class DesignAudit {

	/**
	 * A typography attribute always carries these keys, whatever the block calls it.
	 */
	const TYPOGRAPHY_MARKERS = [ 'fontFamily', 'fontSizeUnit' ];

	/**
	 * Keys inside a typography object that only ever hold a unit, never a choice.
	 */
	const UNIT_SUFFIX = 'Unit';

	/**
	 * Walk parsed blocks and collect the attribute names holding custom values.
	 *
	 * @param array $blocks Parsed blocks (from parse_blocks()).
	 * @param array $found  Accumulator: [ 'typography' => [...], 'colors' => [...] ].
	 * @return array
	 */
	public static function scan_blocks( $blocks, $found = null ) {
		if ( null === $found ) {
			$found = [
				// A hand-picked typeface — what the default lock prevents.
				'families'   => [],
				// Other custom typography (size, spacing, weight). Allowed unless
				// the strict lock is on, so reported separately.
				'typography' => [],
				'colors'     => [],
			];
		}

		if ( ! is_array( $blocks ) ) {
			return $found;
		}

		foreach ( $blocks as $block ) {
			if ( empty( $block['blockName'] ) ) {
				continue;
			}

			if ( 0 === strpos( $block['blockName'], 'ablocks/' ) && ! empty( $block['attrs'] ) ) {
				$found = self::scan_attributes( $block['attrs'], $found );
			}

			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = self::scan_blocks( $block['innerBlocks'], $found );
			}
		}

		return $found;
	}

	/**
	 * Inspect one block's attributes.
	 *
	 * Attributes are checked at the top level rather than recursively, because the
	 * "is this compliant?" test needs the sibling `<name>Global` attribute — a
	 * typography object is only a violation when nothing global is referenced.
	 *
	 * @param array $attrs Block attributes.
	 * @param array $found Accumulator.
	 * @return array
	 */
	protected static function scan_attributes( $attrs, $found ) {
		foreach ( $attrs as $name => $value ) {
			// The global reference itself is never a violation.
			if ( self::ends_with( $name, 'Global' ) ) {
				continue;
			}

			$has_global = ! empty( $attrs[ $name . 'Global' ] );

			if ( self::is_typography( $value ) ) {
				if ( $has_global ) {
					continue;
				}
				if ( ! empty( $value['fontFamily'] ) && 'Default' !== $value['fontFamily'] ) {
					$found['families'][] = $name;
				} elseif ( self::has_custom_typography( $value ) ) {
					$found['typography'][] = $name;
				}
				continue;
			}

			if ( ! $has_global && self::is_custom_color( $value ) ) {
				$found['colors'][] = $name;
			}
		}

		return $found;
	}

	/**
	 * Whether an attribute value is a typography object.
	 *
	 * @param mixed $value Attribute value.
	 * @return bool
	 */
	public static function is_typography( $value ) {
		if ( ! is_array( $value ) ) {
			return false;
		}
		foreach ( self::TYPOGRAPHY_MARKERS as $marker ) {
			if ( ! array_key_exists( $marker, $value ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Whether a typography object holds anything an author actually chose.
	 *
	 * Unit keys default to 'px' whether or not anyone touched the control, so they
	 * would report every block on the site as a violation.
	 *
	 * @param array $value Typography attribute value.
	 * @return bool
	 */
	public static function has_custom_typography( $value ) {
		foreach ( $value as $key => $entry ) {
			if ( self::ends_with( $key, self::UNIT_SUFFIX )
				|| self::ends_with( $key, self::UNIT_SUFFIX . 'Tablet' )
				|| self::ends_with( $key, self::UNIT_SUFFIX . 'Mobile' ) ) {
				continue;
			}
			if ( '' !== $entry && null !== $entry && 'Default' !== $entry ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether a value is a literal colour rather than a preset reference.
	 *
	 * Global colours are stored as `var:global|color|<id>` and theme presets as
	 * `var:preset|color|<slug>`; both stay inside a design system.
	 *
	 * @param mixed $value Attribute value.
	 * @return bool
	 */
	public static function is_custom_color( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}
		$value = trim( $value );
		if ( '' === $value || 0 === strpos( $value, 'var:' ) || 0 === strpos( $value, 'var(' ) ) {
			return false;
		}
		return (bool) preg_match( '/^(#|rgba?\(|hsla?\(|(linear|radial|conic)-gradient\()/i', $value );
	}

	/**
	 * str_ends_with(), which is PHP 8 only and this plugin supports 7.4.
	 *
	 * @param string $haystack Subject.
	 * @param string $needle   Suffix.
	 * @return bool
	 */
	protected static function ends_with( $haystack, $needle ) {
		$length = strlen( $needle );
		return 0 !== $length && substr( $haystack, -$length ) === $needle;
	}
}
