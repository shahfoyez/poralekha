<?php
/**
 * Stores Stripe Tax calculation ids + breakdowns keyed by cart hash.
 *
 * Backed by transients (WP object cache when available). Calculations are valid
 * for ~48h on Stripe's side; we expire much sooner to ensure freshness.
 */

namespace StoreEngine\Addons\Stripe\Tax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CalculationCache {

	private const PREFIX = 'storeengine_stripe_tax_';
	private const TTL    = 15 * MINUTE_IN_SECONDS;

	public static function get( string $cart_hash ): ?array {
		if ( ! $cart_hash ) {
			return null;
		}
		$value = get_transient( self::PREFIX . $cart_hash );

		return is_array( $value ) ? $value : null;
	}

	public static function put( string $cart_hash, array $payload ): void {
		if ( ! $cart_hash ) {
			return;
		}
		set_transient( self::PREFIX . $cart_hash, $payload, self::TTL );
	}

	public static function forget( string $cart_hash ): void {
		if ( ! $cart_hash ) {
			return;
		}
		delete_transient( self::PREFIX . $cart_hash );
	}
}
