<?php
namespace ABlocks\Classes\Scanner;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;
use ABlocks\Classes\CacheBackend;
use ABlocks\Classes\Images\UnusedScanner;
use ABlocks\Classes\FontCollector;
use ABlocks\Performance\ImageTools;

/**
 * Site scanner — find what is holding a site back, and say how to fix it.
 *
 * Every check returns the same shape so the UI never has to special-case one:
 * what was found, why it matters, and what to do about it. The "why" is not
 * decoration — a warning a site owner cannot act on is noise, and noise is what
 * makes people stop reading these screens.
 *
 * Three rules the checks follow:
 *
 * - Say the number. "3 of 214 images optimized" is actionable; "images could be
 *   optimized" is not.
 * - Never invent severity. A setting that is off on purpose is not a failure,
 *   so preferences are reported as suggestions and only genuine faults —
 *   blocked search engines, no HTTPS, two caching plugins fighting — are
 *   raised as critical.
 * - Only claim what was actually measured. Checks that cannot run on this
 *   install say so rather than guessing.
 */
class Scanner {

	const CRITICAL = 'critical';
	const WARNING  = 'warning';
	const GOOD     = 'good';
	const INFO     = 'info';

	/**
	 * Caching plugins that would fight with the page cache.
	 *
	 * Keyed by the plugin's main file so activation is checked exactly, not by
	 * guessing from a folder name.
	 */
	const CONFLICTING = [
		'wp-rocket/wp-rocket.php'                 => 'WP Rocket',
		'litespeed-cache/litespeed-cache.php'     => 'LiteSpeed Cache',
		'w3-total-cache/w3-total-cache.php'       => 'W3 Total Cache',
		'wp-super-cache/wp-cache.php'             => 'WP Super Cache',
		'wp-fastest-cache/wpFastestCache.php'     => 'WP Fastest Cache',
		'cache-enabler/cache-enabler.php'         => 'Cache Enabler',
		'breeze/breeze.php'                       => 'Breeze',
		'sg-cachepress/sg-cachepress.php'         => 'SG Optimizer',
		'hummingbird-performance/wp-hummingbird.php' => 'Hummingbird',
	];

	/**
	 * Where dismissed check ids are stored.
	 */
	const DISMISSED_OPTION = 'ablocks_scanner_dismissed';

	/**
	 * Where past scores are kept, for the trend line.
	 */
	const HISTORY_OPTION = 'ablocks_scanner_history';

	/**
	 * How much each check moves the score.
	 *
	 * Weighted rather than counted, because a site with search engines blocked
	 * and everything else perfect is not "95% healthy". Anything not listed
	 * scores 1.
	 */
	const WEIGHTS = [
		'search_visibility'   => 10,
		'https'               => 10,
		'cache_conflict'      => 8,
		'page_cache'          => 6,
		'asset_generation'    => 4,
		'css_consolidation'   => 3,
		'images_unoptimized'  => 4,
		'images_oversized'    => 3,
		'lazy_images'         => 3,
		'font_load'           => 3,
		'font_local'          => 2,
		'repeated_colors'     => 2,
		'repeated_typography' => 2,
		'images_alt'          => 3,
		'php_version'         => 4,
		'permalinks'          => 4,
		'wp_cron'             => 3,
		'file_editing'        => 2,
		'autoloaded_options'  => 3,
	];

	/**
	 * Run every check.
	 *
	 * @return array{checks:array, summary:array, score:array}
	 */
	public static function run() {
		$checks = [];

		foreach ( self::checks() as $method ) {
			$result = self::$method();
			if ( is_array( $result ) && ! empty( $result['id'] ) ) {
				$checks[] = $result;
			}
		}

		$checks = (array) apply_filters( 'ablocks/scanner/checks', $checks );

		// Dismissed checks stay in the payload so the UI can list and undo them,
		// but they take no part in the score — an issue the site owner has
		// consciously accepted should not keep dragging the number down.
		$dismissed = self::dismissed();
		foreach ( $checks as &$check ) {
			$check['weight']    = isset( self::WEIGHTS[ $check['id'] ] ) ? (int) self::WEIGHTS[ $check['id'] ] : 1;
			$check['dismissed'] = in_array( $check['id'], $dismissed, true );
		}
		unset( $check );

		// Worst first: a screen that opens on "everything is fine" buries the one
		// thing that is not.
		$rank = [
			self::CRITICAL => 0,
			self::WARNING  => 1,
			self::INFO     => 2,
			self::GOOD     => 3,
		];
		usort(
			$checks,
			function ( $a, $b ) use ( $rank ) {
				$sa = isset( $rank[ $a['status'] ] ) ? $rank[ $a['status'] ] : 4;
				$sb = isset( $rank[ $b['status'] ] ) ? $rank[ $b['status'] ] : 4;
				return $sa <=> $sb;
			}
		);

		$summary = [
			self::CRITICAL => 0,
			self::WARNING  => 0,
			self::INFO     => 0,
			self::GOOD     => 0,
		];
		foreach ( $checks as $check ) {
			if ( isset( $summary[ $check['status'] ] ) ) {
				$summary[ $check['status'] ]++;
			}
		}

		$score = self::score( $checks );

		self::remember_score( $score['score'] );

		return [
			'checks'  => $checks,
			'summary' => $summary,
			'score'   => $score,
			'history' => self::history(),
			'scanned' => time(),
		];
	}

