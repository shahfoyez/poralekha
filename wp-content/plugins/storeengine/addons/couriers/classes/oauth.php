<?php
/**
 * Reusable OAuth2 engine for courier providers.
 *
 * One engine drives every OAuth grant a courier API might use:
 *
 *   - authorization_code  (3-legged): the merchant clicks "Connect", is sent to
 *     the courier's consent screen, and returns to our callback with a code we
 *     exchange for an access + refresh token. Tokens persist in
 *     {@see OAuthTokenStore}; access tokens are refreshed silently on expiry.
 *
 *   - client_credentials  (2-legged): server-to-server. We mint a short-lived
 *     access token straight from the stored client id/secret and cache it in a
 *     transient. No user interaction, no refresh token.
 *
 *   - password            (2-legged): same as above but exchanges a stored
 *     username/password (e.g. Shiprocket) for the token.
 *
 * A provider opts in by implementing an `oauth_config()` method (duck-typed, so
 * satellite providers need not import a core interface) that returns the grant,
 * endpoints, and credentials — see {@see OAuth::for_provider()}. Providers then
 * just call their `bearer_token()` helper and never touch token plumbing.
 *
 * @package StoreEngine\Addons\Couriers
 */

namespace StoreEngine\Addons\Couriers\Classes;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OAuth {

	const STATE_PREFIX   = 'se_courier_oauth_state_';
	const CC_CACHE_PREFIX = 'se_courier_oauth_cc_';
	const STATE_TTL      = 900; // 15 min to complete the consent round-trip.
	const EXPIRY_SKEW    = 30;  // Treat a token as expired this many secs early.

	private string $provider_id;

	/** @var array<string,mixed> */
	private array $cfg;

	/**
	 * @param array<string,mixed> $cfg Normalised oauth_config().
	 */
	private function __construct( string $provider_id, array $cfg ) {
		$this->provider_id = $provider_id;
		$this->cfg         = $cfg;
	}

	/**
	 * Build an engine for a registered provider, or null if the provider does
	 * not support OAuth / declares no token endpoint.
	 */
	public static function for_provider( string $provider_id ): ?OAuth {
		$provider = Registry::get( $provider_id );
		if ( ! $provider || ! method_exists( $provider, 'oauth_config' ) ) {
			return null;
		}

		$cfg = $provider->oauth_config();
		if ( ! is_array( $cfg ) || empty( $cfg['token_url'] ) ) {
			return null;
		}

		$cfg = wp_parse_args( $cfg, [
			'grant'           => 'authorization_code',
			'auth_url'        => '',
			'token_url'       => '',
			'client_id'       => '',
			'client_secret'   => '',
			'scope'           => '',
			'auth_style'      => 'body', // 'basic' → HTTP Basic header, else in body.
			'username'        => '',
			'password'        => '',
			'extra'           => [],     // extra token-request params.
			'authorize_extra' => [],     // extra authorize-url params.
		] );

		return new self( $provider_id, $cfg );
	}

	public function grant(): string {
		return (string) $this->cfg['grant'];
	}

	public function is_authorization_code(): bool {
		return 'authorization_code' === $this->grant();
	}

	/**
	 * Are the credentials needed to even attempt a connection present?
	 * (client id/secret for code & client_credentials; username/password too for
	 * the password grant.)
	 */
	public function is_configured(): bool {
		if ( empty( $this->cfg['client_id'] ) ) {
			// The password grant can run with just username/password if the API
			// issues tokens without a client id.
			if ( 'password' === $this->grant() ) {
				return ! empty( $this->cfg['username'] ) && ! empty( $this->cfg['password'] );
			}
			return false;
		}
		return true;
	}

	/**
	 * Connected = we hold a usable access token. For 3-legged that means the
	 * store has one (refreshable); for 2-legged it means credentials that can
	 * mint one on demand.
	 */
	public function is_connected(): bool {
		if ( $this->is_authorization_code() ) {
			$row = OAuthTokenStore::get( $this->provider_id );
			if ( empty( $row['access_token'] ) ) {
				return false;
			}
			// Still connected if unexpired OR refreshable.
			return $this->token_fresh( $row ) || ! empty( $row['refresh_token'] );
		}

		return $this->is_configured();
	}

