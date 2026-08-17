<?php

namespace StoreEngine\Addons\Inventory\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Centralized permission checks for inventory operations.
 *
 * Admin and shop_manager keep full access. Vendor users (with the
 * `manage_storeengine_vendor` capability granted by the multi-vendor addon)
 * are scoped to products they author — they cannot read or mutate stock for
 * products owned by other vendors. Inventory works standalone if the
 * multi-vendor addon isn't installed (the vendor branch never triggers).
 */
final class Authorization {

	const VENDOR_CAP = 'manage_storeengine_vendor';

	/**
	 * Permission gate for general inventory access (listing endpoints).
	 * Vendors get true, but the controller MUST scope query results
	 * to their own products via current_user_id().
	 */
	public static function can_access_inventory(): bool {
		if ( current_user_can( 'manage_options' )
			|| current_user_can( 'manage_storeengine_settings' ) ) {
			return true;
		}
		return current_user_can( 'edit_storeengine_products' )
			|| current_user_can( self::VENDOR_CAP );
	}

	/**
	 * Permission gate for ops that require a specific product
	 * (adjust, transfer, set stock). Vendors must own the product.
	 */
	public static function can_modify_product( int $product_id, int $user_id = 0 ): bool {
		if ( $product_id <= 0 ) return false;

		if ( $user_id <= 0 ) {
			$user_id = (int) get_current_user_id();
		}

		// Privileged roles bypass the per-product check.
		$user = $user_id ? get_userdata( $user_id ) : null;
		if ( $user && ( in_array( 'administrator', (array) $user->roles, true )
			|| user_can( $user, 'manage_storeengine_settings' ) ) ) {
			return true;
		}

		if ( ! $user || ! user_can( $user, 'edit_storeengine_products' ) ) {
			// User must at least hold the product-edit cap.
			if ( ! $user || ! user_can( $user, self::VENDOR_CAP ) ) {
				return false;
			}
		}

		// Vendor or product-edit-only user: must own the product.
		if ( user_can( $user, self::VENDOR_CAP ) && ! user_can( $user, 'manage_options' ) ) {
			$author = (int) get_post_field( 'post_author', $product_id );
			return $author === $user_id;
		}

		return true;
	}

	/**
	 * Returns the user_id to scope inventory list queries by, or 0 if
	 * the caller has full visibility (admin/shop_manager).
	 */
	public static function scope_user_id(): int {
		if ( current_user_can( 'manage_options' )
			|| current_user_can( 'manage_storeengine_settings' ) ) {
			return 0;
		}
		if ( current_user_can( self::VENDOR_CAP ) ) {
			return (int) get_current_user_id();
		}
		return 0;
	}
}
