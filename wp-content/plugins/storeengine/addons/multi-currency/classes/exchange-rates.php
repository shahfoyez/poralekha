<?php
/**
 * Exchange Rates
 *
 * Fetches rates from a configurable external API and caches them in a
 * WordPress transient. Served from the object cache (Redis/Memcached when
 * available) so price display is near-zero cost.
 *
 * Storage
 * ───────
 * Transient key:  storeengine_exchange_rates
 * TTL:            Configurable (default 6 hours)
 * Written by:     Cron refresh + on-save instant refresh + cache-miss fallback
 * Read by:        Every price display call (fast — served from object cache)
 *
 * Transient structure
 * ───────────────────
 * {
 *   base:    'USD',
 *   rates:   { EUR: 0.9234, GBP: 0.7891, JPY: 149.32, ... },
 *   updated: '2026-03-31',
 *   source:  'frankfurter'
 * }
 *
 * Rate priority in get_rate()
 * ───────────────────────────
 * 1. Admin manual override (custom_rate on the currency setting object)
 * 2. Live rate from the transient cache
 * 3. 1.0 fallback (no conversion) if rate unavailable
 *
 * Update triggers
 * ───────────────
 * 1. Addon activation          — immediate fetch so addon works straight away
 * 2. Admin adds a currency     — detected in save_settings; fetches if missing
 * 3. ActionScheduler cron      — every N hours (configurable, default 6h)
 * 4. Cache miss                — transient expired before cron ran
 * 5. Admin clicks Refresh Now  — forces immediate fetch via AJAX
 *
 * Rate sources
 * ────────────
 * frankfurter       — https://api.frankfurter.app         FREE, no key, ~30 currencies (ECB)
 * openexchangerates — https://openexchangerates.org        FREE key, 170+ currencies
 * exchangerate-api  — https://exchangerate-api.com         FREE key, 161 currencies
 * currencybeacon    — https://currencybeacon.com           FREE key, 150+, 5,000 req/month
 *
 * @package StoreEngine\Addons\MultiCurrency
 */

namespace StoreEngine\Addons\MultiCurrency\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ExchangeRates {

	const TRANSIENT_KEY = 'storeengine_exchange_rates';

	// ── Public API ────────────────────────────────────────────────────────

	/**
	 * Get the exchange rate from the store base currency to $currency.
	 *
	 * Priority:
	 *   1. Admin manual override (custom_rate on the currency setting object)
	 *   2. Live/cached rate from the transient
	 *   3. 1.0 fallback if rate is unavailable
	 *
	 * @param string $currency  Target currency code, e.g. 'EUR'.
	 * @param string $base      Base currency code, e.g. 'USD'. Defaults to store base.
	 * @return float
	 */
	public static function get_rate( string $currency, string $base = '' ): float {
		$currency = strtoupper( $currency );
		$base     = strtoupper( $base ?: self::base_currency() );

		if ( $currency === $base ) {
			return 1.0;
		}

		// ── 1. Admin manual override ──────────────────────────────────────
		// If admin has set a custom_rate for this currency, use it directly.
		// This lets stores lock in a fixed rate (e.g. negotiated bank rate).
		$enabled = Settings::get( 'enabled_currencies', [] );
		foreach ( $enabled as $entry ) {
			if ( strtoupper( $entry['code'] ?? '' ) === $currency ) {
				$custom = isset( $entry['custom_rate'] ) ? (float) $entry['custom_rate'] : 0.0;
				if ( $custom > 0 ) {
					return $custom;
				}
				break;
			}
		}

		// ── 2. Live cached rate ───────────────────────────────────────────
		$data = self::get_cached_rates();

		if ( empty( $data['rates'][ $currency ] ) ) {
			return 1.0;
		}

		$cache_base = strtoupper( $data['base'] ?? '' );

		// Direct match — cache base equals our store base.
		if ( $cache_base === $base ) {
			return (float) $data['rates'][ $currency ];
		}

		// Cross-rate: cache is in a different base (e.g. USD cache, EUR store).
		$base_rate = (float) ( $data['rates'][ $base ] ?? 0.0 );
		if ( $base_rate <= 0 ) {
			return 1.0;
		}

		return (float) $data['rates'][ $currency ] / $base_rate;
	}

	/**
	 * Convert an amount from the store base currency to a display currency.
	 *
	 * @param float  $amount
	 * @param string $display_currency  e.g. 'EUR'
	 * @return float
	 */
	public static function convert( float $amount, string $display_currency ): float {
		return round( $amount * self::get_rate( $display_currency ), 6 );
	}

	/**
	 * Return cached rate data, fetching fresh if the transient is empty.
	 *
	 * @return array  { base:string, rates:array<string,float>, updated:string, source:string }
	 */
	public static function get_cached_rates(): array {
		$cached = get_transient( self::TRANSIENT_KEY );

		if ( false !== $cached && is_array( $cached ) && ! empty( $cached['rates'] ) ) {
			// If the cached data came from a different source than currently
			// configured, discard it and fetch fresh from the new source.
			$configured_source = Settings::get( 'rate_source', 'frankfurter' );
			if ( ( $cached['source'] ?? '' ) !== $configured_source ) {
				delete_transient( self::TRANSIENT_KEY );
				return self::refresh();
			}

			return $cached;
		}

		// Cache miss — fetch fresh and store.
		return self::refresh();
	}

