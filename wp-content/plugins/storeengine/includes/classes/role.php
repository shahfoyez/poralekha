<?php

namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Role {

	const MIGRATION_OPTION = 'storeengine_role_migration_v2';

	public static function add_customer_role() {
		if ( ! get_role( Capabilities::ROLE_CUSTOMER ) ) {
			add_role(
				Capabilities::ROLE_CUSTOMER,
				esc_html__( 'StoreEngine Customer', 'storeengine' ),
				[]
			);
		}

		$customer = get_role( Capabilities::ROLE_CUSTOMER );
		if ( ! $customer ) {
			return;
		}

		foreach ( Capabilities::customer_caps() as $cap ) {
			$customer->add_cap( $cap );
		}
	}

	public static function add_shop_manager_role() {
		if ( ! get_role( Capabilities::ROLE_SHOP_MANAGER ) ) {
			add_role(
				Capabilities::ROLE_SHOP_MANAGER,
				esc_html__( 'StoreEngine Shop manager', 'storeengine' ),
				[]
			);
		}

		$shop_manager = get_role( Capabilities::ROLE_SHOP_MANAGER );
		if ( $shop_manager ) {
			foreach ( Capabilities::shop_manager_caps() as $cap ) {
				$shop_manager->add_cap( $cap );
			}
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( Capabilities::admin_extra_caps() as $cap ) {
				$administrator->add_cap( $cap );
			}
		}

		if ( current_user_can( 'administrator' ) ) {
			$user_id      = get_current_user_id();
			$shop_manager = new \WP_User( $user_id );
			$shop_manager->add_role( Capabilities::ROLE_SHOP_MANAGER );
		}
	}

	public static function remove_customer_role(): void {
		remove_role( Capabilities::ROLE_CUSTOMER );
	}

	public static function remove_shop_manager_role(): void {
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( Capabilities::admin_extra_caps() as $cap ) {
				$administrator->remove_cap( $cap );
			}
		}
		remove_role( Capabilities::ROLE_SHOP_MANAGER );
	}

	/**
	 * One-shot back-fill that adds newly-introduced caps to the existing
	 * shop_manager and administrator role objects on plugin update. Idempotent
	 * via the `storeengine_role_migration_v2` option flag.
	 */
	public static function migrate_caps_v2(): void {
		if ( get_option( self::MIGRATION_OPTION ) ) {
			return;
		}

		$new_caps = [ 'manage_storeengine_settings', 'edit_storeengine_orders' ];

		$shop_manager = get_role( Capabilities::ROLE_SHOP_MANAGER );
		if ( $shop_manager ) {
			foreach ( $new_caps as $cap ) {
				$shop_manager->add_cap( $cap );
			}
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			foreach ( $new_caps as $cap ) {
				$administrator->add_cap( $cap );
			}
		}

		update_option( self::MIGRATION_OPTION, 1, false );
	}
}
