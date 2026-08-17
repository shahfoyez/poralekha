<?php

namespace StoreEngine\Addons\EmbeddableCheckout\Api;

use StoreEngine\Addons\EmbeddableCheckout\Models\EmbedKey;
use StoreEngine\Addons\EmbeddableCheckout\Settings;
use StoreEngine\Addons\InstantCheckout\Api\Session as InstantCheckoutSession;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Hooks the cross-origin auth path into both filter chains:
 *
 *   - The Instant Checkout addon's session-creation filters (storeengine/instant_checkout/session/*)
 *   - Core's checkout controller filters (storeengine/checkout/*) used by /state, /update, /place,
 *     /payment-intent, /coupon/*, /states. Without these, core returns 401 with
 *     "Publishable-key authentication is not active" whenever an embed key is sent.
 *
 * Requires the Instant Checkout addon to be active — otherwise the session-side
 * filters have nothing to fire on. The parent addon class enforces that
 * requirement in `init_addon()` and surfaces an admin notice when missing.
 */
class EmbedAuth {

	public static function init(): void {
		$self = new self();

		// Instant Checkout addon — session creation + sync-items + stripe-intent.
		add_filter( 'storeengine/instant_checkout/session/authorize', [ $self, 'authorize' ], 10, 2 );
		add_filter( 'storeengine/instant_checkout/session/restrict_product', [ $self, 'restrict_product' ], 10, 3 );
		add_filter( 'storeengine/instant_checkout/session/cors_origin', [ $self, 'cors_origin' ], 10, 2 );
		add_filter( 'storeengine/instant_checkout/session/rate_limit', [ $self, 'rate_limit' ], 10, 2 );

		// Core checkout controller — /state, /update, /place, /payment-intent, /coupon/*, /states.
		// These filters are queried by `\StoreEngine\API\Checkout::permission_callback()` and
		// `::maybe_send_cors_headers()` so the same embed key authorises core endpoints.
		add_filter( 'storeengine/checkout/publishable_key_auth', [ $self, 'core_pk_auth' ], 10, 2 );
		add_filter( 'storeengine/checkout/cors_origin', [ $self, 'core_cors_origin' ], 10, 2 );
	}

	/**
	 * Bridge for core's `/checkout/*` permission_callback. Returns true|WP_Error
	 * (null is treated by core as "no handler installed" → 401).
	 */
	public function core_pk_auth( $existing, WP_REST_Request $request ) {
		if ( null !== $existing ) {
			return $existing;
		}
		$auth = $this->authorize( null, $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		// `authorize()` returns array on success or null when no key header.
		// Translate to true so core treats the request as authenticated.
		return is_array( $auth ) ? true : null;
	}

	public function core_cors_origin( $existing, WP_REST_Request $request ) {
		return $this->cors_origin( $existing, $request );
	}

	/**
	 * @param array|WP_Error|null $existing
	 * @return array|WP_Error|null Returns array on cross-origin success, WP_Error
	 *                             on failure, null to fall through to same-origin.
	 */
	public function authorize( $existing, WP_REST_Request $request ) {
		if ( null !== $existing ) {
			return $existing;
		}

		$key = $this->resolve_key( $request );
		if ( null === $key ) {
			// No embed key header → let core handle as same-origin.
			return null;
		}
		if ( is_wp_error( $key ) ) {
			return $key;
		}

		$origin = InstantCheckoutSession::get_request_origin( $request );
		if ( ! $origin ) {
			return new WP_Error( 'storeengine_embed_missing_origin', __( 'Missing Origin header.', 'storeengine' ), [ 'status' => 403 ] );
		}
		if ( ! EmbedKey::origin_is_allowed( $key, $origin ) ) {
			return new WP_Error( 'storeengine_embed_origin_not_allowed', __( 'Origin not allowed for this embed key.', 'storeengine' ), [ 'status' => 403 ] );
		}

		EmbedKey::touch_last_used( (int) $key['key_id'] );

		return [
			'context' => [ 'key_id' => (int) $key['key_id'] ],
			'origin'  => $origin,
		];
	}

	public function restrict_product( $allowed, int $product_id, WP_REST_Request $request ) {
		if ( true !== $allowed ) {
			return $allowed;
		}

		$key = $this->resolve_key( $request );
		if ( null === $key || is_wp_error( $key ) ) {
			return $allowed;
		}

		return EmbedKey::product_is_in_scope( $key, $product_id );
	}

	public function cors_origin( $existing, WP_REST_Request $request ) {
		if ( $existing ) {
			return $existing;
		}

		$key = $this->resolve_key( $request );
		if ( null === $key || is_wp_error( $key ) ) {
			return null;
		}

		$origin = InstantCheckoutSession::get_request_origin( $request );
		return ( $origin && EmbedKey::origin_is_allowed( $key, $origin ) ) ? $origin : null;
	}

	/**
	 * Per-key rate limit. Falls back to per-IP buckets for same-origin callers
	 * so a noisy storefront button can't run away with sessions either.
	 */
	public function rate_limit( $existing, WP_REST_Request $request ) {
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		$key = $this->resolve_key( $request );
		if ( is_wp_error( $key ) ) {
			return $key;
		}

		if ( is_array( $key ) && ! empty( $key['embed_key_hash'] ) ) {
			$bucket = 'storeengine_embed_rl_' . $key['embed_key_hash'];
		} else {
			$ip     = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
			$bucket = 'storeengine_embed_rl_so_' . md5( $ip );
		}

		$count     = (int) get_transient( $bucket );
		$threshold = (int) apply_filters( 'storeengine/embeddable_checkout/session_rate_limit', (int) Settings::init()->get_settings( 'session_rate_limit', 60 ) );
		if ( $count >= $threshold ) {
			return new WP_Error( 'storeengine_embed_rate_limited', __( 'Too many requests. Please retry shortly.', 'storeengine' ), [ 'status' => 429 ] );
		}
		set_transient( $bucket, $count + 1, MINUTE_IN_SECONDS );

		return true;
	}

	/**
	 * @return array|WP_Error|null
	 */
	protected function resolve_key( WP_REST_Request $request ) {
		static $cache = [];
		$cache_key    = spl_object_hash( $request );
		if ( array_key_exists( $cache_key, $cache ) ) {
			return $cache[ $cache_key ];
		}

		$header = (string) $request->get_header( 'x_storeengine_embed_key' );
		if ( '' === $header ) {
			$header = (string) $request->get_header( 'x_storeengine_pk' ); // legacy
		}
		if ( '' === $header ) {
			return $cache[ $cache_key ] = null;
		}

		$row = EmbedKey::find_by_key( $header );
		if ( ! $row || ! empty( $row['revoked_at'] ) ) {
			return $cache[ $cache_key ] = new WP_Error( 'storeengine_embed_invalid_key', __( 'Invalid or revoked embed key.', 'storeengine' ), [ 'status' => 401 ] );
		}

		return $cache[ $cache_key ] = $row;
	}
}