	/**
	 * Turn the results into a score out of 100, overall and per category.
	 *
	 * Informational checks are left out of the maths entirely. They report
	 * something worth knowing rather than something wrong, so counting them
	 * either way would move the number for no reason — a site cannot "fix" not
	 * having Redis, and should not be marked down for it.
	 *
	 * @param array $checks Completed checks.
	 * @return array{score:int, grade:string, categories:array}
	 */
	private static function score( $checks ) {
		$earned = 0;
		$total  = 0;
		$cats   = [];

		foreach ( $checks as $check ) {
			if ( self::INFO === $check['status'] || ! empty( $check['dismissed'] ) ) {
				continue;
			}

			$weight = (int) $check['weight'];
			$points = self::GOOD === $check['status']
				? $weight
				// A warning is a partial pass: the site works, it is just not
				// doing as well as it could. Scoring it zero would make one
				// suggestion look as bad as a broken site.
				: ( self::WARNING === $check['status'] ? $weight * 0.4 : 0 );

			$earned += $points;
			$total  += $weight;

			$cat = $check['category'];
			if ( ! isset( $cats[ $cat ] ) ) {
				$cats[ $cat ] = [
					'earned' => 0,
					'total'  => 0,
				];
			}
			$cats[ $cat ]['earned'] += $points;
			$cats[ $cat ]['total']  += $weight;
		}//end foreach

		$percent = $total > 0 ? (int) round( 100 * $earned / $total ) : 100;

		$categories = [];
		foreach ( $cats as $name => $data ) {
			$categories[ $name ] = $data['total'] > 0
				? (int) round( 100 * $data['earned'] / $data['total'] )
				: 100;
		}
		arsort( $categories );

		return [
			'score'      => $percent,
			'grade'      => self::grade( $percent ),
			'categories' => $categories,
		];
	}

	/**
	 * Letter for a score.
	 *
	 * @param int $percent Score out of 100.
	 * @return string
	 */
	private static function grade( $percent ) {
		if ( $percent >= 90 ) {
			return 'A';
		}
		if ( $percent >= 80 ) {
			return 'B';
		}
		if ( $percent >= 70 ) {
			return 'C';
		}
		if ( $percent >= 60 ) {
			return 'D';
		}
		return 'F';
	}

	/**
	 * Record today's score so the UI can show whether things are improving.
	 *
	 * One entry per day, because the value of a trend line is direction over
	 * weeks; storing every scan would fill the row with a dozen identical
	 * numbers from one afternoon of tinkering.
	 *
	 * @param int $percent Score out of 100.
	 */
	private static function remember_score( $percent ) {
		$history = self::history();
		$today   = gmdate( 'Y-m-d' );

		$history[ $today ] = (int) $percent;

		if ( count( $history ) > 30 ) {
			$history = array_slice( $history, -30, 30, true );
		}

		update_option( self::HISTORY_OPTION, $history, false );
	}

	/**
	 * Past scores, oldest first.
	 *
	 * @return array<string,int>
	 */
	public static function history() {
		$history = get_option( self::HISTORY_OPTION, [] );
		return is_array( $history ) ? $history : [];
	}

	/**
	 * Check ids the site owner has chosen to ignore.
	 *
	 * @return string[]
	 */
	public static function dismissed() {
		$list = get_option( self::DISMISSED_OPTION, [] );
		return is_array( $list ) ? array_values( array_map( 'strval', $list ) ) : [];
	}

	/**
	 * Ignore or un-ignore a check.
	 *
	 * @param string $id      Check id.
	 * @param bool   $dismiss True to ignore, false to bring it back.
	 * @return bool
	 */
	public static function set_dismissed( $id, $dismiss = true ) {
		$id = sanitize_key( $id );
		if ( '' === $id ) {
			return false;
		}

		$list = self::dismissed();

		if ( $dismiss ) {
			if ( ! in_array( $id, $list, true ) ) {
				$list[] = $id;
			}
		} else {
			$list = array_values( array_diff( $list, [ $id ] ) );
		}

		update_option( self::DISMISSED_OPTION, $list, false );

		return true;
	}

	/**
	 * The checks to run, in definition order.
	 *
	 * @return string[]
	 */
	private static function checks() {
		return [
			'check_search_visibility',
			'check_https',
			'check_conflicting_cache',
			'check_page_cache',
			'check_object_cache',
			'check_css_consolidation',
			'check_crawler',
			'check_asset_generation',
			'check_defer_js',
			'check_images_unoptimized',
			'check_images_oversized',
			'check_images_alt_text',
			'check_images_unused',
			'check_lazy_loading',
			'check_repeated_colors',
			'check_repeated_typography',
			'check_font_families',
			'check_font_local',
			'check_font_fallback',
			'check_php_version',
			'check_debug_mode',
			'check_autoloaded_options',
			'check_permalinks',
			'check_revisions',
			'check_wp_cron',
			'check_sitemap',
			'check_site_icon',
			'check_inactive_plugins',
			'check_file_editing',
			'check_expired_transients',
		];
	}

	/**
	 * Assemble one result.
	 *
	 * @param array $args Check fields.
	 * @return array
	 */
	private static function result( $args ) {
		return wp_parse_args(
			$args,
			[
				'id'       => '',
				'label'    => '',
				'category' => 'general',
				'status'   => self::INFO,
				'summary'  => '',
				'why'      => '',
				'fix'      => '',
				'field'    => '',
				'url'      => '',
			]
		);
	}

	// -- Site-level faults ---------------------------------------------------

	/**
	 * Search engine visibility.
	 */
	private static function check_search_visibility() {
		$blocked = ! (int) get_option( 'blog_public', 1 );

		return self::result(
			[
				'id'       => 'search_visibility',
				'label'    => __( 'Search engine visibility', 'ablocks' ),
				'category' => 'seo',
				'status'   => $blocked ? self::CRITICAL : self::GOOD,
				'summary'  => $blocked
					? __( 'Search engines are being asked not to index this site', 'ablocks' )
					: __( 'Search engines can index this site', 'ablocks' ),
				'why'      => $blocked
					? __( 'This setting is normally switched on while a site is being built and forgotten at launch. While it is on, Google is asked to stay away — no amount of speed or content work will bring in search traffic.', 'ablocks' )
					: '',
				'fix'      => $blocked
					? __( 'Settings → Reading → untick "Discourage search engines from indexing this site".', 'ablocks' )
					: '',
				'url'      => $blocked ? admin_url( 'options-reading.php' ) : '',
			]
		);
	}

