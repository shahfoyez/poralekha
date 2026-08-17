<?php
/**
 * REST endpoint for Stripe Tax connectivity / readiness.
 *
 * GET /storeengine/v1/stripe-tax/status
 *
 * Used by the admin UI to render Ready / Warning / Error.
 */

namespace StoreEngine\Addons\Stripe\Tax;

use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StripeTaxRest {

	private const NAMESPACE = STOREENGINE_PLUGIN_SLUG . '/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route( self::NAMESPACE, '/stripe-tax/status', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ self::class, 'get_status' ],
				'permission_callback' => static function () {
					return current_user_can( 'manage_options' );
				},
			],
		] );
	}

	public static function get_status( WP_REST_Request $request ): WP_REST_Response {
		$enabled = StripeTaxSettings::is_enabled();
		$probe   = ( new StripeTaxService() )->probe();

		$state = 'error';
		if ( $probe['key_valid'] && 0 === $probe['registrations_count'] ) {
			$state = 'no_registrations';
		} elseif ( $probe['key_valid'] ) {
			$state = 'ready';
		}

		return rest_ensure_response( [
			'enabled'             => $enabled,
			'state'               => $state,
			'key_valid'           => (bool) $probe['key_valid'],
			'registrations_count' => (int) $probe['registrations_count'],
			'error'               => $probe['error'],
		] );
	}
}
