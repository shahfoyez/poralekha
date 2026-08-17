<?php
namespace ABlocks\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;

/**
 * Performance Suite — defer render-blocking scripts in the document <head>.
 *
 * Core dependencies such as `wp-hooks` and `wp-i18n` print in the head with no
 * loading strategy, so the browser must fetch and execute them before first
 * paint (they show up under PageSpeed "Render-blocking requests"). This module
 * adds `defer` to a filterable set of frontend script handles so they no longer
 * block rendering, while preserving execution order (defer scripts run in DOM
 * order, after parsing).
 *
 * Opt-in via `perf_defer_js`. Scoped to a curated handle list plus aBlocks'
 * own scripts; third-party JS is left untouched. Distinct from `perf_delay_js`,
 * which holds scripts until the first user interaction.
 */
class DeferJs {

	public static function init() {
		if ( is_admin() ) {
			return;
		}
		$enabled = (bool) apply_filters(
			'ablocks/perf/perf_defer_js',
			(bool) Helper::get_settings( 'perf_defer_js', false )
		);
		if ( ! $enabled ) {
			return;
		}
		// Don't defer for a logged-in editor previewing the frontend, so the
		// editing experience is unaffected; real visitors still get it.
		if ( is_user_logged_in() && current_user_can( 'edit_posts' )
			&& (bool) apply_filters( 'ablocks/perf/bypass_optimizations_for_editors', true ) ) {
			return;
		}
		$self = new self();
		add_filter( 'script_loader_tag', [ $self, 'defer_tag' ], 10, 3 );
	}

	/**
	 * Extra (non-aBlocks) handles to defer. Empty by default.
	 *
	 * We deliberately no longer defer the shared core utilities
	 * (`wp-hooks`/`wp-i18n`/`wp-dom-ready`/`wp-a11y`). Plain `defer` moves a head
	 * script's execution to AFTER parsing, i.e. after the non-deferred footer
	 * bundles that depend on it — so a bundle like StoreEngine's `frontend` (whose
	 * asset manifest lists `wp-hooks`/`wp-dom-ready`/`wp-i18n`) runs first and
	 * calls a not-yet-defined global (`wp.hooks.*` / `wp.domReady` / `wp.i18n.__`),
	 * throwing `… is not a function` and killing the storefront/checkout. Deferring
	 * a script that anything depends on is unsafe with this blunt string approach.
	 *
	 * aBlocks' own `ablocks-*` view scripts are leaf scripts (nothing depends on
	 * them, they expose no globals to inline code) so they remain safe to defer —
	 * see {@see should_defer}. Power users can still opt specific handles back in
	 * via this filter if their site's dependency graph allows it.
	 */
	private function handles() {
		return (array) apply_filters( 'ablocks/perf/defer_js_handles', [] );
	}

	/**
	 * Add `defer` to a matching script tag unless it already carries a loading
	 * strategy (defer/async) or is an inline script (no src).
	 */
	public function defer_tag( $tag, $handle, $src ) {
		if ( empty( $src ) ) {
			return $tag;
		}
		if ( ! $this->should_defer( $handle ) ) {
			return $tag;
		}
		if ( false !== strpos( $tag, ' defer' ) || false !== strpos( $tag, ' async' ) ) {
			return $tag;
		}
		// Leave scripts the delay-JS module has already rewritten alone.
		if ( false !== strpos( $tag, 'ablocks/delayed' ) ) {
			return $tag;
		}
		// Never defer a script that carries inline before/after data. WordPress
		// prints that inline as a plain (non-deferred) <script> right beside the
		// tag, so it executes during parse — before the deferred external file
		// runs. The canonical break is `wp-i18n`: its `wp-i18n-js-after` inline
		// calls `wp.i18n.setLocaleData()` before the deferred i18n.js defines
		// `wp.i18n`, throwing and taking every downstream `__()` call (checkout,
		// storefront bundles) down with it.
		if ( $this->has_inline_data( $handle ) ) {
			return $tag;
		}
		return preg_replace( '/^<script\s/', '<script defer ', $tag, 1 );
	}

	/**
	 * Whether the handle has inline `before`/`after` script data queued, which
	 * WordPress emits as non-deferrable inline <script> tags.
	 */
	private function has_inline_data( $handle ) {
		$wp_scripts = wp_scripts();
		if ( ! $wp_scripts ) {
			return false;
		}
		return (bool) $wp_scripts->get_data( $handle, 'before' )
			|| (bool) $wp_scripts->get_data( $handle, 'after' );
	}

	private function should_defer( $handle ) {
		if ( in_array( $handle, $this->handles(), true ) ) {
			return true;
		}
		// aBlocks-owned frontend scripts (library + per-block view scripts).
		return 0 === strpos( (string) $handle, 'ablocks-' );
	}
}
