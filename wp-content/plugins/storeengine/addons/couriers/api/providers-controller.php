<?php

namespace StoreEngine\Addons\Couriers\Api;

use StoreEngine\Addons\Couriers\Classes\Registry;
use StoreEngine\Addons\Couriers\Classes\OAuth;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ProvidersController {

	const NS = 'storeengine/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route( self::NS, '/couriers/providers', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'list_all' ],
			'permission_callback' => [ __CLASS__, 'permission' ],
		] );

		register_rest_route( self::NS, '/couriers/providers/pathao/cities', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'pathao_cities' ],
			'permission_callback' => [ __CLASS__, 'permission' ],
		] );

		register_rest_route( self::NS, '/couriers/providers/pathao/cities/(?P<city_id>\d+)/zones', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'pathao_zones' ],
			'permission_callback' => [ __CLASS__, 'permission' ],
		] );
	}

	public static function permission(): bool {
		return current_user_can( 'manage_options' )
			|| current_user_can( 'manage_storeengine_settings' )
			|| current_user_can( 'edit_storeengine_orders' );
	}

	public static function list_all() {
		$out = [];
		foreach ( Registry::all() as $id => $provider ) {
			$out[] = [
				'id'              => $id,
				'label'           => $provider->label(),
				'settings_schema' => $provider->settings_schema(),
				'oauth'           => self::oauth_state( $id ),
				// One uniform "connected" flag the UI reads for every courier:
				// a live OAuth token for interactive couriers, saved credentials
				// for everyone else. Drives the single Connect/Disconnect button.
				'connected'       => self::is_connected( $id, $provider ),
				// Setup docs + where to get credentials (staging vs production).
				'docs'            => method_exists( $provider, 'docs' ) ? (array) $provider->docs() : [],
			];
		}
		return rest_ensure_response( $out );
	}

	private static function is_connected( string $id, $provider ): bool {
		$oauth = OAuth::for_provider( $id );
		if ( $oauth && $oauth->is_authorization_code() ) {
			return $oauth->is_connected();
		}
		// "Connected" for non-interactive couriers = auth credentials present
		// (not the full shipping config); is_authenticated() narrows to those.
		if ( method_exists( $provider, 'is_authenticated' ) ) {
			return (bool) $provider->is_authenticated();
		}
		return method_exists( $provider, 'is_configured' ) ? (bool) $provider->is_configured() : false;
	}

	/**
	 * OAuth connection state for a provider, so the settings UI can render a
	 * Connect / Disconnect control for the 3-legged (authorization_code) flow.
	 * `supported` is false for API-key providers and 2-legged carriers (which
	 * need no interactive connect step).
	 *
	 * @return array{supported:bool,grant?:string,interactive?:bool,configured?:bool,connected?:bool,expires_at?:int|null}
	 */
	private static function oauth_state( string $id ): array {
		$oauth = OAuth::for_provider( $id );
		if ( ! $oauth ) {
			return [ 'supported' => false ];
		}

		return [
			'supported'   => true,
			'grant'       => $oauth->grant(),
			// Only the authorization_code grant needs a user-facing Connect button.
			'interactive' => $oauth->is_authorization_code(),
			'configured'  => $oauth->is_configured(),
			'connected'   => $oauth->is_connected(),
			'expires_at'  => $oauth->expires_at(),
		];
	}

	public static function pathao_cities() {
		// Pathao now ships in the storeengine-courier satellite; resolve it off
		// the provider registry and duck-type its lookup helper rather than
		// hard-referencing the concrete class (which no longer lives in Pro).
		$pathao = Registry::get( 'pathao' );
		if ( ! $pathao || ! method_exists( $pathao, 'list_cities' ) ) {
			return rest_ensure_response( [] );
		}
		return rest_ensure_response( $pathao->list_cities() );
	}

	public static function pathao_zones( WP_REST_Request $request ) {
		$pathao = Registry::get( 'pathao' );
		if ( ! $pathao || ! method_exists( $pathao, 'list_zones' ) ) {
			return rest_ensure_response( [] );
		}
		return rest_ensure_response( $pathao->list_zones( (int) $request['city_id'] ) );
	}
}
