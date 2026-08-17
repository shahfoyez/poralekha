<?php
/**
 * GeoLocation Helper API trait.
 *
 * @version 1.0.0
 * @since 1.5.6
 *
 * @package StoreEngine/Utils/traits
 */

namespace StoreEngine\Utils\traits;

use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

trait Geolocation {
	/**
	 * API endpoints for looking up user IP address.
	 *
	 * @var array
	 */
	private static array $ip_lookup_apis = [
		'ipify'  => 'http://api.ipify.org/',
		'ipecho' => 'http://ipecho.net/plain',
		'ident'  => 'http://ident.me',
		'tnedi'  => 'http://tnedi.me',
	];

	/**
	 * API endpoints for geolocating an IP address
	 *
	 * @var array
	 */
	private static array $geoip_apis = [
		'ipinfo.io'     => 'http://ipinfo.io/%s/json',
		'ip-api.com'    => 'http://ip-api.com/json/%s',
		'freeipapi.com' => 'http://freeipapi.com/api/json/%s',
		'ipapi.co'      => 'http://ipapi.co/%s/json/',
		'ipwhois.app'   => 'https://ipwhois.app/json/%s',
	];

	/**
	 * Check if geolocation is enabled.
	 *
	 * @return bool
	 */
	public static function is_geolocation_enabled(): bool {
		return in_array( Helper::get_settings( 'default_customer_address', 'store-base' ), [ 'ip-geolocation', 'geolocation' ], true );
	}

	/**
	 * Get user IP Address using an external service.
	 * This can be used as a fallback for users on localhost where
	 * get_ip_address() will be a local IP and non-geolocatable.
	 *
	 * @return string
	 */
	public static function get_external_ip_address(): string {
		$external_ip_address = '0.0.0.0';

		if ( '' !== self::get_user_ip() ) {
			$transient_name      = 'external_ip_address_' . md5( self::get_user_ip() );
			$external_ip_address = apply_filters( 'storeengine/geolocation/external_ip_address', get_transient( $transient_name ) );
		}

		if ( false === $external_ip_address ) {
			$external_ip_address     = '0.0.0.0';
			$ip_lookup_services      = apply_filters( 'storeengine/geolocation/ip_lookup_apis', self::$ip_lookup_apis );
			$ip_lookup_services_keys = array_keys( $ip_lookup_services );
			shuffle( $ip_lookup_services_keys );

			foreach ( $ip_lookup_services_keys as $service_name ) {
				$response = wp_safe_remote_get( $ip_lookup_services[ $service_name ], [
					'timeout'    => 2,
					'user-agent' => 'StoreEngine/' . STOREENGINE_VERSION,
				] );

				if ( ! is_wp_error( $response ) && rest_is_ip_address( $response['body'] ) ) {
					$external_ip_address = apply_filters( 'storeengine/geolocation/ip_lookup_api_response', Formatting::clean( $response['body'] ), $service_name );
					break;
				}
			}

			/** @noinspection PhpUndefinedVariableInspection */
			set_transient( $transient_name, $external_ip_address, DAY_IN_SECONDS );
		}

		return $external_ip_address;
	}

