<?php
namespace ABlocks\Classes\PageCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Page Cache — WP-CLI commands.
 *
 * Lives here rather than in includes/dev-cli/ because that directory is listed
 * in .distignore and never ships; these commands are for site owners, not just
 * for development.
 *
 * Registered even when the cache is switched off, so `purge` can still clear
 * files left behind after someone disables the feature.
 */
class Cli {

	/**
	 * Register the command with WP-CLI.
	 */
	public static function register() {
		\WP_CLI::add_command( 'ablocks cache', __CLASS__ );
	}

	/**
	 * Show cache configuration and disk usage.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp ablocks cache status
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function status( $args, $assoc_args ) {
		$stats = Store::stats();

		$query_args = Rules::allowed_query_args();
		$query_args = empty( $query_args ) ? '(none)' : implode( ', ', $query_args );

		// size_format() returns false for a non-numeric input and '0 B' is a
		// clearer empty-cache reading than a blank cell.
		$size = size_format( $stats['bytes'], 2 );
		$size = ( false === $size ) ? '0 B' : $size;

		$next_prune = wp_next_scheduled( Scheduler::PRUNE_HOOK );

		$rows = [
			[
				'key'   => 'enabled',
				'value' => Rules::is_enabled() ? 'yes' : 'no',
			],
			[
				'key'   => 'scope',
				'value' => (string) \ABlocks\Helper::get_settings( 'perf_page_cache_scope', 'all' ),
			],
			[
				'key'   => 'ttl_hours',
				'value' => (string) (int) \ABlocks\Helper::get_settings( 'perf_page_cache_ttl', 0 ),
			],
			[
				'key'   => 'gzip',
				'value' => \ABlocks\Helper::get_settings( 'perf_page_cache_gzip', true ) ? 'yes' : 'no',
			],
			[
				'key'   => 'mobile_variant',
				'value' => \ABlocks\Helper::get_settings( 'perf_page_cache_mobile_variant', false ) ? 'yes' : 'no',
			],
			[
				'key'   => 'allowed_query_args',
				'value' => $query_args,
			],
			[
				'key'   => 'warm_backend',
				'value' => Scheduler::warm_backend(),
			],
			[
				'key'   => 'next_prune',
				'value' => $next_prune
					? gmdate( 'Y-m-d H:i:s', $next_prune ) . ' UTC'
					: 'not scheduled',
			],
			[
				'key'   => 'directory',
				'value' => Store::base_dir(),
			],
			[
				'key'   => 'cached_pages',
				'value' => (string) $stats['pages'],
			],
			[
				'key'   => 'files_on_disk',
				'value' => (string) $stats['files'],
			],
			[
				'key'   => 'size',
				'value' => $size,
			],
		];

		$format = isset( $assoc_args['format'] ) ? $assoc_args['format'] : 'table';
		\WP_CLI\Utils\format_items( $format, $rows, [ 'key', 'value' ] );
	}

	/**
	 * Delete cached pages.
	 *
	 * The URL is a positional argument rather than `--url=`, because WP-CLI
	 * reserves `--url` as a global parameter for selecting a site in a multisite
	 * network; it never reaches the command, so a `--url=` flag here would be
	 * silently ignored and quietly purge everything instead.
	 *
	 * ## OPTIONS
	 *
	 * [<url>]
	 * : Purge a single URL instead of the whole cache.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ablocks cache purge
	 *     wp ablocks cache purge https://example.com/pricing/
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function purge( $args, $assoc_args ) {
		if ( ! empty( $args[0] ) ) {
			$url = esc_url_raw( $args[0] );
			if ( Store::delete_url( $url ) ) {
				\WP_CLI::success( sprintf( 'Purged %s', $url ) );
			} else {
				\WP_CLI::warning( sprintf( 'Nothing cached for %s', $url ) );
			}
			return;
		}

		$removed = Store::flush();
		\WP_CLI::success( sprintf( 'Purged the page cache (%d files removed).', $removed ) );
	}

	/**
	 * Queue public URLs to be rendered into the cache.
	 *
	 * Requests are spaced apart rather than fired at once — warming exists to
	 * spare visitors a render, and doing it in one burst would create the load
	 * spike the cache is meant to prevent.
	 *
	 * ## OPTIONS
	 *
	 * [--limit=<count>]
	 * : How many URLs to queue.
	 * ---
	 * default: 100
	 * ---
	 *
	 * [--now]
	 * : Fetch immediately in this process instead of queueing.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ablocks cache warm
	 *     wp ablocks cache warm --limit=500
	 *     wp ablocks cache warm --limit=20 --now
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function warm( $args, $assoc_args ) {
		if ( ! Rules::is_enabled() ) {
			\WP_CLI::warning( 'The page cache is switched off, so warming would write nothing.' );
			return;
		}

		$limit = isset( $assoc_args['limit'] ) ? (int) $assoc_args['limit'] : 100;
		$urls  = Scheduler::warmable_urls( $limit );

		if ( empty( $urls ) ) {
			\WP_CLI::warning( 'No public URLs found to warm.' );
			return;
		}

		if ( isset( $assoc_args['now'] ) ) {
			$progress = \WP_CLI\Utils\make_progress_bar( 'Warming', count( $urls ) );
			foreach ( $urls as $url ) {
				Scheduler::warm_url( $url );
				$progress->tick();
			}
			$progress->finish();
			\WP_CLI::success( sprintf( 'Requested %d URL(s).', count( $urls ) ) );
			return;
		}

		$queued = Scheduler::queue_warm( $urls );

		\WP_CLI::success(
			sprintf(
				'Queued %d URL(s) via %s. They warm gradually in the background.',
				$queued,
				Scheduler::warm_backend()
			)
		);
	}

	/**
	 * Delete cached pages older than the configured TTL.
	 *
	 * ## OPTIONS
	 *
	 * [--ttl=<hours>]
	 * : Override the configured TTL for this run.
	 *
	 * ## EXAMPLES
	 *
	 *     wp ablocks cache prune
	 *     wp ablocks cache prune --ttl=24
	 *
	 * @param array $args       Positional arguments.
	 * @param array $assoc_args Named arguments.
	 */
	public function prune( $args, $assoc_args ) {
		$ttl = isset( $assoc_args['ttl'] ) ? (int) $assoc_args['ttl'] : null;

		if ( ( null === $ttl && 0 >= (int) \ABlocks\Helper::get_settings( 'perf_page_cache_ttl', 0 ) ) || 0 === $ttl ) {
			\WP_CLI::warning( 'No TTL configured — nothing expires. Set perf_page_cache_ttl or pass --ttl.' );
			return;
		}

		$removed = Store::prune( $ttl );
		\WP_CLI::success( sprintf( '%d expired file(s) removed.', $removed ) );
	}
}