	/**
	 * HTTPS.
	 */
	private static function check_https() {
		$secure = 0 === strpos( home_url(), 'https://' );

		return self::result(
			[
				'id'       => 'https',
				'label'    => __( 'Secure connection', 'ablocks' ),
				'category' => 'seo',
				'status'   => $secure ? self::GOOD : self::CRITICAL,
				'summary'  => $secure
					? __( 'The site is served over HTTPS', 'ablocks' )
					: __( 'The site is not served over HTTPS', 'ablocks' ),
				'why'      => $secure
					? ''
					: __( 'Browsers mark plain HTTP pages as "Not secure", search engines rank them lower, and several speed features — including HTTP/2 and modern image formats over CDN — depend on it.', 'ablocks' ),
				'fix'      => $secure ? '' : __( 'Install an SSL certificate with your host, then update the WordPress and Site Address to https:// under Settings → General.', 'ablocks' ),
			]
		);
	}

	/**
	 * Another caching plugin running alongside this one.
	 */
	private static function check_conflicting_cache() {
		// is_plugin_active() lives in an admin include that is absent from AJAX
		// and cron. Guarded here rather than only at the caller, because the
		// scanner is callable from anywhere and a missing function would be a
		// fatal error rather than a skipped check.
		if ( ! function_exists( 'is_plugin_active' ) ) {
			if ( ! is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				return [];
			}
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$found = [];
		foreach ( self::CONFLICTING as $plugin => $name ) {
			if ( is_plugin_active( $plugin ) ) {
				$found[] = $name;
			}
		}

		$has = ! empty( $found );

		return self::result(
			[
				'id'       => 'cache_conflict',
				'label'    => __( 'Competing caching plugins', 'ablocks' ),
				'category' => 'caching',
				'status'   => $has ? self::CRITICAL : self::GOOD,
				'summary'  => $has
					/* translators: %s: comma-separated plugin names. */
					? sprintf( __( '%s is also caching pages', 'ablocks' ), implode( ', ', $found ) )
					: __( 'No competing caching plugin found', 'ablocks' ),
				'why'      => $has
					? __( 'Two page caches on one site fight each other: each stores what the other produced, purges do not reach both, and stale pages become almost impossible to trace. This is the single most common cause of "I cleared the cache and nothing changed".', 'ablocks' )
					: '',
				'fix'      => $has
					? __( 'Pick one. Either switch off page caching in the other plugin, or leave aBlocks caching off and use theirs — both work, running both does not.', 'ablocks' )
					: '',
				'url'      => $has ? admin_url( 'plugins.php' ) : '',
			]
		);
	}

	// -- Caching -------------------------------------------------------------

	/**
	 * Page cache enabled.
	 */
	private static function check_page_cache() {
		$on = (bool) Helper::get_settings( 'perf_page_cache', false );

		return self::result(
			[
				'id'       => 'page_cache',
				'label'    => __( 'Full-page cache', 'ablocks' ),
				'category' => 'caching',
				'status'   => $on ? self::GOOD : self::WARNING,
				'summary'  => $on ? __( 'Enabled', 'ablocks' ) : __( 'Not enabled', 'ablocks' ),
				'why'      => $on
					? ''
					: __( 'Without it every visitor waits for WordPress to rebuild the page from scratch — loading plugins, parsing blocks and querying the database — even though the result is identical for everyone. This is usually the single biggest speed gain available to a site.', 'ablocks' ),
				'fix'      => $on ? '' : __( 'Turn on "Full-page cache" under Performance → Caching.', 'ablocks' ),
				'field'    => $on ? '' : 'perf_page_cache',
			]
		);
	}

	/**
	 * Persistent object cache.
	 */
	private static function check_object_cache() {
		$has = CacheBackend::is_persistent();

		return self::result(
			[
				'id'       => 'object_cache',
				'label'    => __( 'Persistent object cache', 'ablocks' ),
				'category' => 'caching',
				'status'   => $has ? self::GOOD : self::INFO,
				'summary'  => $has
					? __( 'Available', 'ablocks' )
					: __( 'Not available (Redis or Memcached)', 'ablocks' ),
				'why'      => $has
					? ''
					: __( 'WordPress repeats the same database queries on every request unless something remembers them between requests. Without one, some optimizations are not worth enabling — the template cache stays off for exactly this reason, because storing lookups in the database costs more than it saves.', 'ablocks' ),
				'fix'      => $has ? '' : __( 'Ask your host whether Redis or Memcached is available; most managed WordPress hosts offer it as a one-click add-on. This is optional — the site works well without it.', 'ablocks' ),
			]
		);
	}

	/**
	 * Inline CSS consolidation.
	 */
	private static function check_css_consolidation() {
		$on = (bool) Helper::get_settings( 'perf_consolidate_css', false );

		return self::result(
			[
				'id'       => 'css_consolidation',
				'label'    => __( 'Inline CSS moved to cached files', 'ablocks' ),
				'category' => 'assets',
				'status'   => $on ? self::GOOD : self::WARNING,
				'summary'  => $on ? __( 'Enabled', 'ablocks' ) : __( 'Not enabled', 'ablocks' ),
				'why'      => $on
					? ''
					: __( 'Block themes print tens of kilobytes of CSS directly into every page, and the browser cannot reuse any of it when the visitor clicks through to the next page. Measured on Twenty Twenty-Four, that is around 40% of the page weight downloaded again and again.', 'ablocks' ),
				'fix'      => $on ? '' : __( 'Turn on "Move inline CSS into cached files" under Performance → Caching.', 'ablocks' ),
				'field'    => $on ? '' : 'perf_consolidate_css',
			]
		);
	}

	/**
	 * Background crawler.
	 */
	private static function check_crawler() {
		$cache_on = (bool) Helper::get_settings( 'perf_page_cache', false );
		$on       = (bool) Helper::get_settings( 'perf_page_cache_crawler', false );

		if ( ! $cache_on ) {
			return [];
		}

		return self::result(
			[
				'id'       => 'crawler',
				'label'    => __( 'Background page caching', 'ablocks' ),
				'category' => 'caching',
				'status'   => $on ? self::GOOD : self::INFO,
				'summary'  => $on ? __( 'Enabled', 'ablocks' ) : __( 'Not enabled', 'ablocks' ),
				'why'      => $on
					? ''
					: __( 'A page is only cached after someone has already waited for it. With background caching on, pages are built ahead of time so the first visitor gets the fast version too.', 'ablocks' ),
				'fix'      => $on ? '' : __( 'Turn on "Cache pages in the background" under Performance → Caching.', 'ablocks' ),
				'field'    => $on ? '' : 'perf_page_cache_crawler',
			]
		);
	}

	// -- Assets --------------------------------------------------------------

	/**
	 * Combined per-page asset files.
	 */
	private static function check_asset_generation() {
		$on = (bool) Helper::get_settings( 'enabled_assets_file_generation', false );

		return self::result(
			[
				'id'       => 'asset_generation',
				'label'    => __( 'Combined block CSS and JavaScript', 'ablocks' ),
				'category' => 'assets',
				'status'   => $on ? self::GOOD : self::WARNING,
				'summary'  => $on ? __( 'Enabled', 'ablocks' ) : __( 'Not enabled', 'ablocks' ),
				'why'      => $on
					? ''
					: __( 'Each block otherwise loads its own stylesheet and script, so a page with fifteen blocks makes fifteen extra requests before it can finish rendering.', 'ablocks' ),
				'fix'      => $on ? '' : __( 'Turn on "Enable asset file generation" under Performance → Assets. Best done once the site is built.', 'ablocks' ),
				'field'    => $on ? '' : 'enabled_assets_file_generation',
			]
		);
	}

	/**
	 * Deferred JavaScript.
	 */
	private static function check_defer_js() {
		$on = (bool) Helper::get_settings( 'perf_defer_js', false );

		return self::result(
			[
				'id'       => 'defer_js',
				'label'    => __( 'Deferred JavaScript', 'ablocks' ),
				'category' => 'assets',
				'status'   => $on ? self::GOOD : self::INFO,
				'summary'  => $on ? __( 'Enabled', 'ablocks' ) : __( 'Not enabled', 'ablocks' ),
				'why'      => $on ? '' : __( 'Scripts that load in the page head stop the browser drawing anything until they have downloaded and run. Deferring them lets the page appear first.', 'ablocks' ),
				'fix'      => $on ? '' : __( 'Turn on "Defer JavaScript" under Performance → Assets.', 'ablocks' ),
				'field'    => $on ? '' : 'perf_defer_js',
			]
		);
	}

	/**
	 * Image lazy loading.
	 */
	private static function check_lazy_loading() {
		$on = (bool) Helper::get_settings( 'perf_lazy_images', true );

		return self::result(
			[
				'id'       => 'lazy_images',
				'label'    => __( 'Lazy-loaded images', 'ablocks' ),
				'category' => 'images',
				'status'   => $on ? self::GOOD : self::WARNING,
				'summary'  => $on ? __( 'Enabled', 'ablocks' ) : __( 'Not enabled', 'ablocks' ),
				'why'      => $on ? '' : __( 'Every image on the page is downloaded immediately, including ones far below the fold that the visitor may never scroll to.', 'ablocks' ),
				'fix'      => $on ? '' : __( 'Turn on "Lazy load images" under Performance → Images.', 'ablocks' ),
				'field'    => $on ? '' : 'perf_lazy_images',
			]
		);
	}

	// -- Images --------------------------------------------------------------

	/**
	 * Images not yet compressed.
	 */
	private static function check_images_unoptimized() {
		$stats   = ImageTools::stats();
		$pending = (int) $stats['pending'];
		$done    = (int) $stats['optimized'];

		if ( 0 === $pending && 0 === $done ) {
			return [];
		}

		return self::result(
			[
				'id'       => 'images_unoptimized',
				'label'    => __( 'Image compression', 'ablocks' ),
				'category' => 'images',
				'status'   => $pending > 0 ? self::WARNING : self::GOOD,
				'summary'  => $pending > 0
					/* translators: 1: images not optimized, 2: images already optimized. */
					? sprintf( __( '%1$d image(s) not compressed, %2$d already done', 'ablocks' ), $pending, $done )
					/* translators: 1: images optimized, 2: space saved. */
					: sprintf( __( '%1$d image(s) compressed, %2$s saved', 'ablocks' ), $done, $stats['saved'] ),
				'why'      => $pending > 0
					? __( 'Images are usually the largest thing on a page. Compressing them typically removes 40-60% of their weight with no visible difference, and your originals are kept so it can be undone.', 'ablocks' )
					: '',
				'fix'      => $pending > 0 ? __( 'Use "Optimize all images" under Performance → Images.', 'ablocks' ) : '',
			]
		);
	}

	/**
	 * Images far larger than any layout will display.
	 */
	private static function check_images_oversized() {
		global $wpdb;

		$rows = $wpdb->get_col(
			"SELECT meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attachment_metadata' LIMIT 500"
		);

		$oversized = 0;
		$widest    = 0;
		foreach ( (array) $rows as $row ) {
			$meta = maybe_unserialize( $row );
			if ( ! is_array( $meta ) || empty( $meta['width'] ) ) {
				continue;
			}
			$width = (int) $meta['width'];
			if ( $width > 2560 ) {
				$oversized++;
				$widest = max( $widest, $width );
			}
		}

		if ( 0 === $oversized ) {
			return [];
		}

		return self::result(
			[
				'id'       => 'images_oversized',
				'label'    => __( 'Oversized images', 'ablocks' ),
				'category' => 'images',
				'status'   => self::WARNING,
				/* translators: 1: number of images, 2: widest image in pixels. */
				'summary'  => sprintf( __( '%1$d image(s) wider than 2560px (largest is %2$dpx)', 'ablocks' ), $oversized, $widest ),
				'why'      => __( 'A photo straight from a phone or camera can be 5000px wide, while the space it fills on screen is rarely more than 1200px. The visitor downloads every one of those extra pixels and the browser throws them away.', 'ablocks' ),
				'fix'      => __( 'Resize large images before uploading, or set a maximum width so WordPress scales them on upload. Compressing them here helps, but resizing helps far more.', 'ablocks' ),
				'url'      => admin_url( 'upload.php' ),
			]
		);
	}

	/**
	 * Images with no alternative text.
	 */
	private static function check_images_alt_text() {
		global $wpdb;

		$missing = (int) $wpdb->get_var(
			"SELECT COUNT(p.ID) FROM {$wpdb->posts} p
			 LEFT JOIN {$wpdb->postmeta} m
			   ON m.post_id = p.ID AND m.meta_key = '_wp_attachment_image_alt'
			 WHERE p.post_type = 'attachment'
			   AND p.post_mime_type LIKE 'image/%'
			   AND ( m.meta_value IS NULL OR m.meta_value = '' )"
		);

		if ( 0 === $missing ) {
			return [];
		}

		return self::result(
			[
				'id'       => 'images_alt',
				'label'    => __( 'Image alternative text', 'ablocks' ),
				'category' => 'seo',
				'status'   => self::WARNING,
				/* translators: %d: number of images. */
				'summary'  => sprintf( __( '%d image(s) have no alt text', 'ablocks' ), $missing ),
				'why'      => __( 'Alt text is what screen readers announce and what search engines read to understand a picture. Without it those images are invisible to both, and in some regions missing alt text is an accessibility compliance issue.', 'ablocks' ),
				'fix'      => __( 'Open Media → Library in list view and fill in the Alt Text field. Describe what the image shows; leave it empty only for purely decorative images.', 'ablocks' ),
				'url'      => admin_url( 'upload.php?mode=list' ),
			]
		);
	}

	/**
	 * Media that nothing appears to reference.
	 */
	private static function check_images_unused() {
		$batch = UnusedScanner::scan( 25, 0 );
		$count = count( $batch['items'] );

		if ( 0 === $count ) {
			return [];
		}

		$bytes = 0;
		foreach ( $batch['items'] as $item ) {
			$bytes += (int) $item['bytes'];
		}

		return self::result(
			[
				'id'       => 'images_unused',
				'label'    => __( 'Possibly unused images', 'ablocks' ),
				'category' => 'images',
				'status'   => self::INFO,
				'summary'  => sprintf(
					/* translators: 1: number of images found in the sample, 2: disk space. */
					__( 'At least %1$d image(s) with no reference found, using %2$s', 'ablocks' ),
					$count,
					size_format( $bytes, 1 )
				),
				'why'      => __( 'These take up space and slow down backups. This is a suggestion, not a verdict — an image can be used in ways no scan can see, so nothing is deleted without your review.', 'ablocks' ),
				'fix'      => __( 'Use "Scan for unused images" under Performance → Media cleanup. Removal moves files aside so they can be put back.', 'ablocks' ),
			]
		);
	}

	// -- Design consistency --------------------------------------------------

	/**
	 * Cached content scan, so both design checks parse the site's blocks once.
	 *
	 * @return array
	 */
	private static function design_repeats() {
		static $scan = null;
		if ( null === $scan ) {
			$scan = DesignRepeats::scan();
		}
		return $scan;
	}

	/**
	 * The same colour typed in repeatedly instead of being a global.
	 */
	private static function check_repeated_colors() {
		$scan   = self::design_repeats();
		$colors = $scan['colors'];

		if ( empty( $colors ) ) {
			return [];
		}

		// A colour that already exists as a preset is a different, smaller
		// problem — the value is right, it is just not referenced — so say which
		// is which rather than lumping them together.
		$globals  = DesignRepeats::global_colors();
		$unnamed  = [];
		$unlinked = [];

		foreach ( $colors as $color ) {
			if ( in_array( $color['value'], $globals, true ) ) {
				$unlinked[] = $color;
			} else {
				$unnamed[] = $color;
			}
		}

		$top   = array_slice( array_merge( $unnamed, $unlinked ), 0, 5 );
		$parts = [];
		foreach ( $top as $color ) {
			/* translators: 1: colour value, 2: number of times used. */
			$parts[] = sprintf( __( '%1$s used %2$d times', 'ablocks' ), $color['value'], $color['count'] );
		}

		return self::result(
			[
				'id'       => 'repeated_colors',
				'label'    => __( 'Repeated colours', 'ablocks' ),
				'category' => 'design',
				'status'   => self::WARNING,
				'summary'  => implode( ', ', $parts ),
				'why'      => ! empty( $unnamed )
					? __( 'These colours are typed directly into blocks rather than referenced from your global palette. They already behave like brand colours — the only difference is that changing one means editing every block by hand, and nothing keeps them consistent as the site grows.', 'ablocks' )
					: __( 'These colours already exist in your global palette, but the blocks hold a copy of the value instead of pointing at the preset. Updating the preset will not update these blocks.', 'ablocks' ),
				'fix'      => ! empty( $unnamed )
					? __( 'Add each colour to Settings → Editor Options → Global Colors, then select the global colour on those blocks instead of the custom value. Afterwards, turn on the colour lock to stop new ones appearing.', 'ablocks' )
					: __( 'Re-select the matching global colour on those blocks so they follow the preset.', 'ablocks' ),
			]
		);
	}

	/**
	 * The same typography settings repeated instead of a global preset.
	 */
	private static function check_repeated_typography() {
		$scan  = self::design_repeats();
		$fonts = $scan['fonts'];

		if ( empty( $fonts ) ) {
			return [];
		}

		$parts = [];
		foreach ( array_slice( $fonts, 0, 5 ) as $font ) {
			/* translators: 1: font description, 2: number of times used. */
			$parts[] = sprintf( __( '"%1$s" used %2$d times', 'ablocks' ), $font['value'], $font['count'] );
		}

		return self::result(
			[
				'id'       => 'repeated_typography',
				'label'    => __( 'Repeated typography', 'ablocks' ),
				'category' => 'design',
				'status'   => self::WARNING,
				'summary'  => implode( ', ', $parts ),
				'why'      => __( 'The same typeface, size and weight combination has been set by hand on many blocks. That is a heading style waiting to be named: as it stands, changing your heading size means finding and editing every one of them, and any that get missed drift out of step.', 'ablocks' ),
				'fix'      => __( 'Define the combination once under Settings → Editor Options → Global Typography, then apply that preset to the blocks. The design-system lock will keep new content on the presets.', 'ablocks' ),
			]
		);
	}

	// -- Fonts ---------------------------------------------------------------

	/**
	 * How many families and weights the site loads.
	 */
	private static function check_font_families() {
		$fonts = FontCollector::merge( FontCollector::get_global_fonts(), FontCollector::get_site_fonts() );

		$families = count( (array) $fonts );
		$weights  = 0;
		foreach ( (array) $fonts as $list ) {
			$weights += count( (array) $list );
		}

		if ( 0 === $families ) {
			return [];
		}

		// Two families is the working recommendation: one for headings, one for
		// body text. A third is almost always an accident — a block that kept a
		// theme default, or a pattern imported from elsewhere — rather than a
		// design decision anybody made.
		$max_families = (int) apply_filters( 'ablocks/scanner/max_font_families', 2 );
		$max_weights  = (int) apply_filters( 'ablocks/scanner/max_font_weights', 8 );

		$heavy = $families > $max_families || $weights > $max_weights;

		return self::result(
			[
				'id'       => 'font_load',
				'label'    => __( 'Web fonts in use', 'ablocks' ),
				'category' => 'fonts',
				'status'   => $heavy ? self::WARNING : self::GOOD,
				'summary'  => $heavy
					? sprintf(
						/* translators: 1: number of font families, 2: number of weights, 3: recommended maximum families. */
						__( '%1$d font families across %2$d weights — %3$d families is the recommended maximum', 'ablocks' ),
						$families,
						$weights,
						$max_families
					)
					: sprintf(
						/* translators: 1: number of font families, 2: number of weights. */
						__( '%1$d font family/families across %2$d weight(s)', 'ablocks' ),
						$families,
						$weights
					),
				'why'      => $heavy
					? __( 'Every family and every weight is a separate file the browser must download before text can appear in the right typeface. Two families — one for headings, one for body — covers almost every design; a third is usually a block that kept a theme default or a pattern imported from elsewhere, rather than a decision anyone made.', 'ablocks' )
					: '',
				'fix'      => $heavy
					? __( 'Open the Font Usage report under Performance → Fonts to see which pages pull in the extras, then set those blocks to your global typography presets. Turning on the typography lock afterwards stops new ones appearing.', 'ablocks' )
					: '',
			]
		);
	}

	/**
	 * Google Fonts served from Google rather than locally.
	 */
	private static function check_font_local() {
		$fonts = FontCollector::merge( FontCollector::get_global_fonts(), FontCollector::get_site_fonts() );
		if ( empty( $fonts ) ) {
			return [];
		}

		$local = (bool) Helper::get_settings( 'enabled_load_google_font_locally', false );

		return self::result(
			[
				'id'       => 'font_local',
				'label'    => __( 'Self-hosted fonts', 'ablocks' ),
				'category' => 'fonts',
				'status'   => $local ? self::GOOD : self::WARNING,
				'summary'  => $local ? __( 'Fonts are served from this site', 'ablocks' ) : __( 'Fonts are fetched from Google', 'ablocks' ),
				'why'      => $local
					? ''
					: __( 'Every visitor makes an extra connection to Google before text can render, and their IP address is shared with a third party — which several European data protection rulings have found unlawful without consent.', 'ablocks' ),
				'fix'      => $local ? '' : __( 'Turn on "Load Google Fonts locally" under Performance → Fonts. The files are downloaded once and served from your own server.', 'ablocks' ),
				'field'    => $local ? '' : 'enabled_load_google_font_locally',
			]
		);
	}

	/**
	 * Metric-matched fallback fonts.
	 */
	private static function check_font_fallback() {
		$fonts = FontCollector::merge( FontCollector::get_global_fonts(), FontCollector::get_site_fonts() );
		if ( empty( $fonts ) ) {
			return [];
		}

		$on = (bool) Helper::get_settings( 'font_metric_fallback', true );

		return self::result(
			[
				'id'       => 'font_fallback',
				'label'    => __( 'Zero-shift font fallbacks', 'ablocks' ),
				'category' => 'fonts',
				'status'   => $on ? self::GOOD : self::INFO,
				'summary'  => $on ? __( 'Enabled', 'ablocks' ) : __( 'Not enabled', 'ablocks' ),
				'why'      => $on ? '' : __( 'While a web font loads, text is drawn in a fallback of a different size, so the page visibly jumps when the real font arrives. Google counts that jump against your Core Web Vitals score.', 'ablocks' ),
				'fix'      => $on ? '' : __( 'Turn on "Zero-shift font fallbacks" under Performance → Fonts. Nothing extra is downloaded.', 'ablocks' ),
				'field'    => $on ? '' : 'font_metric_fallback',
			]
		);
	}

	// -- WordPress hygiene ---------------------------------------------------

	/**
	 * PHP version.
	 */
	private static function check_php_version() {
		$version = PHP_VERSION;
		$old     = version_compare( $version, '8.0', '<' );

		return self::result(
			[
				'id'       => 'php_version',
				'label'    => __( 'PHP version', 'ablocks' ),
				'category' => 'server',
				'status'   => $old ? self::WARNING : self::GOOD,
				/* translators: %s: PHP version number. */
				'summary'  => sprintf( __( 'Running PHP %s', 'ablocks' ), $version ),
				'why'      => $old ? __( 'PHP 8 is roughly twice as fast as PHP 7 for typical WordPress work, and versions below 8.0 no longer receive security fixes.', 'ablocks' ) : '',
				'fix'      => $old ? __( 'Ask your host to move the site to PHP 8.1 or newer. Test on staging first if you run older plugins.', 'ablocks' ) : '',
			]
		);
	}

	/**
	 * Debug mode left on.
	 */
	private static function check_debug_mode() {
		$on = defined( 'WP_DEBUG' ) && WP_DEBUG;

		if ( ! $on ) {
			return [];
		}

		return self::result(
			[
				'id'       => 'debug_mode',
				'label'    => __( 'Debug mode', 'ablocks' ),
				'category' => 'server',
				'status'   => self::WARNING,
				'summary'  => __( 'WP_DEBUG is switched on', 'ablocks' ),
				'why'      => __( 'Debug mode makes WordPress do extra work on every request and can print notices from plugins and themes directly into your pages, occasionally revealing file paths. It is meant for development only.', 'ablocks' ),
				'fix'      => __( 'Set WP_DEBUG to false in wp-config.php on the live site. Leave it on for staging.', 'ablocks' ),
			]
		);
	}

	/**
	 * Autoloaded options size.
	 */
	private static function check_autoloaded_options() {
		global $wpdb;

		$bytes = (int) $wpdb->get_var(
			"SELECT SUM(LENGTH(option_value)) FROM {$wpdb->options} WHERE autoload IN ('yes','on')"
		);

		$heavy = $bytes > 800000;

		return self::result(
			[
				'id'       => 'autoloaded_options',
				'label'    => __( 'Always-loaded settings', 'ablocks' ),
				'category' => 'server',
				'status'   => $heavy ? self::WARNING : self::GOOD,
				/* translators: %s: formatted size. */
				'summary'  => sprintf( __( '%s loaded on every request', 'ablocks' ), size_format( $bytes, 1 ) ),
				'why'      => $heavy
					? __( 'WordPress loads these settings from the database before it does anything else, on every single page view. Past roughly 800KB this becomes a measurable delay, and it is usually caused by plugins that were removed without cleaning up after themselves.', 'ablocks' )
					: '',
				'fix'      => $heavy
					? __( 'Have a developer review the largest autoloaded rows in wp_options; leftovers from uninstalled plugins can normally be deleted. Take a database backup first.', 'ablocks' )
					: '',
			]
		);
	}

	/**
	 * Permalink structure.
	 */
	private static function check_permalinks() {
		$structure = (string) get_option( 'permalink_structure' );
		$plain     = '' === $structure;

		if ( ! $plain ) {
			return [];
		}

		return self::result(
			[
				'id'       => 'permalinks',
				'label'    => __( 'Permalink structure', 'ablocks' ),
				'category' => 'seo',
				'status'   => self::WARNING,
				'summary'  => __( 'URLs are in the plain ?p=123 format', 'ablocks' ),
				'why'      => __( 'Plain URLs tell neither visitors nor search engines what a page is about, and several caching and SEO features — including sitemap-based cache warming — expect readable URLs.', 'ablocks' ),
				'fix'      => __( 'Settings → Permalinks → choose "Post name". Set up redirects if the site is already live with the old URLs.', 'ablocks' ),
				'url'      => admin_url( 'options-permalink.php' ),
			]
		);
	}

	/**
	 * Scheduled tasks able to run.
	 */
	private static function check_wp_cron() {
		$disabled = defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON;

		if ( ! $disabled ) {
			$overdue = 0;
			foreach ( (array) _get_cron_array() as $timestamp => $hooks ) {
				if ( $timestamp < time() - HOUR_IN_SECONDS ) {
					$overdue += count( (array) $hooks );
				}
			}

			if ( $overdue < 3 ) {
				return self::result(
					[
						'id'       => 'wp_cron',
						'label'    => __( 'Scheduled tasks', 'ablocks' ),
						'category' => 'server',
						'status'   => self::GOOD,
						'summary'  => __( 'Running on time', 'ablocks' ),
					]
				);
			}

			return self::result(
				[
					'id'       => 'wp_cron',
					'label'    => __( 'Scheduled tasks', 'ablocks' ),
					'category' => 'server',
					'status'   => self::WARNING,
					/* translators: %d: number of overdue tasks. */
					'summary'  => sprintf( __( '%d task(s) more than an hour overdue', 'ablocks' ), $overdue ),
					'why'      => __( 'WordPress runs scheduled work when someone visits the site, so a quiet site can fall behind. Cache pruning, background caching, scheduled posts and email all depend on this.', 'ablocks' ),
					'fix'      => __( 'Ask your host to set up a real cron job that calls wp-cron.php every few minutes, then add define( \'DISABLE_WP_CRON\', true ); to wp-config.php.', 'ablocks' ),
				]
			);
		}//end if

		// Disabled is the *correct* setup when a server cron replaces it, so this
		// cannot be reported as a fault — only as something to confirm.
		return self::result(
			[
				'id'       => 'wp_cron',
				'label'    => __( 'Scheduled tasks', 'ablocks' ),
				'category' => 'server',
				'status'   => self::INFO,
				'summary'  => __( 'WordPress cron is switched off', 'ablocks' ),
				'why'      => __( 'This is the recommended setup when a real server cron job calls wp-cron.php instead — but if no such job exists, nothing scheduled will ever run: no cache pruning, no background caching, no scheduled posts.', 'ablocks' ),
				'fix'      => __( 'Confirm with your host that a cron job calls wp-cron.php every few minutes.', 'ablocks' ),
			]
		);
	}

	/**
	 * A reachable XML sitemap.
	 */
	private static function check_sitemap() {
		$urls = \ABlocks\Classes\PageCache\Scheduler::sitemap_urls( 5 );
		$has  = count( $urls ) > 1;

		return self::result(
			[
				'id'       => 'sitemap',
				'label'    => __( 'XML sitemap', 'ablocks' ),
				'category' => 'seo',
				'status'   => $has ? self::GOOD : self::WARNING,
				'summary'  => $has ? __( 'Found and readable', 'ablocks' ) : __( 'No sitemap could be read', 'ablocks' ),
				'why'      => $has
					? ''
					: __( 'Search engines use a sitemap to discover pages they might otherwise miss, and background cache warming reads it to decide what to build. Neither can work without one.', 'ablocks' ),
				'fix'      => $has
					? ''
					: __( 'WordPress publishes one at /wp-sitemap.xml unless a plugin or filter has disabled it. Check that the URL loads, or let your SEO plugin generate one.', 'ablocks' ),
			]
		);
	}

	/**
	 * Site icon.
	 */
	private static function check_site_icon() {
		if ( has_site_icon() ) {
			return [];
		}

		return self::result(
			[
				'id'       => 'site_icon',
				'label'    => __( 'Site icon', 'ablocks' ),
				'category' => 'seo',
				'status'   => self::INFO,
				'summary'  => __( 'No site icon set', 'ablocks' ),
				'why'      => __( 'The site icon is the small image shown on browser tabs, bookmarks and phone home screens. Without one the site shows a blank page glyph, which looks unfinished next to other tabs.', 'ablocks' ),
				'fix'      => __( 'Appearance → Customize → Site Identity, or Settings → General on newer themes. A square image of at least 512×512 works everywhere.', 'ablocks' ),
				'url'      => admin_url( 'options-general.php' ),
			]
		);
	}

	/**
	 * Deactivated plugins left installed.
	 */
	private static function check_inactive_plugins() {
		if ( ! function_exists( 'get_plugins' ) ) {
			if ( ! is_readable( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				return [];
			}
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all      = array_keys( (array) get_plugins() );
		$active   = (array) get_option( 'active_plugins', [] );
		$inactive = count( array_diff( $all, $active ) );

		if ( $inactive < 5 ) {
			return [];
		}

		return self::result(
			[
				'id'       => 'inactive_plugins',
				'label'    => __( 'Deactivated plugins', 'ablocks' ),
				'category' => 'server',
				'status'   => self::INFO,
				/* translators: %d: number of inactive plugins. */
				'summary'  => sprintf( __( '%d plugin(s) installed but not active', 'ablocks' ), $inactive ),
				'why'      => __( 'Inactive plugins do not slow the site down, but their files are still on the server and still need security updates. An abandoned plugin nobody is watching is a common way sites get compromised.', 'ablocks' ),
				'fix'      => __( 'Delete the ones you do not intend to use again. Anything you might need later can be reinstalled in seconds.', 'ablocks' ),
				'url'      => admin_url( 'plugins.php?plugin_status=inactive' ),
			]
		);
	}

	/**
	 * Theme and plugin file editing from the dashboard.
	 */
	private static function check_file_editing() {
		$blocked = defined( 'DISALLOW_FILE_EDIT' ) && DISALLOW_FILE_EDIT;

		return self::result(
			[
				'id'       => 'file_editing',
				'label'    => __( 'Dashboard file editor', 'ablocks' ),
				'category' => 'server',
				'status'   => $blocked ? self::GOOD : self::WARNING,
				'summary'  => $blocked ? __( 'Disabled', 'ablocks' ) : __( 'Theme and plugin files can be edited from the dashboard', 'ablocks' ),
				'why'      => $blocked
					? ''
					: __( 'If an administrator account is ever compromised, the built-in editor lets the attacker run any code they like by pasting it into a theme file. It is also an easy way to break a live site with a typo and no undo.', 'ablocks' ),
				'fix'      => $blocked ? '' : __( 'Add define( \'DISALLOW_FILE_EDIT\', true ); to wp-config.php. Edit files over SFTP or through version control instead.', 'ablocks' ),
			]
		);
	}

	/**
	 * Expired transients left in the options table.
	 */
	private static function check_expired_transients() {
		global $wpdb;

		$expired = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$wpdb->options}
				 WHERE option_name LIKE %s AND option_value < %d",
				$wpdb->esc_like( '_transient_timeout_' ) . '%',
				time()
			)
		);

		if ( $expired < 200 ) {
			return [];
		}

		return self::result(
			[
				'id'       => 'expired_transients',
				'label'    => __( 'Expired temporary data', 'ablocks' ),
				'category' => 'server',
				'status'   => self::INFO,
				/* translators: %d: number of expired transients. */
				'summary'  => sprintf( __( '%d expired temporary records still stored', 'ablocks' ), $expired ),
				'why'      => __( 'WordPress only clears these when something asks for them again, so they can accumulate for years. They rarely slow pages down, but they inflate every database backup.', 'ablocks' ),
				'fix'      => __( 'Most database cleanup plugins remove expired transients safely. Take a backup first.', 'ablocks' ),
			]
		);
	}

	/**
	 * Unbounded post revisions.
	 */
	private static function check_revisions() {
		global $wpdb;

		$count = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'revision'" );

		if ( $count < 500 ) {
			return [];
		}

		return self::result(
			[
				'id'       => 'revisions',
				'label'    => __( 'Stored post revisions', 'ablocks' ),
				'category' => 'server',
				'status'   => self::INFO,
				/* translators: %d: number of revisions. */
				'summary'  => sprintf( __( '%d revisions stored', 'ablocks' ), $count ),
				'why'      => __( 'Every save keeps a full copy of the post. Over years this can become the largest table in the database, which slows backups and some queries. It does not usually slow down page loads.', 'ablocks' ),
				'fix'      => __( 'Add define( \'WP_POST_REVISIONS\', 10 ); to wp-config.php to cap future revisions. Clearing old ones is safe but should follow a backup.', 'ablocks' ),
			]
		);
	}
}