	/**
	 * Geolocate an IP address.
	 *
	 * @param  string $ip_address   IP Address.
	 * @param bool $fallback     If true, fallbacks to alternative IP detection (can be slower).
	 * @param bool $api_fallback If true, uses geolocation APIs if the database file doesn't exist (can be slower).
	 *
	 * @return array
	 */
	public static function geolocate_ip( string $ip_address = '', bool $fallback = false, bool $api_fallback = true ): array {
		/**
		 * Filter to allow custom geolocation of the IP address.
		 *
		 * @param string $geolocation Country code.
		 * @param string $ip_address IP Address.
		 * @param bool $fallback If true, fallbacks to alternative IP detection (can be slower).
		 * @param bool $api_fallback If true, uses geolocation APIs if the database file doesn't exist (can be slower).
		 * @return string
		 */
		$country_code = apply_filters( 'storeengine/geolocation/pre-geo-locate-ip', false, $ip_address, $fallback, $api_fallback );
		if ( false !== $country_code ) {
			return [
				'country'  => $country_code,
				'state'    => '',
				'city'     => '',
				'postcode' => '',
			];
		}

		if ( empty( $ip_address ) ) {
			$ip_address   = self::get_user_ip();
			$country_code = self::get_country_code_from_headers();
		}

		/**
		 * Get geolocation filter.
		 *
		 * @param array  $geolocation Geolocation data, including country, state, city, and postcode.
		 * @param string $ip_address  IP Address.
		 */
		$geolocation = apply_filters(
			'storeengine/geolocation/geo-locate-ip',
			[
				'country'  => $country_code,
				'state'    => '',
				'city'     => '',
				'postcode' => '',
			],
			$ip_address
		);

		// If we still haven't found a country code, let's consider doing an API lookup.
		if ( '' === $geolocation['country'] && $api_fallback ) {
			$geolocation['country'] = self::geolocate_via_api( $ip_address );
		}

		// It's possible that we're in a local environment, in which case the geolocation needs to be done from the
		// external address.
		if ( '' === $geolocation['country'] && $fallback ) {
			$external_ip_address = self::get_external_ip_address();

			// Only bother with this if the external IP differs.
			if ( '0.0.0.0' !== $external_ip_address && $external_ip_address !== $ip_address ) {
				return self::geolocate_ip( $external_ip_address, false, $api_fallback );
			}
		}

		return [
			'country'  => $geolocation['country'],
			'state'    => $geolocation['state'],
			'city'     => $geolocation['city'],
			'postcode' => $geolocation['postcode'],
		];
	}

	/**
	 * Fetches the country code from the request headers, if one is available.
	 *
	 * @return string The country code pulled from the headers, or empty string if one was not found.
	 */
	private static function get_country_code_from_headers() {
		$country_code = '';

		$headers = [
			'MM_COUNTRY_CODE',
			'GEOIP_COUNTRY_CODE',
			'HTTP_CF_IPCOUNTRY',
			'HTTP_X_COUNTRY_CODE',
		];

		foreach ( $headers as $header ) {
			if ( empty( $_SERVER[ $header ] ) ) {
				continue;
			}

			$country_code = strtoupper( sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) );
			break;
		}

		return $country_code;
	}

	/**
	 * Use APIs to Geolocate the user.
	 *
	 * Geolocation APIs can be added through the use of the storeengine/geolocation/geoip_apis filter.
	 * Provide a name=>value pair for service-slug=>endpoint.
	 *
	 * If APIs are defined, one will be chosen at random to fulfil the request. After completing, the result
	 * will be cached in a transient.
	 *
	 * @param  string $ip_address IP address.
	 * @return string
	 */
	private static function geolocate_via_api( string $ip_address ) {
		$country_code = get_transient( 'se_geoip_' . md5( $ip_address ) );

		if ( false === $country_code ) {
			$geoip_services = apply_filters( 'storeengine/geolocation/geoip_apis', self::$geoip_apis );

			if ( empty( $geoip_services ) ) {
				return '';
			}

			$geoip_services_keys = array_keys( $geoip_services );

			shuffle( $geoip_services_keys );

			foreach ( $geoip_services_keys as $service_name ) {
				$response = wp_safe_remote_get( sprintf( $geoip_services[ $service_name ], $ip_address ), [
					'timeout'    => 2,
					'user-agent' => 'StoreEngine/' . STOREENGINE_VERSION,
				] );

				if ( ! is_wp_error( $response ) && $response['body'] ) {
					switch ( $service_name ) {
						case 'ipinfo.io':
						case 'ipapi.co':
							$data         = json_decode( $response['body'] );
							$country_code = $data->country ?? '';
							break;
						case 'ip-api.com':
						case 'freeipapi.com':
							$data         = json_decode( $response['body'] );
							$country_code = $data->countryCode ?? '';
							break;
						case 'ipwhois.app':
							$data         = json_decode( $response['body'] );
							$country_code = $data->country_code ?? '';
							break;
						default:
							$country_code = apply_filters( 'storeengine/geolocation/geoip_response_' . $service_name, '', $response['body'] );
							break;
					}

					$country_code = sanitize_text_field( strtoupper( $country_code ) );

					if ( $country_code ) {
						break;
					}
				}
			}

			set_transient( 'se_geoip_' . md5( $ip_address ), $country_code, DAY_IN_SECONDS );
		}

		return $country_code;
	}
}

// End of file geolocation.php.
