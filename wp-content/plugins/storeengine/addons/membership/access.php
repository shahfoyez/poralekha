<?php
/**
 * Central membership access API.
 *
 * Single source of truth for granting / revoking / querying membership access,
 * reused by the Members admin page, manual assignment (Customer editor, WP user
 * profile), nav-menu restriction, block restriction and the REST guard. Writes
 * the exact same user meta the purchase path does
 * (see StoreEngine\Integrations\MembershipAddon::update_user_meta()), so a
 * manual grant is indistinguishable from a purchased one to the frontend gate.
 *
 * @package StoreEngine\Addons\Membership
 */

namespace StoreEngine\Addons\Membership;

use StoreEngine\Utils\Formatting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Access {

	const PURCHASED_META = '_storeengine_purchased_membership_ids';
	const RECORDS_META   = '_storeengine_memberships';
	const DATA_META      = '_storeengine_user_membership_data';

	/**
	 * Grant a user access to an access group.
	 *
	 * @param int    $user_id  WP user id.
	 * @param int    $group_id storeengine_groups post id.
	 * @param string $source   How the grant was made (manual|import|order...). Stored for auditing.
	 *
	 * @return bool True when the grant was applied, false when the user already had it.
	 */
	public static function grant( int $user_id, int $group_id, string $source = 'manual' ): bool {
		if ( ! $user_id || ! $group_id ) {
			return false;
		}

		$purchased = Formatting::parse_ids( get_user_meta( $user_id, self::PURCHASED_META, true ) );
		if ( in_array( $group_id, $purchased, true ) ) {
			return false;
		}

		$purchased[] = $group_id;
		update_user_meta( $user_id, self::PURCHASED_META, Formatting::parse_ids( $purchased ) );

		// Bookkeeping record (mirrors the purchase path; price_id 0 marks a manual grant).
		$records = get_user_meta( $user_id, self::RECORDS_META, true );
		$records = is_array( $records ) ? $records : [];
		$records[] = [
			'customer_id'  => $user_id,
			'price_id'     => 0,
			'order_status' => 'manual',
			'source'       => $source,
		];
		update_user_meta( $user_id, self::RECORDS_META, $records );

		// Content-type + expiration snapshot used by HelperAddon::is_plan_expired().
		$data = get_user_meta( $user_id, self::DATA_META, true );
		$data = is_array( $data ) ? $data : [];
		$data[ $group_id ] = [
			'content_protect_types' => get_post_meta( $group_id, '_storeengine_membership_content_protect_types', true ),
			'expiration_date'       => get_post_meta( $group_id, '_storeengine_membership_expiration', true ),
		];
		update_user_meta( $user_id, self::DATA_META, $data );

		wp_cache_flush_group( 'se_membership_plans' );

		/**
		 * Fires after a user is granted an access group (any source).
		 *
		 * @param int    $user_id
		 * @param int    $group_id
		 * @param string $source
		 */
		do_action( 'storeengine/membership/user_added_to_group', $user_id, $group_id, $source );

		return true;
	}

	/**
	 * Revoke a user's access to an access group.
	 *
	 * @param int $user_id  WP user id.
	 * @param int $group_id storeengine_groups post id.
	 *
	 * @return bool True when access was removed, false when the user did not have it.
	 */
	public static function revoke( int $user_id, int $group_id ): bool {
		if ( ! $user_id || ! $group_id ) {
			return false;
		}

		$purchased = Formatting::parse_ids( get_user_meta( $user_id, self::PURCHASED_META, true ) );
		$index     = array_search( $group_id, $purchased, true );
		if ( false === $index ) {
			return false;
		}

		unset( $purchased[ $index ] );
		update_user_meta( $user_id, self::PURCHASED_META, Formatting::parse_ids( $purchased ) );

		$data = get_user_meta( $user_id, self::DATA_META, true );
		if ( is_array( $data ) && isset( $data[ $group_id ] ) ) {
			unset( $data[ $group_id ] );
			update_user_meta( $user_id, self::DATA_META, $data );
		}

		wp_cache_flush_group( 'se_membership_plans' );

		/**
		 * Fires after a user is removed from an access group (any source).
		 *
		 * @param int $user_id
		 * @param int $group_id
		 */
		do_action( 'storeengine/membership/user_removed_from_group', $user_id, $group_id );

		return true;
	}

	/**
	 * Whether the user currently has access to a given group.
	 *
	 * Mirrors the frontend gate in TemplateRedirect::check_rules_mechanism():
	 * administrators bypass, the group id must be in the purchased list, and the
	 * plan must not be expired. Reused by nav-menu and block restriction so all
	 * three enforcement points agree.
	 */
	public static function user_has_group_access( int $user_id, int $group_id ): bool {
		if ( $user_id && user_can( $user_id, 'administrator' ) ) {
			return true;
		}

		$purchased = self::get_user_groups( $user_id );
		if ( ! in_array( $group_id, $purchased, true ) ) {
			return false;
		}

		return ! HelperAddon::is_plan_expired( $user_id );
	}

	/**
	 * Whether the user can access ANY of the supplied groups.
	 *
	 * @param int   $user_id
	 * @param int[] $group_ids
	 */
	public static function user_has_any_access( int $user_id, array $group_ids ): bool {
		foreach ( $group_ids as $group_id ) {
			if ( self::user_has_group_access( $user_id, (int) $group_id ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Group ids a user currently holds.
	 *
	 * @return int[]
	 */
	public static function get_user_groups( int $user_id ): array {
		return Formatting::parse_ids( get_user_meta( $user_id, self::PURCHASED_META, true ) );
	}

	/**
	 * List members of a group (paginated).
	 *
	 * The purchased ids are stored as a serialized int array, so a coarse LIKE on
	 * the `i:<id>;` token pre-filters, then each row is verified in PHP (guards
	 * `i:5;` matching `i:50;` or an array-index token).
	 *
	 * @param int   $group_id
	 * @param array $args { page:int, per_page:int, search:string }
	 *
	 * @return array{items: array<int,array>, total: int}
	 */
	public static function get_group_members( int $group_id, array $args = [] ): array {
		$args = wp_parse_args( $args, [
			'page'     => 1,
			'per_page' => 20,
			'search'   => '',
		] );

		$matched = self::get_group_member_ids( $group_id );
		$total   = count( $matched );

		if ( '' !== $args['search'] && ! empty( $matched ) ) {
			$found = get_users( [
				'include' => $matched,
				'search'  => '*' . $args['search'] . '*',
				'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
				'fields'  => 'ID',
			] );
			$matched = array_map( 'intval', $found );
			$total   = count( $matched );
		}

		$offset = max( 0, ( (int) $args['page'] - 1 ) * (int) $args['per_page'] );
		$page   = array_slice( $matched, $offset, (int) $args['per_page'] );

		$items = [];
		foreach ( $page as $user_id ) {
			$items[] = self::format_member( (int) $user_id, $group_id );
		}

		return [
			'items' => $items,
			'total' => $total,
		];
	}

	/**
	 * All user ids that hold a group (verified, unpaginated).
	 *
	 * @return int[]
	 */
	public static function get_group_member_ids( int $group_id ): array {
		if ( ! $group_id ) {
			return [];
		}

		$query = new \WP_User_Query( [
			'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'     => self::PURCHASED_META,
					'value'   => sprintf( 'i:%d;', $group_id ),
					'compare' => 'LIKE',
				],
			],
			'fields'     => 'ID',
			'number'     => -1,
		] );

		$matched = [];
		foreach ( $query->get_results() as $user_id ) {
			if ( self::user_holds_group( (int) $user_id, $group_id ) ) {
				$matched[] = (int) $user_id;
			}
		}

		return $matched;
	}

	/**
	 * Raw "is this group in the user's purchased list" check (no admin bypass, no
	 * expiration) — used by member enumeration where we want the stored fact.
	 */
	public static function user_holds_group( int $user_id, int $group_id ): bool {
		return in_array( $group_id, self::get_user_groups( $user_id ), true );
	}

	/**
	 * Shape a member row for the admin table / REST.
	 */
	protected static function format_member( int $user_id, int $group_id ): array {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return [
				'id'      => $user_id,
				'name'    => sprintf( /* translators: %d: user id */ __( 'User #%d', 'storeengine' ), $user_id ),
				'email'   => '',
				'avatar'  => '',
				'expired' => false,
			];
		}

		$data       = get_user_meta( $user_id, self::DATA_META, true );
		$expiration = is_array( $data ) && isset( $data[ $group_id ]['expiration_date'] )
			? $data[ $group_id ]['expiration_date']
			: [];

		return [
			'id'         => $user_id,
			'name'       => $user->display_name ? $user->display_name : $user->user_login,
			'email'      => $user->user_email,
			'avatar'     => get_avatar_url( $user_id, [ 'size' => 48 ] ),
			'roles'      => array_values( $user->roles ),
			'expiration' => $expiration,
			'expired'    => HelperAddon::is_plan_expired( $user_id ),
		];
	}

	/**
	 * Whether a given post is restricted for a given user.
	 *
	 * Mirrors TemplateRedirect::check_rules_mechanism() so REST, feed and listing
	 * guards make the exact same decision the themed page does: match the post's
	 * access groups, honour the `before_content_protect` filter (Academy courses
	 * opt out), and treat it as accessible once the user holds a matching group.
	 */
	public static function is_post_restricted( int $post_id, int $user_id ): bool {
		if ( ! $post_id ) {
			return false;
		}

		$plans = HelperAddon::get_all_plans( $post_id );
		if ( empty( $plans ) ) {
			return false;
		}

		if ( $user_id && user_can( $user_id, 'administrator' ) ) {
			return false;
		}

		$purchased  = self::get_user_groups( $user_id );
		$post       = get_post( $post_id );
		$is_protect = true;

		foreach ( $plans as $plan_id => $plan ) {
			$is_protect = apply_filters( 'storeengine/membership/before_content_protect', $plan_id, $post );

			if ( in_array( (int) $plan_id, $purchased, true ) ) {
				$is_protect = false;
				break;
			}
		}

		return (bool) $is_protect;
	}

	/**
	 * All published access groups as {value,label} for dropdowns.
	 *
	 * @return array<int,array{value:int,label:string}>
	 */
	public static function get_group_options(): array {
		$options = [];
		foreach ( HelperAddon::get_all_groups() as $group ) {
			$options[] = [
				'value' => (int) $group->ID,
				'label' => $group->post_title ? $group->post_title : sprintf( /* translators: %d: group id */ __( 'Access Group #%d', 'storeengine' ), $group->ID ),
			];
		}

		return $options;
	}
}
