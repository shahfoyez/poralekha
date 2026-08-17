<?php
/**
 * GeoLocation Helper Utility.
 *
 * @version 1.0.0
 * @since 1.5.6
 *
 * @package StoreEngine/Utils
 */

namespace StoreEngine\Utils;

use StoreEngine\Classes\Countries;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Geolocation {
	use \StoreEngine\Utils\traits\Geolocation;
	use \StoreEngine\Utils\traits\Maxmind;

	/**
	 * Get current user/remote-client IP Address.
	 *
	 * @return string
	 */
	public static function get_user_ip(): string {
		if ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) {
			return sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_REAL_IP'] ) );
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			// Proxy servers can send through this header like this: X-Forwarded-For: client1, proxy1, proxy2
			// Make sure we always only send through the first IP in the list which should always be the client IP.
			$value = trim( strtok( sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ), ',' ) );
			// $value = trim( current( preg_split( '/,/', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) ) );
			// Account for the '<IPv4 address>:<port>', '[<IPv6>]' and '[<IPv6>]:<port>' cases, removing the port.
			// The regular expression is oversimplified on purpose, later 'rest_is_ip_address' will do the actual IP address validation.
			/** @noinspection RegExpRedundantEscape */
			$value = preg_replace( '/([0-9]+\.[0-9]+\.[0-9]+\.[0-9]+)\:.*|\[([^]]+)\].*/', '$1$2', $value );

			return (string) rest_is_ip_address( $value );
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			// Make sure we always only send through the first IP in the list which should always be the client IP.
			$value = trim( strtok( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ), ',' ) );

			return (string) rest_is_ip_address( $value );
		}

		// Return empty string if no valid IP is found
		return '';
	}

	/**
	 * Uses geolocation to get the customer country and state only if they are valid values.
	 *
	 * @param array $fallback Fallback location.
	 *
	 * @return array
	 */
	public static function get_customer_geolocation( array $fallback = [ 'country' => '', 'state' => '', ] ): array {
		if ( Helper::is_bot() ) {
			return $fallback;
		}

		$geolocation = self::geolocate_ip( '', true, false );

		if ( empty( $geolocation['country'] ) ) {
			return $fallback;
		}

		// Ensure geolocation is valid.
		$allowed_countries = Countries::init()->get_allowed_countries();

		if ( ! isset( $allowed_countries[ $geolocation['country'] ] ) ) {
			return $fallback;
		}

		$allowed_states = Countries::init()->get_allowed_country_states();
		$country_states = $allowed_states[ $geolocation['country'] ] ?? [];

		if ( $country_states && ! isset( $country_states[ $geolocation['state'] ] ) ) {
			$geolocation['state'] = '';
		}

		return [
			'country' => $geolocation['country'],
			'state'   => $geolocation['state'],
		];
	}

	/**
	 * Get the customer's default location.
	 *
	 * Filtered, and set to base location or left blank. If cache-busting,
	 * this should only be used when 'location' is set in the querystring.
	 *
	 * @return array
	 */
	public static function get_customer_default_location(): array {
		$set_default_location_to = Helper::get_settings( 'default_customer_address', 'store-base' );

		// Unless the location should be blank, use the base location as the default.
		if ( '' !== $set_default_location_to ) {
			$default_location_string = Helper::get_settings( 'store_country' ) . ':' . Helper::get_settings( 'store_state' );
		}

		/**
		 * Filter the customer default location before geolocation.
		 *
		 * @param string $default_location_string The default location.
		 *
		 * @return string
		 */
		$default_location_string = apply_filters( 'storeengine/customer_default_location', $default_location_string ?? '' );
		$default_location        = Formatting::format_country_state_string( $default_location_string );

		// Ensure defaults are valid.
		$allowed_countries = Countries::init()->get_allowed_countries();

		if ( ! in_array( $default_location['country'], array_keys( $allowed_countries ), true ) ) {
			$default_location = [
				'country' => '',
				'state'   => '',
			];
		}

		// Geolocation takes priority if geolocation is possible.
		if ( in_array( $set_default_location_to, [ 'ip-geolocation', 'geolocation' ], true ) ) {
			$default_location = self::get_customer_geolocation( $default_location );
		}

		if ( empty( $default_location['country'] ) ) {
			$default_location = [
				'country' => Helper::get_settings( 'checkout_default_country' ),
				'state'   => '',
			];
		}

		/**
		 * Filter the customer default location after geolocation.
		 *
		 * @param array $customer_location The customer location with keys 'country' and 'state'.
		 *
		 * @return array
		 */
		return apply_filters( 'storeengine/customer_default_location_array', $default_location );
	}
}

// End of file geolocation.php.
