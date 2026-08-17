<?php
namespace ABlocks\Performance;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;
use ABlocks\Classes\CacheBackend;

/**
 * Performance Suite — fragment cache for FSE template parts.
 *
 * A block theme re-renders the header and footer through the full block
 * pipeline on every request, including on requests the page cache cannot help:
 * cache misses, the first visitor after a purge, and logged-in traffic. Caching
 * the rendered HTML of `core/template-part` covers exactly that gap.
 *
 * ## The hard part is side effects, not the HTML
 *
 * Rendering a block does more than return markup. It enqueues stylesheets and
 * scripts, it enqueues *script modules* through a registry entirely separate
 * from wp_scripts(), and it pushes block-support rules into the style engine
 * store that core later prints as `core-block-supports-inline-css`.
 * Short-circuiting the render skips all of that, so a naive fragment cache
 * serves correct-looking HTML with missing CSS and dead JavaScript — on the
 * second request only, which makes it very hard to attribute.
 *
 * Measured here, a first attempt that captured only style and script handles
 * produced a warm page missing the Interactivity API import map, both module
 * preloads and all three script-module tags: a navigation block whose mobile
 * menu rendered perfectly and no longer opened.
 *
 * So a stored fragment records four things — style handles, script handles,
 * script-module ids, and the block-support rules its render contributed — and
 * serving a fragment replays all four.
 *
 * This is why the feature ships default-off and why its correctness is asserted
 * by comparing whole rendered pages with the cache cold and warm, rather than
 * by reasoning about which side effects exist. See docs/PAGE-CACHE-PLAN.md.
 *
 * ## Personalisation
 *
 * Only logged-out renders are cached by default. A header can legitimately
 * contain a user's name, avatar or cart count, and there is no general way to
 * detect that from the outside, so sharing one fragment across logged-in users
 * would leak. Sites that know their header is user-invariant can opt in via
 * `ablocks/perf/fragment_cache/should_cache`.
 */
class FragmentCache {

	const VERSION_OPTION = 'ablocks_fragment_version';
	const TRANSIENT_PREFIX = 'ablocks_frag_';
	const DEFAULT_TTL = 12 * HOUR_IN_SECONDS;

	/**
	 * Maximum fragment size worth storing, in bytes.
	 */
	const MAX_BYTES = 512000;

	/**
	 * Snapshots taken at pre-render, keyed by block signature, awaiting store.
	 *
	 * @var array<string, array>
	 */
	private $pending = [];

	public static function init() {
		if ( is_admin() ) {
			return;
		}

		$self = new self();

		// Invalidation is registered unconditionally: the version must keep
		// advancing even while the feature is off, or switching it back on could
		// serve fragments built before an edit.
		foreach ( [ 'save_post', 'deleted_post', 'switch_theme', 'wp_update_nav_menu', 'customize_save_after', 'edited_term' ] as $hook ) {
			add_action( $hook, [ __CLASS__, 'bump_version' ] );
		}

		$enabled = (bool) apply_filters(
			'ablocks/perf/perf_fragment_cache',
			(bool) Helper::get_settings( 'perf_fragment_cache', false )
		);
		if ( ! $enabled ) {
			return;
		}

		// Fail closed. Interactive core blocks (Navigation above all) load their
		// behaviour through the script-module registry, which is separate from
		// wp_scripts(). Without a way to observe that queue we cannot replay it,
		// and a fragment served without it renders correct markup with no
		// JavaScript — a mobile menu that silently stops opening. A slower site
		// is strictly better than a broken one.
		if ( ! self::can_track_script_modules() ) {
			return;
		}

		add_filter( 'pre_render_block', [ $self, 'maybe_serve' ], 10, 2 );
		add_filter( 'render_block', [ $self, 'maybe_store' ], PHP_INT_MAX, 2 );
	}

	/**
	 * Advance the fragment generation, invalidating everything at once.
	 *
	 * A monotonic counter in the key beats deleting transients: it is one option
	 * write regardless of how many fragments exist, and it cannot half-complete.
	 *
	 * Deliberately coarse. `save_post` bumps too, because a template part may
	 * contain a query loop whose output depends on published content, and
	 * silently serving a stale one is worse than a lower hit rate.
	 */
	public static function bump_version() {
		CacheBackend::bump_generation( self::VERSION_OPTION );
	}

