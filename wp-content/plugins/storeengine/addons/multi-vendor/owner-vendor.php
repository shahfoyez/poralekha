<?php

namespace StoreEngine\Addons\MultiVendor;

use StoreEngine\Addons\MultiVendor\Classes\Vendor;
use StoreEngine\Addons\MultiVendor\Classes\Vendors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Owner vendor — a default vendor row tied to the site's primary administrator.
 * Created on addon activation when `auto_create_owner` is enabled. Useful for
 * development/testing and for stores where the admin themself is also a vendor.
 *
 * Visibility on the public store / shop archive filter is controlled by the
 * `owner_visible` setting; the row's `is_visible` column is the source of truth.
 */
class OwnerVendor {

	public static function ensure() {
		if ( ! Settings::get( 'auto_create_owner', false ) ) {
			return;
		}

		$user = self::resolve_owner_user();
		if ( ! $user ) {
			return;
		}

		// Add the vendor role alongside whatever role(s) they already have. We never
		// remove their administrator role; do not call set_role() — use add_role().
		if ( ! in_array( Role::ROLE, (array) $user->roles, true ) ) {
			$user->add_role( Role::ROLE );
		}

		$vendor = new Vendor( (int) $user->ID );
		if ( $vendor->exists() ) {
			// Sync the visibility flag with the current setting on every load —
			// admins toggling owner_visible should take effect immediately.
			$visible = (bool) Settings::get( 'owner_visible', false );
			if ( $vendor->is_visible() !== $visible ) {
				$vendor->set_is_visible( $visible );
				$vendor->save();
			}
			return;
		}

		$store_name = sprintf(
			/* translators: %s: site name */
			__( '%s Store', 'storeengine' ),
			wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES )
		);
		$slug    = Vendors::unique_slug( $store_name, (int) $user->ID );
		$visible = (bool) Settings::get( 'owner_visible', false );

		$vendor->set_user_id( (int) $user->ID );
		$vendor->set_store_name( $store_name );
		$vendor->set_store_slug( $slug );
		$vendor->set_payout_email( $user->user_email );
		$vendor->set_status( Vendor::STATUS_APPROVED );
		$vendor->set_is_visible( $visible );
		$vendor->set_is_default( true );
		$vendor->save();
		$vendor->approve(); // stamps date_approved + fires the hook
	}

	/**
	 * Pick the owner user. Prefer a user with `manage_options`; fall back to the
	 * lowest-id administrator. Returns null when run outside an admin context
	 * (e.g. on the cron) and there's no resolvable admin.
	 */
	protected static function resolve_owner_user(): ?\WP_User {
		// If an admin is currently logged in, they win — but only a *real* admin.
		// We read the stored role-derived caps (`$user->allcaps`) rather than
		// user_can(), because user_can() runs the `user_has_cap` filter, and the
		// Pro role-permission addon dynamically elevates a staff user to
		// `manage_options` for the duration of a StoreEngine request. Trusting
		// that elevation here would mis-tag the staff user as the store owner and
		// hand them the vendor role — which then locks them out of wp-admin via
		// block_admin_for_vendors(). The raw allcaps map is not touched by the
		// filter, so it reflects who is genuinely an administrator.
		$current = wp_get_current_user();
		if ( $current && $current->ID && ! empty( $current->allcaps['manage_options'] ) ) {
			return $current;
		}

		$admins = get_users( [
			'role'    => 'administrator',
			'orderby' => 'ID',
			'order'   => 'ASC',
			'number'  => 1,
		] );
		if ( ! empty( $admins ) ) {
			return $admins[0];
		}
		return null;
	}

	/**
	 * The store-owner user id products revert to when unassigned from a vendor.
	 * Prefers the administrator performing the action (they ARE the store), then
	 * falls back to the first administrator — always skipping `$exclude` (the
	 * vendor being moved away from) so we never "return" a product to itself.
	 *
	 * @param int $exclude Vendor user id to skip (so owner !== vendor).
	 *
	 * @return int Owner user id, or 0 when no eligible administrator exists.
	 */
	public static function owner_user_id( int $exclude = 0 ): int {
		$current = wp_get_current_user();
		if (
			$current &&
			$current->ID &&
			(int) $current->ID !== $exclude &&
			user_can( $current, 'manage_options' )
		) {
			return (int) $current->ID;
		}

		$admins = get_users( [
			'role'    => 'administrator',
			'orderby' => 'ID',
			'order'   => 'ASC',
			'number'  => 1,
			'fields'  => 'ID',
			'exclude' => $exclude ? [ $exclude ] : [],
		] );

		return ! empty( $admins ) ? (int) $admins[0] : 0;
	}
}
