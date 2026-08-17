<?php
/**
 * VAT Number value object.
 *
 * Parses a raw VAT input into a country code and a number, handling the case
 * where the user enters the number with or without the country prefix.
 *
 * @package StoreEngine\Addons\EuVat
 */

namespace StoreEngine\Addons\EuVat\Classes;

use function StoreEngine\Addons\EuVat\eu_country_codes;
use function StoreEngine\Addons\EuVat\has_vat_country_prefix;
use function StoreEngine\Addons\EuVat\normalize_vat_input;
use function StoreEngine\Addons\EuVat\vat_country_code;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class VatNumber {

	public string $country;
	public string $number;
	public string $raw;

	private function __construct( string $country, string $number, string $raw ) {
		$this->country = $country;
		$this->number  = $number;
		$this->raw     = $raw;
	}

	/**
	 * Parse a raw input. The billing country is used as a fallback when the
	 * user omits the country prefix (e.g. enters "123456789" with billing DE
	 * instead of "DE123456789").
	 *
	 * Returns null when the input cannot be turned into a usable VAT pair.
	 */
	public static function parse( string $input, string $billing_country = '' ): ?self {
		$normalized = normalize_vat_input( $input );

		if ( strlen( $normalized ) < 4 ) {
			return null;
		}

		if ( has_vat_country_prefix( $normalized ) ) {
			$country = substr( $normalized, 0, 2 );
			$number  = substr( $normalized, 2 );
		} else {
			$country = vat_country_code( $billing_country );
			$number  = $normalized;
		}

		$supported = array_merge( eu_country_codes(), [ 'GB' ] );
		if ( ! in_array( $country, $supported, true ) ) {
			return null;
		}

		if ( '' === $number ) {
			return null;
		}

		return new self( $country, $number, $normalized );
	}

	public function full(): string {
		return $this->country . $this->number;
	}
}
