<?php
/**
 * EU VAT Settings.
 *
 * Stored in its own wp_options key — never inside the storeengine_settings
 * JSON blob, which is loaded on every request whether or not the addon is
 * active.
 *
 * Option key: storeengine_eu_vat_settings
 *
 * @package StoreEngine\Addons\EuVat
 */

namespace StoreEngine\Addons\EuVat\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	const OPTION_KEY = 'storeengine_eu_vat_settings';

	public function __construct() {
		add_filter( 'storeengine/api/settings', [ $this, 'expose_to_admin' ] );
	}

	public static function get( string $key, $default = null ) {
		$settings = self::all();
		return $settings[ $key ] ?? $default;
	}

	public static function all(): array {
		$saved = get_option( self::OPTION_KEY, [] );
		return wp_parse_args( is_array( $saved ) ? $saved : [], self::defaults() );
	}

	public static function update( array $values ): void {
		$merged = wp_parse_args( $values, self::all() );
		update_option( self::OPTION_KEY, $merged );
	}

	public static function save_default_settings_once(): void {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			update_option( self::OPTION_KEY, self::defaults() );
		}
	}

	public static function defaults(): array {
		return [
			'field_label'              => __( 'EU VAT Number', 'storeengine' ),
			'field_placeholder'        => __( 'e.g. DE123456789', 'storeengine' ),
			'field_description'        => __( 'Enter your VAT number to be exempt from VAT (where applicable).', 'storeengine' ),
			'field_required'           => 'optional', // optional | required | required_if_company
			'preserve_in_base_country' => 'yes',
			'preserve_countries'       => [],
			'messages'                 => [
				'validating'        => __( 'Validating VAT number…', 'storeengine' ),
				'valid'             => __( 'VAT number is valid. VAT will be removed where applicable.', 'storeengine' ),
				'invalid'           => __( 'The VAT number entered is not valid.', 'storeengine' ),
				'validation_failed' => __( 'Could not reach the VAT validation service. Please try again later.', 'storeengine' ),
			],
			'debug_logging'            => 'no',
		];
	}

	/**
	 * Expose to the admin React UI via the storeengine/api/settings filter.
	 * Multi-currency uses the same hook to surface its own settings tab.
	 *
	 * The settings payload is a `\stdClass` (a clone of $storeengine_settings),
	 * not an array — so the value is attached as an object property, mirroring
	 * the multi-currency addon.
	 */
	public function expose_to_admin( $settings ) {
		if ( is_object( $settings ) ) {
			$settings->eu_vat = self::all();
		} elseif ( is_array( $settings ) ) {
			$settings['eu_vat'] = self::all();
		}
		return $settings;
	}
}
