<?php
namespace ABlocks\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;
use ABlocks\Classes\CacheBackend;

/**
 * Performance Suite — cache block template resolution.
 *
 * Resolving which block template renders a URL means scanning the theme's
 * templates directory and querying the `wp_template` / `wp_template_part` post
 * types, on every request. Profiled on this install it costs 10.8–14.2 ms and
 * 7–10 database queries per request — roughly 4–5% of request time but about
 * 10% of all queries, and it produces the same answer until a template or the
 * theme changes.
 *
 * ## Requires a persistent object cache, by measurement
 *
 * The obvious implementation — store the resolved list in a transient — was
 * built and measured, and it made the site *slower*: 81 queries per request
 * with the cache warm against 75 with it off. Transients live in `wp_options`,
 * so each lookup trades 7–10 template queries for its own option reads plus
 * unserializing large WP_Block_Template objects, and the exchange does not pay.
 *
 * With Redis or Memcached the same lookup involves no database at all and the
 * trade clearly wins, so this feature refuses to run without one rather than
 * quietly costing sites performance while claiming to save it. On a site with
 * no persistent object cache the honest answer is that this work is already
 * cheap enough.
 *
 * Frontend only. The site editor calls the same functions and must always see
 * live data, so admin, REST and CLI contexts are left untouched.
 */
class TemplateCache {

	const VERSION_OPTION = 'ablocks_template_cache_version';
	const TRANSIENT_PREFIX = 'ablocks_tmpl_';
	const DEFAULT_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Per-request memo, so repeated lookups avoid even the transient read.
	 *
	 * @var array<string, array>
	 */
	private $memo = [];

	public static function init() {
		$self = new self();

		// Registered unconditionally so the version keeps advancing even while
		// the feature is off; otherwise enabling it later could serve templates
		// resolved before an edit.
		foreach ( [ 'save_post', 'deleted_post', 'switch_theme', 'customize_save_after' ] as $hook ) {
			add_action( $hook, [ __CLASS__, 'maybe_bump_version' ], 10, 2 );
		}

		if ( ! self::is_enabled() ) {
			return;
		}

		add_filter( 'pre_get_block_templates', [ $self, 'serve' ], 10, 3 );
		add_filter( 'get_block_templates', [ $self, 'store' ], PHP_INT_MAX, 3 );
	}

	/**
	 * Is caching active for this request?
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		$enabled = (bool) apply_filters(
			'ablocks/perf/perf_template_cache',
			(bool) Helper::get_settings( 'perf_template_cache', false )
		);
		if ( ! $enabled ) {
			return false;
		}

		// See the class docblock: without a persistent object cache this costs
		// more queries than it saves, so it declines to run rather than making
		// the site slower.
		if ( ! CacheBackend::is_persistent() ) {
			return false;
		}

		// The site editor resolves templates through these same functions and
		// must never be handed a memoised list, or edits appear not to save.
		if ( is_admin() || wp_doing_ajax() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) || ( defined( 'WP_CLI' ) && \WP_CLI ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Advance the generation when something that affects resolution changes.
	 *
	 * Narrower than the fragment cache's equivalent: only template-shaped post
	 * types alter which template a URL resolves to, so an ordinary post save
	 * should not throw the cache away.
	 *
	 * @param int          $post_id Post id.
	 * @param \WP_Post|null $post   Post object, when the hook provides one.
	 */
	public static function maybe_bump_version( $post_id = 0, $post = null ) {
		$relevant = [ 'wp_template', 'wp_template_part', 'wp_global_styles', 'wp_navigation' ];

		if ( $post_id && ! empty( $post ) ) {
			$type = $post instanceof \WP_Post ? $post->post_type : get_post_type( $post_id );
			if ( $type && ! in_array( $type, $relevant, true ) ) {
				return;
			}
		}

		CacheBackend::bump_generation( self::VERSION_OPTION );
	}

	/**
	 * Return a cached template list, if one exists for this query.
	 *
	 * @param \WP_Block_Template[]|null $pre           Short-circuit value.
	 * @param array                     $query         Query args.
	 * @param string                    $template_type Post type being queried.
	 * @return \WP_Block_Template[]|null
	 */
	public function serve( $pre, $query, $template_type ) {
		if ( null !== $pre ) {
			return $pre;
		}

		$key = $this->cache_key( $query, $template_type );

		if ( isset( $this->memo[ $key ] ) ) {
			return $this->memo[ $key ];
		}

		$cached = CacheBackend::get( $key, false );
		if ( is_array( $cached ) && $this->is_valid_payload( $cached ) ) {
			$this->memo[ $key ] = $cached;
			return $cached;
		}

		return $pre;
	}

	/**
	 * Store a freshly resolved template list.
	 *
	 * @param \WP_Block_Template[] $templates     Resolved templates.
	 * @param array                $query         Query args.
	 * @param string               $template_type Post type being queried.
	 * @return \WP_Block_Template[]
	 */
	public function store( $templates, $query, $template_type ) {
		if ( ! is_array( $templates ) || ! $this->is_valid_payload( $templates ) ) {
			return $templates;
		}

		$key = $this->cache_key( $query, $template_type );

		// Already served from cache this request; storing again is pure cost.
		if ( isset( $this->memo[ $key ] ) ) {
			return $templates;
		}

		$this->memo[ $key ] = $templates;

		$ttl = (int) apply_filters(
			'ablocks/perf/template_cache/ttl',
			(int) Helper::get_settings( 'perf_template_cache_ttl', self::DEFAULT_TTL )
		);

		CacheBackend::set( $key, $templates, $ttl, false );

		return $templates;
	}

	/**
	 * Is this a list of genuine template objects?
	 *
	 * Guards both directions: never store something unexpected, and never serve
	 * a payload that a plugin update or a partial write has left malformed.
	 *
	 * @param array $templates Candidate payload.
	 * @return bool
	 */
	private function is_valid_payload( $templates ) {
		foreach ( $templates as $template ) {
			if ( ! $template instanceof \WP_Block_Template ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Transient key for a template query.
	 *
	 * @param array  $query         Query args.
	 * @param string $template_type Post type being queried.
	 * @return string
	 */
	private function cache_key( $query, $template_type ) {
		$parts = [
			(string) $template_type,
			wp_json_encode( $query ),
			get_stylesheet(),
			get_template(),
			CacheBackend::generation( self::VERSION_OPTION ),
		];

		return self::TRANSIENT_PREFIX . md5( implode( '|', $parts ) );
	}
}
