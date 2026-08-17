<?php
namespace ABlocks\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Deduplicate generated per-instance CSS by merging rules that share an
 * identical declaration body into a single grouped selector
 * (`.a{X} .b{X}` -> `.a,.b{X}`).
 *
 * Safety: aBlocks generates per-instance CSS under a unique
 * `.ablocks-block-{id}` wrapper, so almost every selector is globally unique
 * and therefore cascade-independent (nothing else in the sheet targets those
 * elements). We ONLY merge rules whose selector is globally unique, and we emit
 * each merged group at the position of its first member — rules with a
 * duplicated selector (which could carry cascade order) and @media blocks are
 * left exactly in place. Runs on every generation (it produces byte-for-byte
 * equivalent styling); disable via the `ablocks/perf/dedupe_css` filter.
 */
class CssDedupe {

	public static function process( $css ) {
		$css = (string) $css;
		if ( '' === trim( $css ) ) {
			return $css;
		}
		return self::dedupe_scope( $css );
	}

	/**
	 * Dedupe one scope (the whole sheet, or the inside of an @media block).
	 * @media/at-rule segments are passed through (their inner rules are deduped
	 * recursively) and never merged with top-level rules.
	 */
	private static function dedupe_scope( $css ) {
		$segments = self::parse( $css );

		// Frequency of each selector among mergeable (non-at) rules.
		$freq = [];
		foreach ( $segments as $s ) {
			if ( 'rule' === $s['type'] ) {
				$freq[ $s['sel'] ] = ( $freq[ $s['sel'] ] ?? 0 ) + 1;
			}
		}

		// Group unique-selector rules by identical body; remember first position.
		$groups = [];
		foreach ( $segments as $i => $s ) {
			if ( 'rule' === $s['type'] && 1 === $freq[ $s['sel'] ] && '' !== $s['body'] ) {
				if ( ! isset( $groups[ $s['body'] ] ) ) {
					$groups[ $s['body'] ] = [ 'sels' => [], 'first' => $i ];
				}
				$groups[ $s['body'] ]['sels'][] = $s['sel'];
			}
		}

		$out = '';
		foreach ( $segments as $i => $s ) {
			if ( 'at' === $s['type'] ) {
				$out .= self::dedupe_at_rule( $s['raw'] );
				continue;
			}
			$mergeable = ( 1 === $freq[ $s['sel'] ] && '' !== $s['body'] );
			if ( $mergeable ) {
				$group = $groups[ $s['body'] ];
				if ( $group['first'] === $i ) {
					$out .= implode( ',', $group['sels'] ) . '{' . $s['body'] . '}';
				}
				// Non-first members are folded into the group above — skip.
			} else {
				$out .= $s['raw'];
			}
		}
		return $out;
	}

	/**
	 * Recurse into an @media/@supports block's body; leave keyframes/font-face
	 * untouched (order/steps matter).
	 */
	private static function dedupe_at_rule( $raw ) {
		$open = strpos( $raw, '{' );
		if ( false === $open ) {
			return $raw;
		}
		$prelude = substr( $raw, 0, $open );
		if ( 0 !== stripos( ltrim( $prelude ), '@media' ) && 0 !== stripos( ltrim( $prelude ), '@supports' ) ) {
			return $raw; // @keyframes, @font-face, etc.
		}
		$inner = substr( $raw, $open + 1, strrpos( $raw, '}' ) - $open - 1 );
		return $prelude . '{' . self::dedupe_scope( $inner ) . '}';
	}

	/**
	 * Parse CSS into ordered segments respecting brace depth. Each segment is
	 * either ['type'=>'rule','sel'=>..,'body'=>..,'raw'=>..] or
	 * ['type'=>'at','raw'=>..] for at-rules (prelude starts with '@').
	 */
	private static function parse( $css ) {
		$segments = [];
		$len      = strlen( $css );
		$i        = 0;
		$buffer   = '';

		while ( $i < $len ) {
			$char    = $css[ $i ];
			$buffer .= $char;

			if ( '{' === $char ) {
				$prelude = substr( $buffer, 0, -1 );
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
				$sel = trim( $prelude );
				if ( '' !== $sel && '@' === $sel[0] ) {
					$segments[] = [ 'type' => 'at', 'raw' => $buffer ];
				} else {
					$body       = trim( substr( $buffer, strlen( $prelude ) + 1, -1 ) );
					$segments[] = [ 'type' => 'rule', 'sel' => $sel, 'body' => $body, 'raw' => $buffer ];
				}
				$buffer = '';
				continue;
			}
			$i++;
		}
		if ( '' !== trim( $buffer ) ) {
			$segments[] = [ 'type' => 'at', 'raw' => $buffer ]; // stray text, pass through
		}
		return $segments;
	}
}
