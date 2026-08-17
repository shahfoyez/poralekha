<?php
/**
 * ActiveCurrency
 *
 * Resolves which currency the customer should see prices in, in priority order:
 *
 *   1. URL parameter  ?currency=eur  (highest — explicit customer choice)
 *   2. Cookie         se_currency    (persists selection across pages)
 *   3. Geolocation    country → currency (if enabled in settings)
 *   4. Store base     USD / GBP / etc.  (fallback)
 *
 * The active currency is display-only.
 * All orders are created and charged in the store's base currency.
 * The storeengine/currency filter is the integration point with core.
 *
 * @package StoreEngine\Addons\MultiCurrency
 */

namespace StoreEngine\Addons\MultiCurrency\Classes;

use StoreEngine\Utils\Geolocation;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ActiveCurrency {

	const COOKIE_NAME    = 'se_currency';
	const COOKIE_DAYS    = 30;
	const URL_PARAM      = 'currency';

	// Country → ISO 4217 currency code mapping (most common countries).
	// Filterable via storeengine/multi_currency/country_currency_map.
	private static array $country_currency_map = [
		'US' => 'USD', 'GB' => 'GBP', 'DE' => 'EUR', 'FR' => 'EUR',
		'IT' => 'EUR', 'ES' => 'EUR', 'NL' => 'EUR', 'BE' => 'EUR',
		'AT' => 'EUR', 'PT' => 'EUR', 'FI' => 'EUR', 'IE' => 'EUR',
		'JP' => 'JPY', 'CN' => 'CNY', 'AU' => 'AUD', 'CA' => 'CAD',
		'CH' => 'CHF', 'SE' => 'SEK', 'NO' => 'NOK', 'DK' => 'DKK',
		'IN' => 'INR', 'BR' => 'BRL', 'MX' => 'MXN', 'KR' => 'KRW',
		'SG' => 'SGD', 'HK' => 'HKD', 'NZ' => 'NZD', 'ZA' => 'ZAR',
		'AE' => 'AED', 'SA' => 'SAR', 'TR' => 'TRY', 'PL' => 'PLN',
		'CZ' => 'CZK', 'HU' => 'HUF', 'RO' => 'RON', 'BG' => 'BGN',
		'HR' => 'EUR', 'MY' => 'MYR', 'TH' => 'THB', 'ID' => 'IDR',
		'PH' => 'PHP', 'VN' => 'VND', 'PK' => 'PKR', 'BD' => 'BDT',
		'NG' => 'NGN', 'KE' => 'KES', 'GH' => 'GHS', 'EG' => 'EGP',
		'AR' => 'ARS', 'CL' => 'CLP', 'CO' => 'COP', 'PE' => 'PEN',
		'RU' => 'RUB', 'UA' => 'UAH', 'IL' => 'ILS', 'QA' => 'QAR',
		'KW' => 'KWD', 'BH' => 'BHD', 'OM' => 'OMR',
	];

	// ── Resolve ───────────────────────────────────────────────────────────

	/**
	 * Get the active display currency code for the current request.
	 * Caches result in a static variable so it is resolved only once per request.
	 *
	 * @return string  e.g. 'EUR', 'JPY', 'USD'
	 */
	public static function get(): string {
		static $resolved = null;

		if ( null !== $resolved ) {
			return $resolved;
		}

		$resolved = self::resolve();
		return $resolved;
	}

	/**
	 * Run the priority chain and return the winner.
	 *
	 * @return string
	 */
	private static function resolve(): string {
		$enabled = self::get_enabled_codes();
		$base    = self::base_currency();

		// 1. URL parameter — highest priority, lets customers share links
		//    with a specific currency pre-selected.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only currency preference from a shareable link; no state change, sanitized and validated against the enabled list below.
		$from_url = isset( $_GET[ self::URL_PARAM ] )
			? strtoupper( sanitize_text_field( wp_unslash( $_GET[ self::URL_PARAM ] ) ) )
			: '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		if ( $from_url && self::is_allowed( $from_url, $enabled ) ) {
			self::set_cookie( $from_url );
			return $from_url;
		}

		// 2. Cookie — persists the customer's last explicit selection.
		$from_cookie = isset( $_COOKIE[ self::COOKIE_NAME ] )
			? strtoupper( sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) )
			: '';

		if ( $from_cookie && self::is_allowed( $from_cookie, $enabled ) ) {
			return $from_cookie;
		}

		// 3. Geolocation — detect from IP and map country → currency.
		if ( Settings::get( 'geolocation_enabled', true ) && ! Helper::is_bot() ) {
			$from_geo = self::resolve_from_geolocation( $enabled );
			if ( $from_geo ) {
				self::set_cookie( $from_geo );
				return $from_geo;
			}
		}

		// 4. Fallback to base currency.
		return $base;
	}

	// ── Geolocation resolution ────────────────────────────────────────────

	/**
	 * Use the existing StoreEngine geolocation system to detect the visitor's
	 * country and map it to a currency.
	 *
	 * @param  array $enabled  List of allowed currency codes.
	 * @return string  Currency code, or empty string if unresolvable.
	 */
	private static function resolve_from_geolocation( array $enabled ): string {
		// Use the transient-cached geolocation — same one used by tax and shipping.
		$geo = Geolocation::geolocate_ip( '', true, false );

		$country = strtoupper( $geo['country'] ?? '' );

		if ( ! $country ) {
			return '';
		}

		$map      = apply_filters( 'storeengine/multi_currency/country_currency_map', self::$country_currency_map );
		$currency = strtoupper( $map[ $country ] ?? '' );

		if ( ! $currency ) {
			return '';
		}

		if ( ! self::is_allowed( $currency, $enabled ) ) {
			return '';
		}

		return $currency;
	}

	// ── Cookie ────────────────────────────────────────────────────────────

	/**
	 * Set the currency cookie on the customer's browser.
	 * Only sets if not already set to the same value (avoids headers-sent issues).
	 *
	 * @param string $currency
	 */
	public static function set_cookie( string $currency ): void {
		if ( headers_sent() ) {
			return;
		}

		$current = isset( $_COOKIE[ self::COOKIE_NAME ] ) ? sanitize_text_field( wp_unslash( $_COOKIE[ self::COOKIE_NAME ] ) ) : '';

		if ( strtoupper( $current ) === strtoupper( $currency ) ) {
			return;
		}

		$expiry = time() + ( self::COOKIE_DAYS * DAY_IN_SECONDS );

		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.cookies_setcookie
		setcookie(
			self::COOKIE_NAME,
			strtoupper( $currency ),
			[
				'expires'  => $expiry,
				'path'     => COOKIEPATH ?: '/',
				'domain'   => COOKIE_DOMAIN ?: '',
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			]
		);

		// Update the superglobal so the same request sees the new value immediately.
		$_COOKIE[ self::COOKIE_NAME ] = strtoupper( $currency );
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	/**
	 * Check whether a currency code is in the enabled list or is the base.
	 *
	 * @param string $code
	 * @param array  $enabled
	 * @return bool
	 */
	public static function is_allowed( string $code, array $enabled = [] ): bool {
		if ( empty( $enabled ) ) {
			$enabled = self::get_enabled_codes();
		}

		return $code === self::base_currency() || in_array( $code, $enabled, true );
	}

	/**
	 * Get the list of enabled display currency codes from settings.
	 *
	 * @return string[]
	 */
	public static function get_enabled_codes(): array {
		$currencies = Settings::get( 'enabled_currencies', [] );
		return array_column( $currencies, 'code' );
	}

	private static function base_currency(): string {
		return strtoupper( Helper::get_settings( 'store_currency', 'USD' ) );
	}
}
