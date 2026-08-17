<?php
namespace ABlocks\Classes\PageCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;
use ABlocks\Performance\StyleConsolidator;

/**
 * Page Cache — background maintenance.
 *
 * Two jobs need to happen without anyone watching:
 *
 * 1. Expiring cached pages once the configured TTL passes. Without this the
 *    "Expire after (hours)" setting is decorative — nothing prunes, and entries
 *    live until something invalidates them.
 * 2. Reclaiming consolidated stylesheets. They are content-addressed and
 *    referenced by cached HTML, so they cannot be purged alongside pages; they
 *    have to age out instead, or the directory grows for the life of the site.
 *
 * ## On Action Scheduler
 *
 * Recurring maintenance uses WP-Cron. It is built in, needs no dependency, and
 * the work is two small sweeps — Action Scheduler would buy nothing here, and
 * requiring a second plugin to make a setting work is a bad trade for a free
 * plugin that must run standalone.
 *
 * Bulk warming is different: it is a queue of potentially thousands of URLs that
 * wants throttling, retries and progress. Action Scheduler is genuinely better
 * at that, so it is used *when present* — it commonly already is, bundled inside
 * WooCommerce, StoreEngine and others — and WP-Cron is used otherwise. Detection
 * is by function, never by plugin: a bundled copy is as good as an active
 * plugin, and Action Scheduler is designed to be loaded that way.
 */
class Scheduler {

	const PRUNE_HOOK = 'ablocks/page_cache/prune';
	const WARM_HOOK  = 'ablocks/page_cache/warm_url';
	const CRAWL_HOOK = 'ablocks/page_cache/crawl';
	const SKIP_OPTION = 'ablocks_crawl_skip';

	/**
	 * Days after which an unused consolidated stylesheet is reclaimed.
	 */
	const CSS_MAX_AGE_DAYS = 30;

	/**
	 * Seconds between warm requests, so warming never stampedes the site it is
	 * trying to make fast.
	 */
	const WARM_SPACING = 5;

	/**
	 * URLs the background crawler queues per run.
	 */
	const CRAWL_BATCH = 20;

	public static function init() {
		add_action( self::PRUNE_HOOK, [ __CLASS__, 'run_prune' ] );
		add_action( self::WARM_HOOK, [ __CLASS__, 'warm_url' ], 10, 1 );
		add_action( self::CRAWL_HOOK, [ __CLASS__, 'run_crawl' ] );
		add_action( 'init', [ __CLASS__, 'ensure_events' ] );
	}

	/**
	 * Keep the recurring jobs registered, and drop them when unused.
	 */
	public static function ensure_events() {
		$wanted = Rules::is_enabled() || (bool) Helper::get_settings( 'perf_consolidate_css', false );
		$next   = wp_next_scheduled( self::PRUNE_HOOK );

		if ( $wanted && ! $next ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', self::PRUNE_HOOK );
		} elseif ( ! $wanted && $next ) {
			// Leave no orphan cron entry behind when both features are off.
			wp_unschedule_event( $next, self::PRUNE_HOOK );
		}

		$crawl_wanted = Rules::is_enabled() && (bool) Helper::get_settings( 'perf_page_cache_crawler', false );
		$crawl_next   = wp_next_scheduled( self::CRAWL_HOOK );

		if ( $crawl_wanted && ! $crawl_next ) {
			wp_schedule_event( time() + ( 5 * MINUTE_IN_SECONDS ), 'hourly', self::CRAWL_HOOK );
		} elseif ( ! $crawl_wanted && $crawl_next ) {
			wp_unschedule_event( $crawl_next, self::CRAWL_HOOK );
		}
	}

