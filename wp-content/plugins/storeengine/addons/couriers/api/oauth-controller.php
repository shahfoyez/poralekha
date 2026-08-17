<?php
/**
 * REST endpoints for the courier OAuth (3-legged) connect flow.
 *
 *   GET  /couriers/oauth/{provider}/authorize-url  → { url } to send the admin to
 *   GET  /couriers/oauth/callback                  → provider redirect lands here
 *   POST /couriers/oauth/{provider}/disconnect     → forget stored tokens
 *
 * The callback is intentionally public (the courier redirects a browser to it,
 * with no WP nonce) and is instead guarded by the one-time `state` minted in
 * authorize_url() — an unknown/expired state is rejected. On completion it
 * 302-redirects the browser back to Settings → Couriers with a status flag.
 *
 * @package StoreEngine\Addons\Couriers
 */

namespace StoreEngine\Addons\Couriers\Api;

use StoreEngine\Addons\Couriers\Classes\OAuth;
use StoreEngine\Addons\Couriers\Classes\Registry;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class OAuthController {

	const NS = 'storeengine/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route( self::NS, '/couriers/oauth/(?P<provider>[\w-]+)/authorize-url', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'authorize_url' ],
			'permission_callback' => [ __CLASS__, 'permission' ],
			'args'                => [
				'provider' => [ 'type' => 'string', 'required' => true ],
			],
		] );

		register_rest_route( self::NS, '/couriers/oauth/(?P<provider>[\w-]+)/disconnect', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'disconnect' ],
			'permission_callback' => [ __CLASS__, 'permission' ],
			'args'                => [
				'provider' => [ 'type' => 'string', 'required' => true ],
			],
		] );

		// Verify 2-legged (client_credentials / password) OAuth by minting a token.
		register_rest_route( self::NS, '/couriers/oauth/(?P<provider>[\w-]+)/test', [
			'methods'             => 'POST',
			'callback'            => [ __CLASS__, 'test' ],
			'permission_callback' => [ __CLASS__, 'permission' ],
			'args'                => [
				'provider' => [ 'type' => 'string', 'required' => true ],
			],
		] );

		// Public — validated by the one-time `state`, not a nonce.
		register_rest_route( self::NS, '/couriers/oauth/callback', [
			'methods'             => 'GET',
			'callback'            => [ __CLASS__, 'callback' ],
			'permission_callback' => '__return_true',
		] );
	}

	public static function permission(): bool {
		return current_user_can( 'manage_storeengine_settings' )
			|| current_user_can( 'manage_options' );
	}

	public static function authorize_url( WP_REST_Request $request ) {
		$provider = (string) $request['provider'];
		$oauth    = OAuth::for_provider( $provider );

		if ( ! $oauth ) {
			return new WP_Error( 'storeengine_oauth_unsupported', __( 'This courier does not support OAuth.', 'storeengine' ), [ 'status' => 400 ] );
		}
		if ( ! $oauth->is_authorization_code() ) {
			return new WP_Error( 'storeengine_oauth_not_interactive', __( 'This courier uses server credentials — no connect step is required.', 'storeengine' ), [ 'status' => 400 ] );
		}
		if ( ! $oauth->is_configured() ) {
			return new WP_Error( 'storeengine_oauth_unconfigured', __( 'Enter and save the client ID and secret for this courier first.', 'storeengine' ), [ 'status' => 400 ] );
		}

		return rest_ensure_response( [ 'url' => $oauth->authorize_url() ] );
	}

	/**
	 * Uniform disconnect for any courier: revoke OAuth tokens for interactive
	 * couriers; for the rest (2-legged OAuth / API-key), "disconnect" clears the
	 * saved credentials so the card returns to a not-connected state.
	 */
	public static function disconnect( WP_REST_Request $request ) {
		$id    = (string) $request['provider'];
		$oauth = OAuth::for_provider( $id );

		if ( $oauth ) {
			$oauth->disconnect();
		}

		if ( ! $oauth || ! $oauth->is_authorization_code() ) {
			$all = (array) get_option( 'storeengine_courier_settings', [] );
			if ( isset( $all[ $id ] ) ) {
				unset( $all[ $id ] );
				update_option( 'storeengine_courier_settings', $all );
			}
		}

		return rest_ensure_response( [ 'ok' => true, 'connected' => false ] );
	}

	/**
	 * Test a 2-legged (client_credentials / password) courier by minting a token
	 * from the saved client id/secret — the "auth check" for carriers that have
	 * no interactive connect step.
	 */
	public static function test( WP_REST_Request $request ) {
		$oauth = OAuth::for_provider( (string) $request['provider'] );
		if ( ! $oauth ) {
			return new WP_Error( 'storeengine_oauth_unsupported', __( 'This courier does not support OAuth.', 'storeengine' ), [ 'status' => 400 ] );
		}
		if ( ! $oauth->is_configured() ) {
			return new WP_Error( 'storeengine_oauth_unconfigured', __( 'Enter and save this courier’s credentials first, then test the connection.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$token = $oauth->access_token();
		if ( ! $token ) {
			return new WP_Error( 'storeengine_oauth_auth_failed', __( 'Could not authenticate with the courier — double-check the credentials.', 'storeengine' ), [ 'status' => 400 ] );
		}

		return rest_ensure_response( [ 'ok' => true, 'connected' => true ] );
	}

	/**
	 * Provider redirect target. Validates `state`, exchanges `code`, then bounces
	 * the browser back to the couriers settings screen with a status flag.
	 */
	public static function callback( WP_REST_Request $request ) {
		$state = (string) $request->get_param( 'state' );
		$code  = (string) $request->get_param( 'code' );
		$error = (string) $request->get_param( 'error' );

		$resolved = OAuth::consume_state( $state );
		if ( ! $resolved ) {
			return self::bounce( '', 'invalid_state' );
		}

		$provider = $resolved['provider'];

		// The courier denied consent or reported an error.
		if ( $error || '' === $code ) {
			return self::bounce( $provider, $error ?: 'no_code' );
		}

		$oauth = OAuth::for_provider( $provider );
		if ( ! $oauth ) {
			return self::bounce( $provider, 'unsupported' );
		}

		$result = $oauth->exchange_code( $code );
		if ( is_wp_error( $result ) ) {
			return self::bounce( $provider, $result->get_error_code() ?: 'exchange_failed' );
		}

		return self::bounce( $provider, '' );
	}

	/**
	 * 302 back to Settings → Couriers with the outcome. Empty $error = success.
	 */
	private static function bounce( string $provider, string $error ) {
		$url = add_query_arg(
			array_filter( [
				'page'         => 'storeengine-settings',
				'path'         => 'couriers',
				'se_oauth'     => $provider,
				'oauth_status' => $error ? 'error' : 'success',
				'oauth_error'  => $error ?: null,
			] ),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}
