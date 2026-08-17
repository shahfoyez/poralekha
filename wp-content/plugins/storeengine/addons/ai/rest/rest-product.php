<?php
/**
 * RestProduct — AI product-content generation (free).
 *
 * Route (namespace storeengine/v1):
 *   POST /ai/product/generate   { product_id, field, tone?, language? } → { text }
 *
 * The product editor's AiPanel posts here once per field and writes the result
 * back into the form. Generation is delegated to {@see ProductGenerator}.
 *
 * @since StoreEngine 1.7.0
 */

namespace StoreEngine\Addons\Ai\Rest;

use StoreEngine\Addons\Ai\Classes\ProductGenerator;
use StoreEngine\Addons\Ai\Classes\PromptLibrary;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RestProduct {

	const NS = 'storeengine/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function permission(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_storeengine_settings' );
	}

	public static function register_routes(): void {
		register_rest_route( self::NS, '/ai/product/generate', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ __CLASS__, 'generate' ],
			'permission_callback' => [ __CLASS__, 'permission' ],
			'args'                => [
				'product_id' => [ 'type' => 'integer', 'required' => true ],
				'field'      => [ 'type' => 'string', 'required' => true, 'enum' => PromptLibrary::fields() ],
				'tone'       => [ 'type' => 'string', 'required' => false ],
				'language'   => [ 'type' => 'string', 'required' => false ],
			],
		] );
	}

	public static function generate( WP_REST_Request $request ) {
		$opts = [];
		if ( null !== $request->get_param( 'tone' ) ) {
			$opts['tone'] = (string) $request->get_param( 'tone' );
		}
		if ( null !== $request->get_param( 'language' ) ) {
			$opts['language'] = (string) $request->get_param( 'language' );
		}

		$product_id = (int) $request->get_param( 'product_id' );
		$field      = (string) $request->get_param( 'field' );

		$result = ProductGenerator::generate( $product_id, $field, $opts );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Image alt text has no product form field, so persist it straight onto
		// the featured image attachment for the inline editor action.
		if ( 'image_alt' === $field ) {
			$thumb_id = get_post_thumbnail_id( $product_id );
			if ( $thumb_id ) {
				update_post_meta( $thumb_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $result ) );
			}
		}

		return new WP_REST_Response( [ 'text' => $result ], 200 );
	}
}

// End of file rest-product.php.
