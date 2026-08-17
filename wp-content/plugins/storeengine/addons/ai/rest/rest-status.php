<?php
/**
 * RestStatus — AI availability snapshot.
 *
 * Route (namespace storeengine/v1):
 *   GET /ai/status  → { supported, has_connector, available, image_available, connectors_url, min_wp }
 *
 * Single source of truth for every React "Generate" button: one call decides
 * whether to show generation actions or the "Connect WordPress AI" prompt.
 *
 * @since StoreEngine 1.7.0
 */

namespace StoreEngine\Addons\Ai\Rest;

use StoreEngine\AI\AiService;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RestStatus {

	const NS = 'storeengine/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function permission(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_storeengine_settings' );
	}

	public static function register_routes(): void {
		register_rest_route( self::NS, '/ai/status', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'get_status' ],
			'permission_callback' => [ __CLASS__, 'permission' ],
		] );
	}

	public static function get_status(): WP_REST_Response {
		return new WP_REST_Response( AiService::status(), 200 );
	}
}

// End of file rest-status.php.
