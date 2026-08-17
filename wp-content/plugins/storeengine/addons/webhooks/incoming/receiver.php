<?php

namespace StoreEngine\Addons\Webhooks\Incoming;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Public receiver for incoming webhooks.
 *
 * The endpoint is intentionally unauthenticated at the WordPress layer (no
 * cookie/nonce) because callers are machines — the per-config secret *is* the
 * credential. Every request is verified (HMAC signature or bearer token),
 * size-capped, replay-protected and deduped before any side effect runs.
 */
class Receiver {

	const NAMESPACE = STOREENGINE_PLUGIN_SLUG . '/v1';

	public static function register_routes(): void {
		$self = new self();

		// The public ingestion endpoint. Auth is the secret, so permission is open.
		register_rest_route( self::NAMESPACE, '/incoming/(?P<key>[A-Za-z0-9]+)', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $self, 'handle' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'key' => [ 'sanitize_callback' => 'sanitize_text_field' ],
				],
			],
		] );

		// Admin-only: recent deliveries for a config (audit trail).
		register_rest_route( self::NAMESPACE, '/incoming-webhook/(?P<id>\d+)/deliveries', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $self, 'get_deliveries' ],
				'permission_callback' => [ $self, 'admin_permission' ],
			],
		] );

		// Admin-only: replay a stored delivery through the current handler.
		register_rest_route( self::NAMESPACE, '/incoming-webhook/(?P<id>\d+)/replay', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $self, 'replay' ],
				'permission_callback' => [ $self, 'admin_permission' ],
				'args'                => [
					'delivery_id' => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
				],
			],
		] );
	}

	public function admin_permission(): bool {
		return Helper::check_rest_user_cap( 'manage_options' );
	}

	/**
	 * Handle a public ingestion request.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function handle( WP_REST_Request $request ) {
		$key     = (string) $request->get_param( 'key' );
		$webhook = Incoming::find_by_key( $key );

		if ( ! $webhook ) {
			return new WP_Error( 'storeengine_webhook_not_found', __( 'Unknown webhook endpoint.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$webhook_id = (int) $webhook->ID;

		if ( 'publish' !== $webhook->post_status ) {
			return new WP_Error( 'storeengine_webhook_paused', __( 'This webhook endpoint is paused.', 'storeengine' ), [ 'status' => 403 ] );
		}

		$raw = (string) $request->get_body();

		// Size guard — reject oversized bodies before hashing/parsing.
		$max = (int) apply_filters( 'storeengine/incoming_webhook/max_body_size', MB_IN_BYTES, $webhook_id );
		if ( $max > 0 && strlen( $raw ) > $max ) {
			return $this->reject( $webhook_id, $key, 'oversized', __( 'Payload too large.', 'storeengine' ), 413 );
		}

		// Authenticate.
		$auth   = get_post_meta( $webhook_id, Incoming::META_AUTH, true ) ?: 'hmac';
		$secret = (string) get_post_meta( $webhook_id, Incoming::META_SECRET, true );
		$verify = $this->verify( $auth, $secret, $raw, $request, $webhook_id );
		if ( is_wp_error( $verify ) ) {
			return $this->reject( $webhook_id, $key, 'unauthorized', $verify->get_error_message(), 401 );
		}

		// Optional replay window: only enforced when the caller sends a timestamp.
		$ts_error = $this->check_timestamp( $request, $webhook_id );
		if ( is_wp_error( $ts_error ) ) {
			return $this->reject( $webhook_id, $key, 'stale', $ts_error->get_error_message(), 401 );
		}

		// Parse JSON.
		$payload = json_decode( $raw, true );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $payload ) ) {
			return $this->reject( $webhook_id, $key, 'invalid_json', __( 'Request body is not valid JSON.', 'storeengine' ), 400 );
		}

		// Idempotency key: explicit header if given, else a content hash.
		$delivery_id = $this->delivery_id( $request, $raw );

		if ( DeliveryLog::is_seen( $webhook_id, $delivery_id ) ) {
			return new WP_REST_Response( [
				'success'     => true,
				'duplicate'   => true,
				'delivery_id' => $delivery_id,
				'message'     => __( 'Duplicate delivery ignored.', 'storeengine' ),
			], 200 );
		}

		$action = get_post_meta( $webhook_id, Incoming::META_ACTION, true ) ?: Registry::AUTOMATION;

		DeliveryLog::record( $webhook_id, [
			'delivery_id' => $delivery_id,
			'received_at' => current_time( 'mysql', true ),
			'action'      => $action,
			'status'      => 'accepted',
			'source_ip'   => $this->client_ip(),
			'message'     => '',
			'payload'     => $payload,
		] );

		// Deferred path (opt-in) — respond 202 and let Action Scheduler run it.
		$defer = (bool) apply_filters( 'storeengine/incoming_webhook/defer', false, $webhook_id, $action, $payload );
		if ( $defer && function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action(
				'storeengine/incoming_webhook/process',
				[
					'data' => [
						'webhook_id'  => $webhook_id,
						'delivery_id' => $delivery_id,
						'action'      => $action,
						'payload'     => $payload,
					],
				],
				'storeengine-webhooks'
			);

			return new WP_REST_Response( [
				'success'     => true,
				'queued'      => true,
				'delivery_id' => $delivery_id,
			], 202 );
		}

		// Synchronous path — process now and return the real result so the caller
		// learns whether the action succeeded.
		$result = Processor::process( $webhook_id, $delivery_id, $action, $payload );
		DeliveryLog::update_result( $webhook_id, $delivery_id, $result );

		$status = $result['success'] ? 200 : (int) ( $result['status'] ?? 422 );

		return new WP_REST_Response( [
			'success'     => $result['success'],
			'delivery_id' => $delivery_id,
			'message'     => $result['message'],
			'data'        => $result['data'] ?? null,
		], $status );
	}

	/**
	 * Verify the request credential.
	 *
	 * @return true|WP_Error
	 */
	protected function verify( string $auth, string $secret, string $raw, WP_REST_Request $request, int $webhook_id ) {
		if ( '' === $secret ) {
			return new WP_Error( 'no_secret', __( 'Webhook is missing a secret.', 'storeengine' ) );
		}

		if ( 'bearer' === $auth ) {
			$header = (string) $request->get_header( 'authorization' );
			$token  = 0 === stripos( $header, 'bearer ' ) ? substr( $header, 7 ) : $header;
			if ( '' === $token ) {
				$token = (string) $request->get_header( 'x_storeengine_token' );
			}

			return hash_equals( $secret, trim( $token ) )
				? true
				: new WP_Error( 'invalid_token', __( 'Invalid bearer token.', 'storeengine' ) );
		}

		// HMAC (default). Accept the incoming header or the header the outgoing
		// addon emits, so a StoreEngine→StoreEngine loop is symmetric.
		$provided = (string) ( $request->get_header( 'x_storeengine_signature' ) ?: $request->get_header( 'x_storeengine_webhook_signature' ) );
		$provided = trim( $provided );
		if ( '' === $provided ) {
			return new WP_Error( 'missing_signature', __( 'Missing signature header.', 'storeengine' ) );
		}

		// Tolerate an algorithm prefix (e.g. "sha256=") used by some senders.
		$provided = (string) preg_replace( '/^(sha1|sha256|sha512)=/i', '', $provided );

		$algo = (string) apply_filters( 'storeengine/incoming_webhook/hashing_algorithm', 'sha256', $webhook_id );
		$key  = wp_specialchars_decode( $secret, ENT_QUOTES );

		// The outgoing addon signs the raw JSON body and base64-encodes it; also
		// accept plain hex for broader interoperability.
		$candidates = [
			base64_encode( hash_hmac( $algo, $raw, $key, true ) ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
			hash_hmac( $algo, $raw, $key, false ),
		];

		foreach ( $candidates as $candidate ) {
			if ( hash_equals( $candidate, $provided ) ) {
				return true;
			}
		}

		return new WP_Error( 'invalid_signature', __( 'Signature verification failed.', 'storeengine' ) );
	}

	/**
	 * Enforce a mandatory freshness window.
	 *
	 * The timestamp header is NOT covered by the HMAC, so on its own it does not
	 * prove freshness against an active attacker. It is enforced here to bound the
	 * replay window; combined with the body-bound idempotency key ({@see delivery_id()})
	 * this blocks the practical replay of a captured signed request. An integrator
	 * whose sender genuinely cannot supply a timestamp can disable the window by
	 * filtering the tolerance to 0.
	 *
	 * @return true|WP_Error
	 */
	protected function check_timestamp( WP_REST_Request $request, int $webhook_id ) {
		$tolerance = (int) apply_filters( 'storeengine/incoming_webhook/timestamp_tolerance', 5 * MINUTE_IN_SECONDS, $webhook_id );
		if ( $tolerance <= 0 ) {
			return true; // Explicitly disabled by the integrator.
		}

		$ts = (string) ( $request->get_header( 'x_storeengine_timestamp' ) ?: $request->get_header( 'x_storeengine_webhook_triggered_at' ) );
		if ( '' === $ts ) {
			return new WP_Error( 'missing_timestamp', __( 'A request timestamp header is required.', 'storeengine' ) );
		}

		$when = is_numeric( $ts ) ? (int) $ts : strtotime( $ts );
		if ( ! $when ) {
			return new WP_Error( 'invalid_timestamp', __( 'The request timestamp is invalid.', 'storeengine' ) );
		}

		if ( abs( time() - $when ) > $tolerance ) {
			return new WP_Error( 'stale_request', __( 'Request timestamp is outside the allowed window.', 'storeengine' ) );
		}

		return true;
	}

	/**
	 * Replay/idempotency key.
	 *
	 * MUST be derived only from material the HMAC signature covers (the raw body).
	 * The X-Delivery-Id header is attacker-controlled and unsigned: keying off it
	 * let a captured, validly-signed body be replayed indefinitely by simply
	 * varying that header on each request. A sender that needs to distinguish two
	 * otherwise-identical payloads must include a unique field (id/sequence/
	 * timestamp) INSIDE the signed body so the hash differs.
	 *
	 * @return string
	 */
	protected function delivery_id( WP_REST_Request $request, string $raw ): string {
		return hash( 'sha256', $raw );
	}

	protected function client_ip(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return $ip;
	}

	/**
	 * Log a rejected request and return the error response.
	 */
	protected function reject( int $webhook_id, string $key, string $reason, string $message, int $status ): WP_Error {
		DeliveryLog::record( $webhook_id, [
			'delivery_id' => 'rej_' . substr( hash( 'sha256', $key . microtime() ), 0, 24 ),
			'received_at' => current_time( 'mysql', true ),
			'action'      => '',
			'status'      => 'rejected',
			'source_ip'   => $this->client_ip(),
			'message'     => $reason . ': ' . $message,
			'payload'     => [],
		] );

		return new WP_Error( 'storeengine_webhook_rejected', $message, [ 'status' => $status ] );
	}

	/**
	 * Admin: recent deliveries for a config.
	 *
	 * @param WP_REST_Request $request
	 */
	public function get_deliveries( WP_REST_Request $request ): WP_REST_Response {
		$id = (int) $request->get_param( 'id' );

		return new WP_REST_Response( DeliveryLog::all( $id ), 200 );
	}

	/**
	 * Admin: replay a stored delivery through the current handler.
	 *
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public function replay( WP_REST_Request $request ) {
		$id          = (int) $request->get_param( 'id' );
		$delivery_id = (string) $request->get_param( 'delivery_id' );
		$entry       = DeliveryLog::find( $id, $delivery_id );

		if ( ! $entry ) {
			return new WP_Error( 'not_found', __( 'Delivery not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$action  = get_post_meta( $id, Incoming::META_ACTION, true ) ?: Registry::AUTOMATION;
		$result  = Processor::process( $id, 'replay_' . $delivery_id, $action, (array) ( $entry['payload'] ?? [] ) );
		$status  = $result['success'] ? 200 : (int) ( $result['status'] ?? 422 );

		return new WP_REST_Response( $result, $status );
	}
}
