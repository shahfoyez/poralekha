<?php
namespace ABlocks\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;
use ABlocks\Classes\FileUpload;

/**
 * Performance Suite — consolidate render-blocking inline CSS into cached files.
 *
 * Block themes emit a large amount of inline CSS into <head>: global styles,
 * the block library, and one <style> per core block used on the page. Measured
 * on Twenty Twenty-Four, that is ~43 KB across 20 blocks — around 40% of the
 * document — and none of it can be reused by the browser across navigations,
 * because it is embedded in the HTML rather than fetched as a file.
 *
 * This moves those blocks into content-addressed files under uploads so the
 * browser caches them once, and every cached HTML page on disk shrinks by the
 * same amount.
 *
 * ## Why per contiguous run, and not one bundle
 *
 * WordPress prints each stylesheet's <link> followed by its own inline <style>,
 * so the inline blocks are *interleaved* with <link> tags — on the reference
 * page they form 7 separate runs, not one. Concatenating all of them into a
 * single file would move CSS across those <link> boundaries and silently
 * reorder the cascade, which is exactly the class of bug that is impossible to
 * attribute later.
 *
 * So each maximal run of adjacent <style> blocks is replaced, in place, by one
 * <link> to a file containing exactly that run's CSS in its original order. The
 * resulting cascade is byte-for-byte equivalent to what WordPress emitted.
 *
 * Runs below a byte threshold are left inline: below roughly a couple of KB a
 * separate request costs more than the bytes saved, which is the same reasoning
 * behind the existing `perf_inline_css` option.
 *
 * File names are a hash of their contents, so they are immutable and never need
 * invalidating — a change in the CSS simply produces a different file. See
 * docs/PAGE-CACHE-PLAN.md.
 */
class StyleConsolidator {

	const DIR_NAME = 'consolidated-css';

	/**
	 * Default minimum run size, in bytes, worth externalising.
	 */
	const DEFAULT_MIN_BYTES = 2048;

	public static function init() {
		if ( is_admin() ) {
			return;
		}

		$enabled = (bool) apply_filters(
			'ablocks/perf/perf_consolidate_css',
			(bool) Helper::get_settings( 'perf_consolidate_css', false )
		);
		if ( ! $enabled ) {
			return;
		}

		// Never reshape the document for a logged-in editor previewing the
		// frontend — consistent with the rest of the Performance Suite.
		if ( is_user_logged_in() && current_user_can( 'edit_posts' )
			&& (bool) apply_filters( 'ablocks/perf/bypass_optimizations_for_editors', true ) ) {
			return;
		}

		$self = new self();

		// Priority 1 puts this buffer *inside* PageCache's (priority 0). Nested
		// buffers flush inner-first, so consolidation happens before the page
		// cache sees the output — meaning what gets written to disk is the
		// already-consolidated document, not the original.
		add_action( 'template_redirect', [ $self, 'start_buffer' ], 1 );
	}

	/**
	 * Begin capturing output.
	 */
	public function start_buffer() {
		if ( is_feed() || is_robots() || is_trackback() ) {
			return;
		}
		ob_start( [ $this, 'process' ] );
	}

	/**
	 * Rewrite the document head, replacing large runs of inline CSS with links.
	 *
	 * Must never throw and must always return usable HTML: a failure here should
	 * cost the optimisation, not the page.
	 *
	 * @param string $html Buffered output.
	 * @return string
	 */
	public function process( $html ) {
		if ( ! is_string( $html ) || '' === $html ) {
			return $html;
		}

		try {
			$head_end = stripos( $html, '</head>' );
			if ( false === $head_end ) {
				return $html;
			}

			$head = substr( $html, 0, $head_end );
			$rest = substr( $html, $head_end );

			$runs = $this->find_runs( $head );
			if ( empty( $runs ) ) {
				return $html;
			}

			$min = (int) apply_filters(
				'ablocks/perf/consolidate_css/min_bytes',
				(int) Helper::get_settings( 'perf_consolidate_css_min', self::DEFAULT_MIN_BYTES )
			);

			// Replace from the end backwards so earlier offsets stay valid.
			foreach ( array_reverse( $runs ) as $run ) {
				if ( strlen( $run['css'] ) < $min ) {
					continue;
				}
				$url = $this->store_css( $run['css'] );
				if ( null === $url ) {
					continue;
				}
				$tag  = '<link rel="stylesheet" href="' . esc_url( $url ) . '" media="all" />';
				$head = substr_replace( $head, $tag, $run['start'], $run['end'] - $run['start'] );
			}

			return $head . $rest;
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'aBlocks style consolidator: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return $html;
		}//end try
	}

