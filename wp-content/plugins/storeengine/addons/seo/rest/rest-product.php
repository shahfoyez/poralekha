<?php
/**
 * SeoController — REST surface for the SEO pro features.
 *
 * Routes (namespace storeengine/v1):
 *   GET/POST   /seo/products/{id}/meta    per-product title / description / robots (+ AI status)
 *   POST       /seo/generate              AI meta generation (core AI client)
 *
 * Global SEO engine settings (mode, schema, social, noindex) are a FREE feature
 * handled by the core `seo` addon's RestSettings (`/seo/settings`).
 *
 * @since StoreEngine Pro 1.0.0
 */

namespace StoreEngine\Addons\Seo\Rest;

use StoreEngine\Addons\Seo\Classes\MetaBox;
use StoreEngine\Addons\Seo\Classes\SeoGenerator;
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
		register_rest_route( self::NS, '/seo/products/(?P<id>\d+)/meta', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'get_product_meta' ],
				'permission_callback' => [ __CLASS__, 'permission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'update_product_meta' ],
				'permission_callback' => [ __CLASS__, 'permission' ],
			],
		] );

		register_rest_route( self::NS, '/seo/generate', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ __CLASS__, 'generate' ],
			'permission_callback' => [ __CLASS__, 'permission' ],
			'args'                => [
				'product_id' => [ 'type' => 'integer', 'required' => true ],
				'type'       => [ 'type' => 'string', 'default' => 'title', 'enum' => [ 'title', 'description', 'keywords', 'og_title', 'og_description' ] ],
			],
		] );
	}

	/* ------------------------------------------------------ product meta */

	/**
	 * Per-product SEO meta plus AI availability, so the editor panel can show
	 * or hide its "Generate" actions without a second request.
	 */
	protected static function meta_payload( int $id ): array {
		return array_merge( MetaBox::get_meta( $id ), [
			'ai_available'    => SeoGenerator::core_ai_available(),
			'ai_settings_url' => SeoGenerator::settings_url(),
		] );
	}

	public static function get_product_meta( WP_REST_Request $request ): WP_REST_Response {
		return new WP_REST_Response( self::meta_payload( (int) $request['id'] ), 200 );
	}

	public static function update_product_meta( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params() ?: $request->get_params();
		MetaBox::update_meta( (int) $request['id'], [
			'title'  => sanitize_text_field( (string) ( $params['title'] ?? '' ) ),
			'desc'   => sanitize_textarea_field( (string) ( $params['desc'] ?? '' ) ),
			'robots' => ( ( $params['robots'] ?? '' ) === 'noindex' ) ? 'noindex' : '',
		] );

		return new WP_REST_Response( self::meta_payload( (int) $request['id'] ), 200 );
	}

	/* ----------------------------------------------------------- AI gen */

	public static function generate( WP_REST_Request $request ) {
		$result = SeoGenerator::generate( (int) $request->get_param( 'product_id' ), (string) $request->get_param( 'type' ) );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( [ 'text' => $result ], 200 );
	}
}