	/**
	 * Top up the cache with pages that are not in it yet.
	 *
	 * Deliberately incremental rather than a full crawl. It queues a small batch
	 * of *uncached* URLs each hour, so a large site fills in gradually instead of
	 * the site being hit with thousands of renders at once — and once the cache
	 * is warm the job finds nothing to do and costs almost nothing.
	 *
	 * This is also why nothing auto-crawls after a site-wide purge. Rebuilding
	 * every page the instant a header is edited is a self-inflicted load spike at
	 * exactly the wrong moment; letting the crawler refill it over the following
	 * hours, while visitors organically warm the popular pages, is gentler and
	 * reaches the same place.
	 *
	 * @return int URLs queued.
	 */
	public static function run_crawl() {
		if ( ! Rules::is_enabled() ) {
			return 0;
		}

		$batch = (int) apply_filters(
			'ablocks/perf/page_cache/crawl_batch',
			(int) Helper::get_settings( 'perf_page_cache_crawl_batch', self::CRAWL_BATCH )
		);
		$batch = max( 1, min( 200, $batch ) );

		// Pull a wider slice than the batch so that, once the head of the list is
		// cached, the crawler can still find uncached pages further down instead
		// of re-checking the same first N every hour.
		$candidates = self::warmable_urls( $batch * 10 );
		$skip       = self::skip_list();
		$now        = time();
		$missing    = [];

		foreach ( $candidates as $url ) {
			if ( self::is_cached( $url ) ) {
				// Cached now, so any earlier failures are irrelevant.
				unset( $skip[ $url ] );
				continue;
			}

			// Some URLs can never be cached — a login or account page sets a
			// cookie, so the write path correctly refuses it. Without a memory of
			// that, the crawler re-requests those same pages every single hour
			// forever, which is pure waste on a job designed to run unattended.
			if ( isset( $skip[ $url ]['until'] ) && $skip[ $url ]['until'] > $now ) {
				continue;
			}

			$missing[] = $url;

			if ( count( $missing ) >= $batch ) {
				break;
			}
		}//end foreach

		if ( empty( $missing ) ) {
			self::save_skip_list( $skip );
			return 0;
		}

		foreach ( $missing as $url ) {
			$attempts = isset( $skip[ $url ]['n'] ) ? (int) $skip[ $url ]['n'] + 1 : 1;

			$skip[ $url ] = [
				'n'     => $attempts,
				// Three attempts is enough to distinguish "not visited yet" from
				// "will never cache"; after that, back off for a day so a page
				// that becomes cacheable is still picked up eventually.
				'until' => $attempts >= 3 ? $now + DAY_IN_SECONDS : 0,
			];
		}

		self::save_skip_list( $skip );

		return self::queue_warm( $missing, 0 );
	}

	/**
	 * Per-URL crawl attempt record.
	 *
	 * @return array<string, array{n:int, until:int}>
	 */
	private static function skip_list() {
		$list = get_option( self::SKIP_OPTION, [] );
		return is_array( $list ) ? $list : [];
	}

	/**
	 * Persist the crawl attempt record, bounded.
	 *
	 * @param array $list Attempt record.
	 */
	private static function save_skip_list( $list ) {
		// Bounded so a site with a very large sitemap cannot grow this option
		// without limit. Entries still in backoff are kept in preference to ones
		// that have simply not been reached yet.
		if ( count( $list ) > 500 ) {
			uasort(
				$list,
				function ( $a, $b ) {
					return ( isset( $b['until'] ) ? $b['until'] : 0 ) <=> ( isset( $a['until'] ) ? $a['until'] : 0 );
				}
			);
			$list = array_slice( $list, 0, 500, true );
		}

		update_option( self::SKIP_OPTION, $list, false );
	}

	/**
	 * Does a cache entry already exist for a URL?
	 *
	 * @param string $url URL to check.
	 * @return bool
	 */
	public static function is_cached( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return false;
		}

		$file = Store::file_path(
			$parts['host'],
			isset( $parts['path'] ) ? $parts['path'] : '/',
			''
		);