	/**
	 * Locate maximal runs of adjacent <style> blocks within the head.
	 *
	 * Two blocks belong to the same run only when nothing but whitespace sits
	 * between them. Anything else — most importantly a <link> — ends the run,
	 * because merging across it would change the cascade.
	 *
	 * @param string $head Document head.
	 * @return array<int, array{start:int, end:int, css:string}>
	 */
	private function find_runs( $head ) {
		// Only tags carrying an id are considered: those are the ones WordPress
		// generates from wp_add_inline_style(). A bare <style> is more likely to
		// be hand-written critical CSS that a theme placed deliberately, and
		// moving it into a file would defeat its purpose.
		$pattern = '#<style[^>]*\bid=["\']([^"\']+)["\'][^>]*>(.*?)</style>#is';
		if ( ! preg_match_all( $pattern, $head, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER ) ) {
			return [];
		}

		$skip = (array) apply_filters( 'ablocks/perf/consolidate_css/skip_handles', [] );

		$runs     = [];
		$current  = null;
		$prev_end = null;

		foreach ( $matches as $match ) {
			$tag    = $match[0][0];
			$start  = $match[0][1];
			$end    = $start + strlen( $tag );
			$handle = $match[1][0];
			$css    = $match[2][0];

			$breaks_run = false;

			// A media query other than "all" changes when the CSS applies; it
			// cannot be folded in with unconditional rules.
			if ( preg_match( '#\bmedia=["\']([^"\']+)["\']#i', $tag, $media ) && 'all' !== strtolower( trim( $media[1] ) ) ) {
				$breaks_run = true;
			}
			if ( in_array( $handle, $skip, true ) ) {
				$breaks_run = true;
			}

			$adjacent = ( null !== $prev_end && '' === trim( substr( $head, $prev_end, $start - $prev_end ) ) );

			if ( $breaks_run ) {
				if ( null !== $current ) {
					$runs[]  = $current;
					$current = null;
				}
				$prev_end = $end;
				continue;
			}

			if ( null === $current || ! $adjacent ) {
				if ( null !== $current ) {
					$runs[] = $current;
				}
				$current = [
					'start' => $start,
					'end'   => $end,
					'css'   => $css,
				];
			} else {
				$current['end']  = $end;
				$current['css'] .= "\n" . $css;
			}

			$prev_end = $end;
		}//end foreach

		if ( null !== $current ) {
			$runs[] = $current;
		}

		return $runs;
	}

	/**
	 * Write CSS to a content-addressed file and return its URL.
	 *
	 * The name is a hash of the contents, so the file is immutable: changed CSS
	 * yields a different name rather than a stale file, and no invalidation
	 * hook is needed anywhere.
	 *
	 * @param string $css Stylesheet contents.
	 * @return string|null Public URL, or null on failure.
	 */
	private function store_css( $css ) {
		$hash = substr( sha1( $css ), 0, 20 );
		$dir  = self::base_dir();
		$file = $dir . '/' . $hash . '.css';

		if ( ! file_exists( $file ) ) {
			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				return null;
			}
			self::protect_dir();

			// Written through the page-cache store so both features share one
			// atomic write-then-rename implementation.
			if ( ! \ABlocks\Classes\PageCache\Store::put_file( $file, $css ) ) {
				return null;
			}
		}

		return self::base_url() . '/' . $hash . '.css';
	}

	/**
	 * Absolute path to the consolidated CSS directory.
	 *
	 * @return string
	 */
	public static function base_dir() {
		$upload = new FileUpload();
		return untrailingslashit( $upload->get_upload_dir() ) . '/' . self::DIR_NAME;
	}

	/**
	 * Public URL of the consolidated CSS directory.
	 *
	 * @return string
	 */
	public static function base_url() {
		$upload = new FileUpload();
		return untrailingslashit( $upload->get_upload_url() ) . '/' . self::DIR_NAME;
	}

	/**
	 * Drop an index.php guard so the directory cannot be browsed.
	 */
	private static function protect_dir() {
		$index = self::base_dir() . '/index.php';
		if ( ! file_exists( $index ) ) {
			\ABlocks\Classes\PageCache\Store::put_file( $index, "<?php\n// Silence is golden.\n" );
		}
	}

	/**
	 * Delete consolidated files untouched for longer than $days.
	 *
	 * Files are content-addressed and referenced by cached HTML, so they must
	 * never be purged alongside the page cache — doing so would leave cached
	 * pages pointing at stylesheets that no longer exist. Age-based collection
	 * is the safe way to reclaim space.
	 *
	 * @param int $days Age threshold.
	 * @return int Files removed.
	 */
	public static function prune( $days = 30 ) {
		$dir = self::base_dir();
		if ( ! is_dir( $dir ) ) {
			return 0;
		}
		$cutoff  = time() - ( max( 1, (int) $days ) * DAY_IN_SECONDS );
		$removed = 0;
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Return value is checked; a directory vanishing mid-sweep is normal.
		$entries = @scandir( $dir );
		if ( false === $entries ) {
			return 0;
		}
		foreach ( $entries as $entry ) {
			if ( '.css' !== substr( $entry, -4 ) ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if ( filemtime( $path ) < $cutoff ) {
				wp_delete_file( $path );
				$removed++;
			}
		}
		return $removed;
	}
}
