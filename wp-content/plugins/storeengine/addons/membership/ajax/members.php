<?php
/**
 * Members AJAX endpoints — powers the Members admin page and manual assignment
 * from the Customer editor / WP user profile.
 *
 * @package StoreEngine\Addons\Membership\Ajax
 */

namespace StoreEngine\Addons\Membership\Ajax;

use StoreEngine\Addons\Membership\Access;
use StoreEngine\Addons\Membership\HelperAddon;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Members extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'membership_group_options' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'group_options' ],
				'fields'     => [],
			],
			'membership_list_members'  => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'list_members' ],
				'fields'     => [
					'group_id' => 'absint',
					'page'     => 'absint',
					'per_page' => 'absint',
					'search'   => 'string',
				],
			],
			'membership_user_groups'   => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'user_groups' ],
				'fields'     => [ 'user_id' => 'absint' ],
			],
			'membership_search_users'  => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'search_users' ],
				'fields'     => [ 'search' => 'string' ],
			],
			'membership_analytics'     => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'analytics' ],
				'fields'     => [],
			],
			'membership_grant'         => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'grant' ],
				'fields'     => [
					'user_id'  => 'absint',
					'group_id' => 'absint',
				],
			],
			'membership_revoke'        => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'revoke' ],
				'fields'     => [
					'user_id'  => 'absint',
					'group_id' => 'absint',
				],
			],
		];
	}

	public function group_options() {
		wp_send_json_success( Access::get_group_options() );
	}

	public function list_members( $payload ) {
		$group_id = absint( $payload['group_id'] ?? 0 );
		$page     = max( 1, absint( $payload['page'] ?? 1 ) );
		$per_page = min( 100, max( 1, absint( $payload['per_page'] ?? 20 ) ) );
		$search   = sanitize_text_field( $payload['search'] ?? '' );

		if ( ! $group_id ) {
			// No group selected: aggregate members across every group so the page
			// has a sensible default view.
			$aggregate = [];
			$total     = 0;
			foreach ( Access::get_group_options() as $group ) {
				$result = Access::get_group_members( (int) $group['value'], [
					'page'     => 1,
					'per_page' => 100000,
					'search'   => $search,
				] );
				foreach ( $result['items'] as $item ) {
					$item['group_id']   = (int) $group['value'];
					$item['group_name'] = $group['label'];
					$aggregate[]        = $item;
				}
			}
			$total  = count( $aggregate );
			$offset = ( $page - 1 ) * $per_page;
			wp_send_json_success( [
				'items' => array_slice( $aggregate, $offset, $per_page ),
				'total' => $total,
				'page'  => $page,
			] );
		}

		$result           = Access::get_group_members( $group_id, compact( 'page', 'per_page', 'search' ) );
		$group_name       = get_the_title( $group_id );
		$result['items']  = array_map( function ( $item ) use ( $group_id, $group_name ) {
			$item['group_id']   = $group_id;
			$item['group_name'] = $group_name;
			return $item;
		}, $result['items'] );
		$result['page'] = $page;

		wp_send_json_success( $result );
	}

	public function user_groups( $payload ) {
		$user_id = absint( $payload['user_id'] ?? 0 );
		if ( ! $user_id ) {
			wp_send_json_error( [ 'message' => __( 'Invalid user.', 'storeengine' ) ] );
		}

		$held    = Access::get_user_groups( $user_id );
		$options = Access::get_group_options();

		wp_send_json_success( [
			'groups'   => array_values( $held ),
			'options'  => $options,
		] );
	}

	public function search_users( $payload ) {
		$search = sanitize_text_field( $payload['search'] ?? '' );

		$users = get_users( [
			'search'         => '*' . $search . '*',
			'search_columns' => [ 'user_login', 'user_email', 'display_name' ],
			'number'         => 20,
			'fields'         => [ 'ID', 'display_name', 'user_email', 'user_login' ],
		] );

		$items = array_map( function ( $user ) {
			return [
				'value'  => (int) $user->ID,
				'label'  => ( $user->display_name ? $user->display_name : $user->user_login ) . ' (' . $user->user_email . ')',
				'avatar' => get_avatar_url( $user->ID, [ 'size' => 32 ] ),
			];
		}, $users );

		wp_send_json_success( $items );
	}

	/**
	 * On-demand membership analytics — computed entirely from existing user meta,
	 * posts and the integrations table. No dedicated analytics tables.
	 */
	public function analytics() {
		$groups        = Access::get_group_options();
		$per_group     = [];
		$all_holdings  = [];
		$paid_groups   = 0;
		$free_groups   = 0;

		foreach ( $groups as $group ) {
			$gid  = (int) $group['value'];
			$ids  = Access::get_group_member_ids( $gid );
			$paid = ! empty( Helper::get_integration_repository_by_id( 'storeengine/membership-addon', $gid ) );

			if ( $paid ) {
				$paid_groups++;
			} else {
				$free_groups++;
			}

			$per_group[]  = [
				'id'      => $gid,
				'name'    => $group['label'],
				'members' => count( $ids ),
				'paid'    => $paid,
			];
			$all_holdings = array_merge( $all_holdings, $ids );
		}

		$unique_members = array_values( array_unique( array_map( 'intval', $all_holdings ) ) );

		$expired = 0;
		foreach ( $unique_members as $uid ) {
			if ( HelperAddon::is_plan_expired( $uid ) ) {
				$expired++;
			}
		}

		// Busiest groups first so the bar list reads top-down.
		usort( $per_group, fn( $a, $b ) => $b['members'] <=> $a['members'] );

		wp_send_json_success( [
			'total_members'      => count( $unique_members ),
			'active_memberships' => count( $all_holdings ),
			'paid_groups'        => $paid_groups,
			'free_groups'        => $free_groups,
			'total_groups'       => count( $groups ),
			'expired_members'    => $expired,
			'groups'             => $per_group,
		] );
	}

	public function grant( $payload ) {
		$user_id  = absint( $payload['user_id'] ?? 0 );
		$group_id = absint( $payload['group_id'] ?? 0 );

		if ( ! $user_id || ! $group_id ) {
			wp_send_json_error( [ 'message' => __( 'A user and an access group are required.', 'storeengine' ) ] );
		}

		if ( ! get_userdata( $user_id ) || STOREENGINE_MEMBERSHIP_POST_TYPE !== get_post_type( $group_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid user or access group.', 'storeengine' ) ] );
		}

		$granted = Access::grant( $user_id, $group_id, 'manual' );

		wp_send_json_success( [
			'granted' => $granted,
			'message' => $granted
				? __( 'Membership granted.', 'storeengine' )
				: __( 'This user already has that membership.', 'storeengine' ),
			'groups'  => array_values( Access::get_user_groups( $user_id ) ),
		] );
	}

	public function revoke( $payload ) {
		$user_id  = absint( $payload['user_id'] ?? 0 );
		$group_id = absint( $payload['group_id'] ?? 0 );

		if ( ! $user_id || ! $group_id ) {
			wp_send_json_error( [ 'message' => __( 'A user and an access group are required.', 'storeengine' ) ] );
		}

		$revoked = Access::revoke( $user_id, $group_id );

		wp_send_json_success( [
			'revoked' => $revoked,
			'message' => $revoked
				? __( 'Membership revoked.', 'storeengine' )
				: __( 'This user did not have that membership.', 'storeengine' ),
			'groups'  => array_values( Access::get_user_groups( $user_id ) ),
		] );
	}
}
