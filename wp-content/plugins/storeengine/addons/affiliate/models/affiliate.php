<?php

namespace StoreEngine\Addons\Affiliate\models;

use StoreEngine\Addons\Affiliate\Settings\Affiliate as AffiliateSettings;
use WP_Error;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Affiliate {

	const STATUS_PENDING   = 'pending';
	const STATUS_ACTIVE    = 'active';
	const STATUS_INACTIVE  = 'inactive';
	const STATUS_REJECTED  = 'rejected';
	const STATUS_SUSPENDED = 'suspended';

	/**
	 * Every valid affiliate status. Order matches the DB ENUM
	 * ({@see \StoreEngine\Addons\Affiliate\Database\CreateAffiliate::STATUS_ENUM}).
	 *
	 * @return string[]
	 */
	public static function statuses(): array {
		return [
			self::STATUS_ACTIVE,
			self::STATUS_INACTIVE,
			self::STATUS_PENDING,
			self::STATUS_REJECTED,
			self::STATUS_SUSPENDED,
		];
	}

	/**
	 * Whether the given string is a known affiliate status.
	 */
	public static function is_valid_status( string $status ): bool {
		return in_array( $status, self::statuses(), true );
	}

	/**
	 * Human-readable, translated label for a status value.
	 */
	public static function status_label( string $status ): string {
		$labels = [
			self::STATUS_ACTIVE    => __( 'Active', 'storeengine' ),
			self::STATUS_INACTIVE  => __( 'Inactive', 'storeengine' ),
			self::STATUS_PENDING   => __( 'Pending', 'storeengine' ),
			self::STATUS_REJECTED  => __( 'Rejected', 'storeengine' ),
			self::STATUS_SUSPENDED => __( 'Suspended', 'storeengine' ),
		];

		return $labels[ $status ] ?? ucfirst( $status );
	}

	public static function get_affiliates( $args = [] ) {
		global $wpdb;

		$defaults = [
			'affiliate_id' => null,
			'user_id'      => null,
			'count'        => false,
			'offset'       => 0,
			'per_page'     => 10,
			'status'       => 'any',
			'search'       => '',
		];

		$args = wp_parse_args( $args, $defaults );

		if ( $args['count'] ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_affiliates;" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		$query = "SELECT
        a.affiliate_id,
        a.user_id,
        u.display_name,
        u.user_email,
        MD5(u.user_email) AS email_hash,
        ar.total_clicks,
        a.commission_type,
        a.commission_rate,
        ar.total_commissions,
        ar.current_balance,
        a.status,
        a.created_at,
		(
			SELECT ref.referral_post_id
			FROM {$wpdb->prefix}storeengine_affiliate_referrals ref
			WHERE ref.affiliate_id = a.affiliate_id
			ORDER BY ref.referral_id
			LIMIT 1
		) AS referral_post_id,
		(
			SELECT ref.referral_code
			FROM {$wpdb->prefix}storeengine_affiliate_referrals ref
			WHERE ref.affiliate_id = a.affiliate_id
			ORDER BY ref.referral_id
			LIMIT 1
		) AS referral_code
    FROM
        {$wpdb->prefix}storeengine_affiliates a
    LEFT JOIN
        {$wpdb->prefix}storeengine_affiliate_report ar ON a.affiliate_id = ar.affiliate_id
    LEFT JOIN
        {$wpdb->prefix}users u ON a.user_id = u.ID";

		$where  = [];
		$params = [];

		if ( ! empty( $args['affiliate_id'] ) ) {
			$where[]  = 'a.affiliate_id = %d';
			$params[] = $args['affiliate_id'];
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where[]  = 'a.user_id = %d';
			$params[] = $args['user_id'];
		}

		if ( 'any' !== $args['status'] ) {
			$where[]  = 'a.status = %s';
			$params[] = $args['status'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where[]  = 'u.display_name LIKE %s';
			$params[] = '%' . $wpdb->esc_like( $args['search'] ) . '%';
		}

		if ( ! empty( $where ) ) {
			$query .= ' WHERE ' . implode( ' AND ', $where );
		}

		if ( empty( $args['affiliate_id'] ) && empty( $args['user_id'] ) ) {
			$query .= ' ORDER BY a.created_at DESC LIMIT %d, %d';
			array_push( $params, $args['offset'], $args['per_page'] );
		}

		$prepared_query = $wpdb->prepare( $query, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results        = $wpdb->get_results( $prepared_query, ARRAY_A ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared

		if ( ! empty( $args['affiliate_id'] ) || ! empty( $args['user_id'] ) ) {
			return ! empty( $results ) ? self::attach_profile_meta( $results[0] ) : [];
		}

		return array_map( [ self::class, 'attach_profile_meta' ], (array) $results );
	}

	/**
	 * Attach the affiliate's signup profile (stored as user meta) to a result
	 * row so the admin UI / API can show who submitted the request and what
	 * they entered — website, promotional methods, payout email, terms date.
	 *
	 * @param array $row A single affiliate row (must contain user_id).
	 *
	 * @return array
	 */
	protected static function attach_profile_meta( array $row ): array {
		$user_id = (int) ( $row['user_id'] ?? 0 );

		$row['website_url']         = $user_id ? (string) get_user_meta( $user_id, 'storeengine_affiliate_website_url', true ) : '';
		$row['promotional_methods'] = $user_id ? (string) get_user_meta( $user_id, 'storeengine_affiliate_promotional_methods', true ) : '';
		$row['payout_email']        = $user_id ? (string) get_user_meta( $user_id, 'storeengine_affiliate_payout_email', true ) : '';
		$row['agreed_terms']        = $user_id ? (string) get_user_meta( $user_id, 'storeengine_affiliate_agreed_terms', true ) : '';

		return $row;
	}

	public static function update( int $id, array $args ) {
		global $wpdb;

		$updated = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"{$wpdb->prefix}storeengine_affiliates",
			$args,
			[ 'affiliate_id' => $id ],
			[ '%s' ]
		);

		if ( ! $updated && $wpdb->last_error ) {
			return new WP_Error( 'failed-to-update', $wpdb->last_error );
		}

		return true;
	}

	public static function add_an_affiliate_report( int $affiliate_id ) {
		global $wpdb;

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prefix . 'storeengine_affiliate_report',
			[
				'affiliate_id'      => $affiliate_id,
				'referral_id'       => 1,
				'total_clicks'      => 0,
				'total_sales'       => 0,
				'total_commissions' => 0,
				'current_balance'   => 0,
			],
			[
				'%d',
				'%d',
				'%d',
				'%f',
				'%f',
				'%f',
			]
		);

		if ( $wpdb->last_error ) {
			return new WP_Error( 'failed-to-insert-report', $wpdb->last_error );
		}

		return $wpdb->insert_id;
	}

	public static function save( $args = [] ) {
		global $wpdb;

		$args          = wp_parse_args( $args, [
			'user_id' => null,
			'status'  => 'pending',
		] );
		$user_id       = $args['user_id'] ?? null;
		$existing_user = $user_id > 0;

		if ( ! $user_id ) {
			$user_id = self::create_new_user( $args );

			if ( ! is_int( $user_id ) ) {
				return $user_id;
			}

			$existing_user = false;
		}

		$user = new WP_User( $user_id );

		if ( $existing_user && user_can( $user, 'storeengine_affiliate' ) ) {
			return new WP_Error( 'user-already-is-an-affiliate', __( 'User already is an affiliate.', 'storeengine' ) );
		}

		$user->add_role( 'storeengine_affiliate' );
		$user->add_cap( 'manage_storeengine_affiliate' );

		$affiliate_settings = AffiliateSettings::get_settings_saved_data();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"{$wpdb->prefix}storeengine_affiliates",
			[
				'user_id'         => $user_id,
				'commission_type' => $affiliate_settings['commission_type'],
				'commission_rate' => $affiliate_settings['commission_rate'],
				'status'          => $args['status'],
			],
			[
				'%d',
				'%s',
				'%d',
				'%s',
			]
		);

		if ( ! $wpdb->insert_id ) {
			if ( $wpdb->last_error ) {
				// translators: %s. Exception (error) message.
				return new WP_Error( 'failed-to-insert', sprintf( __( 'Something went wrong. Error: %s', 'storeengine' ), $wpdb->last_error ) );
			} else {
				return new WP_Error( 'failed-to-insert', 'Something went wrong. Failed to create db record, please try again later.' );
			}
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$affiliate_id = $wpdb->get_var( "SELECT affiliate_id FROM {$wpdb->prefix}storeengine_affiliates ORDER BY affiliate_id DESC LIMIT 1" );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $affiliate_id ) {
			return new WP_Error( 'failed-to-retrieve-id', 'Could not retrieve the affiliate ID.' );
		}

		self::add_an_affiliate_report( $affiliate_id );

		do_action( 'storeengine/addons/affiliate/after_registration', $affiliate_id );

		return self::get_affiliates( [ 'affiliate_id' => $affiliate_id ] );
	}

	/**
	 * Ensure the given user has an affiliate record, creating one if missing.
	 *
	 * Needed because a user can hold the `storeengine_affiliate` role without a
	 * matching affiliates row (e.g. admins auto-granted the role by
	 * Role::add_affiliate_role). `save()` refuses to create a record for anyone
	 * who already has the role, so this inserts directly (bypassing that guard)
	 * and reuses any existing record.
	 *
	 * @param int    $user_id User to provision.
	 * @param string $status  Status for a newly-created record.
	 *
	 * @return array|WP_Error The affiliate row, or WP_Error on failure.
	 */
	public static function ensure_affiliate_for_user( int $user_id, string $status = 'pending' ) {
		if ( $user_id <= 0 ) {
			return new WP_Error( 'invalid-user', __( 'Invalid user.', 'storeengine' ) );
		}

		$existing = self::get_affiliates( [ 'user_id' => $user_id ] );
		if ( ! empty( $existing ) ) {
			return $existing;
		}

		global $wpdb;
		$affiliate_settings = AffiliateSettings::get_settings_saved_data();

		$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"{$wpdb->prefix}storeengine_affiliates",
			[
				'user_id'         => $user_id,
				'commission_type' => $affiliate_settings['commission_type'] ?? 'percentage',
				'commission_rate' => $affiliate_settings['commission_rate'] ?? 10,
				'status'          => $status,
			],
			[ '%d', '%s', '%d', '%s' ]
		);

		$affiliate_id = (int) $wpdb->insert_id;
		if ( ! $affiliate_id ) {
			return new WP_Error( 'failed-to-insert', $wpdb->last_error ?: __( 'Could not create affiliate record.', 'storeengine' ) );
		}

		self::add_an_affiliate_report( $affiliate_id );

		$user = new WP_User( $user_id );
		$user->add_role( 'storeengine_affiliate' );
		$user->add_cap( 'manage_storeengine_affiliate' );

		do_action( 'storeengine/addons/affiliate/after_registration', $affiliate_id );

		return self::get_affiliates( [ 'affiliate_id' => $affiliate_id ] );
	}

	public static function create_new_user( $args = [] ) {
		// Password is optional now — the affiliate registration form no longer
		// collects one, so generate a strong password and let the user set their
		// own via the emailed reset link.
		$password = ! empty( $args['password'] ) ? $args['password'] : wp_generate_password( 20 );

		$user_data = array(
			'first_name' => $args['first_name'] ?? '',
			'last_name'  => $args['last_name'] ?? '',
			'user_login' => self::create_username( $args['email'] ),
			'user_pass'  => $password,
			'user_email' => $args['email'],
			'role'       => 'subscriber',
		);

		$user_exist  = username_exists( $user_data['user_login'] );
		$email_exist = email_exists( $user_data['user_email'] );

		if ( $user_exist ) {
			return $user_exist;
		} elseif ( $email_exist ) {
			return $email_exist;
		}

		$user_id = wp_insert_user( $user_data );

		if ( is_wp_error( $user_id ) ) {
			return 'Error creating user: ' . $user_id->get_error_message();
		}

		return $user_id;
	}

	public static function create_username( $email ): string {
		return sanitize_user( strstr( $email, '@', true ) );
	}
}
