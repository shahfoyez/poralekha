<?php
namespace ABlocks\Classes\PageCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;

/**
 * Page Cache — the single source of truth for "may this request be cached?".
 *
 * Split into two layers on purpose:
 *
 * - {@see Rules::request_bypass_reason()} uses **superglobals only**. All three
 *   serve tiers (plugins_loaded, the advanced-cache.php drop-in, and the nginx
 *   rule) run before pluggable functions exist, so this layer must never call
 *   is_user_logged_in() or any template conditional. Writing it once against
 *   $_SERVER/$_COOKIE keeps every tier making the identical decision — if they
 *   ever disagree, a logged-in visitor gets served an anonymous page.
 *
 * - {@see Rules::response_bypass_reason()} is WordPress-aware and runs only at
 *   write time, once the query and the response are known.
 *
 * Both return a short machine-readable reason string (or null when cacheable)
 * rather than a bool, so `wp ablocks cache status` and the debug header can say
 * *why* a page is not being cached. That is the difference between a five-minute
 * and a two-hour support thread.
 *
 * Security note: these rules are not merely a correctness feature. Cached files
 * live under uploads/ and are web-reachable, so the invariant "only ever cache
 * the fully-anonymous view of an already-public URL" is what makes that safe.
 * Weakening any check below is a security change. See docs/PAGE-CACHE-PLAN.md §3.2.
 */
class Rules {

	/**
	 * Cookie name prefixes that mean "this response is personalised".
	 *
	 * Matched as prefixes because WordPress suffixes most of these with a hash
	 * of the site URL (wordpress_logged_in_a1b2c3...).
	 */
	const BYPASS_COOKIE_PREFIXES = [
		// WordPress core: authenticated session, post password, comment author.
		'wordpress_logged_in_',
		'wordpressuser_',
		'wordpresspass_',
		'wp-postpass_',
		'comment_author_',
		'comment_author_email_',
		// WordPress core: "your comment is awaiting moderation" needs a fresh render.
		'wp-resetpass-',
		// StoreEngine / WooCommerce: a cart cookie means the header cart count,
		// and usually the page itself, differs per visitor.
		'storeengine_cart',
		'storeengine_session',
		'woocommerce_items_in_cart',
		'woocommerce_cart_hash',
		'wp_woocommerce_session_',
	];

	/**
	 * Reasons that came from the last evaluation, for debugging output.
	 *
	 * @var string|null
	 */
	private static $last_reason = null;

	/**
	 * May this *request* be served from, or written to, the cache?
	 *
	 * Superglobals only — see the class docblock.
	 *
	 * @return string|null Bypass reason, or null when the request is cacheable.
	 */
	public static function request_bypass_reason() {
		if ( ! self::is_enabled() ) {
			return self::remember( 'disabled' );
		}

		// Only GET. HEAD is deliberately excluded: serving a body for HEAD is
		// wrong, and the win is nil.
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		if ( 'GET' !== $method ) {
			return self::remember( 'method:' . strtolower( $method ) );
		}

		// A POST body on a GET is malformed; treat it as uncacheable rather than
		// reasoning about it. Nothing here reads a value — only whether the
		// superglobal is populated at all — so there is no input to verify or
		// sanitize, and a nonce check would be meaningless on an anonymous
		// cacheable request.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Presence check only; no form data is read or acted on.
		if ( ! empty( $_POST ) ) {
			return self::remember( 'post-data' );
		}

		$cookie_reason = self::bypass_cookie_reason();
		if ( null !== $cookie_reason ) {
			return self::remember( $cookie_reason );
		}

		$query_reason = self::query_bypass_reason();
		if ( null !== $query_reason ) {
			return self::remember( $query_reason );
		}

		if ( self::is_excluded_url() ) {
			return self::remember( 'excluded-url' );
		}

		return self::remember( null );
	}

	/**
	 * Convenience boolean wrapper around {@see Rules::request_bypass_reason()}.
	 */
	public static function should_bypass_request() {
		return null !== self::request_bypass_reason();
	}

