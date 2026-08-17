<?php

namespace StoreEngine\Classes;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * SKU + Barcode bulk generation.
 *
 * Used by the React variant-matrix UI and by CSV import to produce stable,
 * human-readable, collision-free codes from product slug + variation
 * attribute slugs.
 */
class SkuGenerator {

	/**
	 * Build a deterministic SKU from a base + attribute slugs.
	 *
	 *   base="tshirt-noir", parts=["black","m"] → "TSHIRT-NOIR-BLACK-M"
	 */
	public static function build( string $base, array $parts ): string {
		$parts  = array_filter( array_map( [ self::class, 'normalize' ], $parts ), 'strlen' );
		$base   = self::normalize( $base );
		$pieces = array_filter( array_merge( [ $base ], $parts ), 'strlen' );

		return strtoupper( implode( '-', $pieces ) );
	}

	/**
	 * Build a SKU and append a numeric suffix until it is unique within
	 * storeengine_product_variations and the post-meta SKU column for simple
	 * products.
	 */
	public static function build_unique( string $base, array $parts ): string {
		$candidate = self::build( $base, $parts );
		$suffix    = 1;
		$final     = $candidate;

		while ( self::sku_exists( $final ) ) {
			$suffix++;
			$final = $candidate . '-' . $suffix;
		}

		return $final;
	}