		return $file && file_exists( $file );
	}

	/**
	 * Remove every scheduled job. Called on deactivation.
	 */
	public static function clear_events() {
		wp_clear_scheduled_hook( self::PRUNE_HOOK );

		if ( self::has_action_scheduler() ) {
			as_unschedule_all_actions( self::WARM_HOOK );
		}
	}

	/**
	 * Expire stale cached pages and reclaim unused stylesheets.
	 *
	 * @return array{pages:int, css:int}
	 */
	public static function run_prune() {
		$pages = Store::prune();

		$css_days = (int) apply_filters( 'ablocks/perf/page_cache/css_max_age_days', self::CSS_MAX_AGE_DAYS );
		$css      = StyleConsolidator::prune( $css_days );

		return [
			'pages' => (int) $pages,
			'css'   => (int) $css,
		];
	}

	/**
	 * Queue a set of URLs to be rendered into the cache.
	 *
	 * Requests are spaced rather than fired at once. Warming exists to spare
	 * visitors a render; doing it in one burst would hand the site the exact
	 * load spike the cache is meant to prevent.
	 *
	 * @param string[] $urls  URLs to warm.
	 * @param int      $delay Seconds before the first request.
	 * @return int Number of jobs queued.
	 */
	public static function queue_warm( array $urls, $delay = 10 ) {
		$urls = array_values( array_unique( array_filter( array_map( 'esc_url_raw', $urls ) ) ) );
		if ( empty( $urls ) ) {
			return 0;
		}

		$spacing = max( 1, (int) apply_filters( 'ablocks/perf/page_cache/warm_spacing', self::WARM_SPACING ) );
		$queued  = 0;

		foreach ( $urls as $index => $url ) {
			$when = time() + (int) $delay + ( $index * $spacing );

			if ( self::has_action_scheduler() ) {
				as_schedule_single_action( $when, self::WARM_HOOK, [ $url ], 'ablocks' );
			} else {
				// WP-Cron dedupes on hook + args, so a URL already queued is not
				// queued twice — which is the behaviour we want anyway.
				if ( ! wp_next_scheduled( self::WARM_HOOK, [ $url ] ) ) {
					wp_schedule_single_event( $when, self::WARM_HOOK, [ $url ] );
				}
			}

			$queued++;
		}

		return $queued;
	}

	/**
	 * Render one URL so its cache entry exists before a visitor asks for it.
	 *
	 * A plain anonymous GET: the write path decides on its own whether the
	 * response is cacheable, so warming needs no special privileges and cannot
	 * cache something a visitor would not have.
	 *
	 * @param string $url URL to fetch.
	 */
	public static function warm_url( $url ) {
		$url = esc_url_raw( (string) $url );
		if ( empty( $url ) ) {
			return;
		}

		// Same-origin only. This runs from a queue whose contents could be
		// filtered by other code, and it must never become a way to make the
		// server issue arbitrary outbound requests.
		$home = wp_parse_url( home_url() );
		$want = wp_parse_url( $url );
		if ( empty( $want['host'] ) || empty( $home['host'] ) || strtolower( $want['host'] ) !== strtolower( $home['host'] ) ) {
			return;
		}

		wp_remote_get(
			$url,
			[
				'timeout'    => 15,
				'blocking'   => false,
				'sslverify'  => false,
				'user-agent' => 'aBlocks-Cache-Warmer/' . ABLOCKS_VERSION,
				'headers'    => [ 'X-ABlocks-Warm' => '1' ],
			]
		);
	}

	/**
	 * URLs worth warming.
	 *
	 * The site's own sitemap is preferred: it is the site's statement of what
	 * matters, and it includes archives, taxonomy and author pages that a post
	 * query alone would miss — which is what the other preloaders in this space
	 * read too. The post query remains as a fallback for sites that have the
	 * sitemap disabled or replaced.
	 *
	 * @param int $limit Maximum URLs to return.
	 * @return string[]
	 */
	public static function warmable_urls( $limit = 100 ) {
		$limit = max( 1, (int) $limit );

		$urls = self::sitemap_urls( $limit );
		if ( count( $urls ) > 1 ) {
			return array_slice( $urls, 0, $limit );
		}

		return self::queried_urls( $limit );
	}

	/**
	 * URLs read from the WordPress sitemap index.
	 *
	 * Fetched over HTTP rather than by calling the sitemap provider directly,
	 * because SEO plugins commonly replace core's sitemap with their own at the
	 * same address; going through the URL gets whichever one the site actually
	 * publishes.
	 *
	 * @param int $limit Stop after this many URLs.
	 * @return string[]
	 */
	public static function sitemap_urls( $limit = 100 ) {
		// Several candidates, because the address depends on what publishes the
		// sitemap: core uses wp-sitemap.xml, Yoast and RankMath use
		// sitemap_index.xml, others use sitemap.xml. Probing costs one request
		// each and only until the first that answers, which beats making the
		// user configure it. A site with none of them falls back to the query.
		$candidates = (array) apply_filters(
			'ablocks/perf/page_cache/sitemap_urls',
			[
				home_url( '/wp-sitemap.xml' ),
				home_url( '/sitemap_index.xml' ),
				home_url( '/sitemap.xml' ),
			]
		);

		$children = [];
		foreach ( $candidates as $candidate ) {
			$children = self::read_sitemap( $candidate );
			if ( ! empty( $children ) ) {
				break;
			}
		}

		if ( empty( $children ) ) {
			return [];
		}

		// An index lists sub-sitemaps; a flat sitemap lists pages. Both arrive as
		// <loc> values, so tell them apart by whether they look like sitemaps.
		$sub = array_values(
			array_filter(
				$children,
				function ( $url ) {
					return (bool) preg_match( '#\.xml(\?|$)#i', $url );
				}
			)
		);

		if ( empty( $sub ) ) {
			return array_slice( $children, 0, $limit );
		}

		$urls = [ home_url( '/' ) ];
		foreach ( $sub as $child ) {
			$urls = array_merge( $urls, self::read_sitemap( $child ) );
			if ( count( $urls ) >= $limit ) {
				break;
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Extract <loc> values from one sitemap document.
	 *
	 * @param string $url Sitemap URL.
	 * @return string[]
	 */
	private static function read_sitemap( $url ) {
		if ( ! self::same_origin( $url ) ) {
			return [];
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout'    => 20,
				'sslverify'  => false,
				'user-agent' => 'aBlocks-Cache-Warmer/' . ABLOCKS_VERSION,
			]
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			return [];
		}

		$body = wp_remote_retrieve_body( $response );
		if ( empty( $body ) || ! preg_match_all( '#<loc>\s*([^<]+?)\s*</loc>#i', $body, $matches ) ) {
			return [];
		}

		$urls = [];
		foreach ( $matches[1] as $loc ) {
			$loc = esc_url_raw( html_entity_decode( trim( $loc ), ENT_QUOTES, 'UTF-8' ) );
			if ( $loc && self::same_origin( $loc ) ) {
				$urls[] = $loc;
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Is a URL on this site?
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	private static function same_origin( $url ) {
		$home = wp_parse_url( home_url() );
		$want = wp_parse_url( $url );

		return ! empty( $want['host'] )
			&& ! empty( $home['host'] )
			&& strtolower( $want['host'] ) === strtolower( $home['host'] );
	}

	/**
	 * Fallback URL list: recently modified public posts, plus the front page.
	 *
	 * @param int $limit Maximum URLs to return.
	 * @return string[]
	 */
	private static function queried_urls( $limit = 100 ) {
		$limit = max( 1, (int) $limit );

		$post_types = array_values(
			array_filter(
				get_post_types( [ 'public' => true ], 'names' ),
				function ( $type ) {
					return 'attachment' !== $type;
				}
			)
		);

		$query = new \WP_Query(
			[
				'post_type'              => $post_types,
				'post_status'            => 'publish',
				'posts_per_page'         => $limit,
				'orderby'                => 'modified',
				'order'                  => 'DESC',
				'has_password'           => false,
				'ignore_sticky_posts'    => true,
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'fields'                 => 'ids',
			]
		);

		$urls = [ home_url( '/' ) ];
		foreach ( $query->posts as $post_id ) {
			$permalink = get_permalink( $post_id );
			if ( $permalink ) {
				$urls[] = $permalink;
			}
		}

		return array_values( array_unique( $urls ) );
	}

	/**
	 * Is Action Scheduler available to this request?
	 *
	 * Checked by function, not by plugin: Action Scheduler is normally bundled
	 * inside another plugin's vendor directory rather than activated on its own,
	 * and a bundled copy works exactly as well.
	 *
	 * @return bool
	 */
	public static function has_action_scheduler() {
		return function_exists( 'as_schedule_single_action' )
			&& function_exists( 'as_unschedule_all_actions' );
	}

	/**
	 * Which backend bulk warming will use, for display.
	 *
	 * @return string
	 */
	public static function warm_backend() {
		return self::has_action_scheduler() ? 'action-scheduler' : 'wp-cron';
	}
}