	/**
	 * May this *response* be written to the cache?
	 *
	 * WordPress-aware; safe to call only at write time (shutdown), when the main
	 * query has run and the status code is final.
	 *
	 * @return string|null Bypass reason, or null when the response is cacheable.
	 */
	public static function response_bypass_reason() {
		// Everything the request-level layer rejects, the response layer does too.
		$request_reason = self::request_bypass_reason();
		if ( null !== $request_reason ) {
			return $request_reason;
		}

		// Contexts that never produce a cacheable public page.
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return self::remember( 'admin-context' );
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return self::remember( 'rest-request' );
		}
		if ( defined( 'WP_CLI' ) && \WP_CLI ) {
			return self::remember( 'wp-cli' );
		}
		if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
			return self::remember( 'xmlrpc' );
		}

		// The de-facto standard opt-out other plugins set when they know a
		// response is personalised. Respecting it is what makes us a good citizen.
		if ( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE ) {
			return self::remember( 'donotcachepage' );
		}

		// Belt and braces: the cookie check above catches logged-in visitors
		// without loading pluggable functions, but by write time we can ask
		// properly, and a session could have been established mid-request.
		if ( is_user_logged_in() ) {
			return self::remember( 'logged-in' );
		}

		// Never freeze a non-200 into a file that a later request would serve as 200.
		$status = self::response_status();
		if ( 200 !== $status ) {
			return self::remember( 'status:' . $status );
		}

		// Query types that are either personalised, unbounded, or not worth caching.
		if ( is_preview() ) {
			return self::remember( 'preview' );
		}
		if ( is_404() ) {
			return self::remember( '404' );
		}
		if ( is_search() ) {
			return self::remember( 'search' );
		}
		if ( is_feed() || is_robots() || is_trackback() ) {
			return self::remember( 'non-html' );
		}
		if ( function_exists( 'is_embed' ) && is_embed() ) {
			return self::remember( 'embed' );
		}
		if ( is_customize_preview() ) {
			return self::remember( 'customizer' );
		}

		// Password-protected content: the unlocked view must never reach disk.
		if ( is_singular() ) {
			$post = get_post();
			if ( $post instanceof \WP_Post ) {
				if ( 'publish' !== $post->post_status ) {
					return self::remember( 'status:' . $post->post_status );
				}
				if ( ! empty( $post->post_password ) || post_password_required( $post ) ) {
					return self::remember( 'password-protected' );
				}
			}
		}

		// A response that sets a cookie is establishing per-visitor state, so the
		// body almost certainly depends on it. headers_list() catches raw header()
		// calls that never touch $_COOKIE.
		if ( self::response_sets_cookie() ) {
			return self::remember( 'sets-cookie' );
		}

		$scope_reason = self::scope_bypass_reason();
		if ( null !== $scope_reason ) {
			return self::remember( $scope_reason );
		}

		$host_reason = self::host_bypass_reason();
		if ( null !== $host_reason ) {
			return self::remember( $host_reason );
		}

		// Logged-in editors previewing the frontend are excluded from every other
		// Performance Suite feature (see DelayJs); stay consistent. This is
		// unreachable while the is_user_logged_in() check above stands, but both
		// are cheap and the intent should survive future edits to either.
		if ( is_user_logged_in() && current_user_can( 'edit_posts' )
			&& (bool) apply_filters( 'ablocks/perf/bypass_optimizations_for_editors', true ) ) {
			return self::remember( 'editor' );
		}

		return self::remember( null );
	}

	/**
	 * Is the response body itself cacheable?
	 *
	 * Guards against freezing a truncated page — a fatal error, an uncaught
	 * exception or a bare exit() mid-render produces output that looks fine to
	 * every check above but is missing its closing tags. Caching that would serve
	 * a broken page to everyone until the next purge, which is the single worst
	 * failure mode this feature has.
	 *
	 * @param string $html Buffered output.
	 * @return string|null Bypass reason, or null when the body is cacheable.
	 */
	public static function body_bypass_reason( $html ) {
		if ( strlen( $html ) < 255 ) {
			return self::remember( 'body-too-short' );
		}
		if ( false === stripos( $html, '</html>' ) ) {
			return self::remember( 'body-truncated' );
		}
		return self::remember( null );
	}

	/**
	 * The last bypass reason recorded, for debug headers and CLI output.
	 *
	 * @return string|null
	 */
	public static function last_reason() {
		return self::$last_reason;
	}

	/**
	 * Is the page cache switched on?
	 */
	public static function is_enabled() {
		$enabled = (bool) Helper::get_settings( 'perf_page_cache', false );
		return (bool) apply_filters( 'ablocks/perf/perf_page_cache', $enabled );
	}

	/**
	 * Cookie prefixes that force a bypass. Filterable so a site can add its own
	 * personalisation cookie (membership plugins, geo redirectors, A/B tools).
	 *
	 * @return string[]
	 */
	public static function bypass_cookie_prefixes() {
		return (array) apply_filters( 'ablocks/perf/page_cache/bypass_cookies', self::BYPASS_COOKIE_PREFIXES );
	}

	/**
	 * Query args that may appear without disabling the cache.
	 *
	 * Deliberately empty by default: any unrecognised query string bypasses. The
	 * alternative — ignoring unknown args — lets ?utm_source=x overwrite the
	 * canonical entry for a URL, which is cache poisoning by typo.
	 *
	 * @return string[]
	 */
	public static function allowed_query_args() {
		$allowed = (array) Helper::get_settings( 'perf_page_cache_query_args', [] );
		return array_filter( array_map( 'strval', (array) apply_filters( 'ablocks/perf/page_cache/allowed_query_args', $allowed ) ) );
	}

	/**
	 * Is the request's Host header one this site actually answers to?
	 *
	 * HTTP_HOST is attacker-controlled. Store::sanitize_host() already guarantees
	 * containment — a spoofed `Host: ../../evil` cannot escape the cache
	 * directory — but containment alone still lets an attacker create an
	 * unbounded number of junk directories by varying the header, which is a
	 * slow disk-fill. It also has no legitimate use: a request for a host we do
	 * not serve should not populate the cache.
	 *
	 * Checked at write time only. Writing is the sole operation that creates
	 * directories, so gating it here closes the vector without adding a database
	 * read to the serve path (which must stay callable before WordPress loads).
	 * A serve-time request for an unknown host simply finds no file.
	 *
	 * @return string|null
	 */
	private static function host_bypass_reason() {
		$request_host = isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '';
		$request_host = self::normalize_host( is_string( $request_host ) ? $request_host : '' );

		if ( '' === $request_host ) {
			return 'host-missing';
		}

		$allowed = [];
		foreach ( [ home_url(), site_url() ] as $known ) {
			$parts = wp_parse_url( $known );
			if ( ! empty( $parts['host'] ) ) {
				$allowed[] = self::normalize_host( $parts['host'] );
			}
		}

		// Sites behind a proxy, CDN or domain alias legitimately serve more than
		// one host; they add theirs here rather than losing the cache entirely.
		$allowed = array_filter( array_map( [ __CLASS__, 'normalize_host' ], (array) apply_filters( 'ablocks/perf/page_cache/allowed_hosts', $allowed ) ) );

		if ( ! in_array( $request_host, $allowed, true ) ) {
			return 'host-mismatch';
		}

		return null;
	}

	/**
	 * Lowercase a host and drop any port, for comparison purposes.
	 *
	 * The port is deliberately kept in the *directory* name by Store, so that a
	 * :8080 dev site cannot collide with production; it is only stripped here,
	 * where the question is which site the request is for.
	 *
	 * @param string $host Raw host, possibly with a port.
	 * @return string
	 */
	public static function normalize_host( $host ) {
		$host = strtolower( trim( (string) $host ) );
		$host = preg_replace( '/:\d+$/', '', $host );
		return (string) $host;
	}

	/**
	 * Does the configured coverage scope exclude this page?
	 *
	 * Default scope is `all`, which is what anyone flipping a switch labelled
	 * "Page Cache" expects. `ablocks_only` is the conservative mode: cache just
	 * the pages this plugin actually renders, so the blast radius is limited to
	 * content aBlocks is responsible for.
	 *
	 * Only singular content can be judged this way — archives and the front page
	 * assemble many posts plus template parts, so a content scan there would be
	 * both expensive and wrong. Those are cached under either scope.
	 *
	 * @return string|null
	 */
	private static function scope_bypass_reason() {
		$scope = (string) Helper::get_settings( 'perf_page_cache_scope', 'all' );
		$scope = (string) apply_filters( 'ablocks/perf/page_cache/scope', $scope );

		if ( 'ablocks_only' !== $scope || ! is_singular() ) {
			return null;
		}

		$post = get_post();
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}

		// `wp:block` counts: a reusable block may wrap aBlocks blocks, and the
		// same allowance is made in Blocks::prewarm_page_assets().
		$content = (string) $post->post_content;
		if ( false === strpos( $content, 'wp:ablocks' ) && false === strpos( $content, 'wp:block' ) ) {
			return 'scope:no-ablocks-blocks';
		}

		return null;
	}

	/**
	 * Does a bypass cookie exist on this request?
	 *
	 * @return string|null
	 */
	private static function bypass_cookie_reason() {
		if ( empty( $_COOKIE ) || ! is_array( $_COOKIE ) ) {
			return null;
		}
		$prefixes = self::bypass_cookie_prefixes();
		foreach ( array_keys( $_COOKIE ) as $name ) {
			$name = (string) $name;
			foreach ( $prefixes as $prefix ) {
				if ( 0 === strpos( $name, $prefix ) ) {
					// The reason names the prefix, never the cookie value.
					return 'cookie:' . $prefix;
				}
			}
		}
		return null;
	}

	/**
	 * Does the query string disqualify this request?
	 *
	 * @return string|null
	 */
	private static function query_bypass_reason() {
		// Only argument *names* are inspected, never values, and nothing is acted
		// on beyond declining to cache — so there is no input to sanitize and a
		// nonce would be meaningless on an anonymous cacheable request.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Reads query-arg names only, to decide cacheability.
		if ( empty( $_GET ) || ! is_array( $_GET ) ) {
			return null;
		}
		$allowed = self::allowed_query_args();
		foreach ( array_keys( $_GET ) as $arg ) {
			if ( ! in_array( (string) $arg, $allowed, true ) ) {
				return 'query-arg';
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		return null;
	}

	/**
	 * Does the request path match a user-configured exclusion pattern?
	 *
	 * Patterns are simple wildcards (`/shop/*`), not regular expressions — a
	 * malformed regex in a settings field would otherwise take the site down.
	 */
	private static function is_excluded_url() {
		$patterns = (array) Helper::get_settings( 'perf_page_cache_exclusions', [] );
		$patterns = (array) apply_filters( 'ablocks/perf/page_cache/exclusions', $patterns );
		if ( empty( $patterns ) ) {
			return false;
		}
		$path = self::request_path();
		foreach ( $patterns as $pattern ) {
			$pattern = trim( (string) $pattern );
			if ( '' === $pattern ) {
				continue;
			}
			if ( fnmatch( $pattern, $path ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * The current request path, without query string, always leading-slashed.
	 *
	 * @return string
	 */
	public static function request_path() {
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$uri = is_string( $uri ) ? $uri : '/';
		$path = (string) strtok( $uri, '?' );
		if ( '' === $path || '/' !== $path[0] ) {
			$path = '/' . $path;
		}
		return $path;
	}

	/**
	 * Final HTTP status for this response.
	 *
	 * @return int
	 */
	private static function response_status() {
		$status = function_exists( 'http_response_code' ) ? http_response_code() : 200;
		return is_int( $status ) ? $status : 200;
	}

	/**
	 * Has anything queued a Set-Cookie header on this response?
	 */
	private static function response_sets_cookie() {
		if ( ! function_exists( 'headers_list' ) ) {
			return false;
		}
		foreach ( headers_list() as $header ) {
			if ( 0 === stripos( $header, 'set-cookie:' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Record and return a reason, so callers can chain `return self::remember(...)`.
	 *
	 * @param string|null $reason Bypass reason or null.
	 * @return string|null
	 */
	private static function remember( $reason ) {
		self::$last_reason = $reason;
		return $reason;
	}
}
