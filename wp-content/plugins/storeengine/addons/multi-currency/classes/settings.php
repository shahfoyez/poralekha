<?php
/**
 * Multi-Currency Settings
 *
 * Stored in its own wp_options key — never inside the storeengine_settings
 * JSON blob, which is json_decoded on every request regardless of whether
 * multi-currency is active.
 *
 * Option key: storeengine_multi_currency_settings
 *
 * Enabled currency object shape:
 * {
 *   code:        string     — ISO 4217 code, e.g. 'EUR'
 *   label:       string     — Human name, e.g. 'Euro'
 *   symbol:      string     — Currency symbol, e.g. '€'
 *   custom_rate: float|null — Admin-set override rate. null = use live API rate.
 * }
 *
 * Rate sources
 * ────────────
 * frankfurter       — free, no key, ~30 currencies (ECB data)
 * openexchangerates — free key, 170+ currencies  (openexchangerates.org)
 * exchangerate-api  — free key, 161 currencies   (exchangerate-api.com)
 * currencybeacon    — free key, 150+ currencies  (currencybeacon.com, 5k/month)
 *
 * @package StoreEngine\Addons\MultiCurrency
 */

namespace StoreEngine\Addons\MultiCurrency\Classes;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	const OPTION_KEY = 'storeengine_multi_currency_settings';

	public function __construct() {
		// Expose settings to the admin JS via the storeengine/api/settings filter.
		add_filter( 'storeengine/api/settings', [ $this, 'expose_to_admin' ] );
	}

	// ── Read ──────────────────────────────────────────────────────────────

	/**
	 * Get a single setting value.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public static function get( string $key, $default = null ) {
		$settings = self::all();
		return $settings[ $key ] ?? $default;
	}

	/**
	 * Get all settings, merged with defaults.
	 *
	 * @return array
	 */
	public static function all(): array {
		$saved = get_option( self::OPTION_KEY );

		if ( $saved ) {
			$decoded = is_array( $saved ) ? $saved : json_decode( $saved, true );
			if ( is_array( $decoded ) ) {
				return wp_parse_args( $decoded, self::defaults() );
			}
		}

		return self::defaults();
	}

	// ── Defaults ──────────────────────────────────────────────────────────

	public static function defaults(): array {
		return [
			// Array of { code, label, symbol, custom_rate } objects.
			// custom_rate: null = use live API rate, float = admin override.
			'enabled_currencies'       => [],

			// Auto-detect visitor country → currency on first load.
			'geolocation_enabled'      => true,

			// 'frankfurter' | 'openexchangerates' | 'exchangerate-api' | 'currencybeacon'
			'rate_source'              => 'frankfurter',

			// API keys — only the key for the active source is used.
			'openexchangerates_app_id' => '',
			'exchangerate_api_key'     => '',
			'currencybeacon_api_key'   => '',

			// How often the ActionScheduler cron fetches fresh rates (hours).
			'refresh_interval_hours'   => 6,

			// Whether [se_currency_switcher] renders on the frontend.
			'show_switcher'            => true,

			// Optional: locale string for number formatting (e.g. 'de_DE').
			// Empty string = use WordPress locale.
			'formatting_locale'        => '',
		];
	}

	// ── Write ─────────────────────────────────────────────────────────────

	/**
	 * Save settings array.
	 *
	 * @param array $data
	 * @return bool
	 */
	public static function save( array $data ): bool {
		$merged = wp_parse_args( $data, self::all() );
		return update_option( self::OPTION_KEY, wp_json_encode( $merged ), false );
	}

	/**
	 * Write defaults once on addon activation.
	 */
	public static function save_default_settings_once(): void {
		if ( false === get_option( self::OPTION_KEY ) ) {
			update_option( self::OPTION_KEY, wp_json_encode( self::defaults() ), false );
		}
	}

	// ── Admin exposure ────────────────────────────────────────────────────

	/**
	 * Inject settings into the admin REST response so the React admin UI
	 * can read and update them via the standard StoreEngine settings endpoint.
	 *
	 * @param \stdClass $settings
	 * @return \stdClass
	 */
	public function expose_to_admin( \stdClass $settings ): \stdClass {
		$settings->multi_currency = self::all();
		return $settings;
	}
}
