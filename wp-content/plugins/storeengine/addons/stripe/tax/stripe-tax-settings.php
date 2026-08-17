<?php
/**
 * Stripe Automatic Tax — settings accessor.
 *
 * Reads from StoreEngine's main settings blob (`storeengine_settings`) so all
 * tax options live in one place under the Tax tab.
 */

namespace StoreEngine\Addons\Stripe\Tax;

use StoreEngine\Admin\Settings\Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StripeTaxSettings {

	/**
	 * Map this addon's logical keys to the keys actually stored in
	 * `storeengine_settings`.
	 */
	private const KEY_MAP = [
		'enabled'            => 'enable_stripe_tax',
		'default_tax_code'   => 'stripe_tax_default_code',
		'shipping_tax_code'  => 'stripe_tax_shipping_code',
		'fallback_to_local'  => 'stripe_tax_fallback_to_local',
		// Shared with the local engine.
		'prices_include_tax' => 'prices_include_tax',
	];

	public static function defaults(): array {
		return [
			'enabled'            => false,
			'default_tax_code'   => 'txcd_99999999',
			'shipping_tax_code'  => 'txcd_92010001',
			'fallback_to_local'  => false,
			'prices_include_tax' => false,
		];
	}

	public static function all(): array {
		$saved = Base::get_settings_saved_data();
		$out   = self::defaults();
		foreach ( self::KEY_MAP as $local => $base_key ) {
			if ( array_key_exists( $base_key, $saved ) ) {
				$out[ $local ] = $saved[ $base_key ];
			}
		}

		return $out;
	}

	public static function get( string $key, $default = null ) {
		$all = self::all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $default;
	}

	public static function is_enabled(): bool {
		return (bool) self::get( 'enabled', false );
	}
}