	public static function sku_exists( string $sku ): bool {
		if ( '' === $sku ) {
			return false;
		}

		global $wpdb;

		$variations_table = $wpdb->prefix . 'storeengine_product_variations';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- %i identifier + %s value bound via prepare() on a custom StoreEngine table; uniqueness probe, not cacheable.
		$found = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(1) FROM %i WHERE sku = %s',
			$variations_table,
			$sku
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( $found > 0 ) {
			return true;
		}

		// Simple-product SKU stored in post meta.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found_meta = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(1) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
			'_storeengine_sku',
			$sku
		) );
		// phpcs:enable

		return $found_meta > 0;
	}

	/**
	 * Generate a numeric EAN-13 candidate. Not a registered prefix, intended
	 * for in-house labelling only — replace with vendor-supplied codes for
	 * retail distribution.
	 */
	public static function make_internal_barcode( int $variation_id ): string {
		$prefix = '200'; // ITF-style "in-store" prefix.
		$body   = str_pad( (string) $variation_id, 9, '0', STR_PAD_LEFT );
		$base   = $prefix . substr( $body, -9 );

		return $base . self::ean13_check_digit( $base );
	}

	/**
	 * Monotonic per-key counter stored in an option. Used for the {number}
	 * token and for internal barcode bodies so generated codes don't collide.
	 */
	public static function next_sequence( string $key ): int {
		$n = (int) get_option( $key, 0 ) + 1;
		update_option( $key, $n, false );
		return $n;
	}

	/**
	 * Generate a SKU from the admin "SKU pattern" setting, ensuring uniqueness.
	 *
	 * Pattern tokens: {prefix is literal text}, {number} (zero-padded running
	 * sequence), {name} (product name slug), {category} (primary category slug),
	 * {year}.
	 *
	 * @param array{name?:string, category?:string} $ctx
	 */
	public static function generate_sku( array $ctx = [] ): string {
		$pattern = (string) Helper::get_settings( 'sku_pattern', '{category}-{number}' );
		if ( '' === trim( $pattern ) ) {
			$pattern = '{number}';
		}
		$padding = (int) Helper::get_settings( 'sku_number_padding', 4 );

		$candidate = self::render_sku_pattern( $pattern, $ctx, $padding );
		if ( '' === $candidate ) {
			$candidate = 'SKU-' . str_pad( (string) self::next_sequence( 'storeengine_sku_sequence' ), max( 1, $padding ), '0', STR_PAD_LEFT );
		}

		$final  = $candidate;
		$suffix = 1;
		while ( self::sku_exists( $final ) ) {
			$suffix++;
			$final = $candidate . '-' . $suffix;
		}

		return $final;
	}

	protected static function render_sku_pattern( string $pattern, array $ctx, int $padding ): string {
		$repl = [
			'{name}'     => strtoupper( self::normalize( (string) ( $ctx['name'] ?? '' ) ) ),
			'{category}' => strtoupper( self::normalize( (string) ( $ctx['category'] ?? '' ) ) ),
			'{year}'     => gmdate( 'Y' ),
		];

		// {number} only consumes a sequence value when the token is present.
		if ( false !== strpos( $pattern, '{number}' ) ) {
			$repl['{number}'] = str_pad( (string) self::next_sequence( 'storeengine_sku_sequence' ), max( 1, $padding ), '0', STR_PAD_LEFT );
		}

		$out = strtr( $pattern, $repl );
		$out = preg_replace( '/-{2,}/', '-', $out );

		return strtoupper( trim( (string) $out, '-' ) );
	}

	/**
	 * Generate a unique internal EAN-13 barcode using the configured in-store
	 * prefix and a running sequence.
	 */
	public static function generate_barcode(): string {
		$prefix = preg_replace( '/\D/', '', (string) Helper::get_settings( 'barcode_ean_prefix', '20' ) );
		if ( '' === $prefix ) {
			$prefix = '20';
		}

		do {
			$seq  = self::next_sequence( 'storeengine_barcode_sequence' );
			$code = self::make_internal_barcode_with_prefix( $prefix, $seq );
		} while ( self::barcode_exists( $code ) );

		return $code;
	}

	/**
	 * Build an EAN-13 from an arbitrary in-store prefix + a number, padded to
	 * the 12-digit base then suffixed with the check digit.
	 */
	public static function make_internal_barcode_with_prefix( string $prefix, int $number ): string {
		$prefix   = substr( preg_replace( '/\D/', '', $prefix ), 0, 11 );
		$body_len = max( 1, 12 - strlen( $prefix ) );
		$body     = substr( str_pad( (string) $number, $body_len, '0', STR_PAD_LEFT ), -$body_len );
		$base     = substr( $prefix . $body, 0, 12 );
		$base     = str_pad( $base, 12, '0', STR_PAD_LEFT );

		return $base . self::ean13_check_digit( $base );
	}

	public static function barcode_exists( string $code ): bool {
		if ( '' === $code ) {
			return false;
		}

		global $wpdb;

		$variations_table = $wpdb->prefix . 'storeengine_product_variations';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- %i identifier + %s value bound via prepare() on a custom StoreEngine table; uniqueness probe, not cacheable.
		$found = (int) $wpdb->get_var( $wpdb->prepare(
			'SELECT COUNT(1) FROM %i WHERE barcode = %s',
			$variations_table,
			$code
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		if ( $found > 0 ) {
			return true;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$found_meta = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(1) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s",
			'_storeengine_barcode',
			$code
		) );
		// phpcs:enable

		return $found_meta > 0;
	}

	/**
	 * Primary-category slug for a product, for the {category} SKU token.
	 */
	public static function product_category_slug( int $product_id ): string {
		if ( $product_id <= 0 ) {
			return '';
		}
		$term_ids = Helper::get_product_term_ids( $product_id, Helper::PRODUCT_CATEGORY_TAXONOMY );
		$first    = $term_ids ? reset( $term_ids ) : 0;
		if ( ! $first ) {
			return '';
		}
		$term = get_term( (int) $first, Helper::PRODUCT_CATEGORY_TAXONOMY );
		return ( $term && ! is_wp_error( $term ) ) ? (string) $term->slug : '';
	}

	protected static function ean13_check_digit( string $base12 ): string {
		$sum = 0;
		for ( $i = 0; $i < 12; $i++ ) {
			$digit = (int) $base12[ $i ];
			$sum  += ( $i % 2 === 0 ) ? $digit : $digit * 3;
		}
		return (string) ( ( 10 - ( $sum % 10 ) ) % 10 );
	}

	protected static function normalize( string $part ): string {
		$part = remove_accents( $part );
		$part = preg_replace( '/[^A-Za-z0-9]+/', '-', $part );
		return trim( (string) $part, '-' );
	}
}
