<?php

namespace StoreEngine\Addons\InstantCheckout\Api;

use StoreEngine\API\AbstractRestApiController;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Instant Checkout session endpoint — same-origin (cookie + nonce) by default.
 *
 * Cross-origin embeds plug in via four filters that the Embeddable Checkout
 * addon hooks into:
 *
 *   storeengine/instant_checkout/session/authorize         (returns array|WP_Error|null)
 *   storeengine/instant_checkout/session/restrict_product  (returns true|false|WP_Error)
 *   storeengine/instant_checkout/session/cors_origin       (returns string|null)
 *   storeengine/instant_checkout/session/rate_limit        (returns true|WP_Error)
 *
 * The session blob lives in a 15-minute transient and is consumed by the
 * iframe handler + sync-items + payment-intent endpoints.
 */
class Session extends AbstractRestApiController {

	protected $rest_base = 'instant-checkout';

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
		add_filter( 'rest_pre_serve_request', [ $self, 'maybe_send_cors_headers' ], 10, 4 );
		add_filter( 'rest_allowed_cors_headers', [ $self, 'allow_embed_key_header' ] );
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/session', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_session' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'product_id' => [ 'type' => 'integer', 'required' => false ],
					'price_id'   => [ 'type' => 'integer', 'required' => false ],
					'quantity'   => [ 'type' => 'integer', 'default' => 1 ],
					'items'      => [ 'type' => 'array', 'required' => false ],
				],
			],
		] );
	}

	public function allow_embed_key_header( $headers ) {
		$headers[] = 'X-StoreEngine-Embed-Key';
		$headers[] = 'X-StoreEngine-Pk'; // legacy header — kept for storefront builds shipped before the rename

		return array_values( array_unique( $headers ) );
	}

	public function permission_callback( WP_REST_Request $request ) {
		$ctx = $this->resolve_context( $request );

		return is_wp_error( $ctx ) ? $ctx : true;
	}

	public function maybe_send_cors_headers( $served, $result, $request, $server ) {
		if ( ! ( $request instanceof WP_REST_Request ) ) {
			return $served;
		}

		$route = $request->get_route();
		if ( strpos( $route, '/' . $this->namespace . '/' . $this->rest_base ) !== 0 ) {
			return $served;
		}

		$origin = apply_filters( 'storeengine/instant_checkout/session/cors_origin', null, $request );
		if ( ! $origin ) {
			return $served;
		}

		header( 'Access-Control-Allow-Origin: ' . $origin );
		header( 'Vary: Origin' );
		header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
		header( 'Access-Control-Allow-Headers: Content-Type, X-StoreEngine-Embed-Key, X-WP-Nonce' );
		header( 'Access-Control-Allow-Credentials: false' );

		return $served;
	}

	public function create_session( WP_REST_Request $request ) {
		$ctx = $this->resolve_context( $request );
		if ( is_wp_error( $ctx ) ) {
			return $ctx;
		}

		$raw_items = (array) $request->get_param( 'items' );
		$items     = self::sanitize_items( $raw_items );

		if ( ! $items ) {
			$product_id = (int) $request->get_param( 'product_id' );
			$price_id   = (int) $request->get_param( 'price_id' );
			$quantity   = max( 1, (int) $request->get_param( 'quantity' ) );
			if ( $product_id ) {
				$items = [ [
					'product_id' => $product_id,
					'price_id'   => $price_id,
					'quantity'   => $quantity,
					'optional'   => false,
					'default'    => true,
				] ];
			}
		}

		if ( ! $items ) {
			return new WP_Error( 'storeengine_instant_checkout_invalid_product', __( 'No items supplied.', 'storeengine' ), [ 'status' => 400 ] );
		}

		foreach ( $items as $it ) {
			$allowed = apply_filters( 'storeengine/instant_checkout/session/restrict_product', true, (int) $it['product_id'], $request );
			if ( is_wp_error( $allowed ) ) {
				return $allowed;
			}
			if ( false === $allowed ) {
				return new WP_Error( 'storeengine_instant_checkout_product_out_of_scope', __( 'Product is not in scope for this embed key.', 'storeengine' ), [ 'status' => 403 ] );
			}
		}

		$rl = apply_filters( 'storeengine/instant_checkout/session/rate_limit', true, $request );
		if ( is_wp_error( $rl ) ) {
			return $rl;
		}

		$selection = [];
		foreach ( $items as $it ) {
			if ( ! $it['optional'] || $it['default'] ) {
				$selection[] = [ 'product_id' => $it['product_id'], 'price_id' => $it['price_id'], 'quantity' => $it['quantity'] ];
			}
		}

		$primary    = $items[0];
		$session_id = wp_generate_uuid4();

		set_transient(
			'storeengine_instant_checkout_sess_' . $session_id,
			[
				'same_origin' => empty( $ctx['cross_origin'] ),
				'origin'      => $ctx['origin'] ?? '',
				'context'     => $ctx['context'] ?? [],
				'product_id'  => (int) $primary['product_id'],
				'price_id'    => (int) $primary['price_id'],
				'quantity'    => (int) $primary['quantity'],
				'items'       => $items,
				'selection'   => $selection,
			],
			15 * MINUTE_IN_SECONDS
		);

		return rest_ensure_response( [
			'session_id'   => $session_id,
			'checkout_url' => add_query_arg(
				[
					'se_checkout' => 1,
					'sid'         => $session_id,
				],
				home_url( '/' )
			),
			'expires_in'   => 15 * MINUTE_IN_SECONDS,
			'items'        => $items,
		] );
	}

	protected function resolve_context( WP_REST_Request $request ) {
		static $cache = [];
		$cache_key    = spl_object_hash( $request );
		if ( isset( $cache[ $cache_key ] ) ) {
			return $cache[ $cache_key ];
		}

		$origin = self::get_request_origin( $request );

		$auth = apply_filters( 'storeengine/instant_checkout/session/authorize', null, $request );
		if ( is_wp_error( $auth ) ) {
			return $cache[ $cache_key ] = $auth;
		}
		if ( is_array( $auth ) ) {
			return $cache[ $cache_key ] = array_merge( [ 'origin' => $origin, 'cross_origin' => true ], $auth );
		}

		// Same-origin fallback. WP REST already validated the cookie + nonce.
		return $cache[ $cache_key ] = [ 'origin' => $origin, 'cross_origin' => false ];
	}

	public static function get_request_origin( WP_REST_Request $request ): string {
		$origin = (string) $request->get_header( 'origin' );
		if ( '' === $origin ) {
			$referer = (string) $request->get_header( 'referer' );
			if ( $referer ) {
				$parts = wp_parse_url( $referer );
				if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
					$origin = $parts['scheme'] . '://' . $parts['host'];
					if ( ! empty( $parts['port'] ) ) {
						$origin .= ':' . (int) $parts['port'];
					}
				}
			}
		}
		return self::normalize_origin( $origin );
	}

	public static function normalize_origin( string $origin ): string {
		$origin = trim( $origin );
		if ( '' === $origin ) {
			return '';
		}
		// Accept any URI scheme so React Native / Capacitor / Cordova /
		// Tauri / Electron apps can identify themselves with their natural
		// origin (e.g. `app://com.example.myapp`, `capacitor://localhost`).
		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $origin ) ) {
			$origin = 'https://' . ltrim( $origin, '/' );
		}
		$parts = wp_parse_url( $origin );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}
		$scheme     = strtolower( $parts['scheme'] );
		$normalized = $scheme . '://' . strtolower( $parts['host'] );
		if ( ! empty( $parts['port'] ) ) {
			$normalized .= ':' . (int) $parts['port'];
		}
		return $normalized;
	}

	public static function sanitize_items( $raw ): array {
		$out = [];
		foreach ( (array) $raw as $row ) {
			$row = (array) $row;
			$pid = (int) ( $row['product_id'] ?? 0 );
			if ( ! $pid ) {
				continue;
			}
			$out[] = [
				'product_id' => $pid,
				'price_id'   => (int) ( $row['price_id'] ?? 0 ),
				'quantity'   => max( 1, (int) ( $row['quantity'] ?? 1 ) ),
				'optional'   => ! empty( $row['optional'] ),
				'default'    => ! empty( $row['default'] ),
			];
		}

		return $out;
	}

	public static function update_session_selection( string $session_id, array $selection ): bool {
		$key  = 'storeengine_instant_checkout_sess_' . $session_id;
		$data = get_transient( $key );
		if ( ! is_array( $data ) ) {
			return false;
		}
		$data['selection'] = $selection;
		set_transient( $key, $data, 15 * MINUTE_IN_SECONDS );

		return true;
	}

	public static function consume_session( string $session_id ): ?array {
		$payload = get_transient( 'storeengine_instant_checkout_sess_' . $session_id );

		return ( $payload && is_array( $payload ) ) ? $payload : null;
	}
}
