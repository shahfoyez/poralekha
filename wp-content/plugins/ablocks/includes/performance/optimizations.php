<?php
namespace ABlocks\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;

/**
 * Performance Suite — platform-level optimizations ("WordPress bloat" removers).
 *
 * Every optimization is opt-in via the Performance settings tab (default off, so
 * existing sites are unchanged) and exposes an `ablocks/perf/{key}` filter for
 * programmatic control. See docs/PERFORMANCE-FEATURES-SPEC.md.
 */
class Optimizations {

	public static function init() {
		$self = new self();
		add_action( 'init', [ $self, 'apply' ] );
	}

	public function apply() {
		if ( $this->enabled( 'perf_disable_emojis' ) ) {
			$this->disable_emojis();
		}
		if ( $this->enabled( 'perf_disable_embeds' ) ) {
			$this->disable_embeds();
		}
		if ( $this->enabled( 'perf_disable_dashicons' ) ) {
			add_action( 'wp_enqueue_scripts', [ $this, 'disable_dashicons' ], 100 );
		}
		if ( $this->enabled( 'perf_disable_jquery_migrate' ) ) {
			// Run late on wp_enqueue_scripts (after everything registers, before
			// scripts print) — hooking wp_default_scripts from init is unreliable
			// because it can fire before this callback is registered.
			add_action( 'wp_enqueue_scripts', [ $this, 'remove_jquery_migrate' ], 100 );
		}
		if ( $this->enabled( 'perf_control_heartbeat' ) ) {
			add_filter( 'heartbeat_settings', [ $this, 'heartbeat_frequency' ] );
		}
	}

	private function enabled( $key ) {
		$value = (bool) Helper::get_settings( $key, false );
		return (bool) apply_filters( "ablocks/perf/{$key}", $value );
	}

	/**
	 * Remove the WordPress emoji detection script + styles.
	 */
	public function disable_emojis() {
		remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
		remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
		remove_action( 'wp_print_styles', 'print_emoji_styles' );
		remove_action( 'admin_print_styles', 'print_emoji_styles' );
		remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
		remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
		remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
		add_filter( 'emoji_svg_url', '__return_false' );
		add_filter(
			'tiny_mce_plugins',
			function ( $plugins ) {
				return is_array( $plugins ) ? array_diff( $plugins, [ 'wpemoji' ] ) : [];
			}
		);
	}

	/**
	 * Remove the wp-embed script and oEmbed discovery/host output on the frontend.
	 */
	public function disable_embeds() {
		remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
		remove_action( 'wp_head', 'wp_oembed_add_host_js' );
		add_action(
			'wp_footer',
			function () {
				wp_dequeue_script( 'wp-embed' );
			}
		);
	}

	/**
	 * Drop the front-end Dashicons stylesheet for logged-out visitors (kept for
	 * logged-in users so the admin bar still renders).
	 */
	public function disable_dashicons() {
		if ( ! is_user_logged_in() ) {
			wp_dequeue_style( 'dashicons' );
		}
	}

	/**
	 * Remove the legacy jquery-migrate dependency on the frontend.
	 */
	public function remove_jquery_migrate() {
		if ( is_admin() ) {
			return;
		}
		$scripts = wp_scripts();
		foreach ( [ 'jquery', 'jquery-core' ] as $handle ) {
			if ( isset( $scripts->registered[ $handle ] ) && ! empty( $scripts->registered[ $handle ]->deps ) ) {
				$scripts->registered[ $handle ]->deps = array_diff(
					$scripts->registered[ $handle ]->deps,
					[ 'jquery-migrate' ]
				);
			}
		}
		// Deregister so nothing can pull it in directly either.
		$scripts->remove( 'jquery-migrate' );
		$scripts->dequeue( 'jquery-migrate' );
	}

	/**
	 * Throttle the Heartbeat API to a configurable interval (WP clamps 15–300s).
	 */
	public function heartbeat_frequency( $settings ) {
		$freq = (int) Helper::get_settings( 'perf_heartbeat_frequency', 60 );
		$settings['interval'] = max( 15, min( 300, $freq ) );
		return $settings;
	}
}
