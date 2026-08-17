<?php

namespace StoreEngine\Addons\MultiVendor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Global multi-vendor settings, persisted as a single JSON-encoded option.
 */
class Settings {

	const OPTION = 'storeengine_multi_vendor_settings';

	public static function defaults(): array {
		return apply_filters( 'storeengine/multi_vendor/settings/defaults', [
			'commission_rate'      => 10.0,
			'commission_type'      => 'percent', // percent | flat
			'auto_create_owner'    => true,
			'owner_visible'        => false,
			'min_withdraw_amount'  => 50.0,
			'enabled_payment_methods' => [ 'paypal', 'bank' ],
			'allow_vendor_signup'  => true,
			// When ON, an order auto-advances processing → completed once every
			// line item (across all vendors) reaches "delivered". Off by default
			// so existing stores keep their manual completion flow.
			'auto_complete_on_all_delivered' => false,
			'badges'               => [
				[ 'slug' => 'verified', 'label' => 'Verified', 'color' => '#2563eb' ],
				[ 'slug' => 'top-rated', 'label' => 'Top Rated', 'color' => '#16a34a' ],
				[ 'slug' => 'new', 'label' => 'New', 'color' => '#f59e0b' ],
			],
		] );
	}

	public static function all(): array {
		$saved = get_option( self::OPTION, null );
		if ( null === $saved ) {
			return self::defaults();
		}
		if ( is_string( $saved ) ) {
			$saved = json_decode( $saved, true );
		}
		if ( ! is_array( $saved ) ) {
			$saved = [];
		}
		return wp_parse_args( $saved, self::defaults() );
	}

	public static function get( string $key, $fallback = null ) {
		$all = self::all();
		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	public static function save( array $values ): bool {
		$existing = self::all();
		$merged   = array_merge( $existing, $values );
		return (bool) update_option( self::OPTION, wp_json_encode( $merged ) );
	}

	public static function badge_label( string $slug ): string {
		foreach ( (array) self::get( 'badges', [] ) as $badge ) {
			if ( ( $badge['slug'] ?? '' ) === $slug ) {
				return (string) ( $badge['label'] ?? $slug );
			}
		}
		return $slug;
	}

	public static function badge_color( string $slug ): string {
		foreach ( (array) self::get( 'badges', [] ) as $badge ) {
			if ( ( $badge['slug'] ?? '' ) === $slug ) {
				return (string) ( $badge['color'] ?? '#64748b' );
			}
		}
		return '#64748b';
	}
}
