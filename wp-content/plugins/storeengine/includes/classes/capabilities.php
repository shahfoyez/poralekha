<?php

namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical role → capability matrix for the StoreEngine ecosystem.
 *
 * Single source of truth used by Role classes (so caps aren't duplicated
 * across `add_*_role()` methods) and by the one-shot migration that
 * back-fills caps onto existing role objects when the plugin updates.
 */
final class Capabilities {

	const ROLE_SHOP_MANAGER = 'storeengine_shop_manager';
	const ROLE_CUSTOMER     = 'storeengine_customer';

	/**
	 * Caps granted to `storeengine_shop_manager`.
	 */
	public static function shop_manager_caps(): array {
		return [
			// admin gates
			'manage_storeengine_shop_manager',
			'manage_storeengine_settings',
			'edit_storeengine_orders',

			// product
			'edit_storeengine_product',
			'read_storeengine_product',
			'delete_storeengine_product',
			'delete_storeengine_products',
			'edit_storeengine_products',
			'edit_others_storeengine_products',
			'read_private_storeengine_products',

			// coupon
			'edit_storeengine_coupon',
			'read_storeengine_coupon',
			'delete_storeengine_coupon',
			'delete_storeengine_coupons',
			'edit_storeengine_coupons',
			'edit_others_storeengine_coupons',
			'read_private_storeengine_coupons',

			// membership
			'edit_storeengine_group',
			'read_storeengine_group',
			'delete_storeengine_group',
			'delete_storeengine_groups',
			'edit_storeengine_groups',
			'edit_others_storeengine_groups',
			'publish_storeengine_group',
			'publish_storeengine_groups',
			'read_private_storeengine_groups',

			// common WP
			'edit_post',
			'edit_posts',
			'read',
			'upload_files',
			'edit_others_posts',

			// order bumps
			'edit_se_order_bump',
			'read_se_order_bump',
			'delete_se_order_bump',
			'edit_se_order_bumps',
			'publish_se_order_bumps',
			'read_private_se_order_bumps',
		];
	}

	/**
	 * Caps granted to `administrator` on top of WP defaults.
	 */
	public static function admin_extra_caps(): array {
		return [
			'manage_storeengine_shop_manager',
			'manage_storeengine_settings',
			'edit_storeengine_orders',
			'publish_storeengine_products',
			'publish_storeengine_coupons',
			'publish_storeengine_groups',
			'publish_se_order_bumps',
			'read_private_storeengine_products',
			'read_private_storeengine_coupons',
			'read_private_storeengine_groups',
			'read_private_se_order_bumps',
		];
	}

	/**
	 * Caps granted to `storeengine_customer`.
	 */
	public static function customer_caps(): array {
		// @TODO drop edit_posts once the customer dashboard no longer relies on it.
		return [ 'read', 'edit_posts' ];
	}
}