	/**
	 * Serve a cached fragment, replaying the side effects its render had.
	 *
	 * @param string|null $pre_render   Short-circuit value.
	 * @param array       $parsed_block Parsed block.
	 * @return string|null
	 */
	public function maybe_serve( $pre_render, $parsed_block ) {
		if ( null !== $pre_render || ! $this->is_cacheable_block( $parsed_block ) ) {
			return $pre_render;
		}

		$key    = $this->cache_key( $parsed_block );
		$cached = CacheBackend::get( $key );

		if ( is_array( $cached ) && isset( $cached['html'] ) ) {
			$this->replay( $cached );
			return $cached['html'];
		}

		// Miss: record the current asset state so maybe_store() can work out what
		// rendering this block adds.
		$this->pending[ $this->signature( $parsed_block ) ] = $this->snapshot();

		return $pre_render;
	}

	/**
	 * Store a freshly rendered fragment together with its side effects.
	 *
	 * @param string $content      Rendered block HTML.
	 * @param array  $parsed_block Parsed block.
	 * @return string
	 */
	public function maybe_store( $content, $parsed_block ) {
		$signature = $this->signature( $parsed_block );
		if ( ! isset( $this->pending[ $signature ] ) ) {
			return $content;
		}

		$before = $this->pending[ $signature ];
		unset( $this->pending[ $signature ] );

		if ( ! $this->should_store( $content ) ) {
			return $content;
		}

		$after = $this->snapshot();

		$payload = [
			'html'          => $content,
			'styles'        => array_values( array_diff( $after['styles'], $before['styles'] ) ),
			'scripts'       => array_values( array_diff( $after['scripts'], $before['scripts'] ) ),
			'modules'       => array_values( array_diff( $after['modules'], $before['modules'] ) ),
			'support_rules' => $this->rules_delta( $before['rules'], $after['rules'] ),
		];

		$ttl = (int) apply_filters(
			'ablocks/perf/fragment_cache/ttl',
			(int) Helper::get_settings( 'perf_fragment_cache_ttl', self::DEFAULT_TTL )
		);

		CacheBackend::set( $this->cache_key( $parsed_block ), $payload, $ttl );

		return $content;
	}

	/**
	 * Re-apply the asset side effects recorded with a fragment.
	 *
	 * @param array $cached Stored payload.
	 */
	private function replay( $cached ) {
		foreach ( (array) ( isset( $cached['styles'] ) ? $cached['styles'] : [] ) as $handle ) {
			wp_enqueue_style( $handle );
		}
		foreach ( (array) ( isset( $cached['scripts'] ) ? $cached['scripts'] : [] ) as $handle ) {
			wp_enqueue_script( $handle );
		}
		foreach ( (array) ( isset( $cached['modules'] ) ? $cached['modules'] : [] ) as $module_id ) {
			// Modules are registered at block-registration time, not render time,
			// so enqueueing by id after skipping the render still resolves.
			wp_enqueue_script_module( $module_id );
		}

		// Block-support rules are pushed back into the style engine's own store
		// rather than added as inline CSS on a handle of our own.
		//
		// The difference is not cosmetic. Enqueueing a handle at replay time
		// prints those rules earlier in <head> than core would have, and layout
		// rules like `.wp-container-core-group-is-layout-<hash>` carry the same
		// specificity (0,1,0) as the generic block styles they are meant to
		// override. Moving them earlier silently hands ties to the generic rule,
		// so a cached header could lay out differently from an uncached one.
		// Returning them to the store lets core emit them in its usual place and
		// order, which is the only way the cascade is guaranteed to match.
		$rules = isset( $cached['support_rules'] ) ? (array) $cached['support_rules'] : [];
		if ( empty( $rules ) || ! class_exists( 'WP_Style_Engine' ) ) {
			return;
		}
		foreach ( $rules as $selector => $declarations ) {
			if ( ! is_array( $declarations ) || empty( $declarations ) ) {
				continue;
			}
			\WP_Style_Engine::store_css_rule( 'block-supports', (string) $selector, $declarations );
		}
	}

	/**
	 * Capture the asset state that rendering can add to.
	 *
	 * @return array{styles:array, scripts:array, modules:array, rules:array}
	 */
	private function snapshot() {
		$styles  = wp_styles();
		$scripts = wp_scripts();

		return [
			'styles'      => $styles ? (array) $styles->queue : [],
			'scripts'     => $scripts ? (array) $scripts->queue : [],
			'modules'     => self::script_module_queue(),
			'rules'       => $this->support_rules(),
		];
	}

