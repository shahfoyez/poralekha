<?php
/**
 * RestTerm — AI taxonomy-term description generation.
 *
 * Route (namespace storeengine/v1):
 *   POST /ai/term/generate   { taxonomy, name, field } → { text }
 *
 * Accepts the term name (not a saved term id) so the endpoint works while the
 * Add Category / Add Tag form is still open and the term does not exist yet.
 *
 * @since StoreEngine 1.10.0
 */

namespace StoreEngine\Addons\Ai\Rest;

use StoreEngine\Addons\Ai\Classes\TermGenerator;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RestTerm {

	const NS = 'storeengine/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function permission(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_storeengine_settings' );
	}

	public static function register_routes(): void {
		register_rest_route( self::NS, '/ai/term/generate', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ __CLASS__, 'generate' ],
			'permission_callback' => [ __CLASS__, 'permission' ],
			'args'                => [
				'taxonomy' => [ 'type' => 'string', 'required' => true ],
				'name'     => [ 'type' => 'string', 'required' => true ],
				'field'    => [ 'type' => 'string', 'required' => true, 'enum' => [ 'description' ] ],
			],
		] );
	}

	public static function generate( WP_REST_Request $request ) {
		$taxonomy = sanitize_key( (string) $request->get_param( 'taxonomy' ) );
		$name     = sanitize_text_field( (string) $request->get_param( 'name' ) );

		$opts = [];
		if ( null !== $request->get_param( 'tone' ) ) {
			$opts['tone'] = (string) $request->get_param( 'tone' );
		}
		if ( null !== $request->get_param( 'language' ) ) {
			$opts['language'] = (string) $request->get_param( 'language' );
		}

		$result = TermGenerator::generate( $taxonomy, $name, $opts );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( [ 'text' => $result ], 200 );
	}
}

// End of file rest-term.php.
