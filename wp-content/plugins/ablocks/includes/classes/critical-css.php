<?php
namespace ABlocks\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Split combined page CSS into a "critical" subset (inlined in <head>) and a
 * deferred remainder (loaded non-blocking).
 *
 * The split is by CSS PROPERTY, not by selector. A rule is critical if it sets
 * anything that affects layout/box size (dimensions, spacing, position, flex/
 * grid, typography metrics, …); only rules that are purely cosmetic (color,
 * background, shadow, radius, transition, filter, opacity) or interaction
 * states (:hover/:focus/:active) and @keyframes are deferred. Because the
 * deferred CSS changes nothing that occupies space, applying it after paint
 * cannot cause a layout shift (CLS) — which the earlier selector-prefix split
 * did, since it deferred per-block spacing/sizing.
 *
 * Parsing respects brace depth so nested at-rules (@media/@supports) stay whole.
 * The heuristic is deliberately biased toward "critical": a rule we can't prove
 * is cosmetic stays inlined (safe but slightly less deferral).
 */
class CriticalCss {

	/**
	 * Property-name substrings that affect layout/box size. Over-inclusive on
	 * purpose (e.g. matches background-position) — false positives only cost a
	 * little deferral; a miss would cost CLS.
	 */
	const LAYOUT_TOKENS = [
		'width', 'height', 'margin', 'padding', 'border', 'inset', 'top', 'bottom', 'left', 'right',
		'display', 'position', 'float', 'clear', 'flex', 'grid', 'gap', 'align', 'justify', 'place', 'order',
		'font', 'spacing', 'text-align', 'text-indent', 'text-transform', 'white-space', 'vertical-align',
		'writing-mode', 'direction', 'box-sizing', 'column', 'overflow', 'list-style', 'aspect-ratio', 'tab-size',
	];

	/**
	 * @param string $css Minified combined CSS.
	 * @return array [ critical_css, rest_css ]
	 */
	public static function split( $css ) {
		$css = (string) $css;
		if ( '' === trim( $css ) ) {
			return [ '', '' ];
		}

		$critical = '';
		$rest     = '';
		$len      = strlen( $css );
		$i        = 0;
		$buffer   = '';

		while ( $i < $len ) {
			$char    = $css[ $i ];
			$buffer .= $char;

			if ( '{' === $char ) {
				$prelude = trim( substr( $buffer, 0, -1 ) );
				$depth   = 1;
				$i++;
				while ( $i < $len && $depth > 0 ) {
					$c       = $css[ $i ];
					$buffer .= $c;
					if ( '{' === $c ) {
						$depth++;
					} elseif ( '}' === $c ) {
						$depth--;
					}
					$i++;
				}

				if ( self::is_critical( $prelude, $buffer ) ) {
					$critical .= $buffer;
				} else {
					$rest .= $buffer;
				}
				$buffer = '';
				continue;
			}
			$i++;
		}
		$rest .= $buffer; // trailing text without a block (shouldn't happen)

		return [ $critical, $rest ];
	}

	private static function is_critical( $prelude, $rule_text ) {
		if ( '' === $prelude ) {
			return false;
		}

		// At-rules.
		if ( '@' === $prelude[0] ) {
			if ( 0 === stripos( $prelude, '@keyframes' ) || 0 === stripos( $prelude, '@-webkit-keyframes' ) ) {
				return false; // animations never affect initial layout
			}
			if ( 0 === stripos( $prelude, '@font-face' ) ) {
				return true; // fonts affect text metrics
			}
			if ( 0 === stripos( $prelude, '@media' ) || 0 === stripos( $prelude, '@supports' ) ) {
				return self::has_layout( $rule_text ); // critical only if it changes layout
			}
			return true; // @import/@charset/other — keep (safe)
		}

		// Interaction states can't shift the initial layout — defer them.
		if ( preg_match( '/:(hover|focus|active|focus-within|focus-visible)\b/i', $prelude ) ) {
			return false;
		}

		return self::has_layout( $rule_text );
	}

	/**
	 * True if the rule text declares any layout-affecting property.
	 */
	private static function has_layout( $rule_text ) {
		// Property names appear right after '{' or ';'. This avoids matching
		// pseudo-selectors and colons inside values (url(), data:, http:).
		if ( ! preg_match_all( '/[{;]\s*(-?[a-z][a-z-]*)\s*:/i', $rule_text, $m ) ) {
			return false;
		}
		foreach ( $m[1] as $prop ) {
			$prop = strtolower( $prop );
			// Props that merely contain a layout token but are purely cosmetic:
			// border-radius / *-color / *-style (incl. list-style, outline-*).
			if ( preg_match( '/(-radius|-color|-style)$/', $prop ) ) {
				continue;
			}
			foreach ( self::LAYOUT_TOKENS as $token ) {
				if ( false !== strpos( $prop, $token ) ) {
					return true;
				}
			}
		}
		return false;
	}
}
