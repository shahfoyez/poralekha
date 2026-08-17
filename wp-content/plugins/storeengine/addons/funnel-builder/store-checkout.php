<?php
/**
 * Store Checkout override.
 *
 * When a published Store Checkout funnel (trigger_type = global_checkout) with a
 * LIVE checkout step exists, route the site's checkout through that funnel's
 * checkout step page — so "Proceed to checkout" everywhere lands on the funnel
 * (order bumps → one-click upsells → thank-you) instead of the default checkout.
 *
 * Implemented purely via StoreEngine's own checkout-URL / page-permalink /
 * is-checkout filters, so it degrades to the default checkout when no Store
 * Checkout funnel is published (or its step page isn't live).
 *
 * The resolved checkout-step page id is cached in an autoloaded option to keep
 * the override off the per-request DB path (it fires on essentially every
 * storefront page). The cache is re-verified for liveness each request — a
 * drafted/trashed step page self-heals to the default checkout — and flushed
 * when a page's publish state flips or a funnel/step is written.
 *
 * @version 1.1.0
 */

namespace StoreEngine\Addons\FunnelBuilder;

use StoreEngine\Addons\FunnelBuilder\Classes\Funnel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StoreCheckout {

	/**
	 * Autoloaded option caching the resolved checkout-step page id ('' = not yet
	 * resolved, '0' = resolved to "no store checkout").
	 */
	const CACHE_OPTION = 'storeengine_funnel_store_checkout_page';

	protected static ?int $page_id = null;
	protected static bool $resolved = false;

	public static function init() {
		$self = new self();

		add_filter( 'storeengine/checkout/get_checkout_url', [ $self, 'override_checkout_url' ] );
		add_filter( 'storeengine/get_page_permalink', [ $self, 'override_checkout_permalink' ], 10, 4 );
		add_filter( 'storeengine_is_checkout', [ $self, 'mark_is_checkout' ] );
		// Order-pay URLs must work on the funnel checkout page (see fix_pay_url).
		add_filter( 'storeengine/get_checkout_payment_url', [ $self, 'fix_pay_url' ], 10, 2 );

		// A checkout-step page being published/trashed changes what the override
		// serves; the liveness re-check handles draft/trash, but a *newly
		// published* step page needs the cache dropped.
		add_action( 'transition_post_status', [ __CLASS__, 'on_transition' ], 10, 3 );
	}

	/**
	 * The live checkout-step page id of the published Store Checkout funnel, or 0.
	 *
	 * Cached in an autoloaded option and re-verified for liveness each request, so
	 * a drafted/trashed step page falls back to the default checkout on its own.
	 */
	public static function checkout_page_id(): int {
		if ( self::$resolved ) {
			return (int) self::$page_id;
		}
		self::$resolved = true;

		$cached = get_option( self::CACHE_OPTION, null );
		if ( null !== $cached && '' !== $cached ) {
			$pid = (int) $cached;
			if ( 0 === $pid ) {
				// Cached "no store checkout" — trust it (flushed on funnel/step write).
				self::$page_id = 0;

				return 0;
			}
			if ( 'publish' === get_post_status( $pid ) ) {
				self::$page_id = $pid;

				return $pid;
			}
			// Cached page is no longer live — re-resolve below.
		}

		self::$page_id = self::resolve_and_cache();

		return (int) self::$page_id;
	}

	protected static function resolve_and_cache(): int {
		$pid    = 0;
		$funnel = Funnel::get_global_checkout_funnel();
		if ( $funnel ) {
			foreach ( $funnel->steps() as $step ) {
				if ( 'checkout' === $step->type && $step->is_page_live() ) {
					$pid = (int) $step->page_id;
					break;
				}
			}
		}

		update_option( self::CACHE_OPTION, (string) $pid, false );

		return $pid;
	}

	/**
	 * Drop the cached resolution. Called on funnel/step writes and page-status
	 * transitions.
	 */
	public static function flush(): void {
		self::$resolved = false;
		self::$page_id  = null;
		delete_option( self::CACHE_OPTION );
	}

	public static function on_transition( $new_status, $old_status, $post ) {
		if (
			$post && 'page' === $post->post_type && $new_status !== $old_status &&
			( 'publish' === $new_status || 'publish' === $old_status )
		) {
			self::flush();
		}
	}

	public function override_checkout_url( $url ) {
		$pid = self::checkout_page_id();
		if ( $pid ) {
			$permalink = get_permalink( $pid );
			if ( $permalink ) {
				return $permalink;
			}
		}

		return $url;
	}

	public function override_checkout_permalink( $permalink, $page, $page_id, $fallback ) {
		if ( 'checkout_page' !== $page ) {
			return $permalink;
		}
		$pid = self::checkout_page_id();
		if ( $pid ) {
			$live = get_permalink( $pid );
			if ( $live ) {
				return $live;
			}
		}

		return $permalink;
	}

	public function mark_is_checkout( $is_checkout ) {
		if ( $is_checkout ) {
			return $is_checkout;
		}
		$pid = self::checkout_page_id();
		if ( $pid && is_page( $pid ) ) {
			return true;
		}

		return $is_checkout;
	}

	/**
	 * Rewrite the order-pay URL to query-arg form on the funnel checkout page.
	 *
	 * `get_checkout_payment_url()` builds the pay URL on the (now overridden)
	 * checkout url, producing a pretty `{funnel-page}/order-pay/{id}/` path — but
	 * the order-pay rewrite rule only targets the *default* checkout page slug, so
	 * that path 404s. `order_pay`/`order_id` are registered query vars, so the
	 * query-arg form works on any page regardless of permalink structure.
	 */
	public function fix_pay_url( $url, $order ) {
		$pid = self::checkout_page_id();
		if ( ! $pid || ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
			return $url;
		}
		$base = get_permalink( $pid );
		if ( ! $base ) {
			return $url;
		}

		// Preserve the key / pay_for_order args already added to the URL.
		$args = [];
		$query = wp_parse_url( $url, PHP_URL_QUERY );
		if ( $query ) {
			parse_str( $query, $args );
		}
		$args['order_pay'] = 'true';
		$args['order_id']  = $order->get_id();

		return add_query_arg( $args, $base );
	}
}
