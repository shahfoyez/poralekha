<?php
namespace ABlocks\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;

/**
 * Performance Suite — mobile touch-target sizing.
 *
 * PageSpeed / accessibility flags interactive elements that are smaller than
 * ~48px or too close together on mobile ("Touch targets do not have sufficient
 * size or spacing"). This prints a small, mobile-only stylesheet that gives
 * aBlocks buttons and linked icons a minimum tap size.
 *
 * Opt-in via `perf_touch_targets` (default off — it nudges visual sizing, so
 * it's promoted only after the site owner confirms it fits their design). The
 * target size is filterable.
 */
class TouchTargets {

	public static function init() {
		if ( is_admin() ) {
			return;
		}
		$enabled = (bool) apply_filters(
			'ablocks/perf/perf_touch_targets',
			(bool) Helper::get_settings( 'perf_touch_targets', false )
		);
		if ( ! $enabled ) {
			return;
		}
		add_action( 'wp_head', [ new self(), 'print_css' ], 8 );
	}

	public function print_css() {
		$min      = (int) apply_filters( 'ablocks/perf/touch_target_min_px', 44 );
		$min      = max( 24, min( 96, $min ) );
		$max_view = (int) apply_filters( 'ablocks/perf/touch_target_breakpoint_px', 600 );

		$css = sprintf(
			'@media (max-width:%1$dpx){' .
				'.ablocks-button{min-height:%2$dpx;display:inline-flex;align-items:center;justify-content:center;}' .
				'a:has(>.ablocks-icon-wrap),a:has(>.ablocks-image-icon){min-width:%2$dpx;min-height:%2$dpx;display:inline-flex;align-items:center;justify-content:center;}' .
			'}',
			$max_view,
			$min
		);

		echo '<style id="ablocks-touch-targets">' . $css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}
