<?php

namespace StoreEngine\Addons\MultiVendor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Role {

	const ROLE = 'storeengine_vendor';

	public static function add_vendor_role() {
		// Idempotent: only add if missing, then refresh caps to match.
		if ( ! get_role( self::ROLE ) ) {
			add_role( self::ROLE, esc_html__( 'StoreEngine Vendor', 'storeengine' ), [] );
		}

		$role = get_role( self::ROLE );
		if ( ! $role ) {
			return;
		}

		// Vendors get edit on own products, no publish (admin moderates), no edit_others.
		$caps = [
			'read',
			'upload_files',
			'edit_post',
			'edit_posts',
			// product (own only — no edit_others_*, no read_private_*)
			'edit_storeengine_product',
			'read_storeengine_product',
			'delete_storeengine_product',
			'edit_storeengine_products',
			'delete_storeengine_products',
			// vendor-specific cap used as the REST permission gate
			'manage_storeengine_vendor',
		];

		foreach ( $caps as $cap ) {
			$role->add_cap( $cap );
		}

		// Caps the vendor must NOT have — strip if present from prior installs.
		$denied = [
			'publish_storeengine_products',
			'edit_others_storeengine_products',
			'read_private_storeengine_products',
			'delete_others_storeengine_products',
		];

		foreach ( $denied as $cap ) {
			$role->remove_cap( $cap );
		}

		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->add_cap( 'manage_storeengine_vendor' );
		}
	}

	public static function remove_vendor_role(): void {
		// Use only on uninstall — orphans any user holding the role.
		$administrator = get_role( 'administrator' );
		if ( $administrator ) {
			$administrator->remove_cap( 'manage_storeengine_vendor' );
		}
		remove_role( self::ROLE );
	}
}
