<?php
/**
 * Multi-Currency Ajax
 *
 * Endpoints
 * ─────────
 * POST storeengine_action / multi_currency/switch
 *   Sets the customer's display currency cookie.
 *   Public (no login required).
 *
 * POST storeengine_action / multi_currency/save_settings
 *   Saves admin settings. Detects newly added currencies and fetches their
 *   rates immediately. Returns full per-currency rate map for the admin UI.
 *   Requires manage_options capability.
 *
 * POST storeengine_action / multi_currency/refresh_rates
 *   Triggers an immediate exchange rate fetch and returns the new rates.
 *   Requires manage_options capability.
 *
 * GET  storeengine_action / multi_currency/get_rates
 *   Returns the currently cached rates with full per-currency metadata.
 *   Called when the admin settings page loads.
 *   Requires manage_options capability.
 *
 * @package StoreEngine\Addons\MultiCurrency
 */

namespace StoreEngine\Addons\MultiCurrency\Classes;

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\AbstractRequestHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Ajax extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [

			// ── Public: currency switcher ─────────────────────────────────
			'multi_currency/switch' => [
				'callback'             => [ $this, 'switch_currency' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'currency' => AbstractRequestHandler::STRING,
				],
			],

			// ── Admin: save settings ──────────────────────────────────────
			'multi_currency/save_settings' => [
				'callback'   => [ $this, 'save_settings' ],
				'capability' => 'manage_options',
				'fields'     => [
					// enabled_currencies is an array of currency objects.
					// Uses the repeated-field schema: a list wrapping one item schema.
					// AbstractRequestHandler::sanitize_by_schema() detects array_is_list
					// and applies the item schema to every element in the submitted array.
					'enabled_currencies' => [
						[
							'code'        => AbstractRequestHandler::STRING,
							'label'       => AbstractRequestHandler::STRING,
							'symbol'      => AbstractRequestHandler::STRING,
							// custom_rate: float or empty string (admin override).
							// nullable so missing / empty values are preserved as null.
							'custom_rate' => [
								'rules'    => 'float|min:0',
								'nullable' => true,
								'default'  => null,
							],
						],
					],
					'geolocation_enabled'      => AbstractRequestHandler::BOOLEAN,
					'rate_source'              => AbstractRequestHandler::STRING,
					'openexchangerates_app_id' => AbstractRequestHandler::STRING,
					'exchangerate_api_key'     => AbstractRequestHandler::STRING,
					'currencybeacon_api_key'   => AbstractRequestHandler::STRING,
					'refresh_interval_hours'   => AbstractRequestHandler::ABSINT,
					'show_switcher'            => AbstractRequestHandler::BOOLEAN,
					'formatting_locale'        => AbstractRequestHandler::STRING,
				],
			],

			// ── Admin: force rate refresh ────────────────────────────────
			'multi_currency/refresh_rates' => [
				'callback'   => [ $this, 'refresh_rates' ],
				'capability' => 'manage_options',
			],

			// ── Admin: get current cached rates ──────────────────────────
			'multi_currency/get_rates' => [
				'callback'   => [ $this, 'get_rates' ],
				'capability' => 'manage_options',
			],
		];

		$this->dispatch_actions();
	}

	// ── Handlers ─────────────────────────────────────────────────────────

	/**
	 * Switch the customer's active display currency.
	 * Sets a cookie and returns the new currency with the current rate.
	 *
	 * @param array $payload
	 * @return array
	 */
	public function switch_currency( array $payload ) {
		$currency = strtoupper( sanitize_text_field( $payload['currency'] ?? '' ) );

		if ( ! $currency ) {
			wp_send_json_error( [ 'message' => __( 'Currency code is required.', 'storeengine' ) ] );
		}

		if ( ! ActiveCurrency::is_allowed( $currency ) ) {
			wp_send_json_error( [
				'message' => sprintf(
					/* translators: %s: currency code */
					__( 'Currency %s is not enabled in this store.', 'storeengine' ),
					esc_html( $currency )
				),
			] );
		}

		// Capture previous currency before overwriting the cookie.
		$previous = strtoupper( sanitize_text_field( wp_unslash( $_COOKIE[ ActiveCurrency::COOKIE_NAME ] ?? '' ) ) )
					?: strtoupper( \StoreEngine\Utils\Helper::get_settings( 'store_currency', 'USD' ) );

		// Clear the cart so the customer starts fresh in the new currency.
		// Prices stored in the session are in the old currency — clearing is
		// simpler and safer than re-pricing every item on the fly.
		\StoreEngine\Utils\Helper::cart()->clear_cart();

		ActiveCurrency::set_cookie( $currency );

		// Fire action so other code can react to the switch.
		do_action( 'storeengine/multi_currency/switched', $previous, $currency );

		wp_send_json_success( [
			'currency'          => $currency,
			'previous_currency' => $previous,
			'rate'              => ExchangeRates::get_rate( $currency ),
			'symbol'            => \StoreEngine\Utils\Helper::get_currency_symbol( $currency ),
			// Cart is now empty — no reload needed for stale price reasons.
			// JS will still reload to update the URL param and page display.
			'cart_needs_reload' => true,
		] );
	}

	/**
	 * Save admin settings.
	 *
	 * Key behaviours:
	 * 1. Detects which currency codes are newly added vs already saved.
	 * 2. If any newly added code is missing from the rate cache → refresh now.
	 * 3. Returns full currency_rates map so the admin UI updates instantly —
	 *    no page reload needed after adding a currency.
	 *
	 * @param array $payload
	 * @return array
	 */
	public function save_settings( array $payload ) {

		// ── 1. Record old codes before saving ─────────────────────────────
		$old_codes = array_map(
			'strtoupper',
			array_column( Settings::get( 'enabled_currencies', [] ), 'code' )
		);

		$old_rate_source = Settings::get( 'rate_source', 'frankfurter' );

		$new_currencies_raw = $payload['enabled_currencies'] ?? [];
		$new_codes          = array_map(
			fn( $c ) => strtoupper( $c['code'] ?? '' ),
			$new_currencies_raw
		);

		// Codes present in new list but absent from old list.
		$added_codes = array_values( array_diff( $new_codes, $old_codes ) );

		// ── 2. Save all settings ───────────────────────────────────────────
		$old_interval = (int) Settings::get( 'refresh_interval_hours', 6 );

		Settings::save( [
			'enabled_currencies'       => $new_currencies_raw,
			'geolocation_enabled'      => (bool) ( $payload['geolocation_enabled']      ?? true ),
			'rate_source'              => sanitize_text_field( $payload['rate_source']              ?? 'frankfurter' ),
			'openexchangerates_app_id' => sanitize_text_field( $payload['openexchangerates_app_id'] ?? '' ),
			'exchangerate_api_key'     => sanitize_text_field( $payload['exchangerate_api_key']     ?? '' ),
			'currencybeacon_api_key'   => sanitize_text_field( $payload['currencybeacon_api_key']   ?? '' ),
			'refresh_interval_hours'   => max( 1, (int) ( $payload['refresh_interval_hours'] ?? 6 ) ),
			'show_switcher'            => (bool) ( $payload['show_switcher'] ?? true ),
			'formatting_locale'        => sanitize_text_field( $payload['formatting_locale'] ?? '' ),
		] );

		// ── 3. Reschedule cron if interval changed ─────────────────────────
		$new_interval = (int) Settings::get( 'refresh_interval_hours', 6 );
		if ( $new_interval !== $old_interval ) {
			Schedule::unschedule();
			Schedule::register();
		}

		// ── 4. Ensure rate cache covers all enabled currencies ─────────────
		//
		// Strategy:
		//  a) No transient at all              → full refresh (first save)
		//  b) Rate source changed              → full refresh (new source)
		//  c) New currencies added             → check each against cache
		//     Any code missing from cache?     → full refresh
		//     All codes already in cache?      → skip (cron handles updates)
		//  d) No new currencies                → skip
		//
		// Both Frankfurter and OXR return ALL currencies in one request,
		// so a single refresh covers any newly added code.
		//
		$new_rate_source = Settings::get( 'rate_source', 'frankfurter' );
		$cached = get_transient( ExchangeRates::TRANSIENT_KEY );

		if ( false === $cached ) {
			// No cache at all — must fetch now.
			$cached = ExchangeRates::refresh();
		} elseif ( $new_rate_source !== $old_rate_source ) {
			// Rate source changed — discard old cache and fetch from new source.
			delete_transient( ExchangeRates::TRANSIENT_KEY );
			$cached = ExchangeRates::refresh();
		} elseif ( ! empty( $added_codes ) ) {
			$cached_rates = is_array( $cached ) ? ( $cached['rates'] ?? [] ) : [];
			$missing      = array_filter(
				$added_codes,
				fn( $code ) => empty( $cached_rates[ $code ] )
			);

			if ( ! empty( $missing ) ) {
				// At least one newly added currency is absent from cache.
				$cached = ExchangeRates::refresh();
			}
		}

		if ( ! is_array( $cached ) ) {
			$cached = [];
		}

		// ── 5. Build and return the per-currency rate map ──────────────────
		$currency_rates = $this->build_currency_rate_map( $new_currencies_raw, $cached );

		wp_send_json_success([
			'settings'       => Settings::all(),
			'currency_rates' => $currency_rates,
			'rates_updated'  => $cached['updated'] ?? '',
			'rate_source'    => $cached['source']  ?? Settings::get( 'rate_source', 'frankfurter' ),
			'next_refresh'   => $this->get_next_refresh_time(),
		]);
	}

	/**
	 * Force an immediate rate fetch and return the new rates.
	 * Called when admin clicks the "Refresh now" button.
	 *
	 * @param array $payload
	 * @return array
	 */
	public function refresh_rates( array $payload ) {
		$rates = ExchangeRates::refresh();

		if ( empty( $rates['rates'] ) ) {
			wp_send_json_error( [
				'message' => __( 'Failed to fetch exchange rates. Check your rate source configuration and server connectivity.', 'storeengine' ),
			] );
		}

		$enabled        = Settings::get( 'enabled_currencies', [] );
		$currency_rates = $this->build_currency_rate_map( $enabled, $rates );

		wp_send_json_success([
			'currency_rates' => $currency_rates,
			'rates_updated'  => $rates['updated'] ?? '',
			'rate_source'    => $rates['source']  ?? '',
			'next_refresh'   => $this->get_next_refresh_time(),
		]);
	}

	/**
	 * Return the currently cached rates with full per-currency metadata.
	 * Called when the admin settings page loads.
	 *
	 * @param array $payload
	 * @return array
	 */
	public function get_rates( array $payload ) {
		$cached  = ExchangeRates::get_cached_rates();
		$enabled = Settings::get( 'enabled_currencies', [] );

		return wp_send_json_success([
			'base'           => $cached['base']    ?? '',
			'currency_rates' => $this->build_currency_rate_map( $enabled, $cached ),
			'rates_updated'  => $cached['updated'] ?? '',
			'rate_source'    => $cached['source']  ?? '',
			'is_cached'      => false !== get_transient( ExchangeRates::TRANSIENT_KEY ),
			'next_refresh'   => $this->get_next_refresh_time(),
		]);
	}

	// ── Private helpers ───────────────────────────────────────────────────

	/**
	 * Build a keyed rate map for the admin UI.
	 *
	 * Returns an array keyed by currency code:
	 * {
	 *   EUR: {
	 *     code:          'EUR'
	 *     label:         'Euro'
	 *     symbol:        '€'
	 *     rate:          0.923400    effective rate used for conversion
	 *     live_rate:     0.923400    rate from the API cache (null if unavailable)
	 *     custom_rate:   null        admin override, or null when using auto rate
	 *     is_manual:     false       true when custom_rate is active
	 *     source_label:  'Auto'      'Auto' | 'Manual' — badge shown in UI
	 *     preview:       '€0.9234'   how 1 base unit looks in this currency
	 *     has_live_rate: true        false when this currency is missing from cache
	 *   }
	 * }
	 *
	 * @param array $enabled_currencies  Currency objects from settings.
	 * @param array $rate_data           Full rate array from transient.
	 * @return array
	 */
	private function build_currency_rate_map( array $enabled_currencies, array $rate_data ): array {
		$map        = [];
		$base       = strtoupper( \StoreEngine\Utils\Helper::get_settings( 'store_currency', 'USD' ) );
		$cache_base = strtoupper( $rate_data['base'] ?? '' );
		$raw_rates  = $rate_data['rates'] ?? [];

		foreach ( $enabled_currencies as $entry ) {
			$code = strtoupper( $entry['code'] ?? '' );
			if ( ! $code ) {
				continue;
			}

			// Admin-set manual override.
			$custom_rate = isset( $entry['custom_rate'] ) && (float) $entry['custom_rate'] > 0
				? round( (float) $entry['custom_rate'], 6 )
				: null;

			// Live rate from the transient cache.
			$live_rate = null;
			if ( ! empty( $raw_rates[ $code ] ) ) {
				if ( $cache_base === $base ) {
					$live_rate = round( (float) $raw_rates[ $code ], 6 );
				} elseif ( ! empty( $raw_rates[ $base ] ) ) {
					$live_rate = round( (float) $raw_rates[ $code ] / (float) $raw_rates[ $base ], 6 );
				}
			}

			// Effective rate used for price conversion.
			$effective = $custom_rate ?? $live_rate ?? 1.0;

			$symbol    = \StoreEngine\Utils\Helper::get_currency_symbol( $code );

			$map[ $code ] = [
				'code'          => $code,
				'label'         => $entry['label']  ?? $code,
				'symbol'        => $symbol,
				'rate'          => round( $effective, 6 ),
				'live_rate'     => $live_rate,
				'custom_rate'   => $custom_rate,
				'is_manual'     => null !== $custom_rate,
				'source_label'  => null !== $custom_rate
					? __( 'Manual', 'storeengine' )
					: __( 'Auto',   'storeengine' ),
				'has_live_rate' => null !== $live_rate,
			];
		}

		return $map;
	}

	/**
	 * Return a human-readable "next scheduled refresh" string.
	 * e.g. "in 4h 32min" — shown in the admin status bar.
	 *
	 * @return string
	 */
	private function get_next_refresh_time(): string {
		if ( ! function_exists( 'as_next_scheduled_action' ) ) {
			return '';
		}

		$next = as_next_scheduled_action( Schedule::ACTION_HOOK );

		if ( ! $next ) {
			return __( 'not scheduled', 'storeengine' );
		}

		$diff = $next - time();

		if ( $diff <= 0 ) {
			return __( 'updating now', 'storeengine' );
		}

		$hours   = (int) floor( $diff / HOUR_IN_SECONDS );
		$minutes = (int) floor( ( $diff % HOUR_IN_SECONDS ) / MINUTE_IN_SECONDS );

		if ( $hours > 0 ) {
			/* translators: 1: hours 2: minutes */
			return sprintf( __( 'in %1$dh %2$dmin', 'storeengine' ), $hours, $minutes );
		}

		/* translators: %d: minutes */
		return sprintf( __( 'in %dmin', 'storeengine' ), max( 1, $minutes ) );
	}
}
