<?php
namespace ABlocks\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\PageCache\Rules;
use ABlocks\Classes\PageCache\Store;

/**
 * Performance Suite — full-page HTML cache (write path).
 *
 * Captures the rendered page and stores it on disk so later requests can be
 * served without re-running WordPress, block parsing and theme rendering.
 *
 * This class owns the *write* side only. Serving happens far earlier in the
 * request than any plugin class is loaded — see the serve hook registered from
 * ablocks.php, the advanced-cache.php drop-in, and the nginx rule. All of them
 * defer to {@see Rules} so every tier makes the same decision.
 *
 * Opt-in via `perf_page_cache` (default off). See docs/PAGE-CACHE-PLAN.md.
 */
class PageCache {

	/**
	 * Marker appended to cached copies, so `curl` shows whether a response came
	 * from disk and when it was generated. Never present in the live response.
	 */
	const META_PREFIX = '<!-- ablocks-page-cache ';

	public static function init() {
		// Registered before the gates below: `purge` must stay available so a
		// site owner can clear files left behind after switching the feature off.
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			\ABlocks\Classes\PageCache\Cli::register();
		}

		// Maintenance is registered before the frontend gates below: pruning has
		// to keep running in admin and cron contexts, which is where WP-Cron
		// actually fires.
		\ABlocks\Classes\PageCache\Scheduler::init();

		// Also before the frontend gates: the toolbar has to appear both on the
		// site and in wp-admin, and its action handler runs on admin-post.php.
		\ABlocks\Classes\PageCache\AdminBar::init();

		if ( is_admin() ) {
			return;
		}
		if ( ! Rules::is_enabled() ) {
			return;
		}

		$self = new self();

		// Priority 0 on template_redirect: the earliest point at which the main
		// query has run (so is_404()/is_search() are meaningful) while still
		// being ahead of anything that renders. Starting first also makes this
		// the outermost output buffer, and nested buffers flush inner-first —
		// so the callback receives output after other plugins have transformed
		// it, which is what we want to store.
		add_action( 'template_redirect', [ $self, 'start_buffer' ], 0 );
	}

	/**
	 * Begin capturing output, unless the request is disqualified up front.
	 */
	public function start_buffer() {
		// Cheap superglobal-only gate. The expensive, WordPress-aware checks run
		// at write time, when the response is actually known.
		if ( Rules::should_bypass_request() ) {
			$this->debug_header( 'bypass', Rules::last_reason() );
			return;
		}

		ob_start( [ $this, 'maybe_cache' ] );
	}

	/**
	 * Output-buffer callback: decide, write, and always return the buffer intact.
	 *
	 * This runs during shutdown. It must never throw and must never alter the
	 * response — a caching layer that can break the page it is caching is worse
	 * than no caching layer.
	 *
	 * @param string $buffer Rendered output.
	 * @return string The same buffer, unmodified.
	 */
	public function maybe_cache( $buffer ) {
		if ( ! is_string( $buffer ) || '' === $buffer ) {
			return $buffer;
		}

		try {
			$reason = Rules::response_bypass_reason();
			if ( null === $reason ) {
				$reason = Rules::body_bypass_reason( $buffer );
			}

			if ( null !== $reason ) {
				$this->debug_header( 'bypass', $reason );
				return $buffer;
			}

			$file = Store::current_file_path();
			if ( null === $file ) {
				$this->debug_header( 'bypass', 'unrepresentable-path' );
				return $buffer;
			}

			$gzip    = (bool) \ABlocks\Helper::get_settings( 'perf_page_cache_gzip', true );
			$written = Store::write( $file, $buffer . $this->meta_comment(), $gzip );

			$this->debug_header( $written ? 'store' : 'bypass', $written ? null : 'write-failed' );
		} catch ( \Throwable $e ) {
			// Any failure here is a caching failure, not a page failure. Swallow
			// it, note it when debugging, and serve the page as normal.
			$this->debug_header( 'bypass', 'exception' );
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'aBlocks page cache: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
		}//end try

		return $buffer;
	}

	/**
	 * Trailing marker stored with the cached copy.
	 *
	 * Kept inside an HTML comment rather than a sidecar file: no extra inode per
	 * page, harmless when nginx serves the file directly, and it makes a cache
	 * hit self-identifying when someone curls the URL.
	 *
	 * @return string
	 */
	private function meta_comment() {
		$meta = [
			'url'     => home_url( Rules::request_path() ),
			'created' => gmdate( 'c' ),
			'version' => ABLOCKS_VERSION,
		];

		$json = wp_json_encode( $meta );
		if ( false === $json ) {
			return '';
		}

		// Defensive: a comment body containing "--" or ">" would break out of the
		// comment. None of the values above can, but the URL is request-derived.
		$json = str_replace( [ '--', '>' ], [ '- -', '' ], $json );

		return "\n" . self::META_PREFIX . $json . " -->\n";
	}

	/**
	 * Emit a diagnostic header while debugging.
	 *
	 * Only under WP_DEBUG: the reason string names cookie prefixes and query
	 * behaviour, which is useful to a developer and needless surface on a
	 * production response.
	 *
	 * @param string      $state  'store' or 'bypass'.
	 * @param string|null $reason Bypass reason, when applicable.
	 */
	private function debug_header( $state, $reason = null ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}
		if ( headers_sent() ) {
			return;
		}
		$value = $state . ( $reason ? '; ' . $reason : '' );
		header( 'X-ABlocks-Cache: ' . $value );
	}
}
