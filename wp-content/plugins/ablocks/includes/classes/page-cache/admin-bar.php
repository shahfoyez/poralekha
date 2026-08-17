<?php
namespace ABlocks\Classes\PageCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Performance\FragmentCache;
use ABlocks\Performance\TemplateCache;

/**
 * Page Cache — toolbar controls.
 *
 * Cache entries are built lazily, on the first visit to each URL, so the
 * question an editor actually has while looking at a page is "is *this* page
 * cached, and can I rebuild it right now?". Answering that from the toolbar is
 * far more direct than a settings screen, which can only ever talk about the
 * cache in aggregate.
 *
 * The node reports the current page's state and offers, per page, a purge and a
 * rebuild — plus a purge-everything for the whole site.
 *
 * Nothing here can be served stale: logged-out visitors are the only ones who
 * receive cached HTML, so a logged-in user with a toolbar is by definition
 * looking at a freshly rendered page.
 */
class AdminBar {

	const ACTION = 'ablocks_cache_action';
	const NOTICE_TRANSIENT = 'ablocks_cache_notice_';

	public static function init() {
		add_action( 'admin_bar_menu', [ __CLASS__, 'menu' ], 100 );
		add_action( 'admin_post_' . self::ACTION, [ __CLASS__, 'handle' ] );
	}

	/**
	 * Who may operate these controls.
	 *
	 * @return bool
	 */
	public static function user_can() {
		$capability = (string) apply_filters( 'ablocks/perf/page_cache/manage_capability', 'manage_options' );
		return current_user_can( $capability );
	}

	/**
	 * Build the toolbar node.
	 *
	 * @param \WP_Admin_Bar $bar Toolbar instance.
	 */
	public static function menu( $bar ) {
		if ( ! is_admin_bar_showing() || ! self::user_can() ) {
			return;
		}
		if ( ! Rules::is_enabled() ) {
			return;
		}

		$url    = self::current_url();
		$cached = $url ? self::is_cached( $url ) : false;

		$bar->add_node(
			[
				'id'    => 'ablocks-cache',
				'title' => __( 'aBlocks Cache', 'ablocks' ),
				'href'  => admin_url( 'admin.php?page=ablocks-settings&path=performance&sub=caching' ),
				'meta'  => [ 'title' => __( 'aBlocks Cache', 'ablocks' ) ],
			]
		);

		// The current page's state moves into the submenu rather than the top
		// label, which stays a stable product name. A toolbar item whose text
		// changes as you browse is hard to aim at and easy to misread.
		if ( $url ) {
			$bar->add_node(
				[
					'id'     => 'ablocks-cache-state',
					'parent' => 'ablocks-cache',
					'title'  => self::node_title( $url, $cached ),
					'meta'   => [ 'class' => 'ablocks-cache-state' ],
				]
			);
		}

		$notice = get_transient( self::NOTICE_TRANSIENT . get_current_user_id() );
		if ( $notice ) {
			delete_transient( self::NOTICE_TRANSIENT . get_current_user_id() );
			$bar->add_node(
				[
					'id'     => 'ablocks-cache-notice',
					'parent' => 'ablocks-cache',
					'title'  => esc_html( $notice ),
				]
			);
		}

		if ( $url ) {
			// Only offered when there is something to remove, so the menu never
			// presents an action that would do nothing.
			if ( $cached ) {
				$bar->add_node(
					[
						'id'     => 'ablocks-cache-purge-current',
						'parent' => 'ablocks-cache',
						'title'  => __( 'Clear cache for this page', 'ablocks' ),
						'href'   => self::action_url( 'purge_current', $url ),
					]
				);
			}

			$bar->add_node(
				[
					'id'     => 'ablocks-cache-warm-current',
					'parent' => 'ablocks-cache',
					'title'  => $cached
						? __( 'Rebuild this page', 'ablocks' )
						: __( 'Cache this page now', 'ablocks' ),
					'href'   => self::action_url( 'warm_current', $url ),
				]
			);
		}//end if

		$bar->add_node(
			[
				'id'     => 'ablocks-cache-purge-all',
				'parent' => 'ablocks-cache',
				'title'  => __( 'Clear entire cache', 'ablocks' ),
				'href'   => self::action_url( 'purge_all' ),
				'meta'   => [
					'onclick' => "return confirm('" . esc_js( __( 'Clear the cache for the whole site? Pages rebuild as visitors request them.', 'ablocks' ) ) . "');",
				],
			]
		);

		$bar->add_node(
			[
				'id'     => 'ablocks-cache-settings',
				'parent' => 'ablocks-cache',
				'title'  => __( 'Cache settings', 'ablocks' ),
				'href'   => admin_url( 'admin.php?page=ablocks-settings&path=performance&sub=caching' ),
			]
		);
	}

