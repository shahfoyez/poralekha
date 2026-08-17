<?php

namespace StoreEngine\Addons\Affiliate\Models;

use StoreEngine\Classes\AbstractModel;
use StoreEngine\Addons\Affiliate\Helper as HelperAddon;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Helper;
use WP_Error;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Referral {

	public static function get_referrals( $args = [] ) {
		global $wpdb;

		$defaults = [
			'referral_id' => null,
			'count'       => false,
			'offset'      => 0,
			'per_page'    => 10,
			'search'      => '',
		];

		$args = wp_parse_args( $args, $defaults );

		if ( $args['count'] ) {
			return $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_affiliate_referrals;" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$query = "SELECT
				r.referral_id,
				u.display_name, u.user_email,
				r.referral_code,
				r.referral_post_id,
				r.created_at,
				r.click_counts
			FROM
				{$wpdb->prefix}storeengine_affiliate_referrals r
			LEFT JOIN
				{$wpdb->prefix}storeengine_affiliates a ON r.affiliate_id = a.affiliate_id
			LEFT JOIN
				{$wpdb->prefix}users u ON a.user_id = u.ID";

		if ( $args['referral_id'] ) {
			$query .= $wpdb->prepare( ' WHERE referral_id = %d', $args['referral_id'] );

			$referral = $wpdb->get_row( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

			$referral = $referral ? self::modify_referral_url( [ $referral ] ) : [];

			return $referral ? reset( $referral ) : null;
		}

		$query .= $wpdb->prepare( ' WHERE u.display_name LIKE %s', '%' . $wpdb->esc_like( $args['search'] ) . '%' );

		$referrals = $wpdb->get_results( $wpdb->prepare( "{$query} ORDER BY r.created_at DESC LIMIT %d, %d", $args['offset'], $args['per_page'] ), ARRAY_A ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- query prepared above.

		return self::modify_referral_url( $referrals );
	}

	public static function modify_referral_url( $query_result = [] ) {
		if ( empty( $query_result ) ) {
			return [];
		}

		foreach ( $query_result as &$result ) {
			if ( isset( $result['referral_post_id'] ) ) {
				$result['referral_url'] = self::create_link( $result['referral_code'], $result['referral_post_id'] );
				unset( $result['referral_post_id'] );
			}
		}

		return $query_result;
	}

	public static function create_link( $referral_code, $referral_post_id ) {
		// Base the referral link on the full website (home) or the store page, per setting.
		if ( 'home' === HelperAddon::get_affiliate_setting( 'referral_link_target' ) ) {
			$base_url = home_url( '/' );
		} else {
			$base_url = get_permalink( $referral_post_id );
		}

		$has_query                  = false !== strpos( $base_url, '?' );
		$url_parameter_prefix_style = $has_query ? '&' : '?';

		$referral_query_string = sprintf( '%s%s=%s', $url_parameter_prefix_style, HelperAddon::get_referral_param(), $referral_code );
		return esc_url( $base_url . $referral_query_string );
	}

	public static function save( $args = [] ) {
		global $wpdb;

		try {
			$inserted = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				"{$wpdb->prefix}storeengine_affiliate_referrals",
				[
					'affiliate_id'     => $args['affiliate_id'],
					'referral_code'    => HelperAddon::generate_random_code( 'referrals' ),
					'referral_post_id' => $args['referral_post_id'],
					'click_counts'     => 0,
				],
				[
					'%d',
					'%s',
					'%d',
					'%d',
				]
			);

			if ( ! $inserted ) {
				return new WP_Error( 'failed-to-insert', $wpdb->last_error );
			}

			return self::get_referrals([ 'referral_id' => $wpdb->insert_id ]);
		} catch ( StoreEngineException $e ) {
			return $e->toWpError();
		}
	}

	public static function update( int $id, array $args ) {
		global $wpdb;

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"{$wpdb->prefix}storeengine_affiliate_referrals",
			$args,
			[ 'referral_id' => $id ],
			[ '%d' ]
		);

		if ( ! $updated ) {
			return new WP_Error( 'failed-to-update', $wpdb->last_error );
		}

		return $updated;
	}

	/**
	 * Whether a referral code is free to use — either unused, or already owned
	 * by the given affiliate (so re-saving an unchanged slug is allowed).
	 */
	public static function is_code_available( string $code, int $for_affiliate_id = 0 ): bool {
		global $wpdb;

		$owner = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT affiliate_id FROM {$wpdb->prefix}storeengine_affiliate_referrals WHERE referral_code = %s LIMIT 1",
			$code
		) );

		return null === $owner || (int) $owner === $for_affiliate_id;
	}

	/**
	 * Set a custom (vanity) referral code on an affiliate's primary referral
	 * row. Validates length + uniqueness. Returns the normalised code on
	 * success or a WP_Error.
	 *
	 * Note: does not reuse self::update() because that method formats every
	 * value as %d, which would coerce a string code to 0.
	 *
	 * @param int    $affiliate_id Affiliate to update.
	 * @param string $code         Desired slug.
	 *
	 * @return string|WP_Error
	 */
	public static function update_code( int $affiliate_id, string $code ) {
		if ( $affiliate_id <= 0 ) {
			return new WP_Error( 'invalid-affiliate', __( 'Invalid affiliate.', 'storeengine' ) );
		}

		// Normalise to a URL-safe slug (lowercase letters, numbers, dashes).
		$code = sanitize_title( $code );

		if ( strlen( $code ) < 3 ) {
			return new WP_Error( 'invalid-slug', __( 'Referral slug must be at least 3 characters (letters, numbers and dashes).', 'storeengine' ) );
		}

		if ( ! self::is_code_available( $code, $affiliate_id ) ) {
			return new WP_Error( 'slug-taken', __( 'That referral slug is already in use. Please choose another.', 'storeengine' ) );
		}

		global $wpdb;

		$referral_id = $wpdb->get_var( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT referral_id FROM {$wpdb->prefix}storeengine_affiliate_referrals WHERE affiliate_id = %d ORDER BY referral_id LIMIT 1",
			$affiliate_id
		) );

		if ( ! $referral_id ) {
			return new WP_Error( 'no-referral', __( 'No referral link found for this affiliate.', 'storeengine' ) );
		}

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"{$wpdb->prefix}storeengine_affiliate_referrals",
			[ 'referral_code' => $code ],
			[ 'referral_id' => (int) $referral_id ],
			[ '%s' ],
			[ '%d' ]
		);

		if ( false === $updated ) {
			return new WP_Error( 'failed-to-update', $wpdb->last_error ?: __( 'Could not update referral slug.', 'storeengine' ) );
		}

		return $code;
	}
}
