<?php
/**
 * RestSettings — read/write the AI authoring settings (free).
 *
 * Routes (namespace storeengine/v1):
 *   GET/POST   /ai/settings
 *
 * Stored under the `ai` sub-key of the shared StoreEngine settings option.
 * Mirrors the seo addon's RestSettings full-array overlay save.
 *
 * @since StoreEngine 1.7.0
 */

namespace StoreEngine\Addons\Ai\Rest;

use StoreEngine\Addons\Ai\Settings;
use StoreEngine\Admin\Settings\Base;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RestSettings {

	const NS = 'storeengine/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function permission(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_storeengine_settings' );
	}

	public static function register_routes(): void {
		register_rest_route( self::NS, '/ai/settings', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'get_settings' ],
				'permission_callback' => [ __CLASS__, 'permission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'save_settings' ],
				'permission_callback' => [ __CLASS__, 'permission' ],
			],
		] );
	}

	/** Keys this tab manages, with their value type for sanitisation. */
	protected static function field_types(): array {
		return [
			'enabled'           => 'bool',
			'default_tone'      => 'enum:professional,friendly,persuasive,minimal',
			'default_language'  => 'text',
			'temperature'       => 'float',
			'model_preference'  => 'text',
			'product_fields'    => 'list',
			'image_alt_enabled' => 'bool',
		];
	}

	protected static function current(): array {
		return (array) Settings::init()->load_settings( true );
	}

	public static function get_settings(): WP_REST_Response {
		return new WP_REST_Response( self::current(), 200 );
	}

	public static function save_settings( WP_REST_Request $request ): WP_REST_Response {
		$params = $request->get_json_params() ?: $request->get_params();

		// Full-array overlay; Base::save_settings merges shallowly at the top
		// level, so the complete `ai` sub-array must be written each time.
		$settings = self::current();
		foreach ( self::field_types() as $key => $type ) {
			if ( array_key_exists( $key, $params ) ) {
				$settings[ $key ] = self::sanitize_field( $type, $params[ $key ] );
			}
		}

		Base::save_settings( [ Settings::init()->get_settings_name() => $settings ] );

		// Return in-memory values — the settings global is stale right after save.
		return new WP_REST_Response( $settings, 200 );
	}

	/**
	 * @param string $type  bool | int | float | text | list | enum:a,b,c
	 * @param mixed  $value
	 *
	 * @return mixed
	 */
	protected static function sanitize_field( string $type, $value ) {
		if ( 0 === strpos( $type, 'enum:' ) ) {
			$allowed = explode( ',', substr( $type, 5 ) );
			$value   = sanitize_text_field( (string) $value );

			return in_array( $value, $allowed, true ) ? $value : $allowed[0];
		}

		switch ( $type ) {
			case 'bool':
				return (bool) $value;
			case 'int':
				return (int) $value;
			case 'float':
				return max( 0, min( 1, (float) $value ) );
			case 'list':
				return array_values( array_map( 'sanitize_text_field', (array) $value ) );
			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}
}

// End of file rest-settings.php.
