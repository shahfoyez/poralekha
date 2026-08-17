<?php
namespace StoreEngine\Addons\Affiliate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Role {

	const ROLE = 'storeengine_affiliate';

	const CAPS = [
		'manage_storeengine_affiliate',
		// affiliate post type
		'edit_storeengine_affiliate',
		'read_storeengine_affiliate',
		'delete_storeengine_affiliate',
		'delete_storeengine_affiliates',
		'edit_storeengine_affiliates',
		'edit_others_storeengine_affiliates',
		'read_private_storeengine_affiliates',
		// common WP
		'edit_post',
		'edit_posts',
		'read',
		'edit_others_posts',
	];

	public static function add_affiliate_role() {
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, esc_html__( 'StoreEngine Affiliate', 'storeengine' ), [] );
		}

		$affiliate = get_role( self::ROLE );
		if ( $affiliate ) {
			foreach ( self::CAPS as $cap ) {
				$affiliate->add_cap( $cap );
			}
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( 'manage_storeengine_affiliate' );
		}

		if ( current_user_can( 'administrator' ) ) {
			$user_id   = get_current_user_id();
			$affiliate = new \WP_User( $user_id );
			$affiliate->add_role( self::ROLE );
		}
	}

	public static function remove_affiliate_role(): void {
		// Use only on uninstall — orphans any user holding the role.
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->remove_cap( 'manage_storeengine_affiliate' );
		}
		remove_role( self::ROLE );
	}
}
