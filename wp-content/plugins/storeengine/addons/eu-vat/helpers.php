<?php
/**
 * EU VAT Helpers
 *
 * Pure functions only — no class state, no side effects.
 *
 * @package StoreEngine\Addons\EuVat
 */

namespace StoreEngine\Addons\EuVat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Country codes covered by VIES (EU member states + Northern Ireland).
 * Greece reports as "EL" via VIES, not "GR".
 */
function eu_country_codes(): array {
	return [
		'AT', 'BE', 'BG', 'CY', 'CZ', 'DE', 'DK', 'EE', 'EL', 'ES',
		'FI', 'FR', 'HR', 'HU', 'IE', 'IT', 'LT', 'LU', 'LV', 'MT',
		'NL', 'PL', 'PT', 'RO', 'SE', 'SI', 'SK', 'XI',
	];
}

/**
 * Map an ISO 3166-1 alpha-2 country code to its VAT country code.
 * Greece (GR) → EL; Northern Ireland uses XI for VAT purposes.
 */
function vat_country_code( string $country ): string {
	$country = strtoupper( $country );
	if ( 'GR' === $country ) {
		return 'EL';
	}
	return $country;
}

/**
 * Reverse of vat_country_code() — turn a VAT-country code back into ISO.
 */
function iso_country_code( string $vat_country ): string {
	$vat_country = strtoupper( $vat_country );
	if ( 'EL' === $vat_country ) {
		return 'GR';
	}
	if ( 'XI' === $vat_country ) {
		return 'GB';
	}
	return $vat_country;
}

/**
 * Strip everything except A-Z and 0-9 from a raw VAT number, uppercase.
 */
function normalize_vat_input( string $input ): string {
	return strtoupper( preg_replace( '/[^A-Z0-9]/i', '', $input ) );
}

/**
 * True when a normalized VAT string starts with a VIES-supported country code.
 */
function has_vat_country_prefix( string $normalized ): bool {
	if ( strlen( $normalized ) < 3 ) {
		return false;
	}
	$prefix = substr( $normalized, 0, 2 );
	return in_array( $prefix, array_merge( eu_country_codes(), [ 'GB' ] ), true );
}