	/**
	 * Fetch fresh rates from the configured source and store in transient.
	 * Returns the rate data array, or empty array on failure.
	 *
	 * @return array
	 */
	public static function refresh(): array {
		$source = Settings::get( 'rate_source', 'frankfurter' );
		$ttl    = max( 1, (int) Settings::get( 'refresh_interval_hours', 6 ) );

		switch ( $source ) {
			case 'openexchangerates':
				$data = self::fetch_openexchangerates();
				break;
			case 'exchangerate-api':
				$data = self::fetch_exchangerate_api();
				break;
			case 'currencybeacon':
				$data = self::fetch_currencybeacon();
				break;
			default:
				$data = self::fetch_frankfurter();
				break;
		}

		if ( ! empty( $data['rates'] ) ) {
			set_transient( self::TRANSIENT_KEY, $data, $ttl * HOUR_IN_SECONDS );
		}

		return $data;
	}

	/**
	 * Return the date/time when rates were last successfully fetched.
	 * Empty string if rates have never been cached.
	 *
	 * @return string  e.g. '2026-03-31' or '2026-03-31T12:00:00Z'
	 */
	public static function get_last_updated(): string {
		$cached = get_transient( self::TRANSIENT_KEY );
		return is_array( $cached ) ? ( $cached['updated'] ?? '' ) : '';
	}

	// ── Frankfurter — free, ECB data, no key ─────────────────────────────

	/**
	 * https://api.frankfurter.app
	 *
	 * Coverage:  ~30 major currencies
	 * Key:       None required
	 * Limit:     None published (best-effort public service)
	 * Updates:   Once per business day (ECB reference rates)
	 */
	private static function fetch_frankfurter(): array {
		$base = self::base_currency();
		$body = self::http_get( "https://api.frankfurter.app/latest?base={$base}" );

		if ( empty( $body['rates'] ) ) {
			return [];
		}

		// Frankfurter omits the base currency — add it as 1.0.
		$body['rates'][ $base ] = 1.0;

		return [
			'base'    => $body['base']  ?? $base,
			'rates'   => $body['rates'],
			'updated' => $body['date']  ?? gmdate( 'Y-m-d' ),
			'source'  => 'frankfurter',
		];
	}

	// ── Open Exchange Rates — free tier, needs app_id ─────────────────────

	/**
	 * https://openexchangerates.org
	 *
	 * Coverage:  170+ currencies
	 * Key:       Free app_id from openexchangerates.org
	 * Limit:     1,000 requests/month (free tier, USD base only)
	 */
	private static function fetch_openexchangerates(): array {
		$app_id = trim( (string) Settings::get( 'openexchangerates_app_id', '' ) );
		if ( ! $app_id ) {
			return [];
		}

		$body = self::http_get( "https://openexchangerates.org/api/latest.json?app_id={$app_id}&base=USD" );

		if ( empty( $body['rates'] ) ) {
			return [];
		}

		return [
			'base'    => 'USD',
			'rates'   => $body['rates'],
			'updated' => isset( $body['timestamp'] )
				? gmdate( 'Y-m-d\TH:i:s\Z', (int) $body['timestamp'] )
				: gmdate( 'Y-m-d' ),
			'source'  => 'openexchangerates',
		];
	}

	// ── ExchangeRate-API — free tier, 1,500 req/month ─────────────────────

	/**
	 * https://exchangerate-api.com
	 *
	 * Coverage:  161 currencies
	 * Key:       Free from exchangerate-api.com (no credit card required)
	 * Limit:     1,500 requests/month (free tier)
	 */
	private static function fetch_exchangerate_api(): array {
		$key = trim( (string) Settings::get( 'exchangerate_api_key', '' ) );
		if ( ! $key ) {
			return [];
		}

		$base = self::base_currency();
		$body = self::http_get( "https://v6.exchangerate-api.com/v6/{$key}/latest/{$base}" );

		if ( empty( $body['conversion_rates'] ) || ( $body['result'] ?? '' ) !== 'success' ) {
			return [];
		}

		return [
			'base'    => $body['base_code']            ?? $base,
			'rates'   => $body['conversion_rates'],
			'updated' => $body['time_last_update_utc']  ?? gmdate( 'Y-m-d' ),
			'source'  => 'exchangerate-api',
		];
	}

	// ── Currency Beacon — free tier, 5,000 req/month ──────────────────────

	/**
	 * https://currencybeacon.com
	 *
	 * Coverage:  150+ currencies
	 * Key:       Free from currencybeacon.com
	 * Limit:     5,000 requests/month (free tier — most generous free option)
	 */
	private static function fetch_currencybeacon(): array {
		$key = trim( (string) Settings::get( 'currencybeacon_api_key', '' ) );
		if ( ! $key ) {
			return [];
		}

		$base = self::base_currency();
		$body = self::http_get( "https://api.currencybeacon.com/v1/latest?api_key={$key}&base={$base}" );

		if ( empty( $body['rates'] ) ) {
			return [];
		}

		return [
			'base'    => $body['meta']['base']            ?? $base,
			'rates'   => $body['rates'],
			'updated' => $body['meta']['last_updated_at'] ?? gmdate( 'Y-m-d' ),
			'source'  => 'currencybeacon',
		];
	}

	// ── Shared HTTP helper ────────────────────────────────────────────────

	/**
	 * Make a GET request and return the decoded JSON body.
	 * Returns empty array on any failure (WP_Error, non-200, invalid JSON).
	 *
	 * @param string $url
	 * @return array
	 */
	private static function http_get( string $url ): array {
		$response = wp_safe_remote_get( $url, [
			'timeout'    => 5,
			'user-agent' => 'StoreEngine/' . ( defined( 'STOREENGINE_VERSION' ) ? STOREENGINE_VERSION : '1.0' ),
		] );

		if ( is_wp_error( $response ) ) {
			return [];
		}

		if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return [];
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		return is_array( $body ) ? $body : [];
	}

	// ── Helpers ───────────────────────────────────────────────────────────

	private static function base_currency(): string {
		return strtoupper( \StoreEngine\Utils\Helper::get_settings( 'store_currency', 'USD' ) );
	}
}