	/**
	 * When the current authorization_code access token expires (unix ts), or null.
	 */
	public function expires_at(): ?int {
		if ( ! $this->is_authorization_code() ) {
			return null;
		}
		$row = OAuthTokenStore::get( $this->provider_id );
		return isset( $row['expires_at'] ) ? (int) $row['expires_at'] : null;
	}

	/**
	 * The fixed redirect/callback URL the provider's OAuth app must whitelist.
	 * Provider-agnostic — the provider is recovered from the signed `state`.
	 */
	public static function redirect_uri(): string {
		return rest_url( 'storeengine/v1/couriers/oauth/callback' );
	}

	// ── 3-legged: authorization_code ──────────────────────────────────────────

	/**
	 * Build the provider's consent URL and persist a one-time `state` bound to
	 * this provider + the current user (CSRF protection for the callback).
	 */
	public function authorize_url(): string {
		$state = wp_generate_password( 24, false, false );
		set_transient( self::STATE_PREFIX . $state, [
			'provider' => $this->provider_id,
			'user'     => get_current_user_id(),
		], self::STATE_TTL );

		$params = array_merge( [
			'response_type' => 'code',
			'client_id'     => $this->cfg['client_id'],
			'redirect_uri'  => self::redirect_uri(),
			'state'         => $state,
		], (array) $this->cfg['authorize_extra'] );

		if ( ! empty( $this->cfg['scope'] ) ) {
			$params['scope'] = $this->cfg['scope'];
		}

		$sep = false === strpos( (string) $this->cfg['auth_url'], '?' ) ? '?' : '&';
		return $this->cfg['auth_url'] . $sep . http_build_query( $params );
	}

	/**
	 * Resolve the provider id a callback `state` belongs to (and consume it).
	 * Returns null if unknown/expired. Static because the callback endpoint does
	 * not yet know which provider it is for.
	 *
	 * @return array{provider:string,user:int}|null
	 */
	public static function consume_state( string $state ): ?array {
		if ( '' === $state ) {
			return null;
		}
		$key  = self::STATE_PREFIX . $state;
		$data = get_transient( $key );
		if ( ! is_array( $data ) || empty( $data['provider'] ) ) {
			return null;
		}
		delete_transient( $key );
		return [ 'provider' => (string) $data['provider'], 'user' => (int) ( $data['user'] ?? 0 ) ];
	}

	/**
	 * Exchange an authorization code for tokens and persist them.
	 *
	 * @return true|WP_Error
	 */
	public function exchange_code( string $code ) {
		$res = $this->request_token( [
			'grant_type'   => 'authorization_code',
			'code'         => $code,
			'redirect_uri' => self::redirect_uri(),
		] );

		if ( is_wp_error( $res ) ) {
			return $res;
		}

		$this->persist_tokens( $res );
		return true;
	}

	/**
	 * Refresh the authorization_code access token from the stored refresh token.
	 * Clears the store on invalid_grant so the UI prompts a reconnect.
	 */
	public function refresh(): ?string {
		$row = OAuthTokenStore::get( $this->provider_id );
		if ( empty( $row['refresh_token'] ) ) {
			return null;
		}

		$res = $this->request_token( [
			'grant_type'    => 'refresh_token',
			'refresh_token' => $row['refresh_token'],
		] );

		if ( is_wp_error( $res ) ) {
			if ( 'invalid_grant' === $res->get_error_code() ) {
				OAuthTokenStore::clear( $this->provider_id );
			}
			return null;
		}

		// Some providers omit the refresh token on refresh — keep the old one.
		if ( empty( $res['refresh_token'] ) ) {
			$res['refresh_token'] = $row['refresh_token'];
		}
		$this->persist_tokens( $res );

		return $res['access_token'] ?? null;
	}

	// ── 2-legged: client_credentials / password ───────────────────────────────

	private function fetch_two_legged(): ?string {
		$cache_key = self::CC_CACHE_PREFIX . $this->provider_id;
		$cached    = get_transient( $cache_key );
		if ( is_string( $cached ) && '' !== $cached ) {
			return $cached;
		}

		$body = 'password' === $this->grant()
			? [
				'grant_type' => 'password',
				'username'   => $this->cfg['username'],
				'password'   => $this->cfg['password'],
			]
			: [ 'grant_type' => 'client_credentials' ];

		if ( ! empty( $this->cfg['scope'] ) ) {
			$body['scope'] = $this->cfg['scope'];
		}

		$res = $this->request_token( $body );
		if ( is_wp_error( $res ) || empty( $res['access_token'] ) ) {
			return null;
		}

		$ttl = (int) ( $res['expires_in'] ?? 3600 );
		set_transient( $cache_key, $res['access_token'], max( 60, $ttl - 60 ) );

		return $res['access_token'];
	}

