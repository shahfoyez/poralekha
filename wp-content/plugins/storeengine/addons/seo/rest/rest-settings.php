<?php
/**
 * RestSettings — read/write the global SEO engine settings (free).
 *
 * Routes (namespace storeengine/v1):
 *   GET/POST   /seo/settings
 *
 * These settings configure the FREE engine (integration mode, schema toggles,
 * social cards, organization, search-visibility/noindex), stored under the
 * `seo` sub-key of the shared StoreEngine settings option. Per-product SEO and
 * AI generation are separate pro features in storeengine-pro.
 *
 * @since StoreEngine 1.0.0
 */

namespace StoreEngine\Addons\Seo\Rest;

use StoreEngine\Addons\Seo\Settings;
use StoreEngine\Admin\Settings\Base;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RestSettings {

	const NS = 'storeengine/v1';

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function permission(): bool {
		return current_user_can( 'manage_options' ) || current_user_can( 'manage_storeengine_settings' );
	}

	public static function register_routes(): void {
		register_rest_route( self::NS, '/seo/settings', [
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
			'mode'                      => 'enum:auto,standalone,bridge',
			'schema_product_enabled'    => 'bool',
			'schema_breadcrumb_enabled' => 'bool',
			'og_enabled'                => 'bool',
			'twitter_card_type'         => 'enum:summary,summary_large_image',
			'twitter_site'              => 'text',
			'default_share_image_id'    => 'int',
			'org_name'                  => 'text',
			'org_logo_id'               => 'int',
			'noindex_cart'              => 'bool',
			'noindex_checkout'          => 'bool',
			'noindex_account'           => 'bool',
			'noindex_thankyou'          => 'bool',
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
		// level, so the complete `seo` sub-array must be written each time.
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
	 * @param string $type  bool | int | text | enum:a,b,c
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
			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}
}

// End of file rest-settings.php.