	/**
	 * Snapshot the style engine's block-support rules as plain arrays.
	 *
	 * Returned as selector => declarations so two snapshots can be compared and
	 * the difference replayed through the public store API.
	 *
	 * @return array<string, array>
	 */
	private function support_rules() {
		if ( ! class_exists( 'WP_Style_Engine' ) || ! method_exists( 'WP_Style_Engine', 'get_store' ) ) {
			return [];
		}

		$store = \WP_Style_Engine::get_store( 'block-supports' );
		if ( ! is_object( $store ) || ! method_exists( $store, 'get_all_rules' ) ) {
			return [];
		}

		$out = [];
		foreach ( (array) $store->get_all_rules() as $selector => $rule ) {
			if ( ! is_object( $rule ) || ! method_exists( $rule, 'get_declarations' ) ) {
				continue;
			}
			$declarations = $rule->get_declarations();
			if ( is_object( $declarations ) && method_exists( $declarations, 'get_declarations' ) ) {
				$declarations = $declarations->get_declarations();
			}
			$out[ (string) $selector ] = (array) $declarations;
		}

		return $out;
	}

	/**
	 * Block-support rules a render added or changed.
	 *
	 * @param array $before Rules before the render.
	 * @param array $after  Rules after the render.
	 * @return array<string, array>
	 */
	private function rules_delta( $before, $after ) {
		$delta = [];
		foreach ( $after as $selector => $declarations ) {
			if ( ! isset( $before[ $selector ] ) || $before[ $selector ] !== $declarations ) {
				$delta[ $selector ] = $declarations;
			}
		}
		return $delta;
	}

	/**
	 * Ids of the currently enqueued script modules.
	 *
	 * @return string[]
	 */
	private static function script_module_queue() {
		if ( ! self::can_track_script_modules() ) {
			return [];
		}
		$queue = wp_script_modules()->get_queue();
		return is_array( $queue ) ? array_values( array_map( 'strval', $queue ) ) : [];
	}

	/**
	 * Can the script-module queue be observed on this WordPress version?
	 *
	 * @return bool
	 */
	private static function can_track_script_modules() {
		static $can = null;
		if ( null !== $can ) {
			return $can;
		}
		$can = function_exists( 'wp_script_modules' )
			&& function_exists( 'wp_enqueue_script_module' )
			&& method_exists( wp_script_modules(), 'get_queue' );
		return $can;
	}

	/**
	 * Is this a block worth caching?
	 *
	 * @param array $parsed_block Parsed block.
	 * @return bool
	 */
	private function is_cacheable_block( $parsed_block ) {
		if ( empty( $parsed_block['blockName'] ) ) {
			return false;
		}

		$blocks = (array) apply_filters( 'ablocks/perf/fragment_cache/blocks', [ 'core/template-part' ] );
		if ( ! in_array( $parsed_block['blockName'], $blocks, true ) ) {
			return false;
		}

		// Contexts where the output is intentionally not the canonical one.
		if ( is_preview() || is_customize_preview() || is_admin() ) {
			return false;
		}

		$should = ! is_user_logged_in();

		return (bool) apply_filters( 'ablocks/perf/fragment_cache/should_cache', $should, $parsed_block );
	}

	/**
	 * Is this rendered output safe to store?
	 *
	 * @param string $content Rendered HTML.
	 * @return bool
	 */
	private function should_store( $content ) {
		if ( ! is_string( $content ) || '' === trim( $content ) ) {
			return false;
		}
		if ( strlen( $content ) > self::MAX_BYTES ) {
			return false;
		}
		// A fragment carrying a nonce would freeze it for the whole TTL. Cheap to
		// detect, and far better to skip the fragment than to serve a dead token.
		if ( false !== stripos( $content, '_wpnonce' ) || false !== stripos( $content, 'wp_rest' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Stable identity for a parsed block within one request.
	 *
	 * @param array $parsed_block Parsed block.
	 * @return string
	 */
	private function signature( $parsed_block ) {
		$attrs = isset( $parsed_block['attrs'] ) ? $parsed_block['attrs'] : [];
		return md5( $parsed_block['blockName'] . '|' . wp_json_encode( $attrs ) );
	}

	/**
	 * Transient key for a fragment.
	 *
	 * @param array $parsed_block Parsed block.
	 * @return string
	 */
	private function cache_key( $parsed_block ) {
		$parts = [
			$this->signature( $parsed_block ),
			get_stylesheet(),
			CacheBackend::generation( self::VERSION_OPTION ),
			determine_locale(),
			is_user_logged_in() ? 'u' . get_current_user_id() : 'anon',
		];

		// Transient keys are capped at 172 characters; a hash keeps this well
		// inside that regardless of theme or locale name length.
		return self::TRANSIENT_PREFIX . md5( implode( '|', $parts ) );
	}
}