	// ── Public entry point ────────────────────────────────────────────────────

	/**
	 * A valid bearer access token for the current grant, or null if the provider
	 * is not connected / credentials are missing / the token cannot be minted.
	 */
	public function access_token(): ?string {
		if ( ! $this->is_configured() ) {
			return null;
		}

		if ( ! $this->is_authorization_code() ) {
			return $this->fetch_two_legged();
		}

		$row = OAuthTokenStore::get( $this->provider_id );
		if ( ! empty( $row['access_token'] ) && $this->token_fresh( $row ) ) {
			return $row['access_token'];
		}
		// Expired (or unknown expiry) — try a silent refresh.
		return $this->refresh();
	}

	public function disconnect(): void {
		OAuthTokenStore::clear( $this->provider_id );
		delete_transient( self::CC_CACHE_PREFIX . $this->provider_id );
	}

	// ── Internals ─────────────────────────────────────────────────────────────

	/**
	 * @param array{expires_at?:int} $row
	 */
	private function token_fresh( array $row ): bool {
		if ( empty( $row['expires_at'] ) ) {
			// No expiry known — assume usable and let a 401 trigger a refresh next time.
			return true;
		}
		return (int) $row['expires_at'] > ( time() + self::EXPIRY_SKEW );
	}

	/**
	 * @param array<string,mixed> $token OAuth token response body.
	 */
	private function persist_tokens( array $token ): void {
		$data = [
			'access_token' => (string) ( $token['access_token'] ?? '' ),
			'token_type'   => (string) ( $token['token_type'] ?? 'Bearer' ),
			'scope'        => (string) ( $token['scope'] ?? $this->cfg['scope'] ),
			'obtained_at'  => time(),
		];
		if ( isset( $token['expires_in'] ) ) {
			$data['expires_at'] = time() + (int) $token['expires_in'];
		}
		if ( ! empty( $token['refresh_token'] ) ) {
			$data['refresh_token'] = (string) $token['refresh_token'];
		}

		OAuthTokenStore::save( $this->provider_id, $data );
	}

	/**
	 * POST the token endpoint (form-encoded, per the OAuth2 spec) and return the
	 * decoded token body, or a WP_Error whose code is the OAuth `error` field
	 * (e.g. invalid_grant) when the server reports one.
	 *
	 * @param array<string,scalar> $body Grant-specific params (client creds added here).
	 * @return array<string,mixed>|WP_Error
	 */
	private function request_token( array $body ) {
		$headers = [
			'Accept'       => 'application/json',
			'Content-Type' => 'application/x-www-form-urlencoded',
		];

		if ( 'basic' === $this->cfg['auth_style'] && ! empty( $this->cfg['client_id'] ) ) {
			$headers['Authorization'] = 'Basic ' . base64_encode(
				$this->cfg['client_id'] . ':' . $this->cfg['client_secret']
			);
		} else {
			if ( ! empty( $this->cfg['client_id'] ) ) {
				$body['client_id'] = $this->cfg['client_id'];
			}
			if ( ! empty( $this->cfg['client_secret'] ) ) {
				$body['client_secret'] = $this->cfg['client_secret'];
			}
		}

		$body = array_merge( $body, (array) $this->cfg['extra'] );

		$response = wp_remote_post( $this->cfg['token_url'], [
			'headers' => $headers,
			'body'    => $body, // array → form-encoded by WP.
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$data = is_array( $data ) ? $data : [];

		if ( $code < 200 || $code >= 300 || empty( $data['access_token'] ) ) {
			$err  = (string) ( $data['error'] ?? 'oauth_token_error' );
			$desc = (string) ( $data['error_description'] ?? $data['message'] ?? ( 'HTTP ' . $code ) );
			return new WP_Error( $err, $desc, [ 'status' => $code ] );
		}

		return $data;
	}
}