	/**
	 * Run the requested action, then return the user where they came from.
	 */
	public static function handle() {
		if ( ! self::user_can() ) {
			wp_die( esc_html__( 'You are not allowed to manage the cache.', 'ablocks' ), 403 );
		}

		$do = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
		check_admin_referer( self::ACTION . '_' . $do );

		$target = isset( $_GET['target'] ) ? esc_url_raw( wp_unslash( $_GET['target'] ) ) : '';
		$target = self::same_origin( $target ) ? $target : '';

		switch ( $do ) {
			case 'purge_current':
				$message = $target && Store::delete_url( $target )
					? __( 'Cache cleared for this page.', 'ablocks' )
					: __( 'This page was not cached.', 'ablocks' );
				break;

			case 'warm_current':
				if ( $target ) {
					// Purge first so the request rebuilds rather than being a
					// no-op against an entry that already exists.
					Store::delete_url( $target );
					Scheduler::warm_url( $target );
					$message = __( 'Rebuilding this page in the background.', 'ablocks' );
				} else {
					$message = __( 'Nothing to rebuild.', 'ablocks' );
				}
				break;

			case 'purge_all':
				$removed = Store::flush();
				FragmentCache::bump_version();
				TemplateCache::maybe_bump_version();
				/* translators: %d: number of files removed. */
				$message = sprintf( __( 'Cache cleared (%d files removed).', 'ablocks' ), (int) $removed );
				break;

			default:
				$message = '';
		}//end switch

		if ( $message ) {
			// Carried in a short-lived transient rather than a query argument:
			// any unrecognised query arg makes the destination uncacheable, so a
			// redirect that advertised the result would quietly prevent the very
			// page just rebuilt from being cached again.
			set_transient( self::NOTICE_TRANSIENT . get_current_user_id(), $message, 60 );
		}

		wp_safe_redirect( self::return_url( $target ) );
		exit;
	}

	/**
	 * Submenu label reporting whether the page being viewed is cached.
	 *
	 * @param string $url    Current URL, if any.
	 * @param bool   $cached Whether it is cached.
	 * @return string
	 */
	private static function node_title( $url, $cached ) {
		if ( ! $url ) {
			return '';
		}

		return $cached
			? __( 'This page: cached', 'ablocks' )
			: __( 'This page: not cached', 'ablocks' );
	}

	/**
	 * Is there a cache entry for a URL?
	 *
	 * @param string $url URL to check.
	 * @return bool
	 */
	private static function is_cached( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return false;
		}
		$path = isset( $parts['path'] ) ? $parts['path'] : '/';
		$file = Store::file_path( $parts['host'], $path, Store::current_variant() );

		return $file && file_exists( $file );
	}

	/**
	 * The page these controls act on.
	 *
	 * On the frontend that is the page being viewed. In the admin there is no
	 * such page, except on a post editor screen, where the post being edited is
	 * unambiguously what the user means.
	 *
	 * @return string Absolute URL, or '' when there is no meaningful target.
	 */
	private static function current_url() {
		if ( ! is_admin() ) {
			if ( is_singular() || is_home() || is_front_page() || is_archive() ) {
				$path = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
				$path = is_string( $path ) ? (string) strtok( $path, '?' ) : '/';

				// Built from the host rather than home_url( $path ), because
				// REQUEST_URI already contains any subdirectory the site is
				// installed in — passing it to home_url() would repeat that
				// segment and produce /blog/blog/page/ on a subdirectory install.
				$host = wp_parse_url( home_url(), PHP_URL_HOST );
				if ( ! $host ) {
					return '';
				}
				$port = wp_parse_url( home_url(), PHP_URL_PORT );

				return ( is_ssl() ? 'https://' : 'http://' ) . $host . ( $port ? ':' . $port : '' ) . $path;
			}
			return '';
		}

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && 'post' === $screen->base ) {
			// Reading which post the editor screen is showing, to label the menu.
			// Nothing is written, and the actions themselves are nonce-checked in
			// handle().
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Screen context only; no state is changed here.
			$post_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
			if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
				$permalink = get_permalink( $post_id );
				return $permalink ? $permalink : '';
			}
		}

		return '';
	}

	/**
	 * Build a nonced action URL.
	 *
	 * @param string $do     Action key.
	 * @param string $target Optional URL the action applies to.
	 * @return string
	 */
	private static function action_url( $do, $target = '' ) {
		$args = [
			'action' => self::ACTION,
			'do'     => $do,
		];
		if ( $target ) {
			$args['target'] = rawurlencode( $target );
		}

		return wp_nonce_url(
			add_query_arg( $args, admin_url( 'admin-post.php' ) ),
			self::ACTION . '_' . $do
		);
	}

	/**
	 * Where to send the user afterwards.
	 *
	 * @param string $target Action target.
	 * @return string
	 */
	private static function return_url( $target ) {
		$referer = wp_get_referer();
		if ( $referer && self::same_origin( $referer ) ) {
			return $referer;
		}
		if ( $target ) {
			return $target;
		}
		return admin_url();
	}

	/**
	 * Does a URL belong to this site?
	 *
	 * Both the redirect target and the action target come from the request, so
	 * neither is trusted: one could send a user off-site, the other could make
	 * the server fetch an arbitrary host.
	 *
	 * @param string $url Candidate URL.
	 * @return bool
	 */
	private static function same_origin( $url ) {
		if ( empty( $url ) ) {
			return false;
		}
		$home = wp_parse_url( home_url() );
		$want = wp_parse_url( $url );

		if ( empty( $want['host'] ) || empty( $home['host'] ) ) {
			return false;
		}

		return strtolower( $want['host'] ) === strtolower( $home['host'] );
	}
}
