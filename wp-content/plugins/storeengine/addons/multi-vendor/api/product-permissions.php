<?php

namespace StoreEngine\Addons\MultiVendor\Api;

use StoreEngine\Addons\MultiVendor\Classes\Vendor;
use StoreEngine\Addons\MultiVendor\Role;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductPermissions {

	public static function init() {
		$self = new self();

		$post_type = Helper::PRODUCT_POST_TYPE;

		// Force post_status=pending and post_author=current_user when a vendor creates/updates.
		add_filter( "rest_pre_insert_{$post_type}", [ $self, 'force_vendor_fields' ], 10, 2 );

		// Limit list endpoint to own products for vendors.
		add_filter( "rest_{$post_type}_query", [ $self, 'scope_list_query' ], 10, 2 );

		// Block edit/delete of products owned by other vendors at the cap layer.
		add_filter( 'map_meta_cap', [ $self, 'restrict_others_products' ], 10, 4 );
	}

	protected function current_user_is_approved_vendor(): bool {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return false;
		}
		if ( ! in_array( Role::ROLE, (array) $user->roles, true ) ) {
			return false;
		}
		// Admins/shop_managers also wearing the vendor role pass through unrestricted.
		if ( user_can( $user, 'edit_others_storeengine_products' ) ) {
			return false;
		}
		$vendor = new Vendor( (int) $user->ID );
		return $vendor->is_approved();
	}

	/**
	 * @param object          $prepared_post
	 * @param WP_REST_Request $request
	 * @return object|WP_Error
	 */
	public function force_vendor_fields( $prepared_post, $request ) {
		if ( ! $this->current_user_is_approved_vendor() ) {
			return $prepared_post;
		}

		$user_id = get_current_user_id();

		// Updates: confirm ownership before allowing fields through.
		if ( ! empty( $prepared_post->ID ) ) {
			$existing_author = (int) get_post_field( 'post_author', (int) $prepared_post->ID );
			if ( $existing_author !== (int) $user_id ) {
				return new WP_Error(
					'storeengine_vendor_forbidden',
					__( 'You can only edit your own products.', 'storeengine' ),
					[ 'status' => 403 ]
				);
			}
		}

		// Always pin author to the vendor and force pending on create/update.
		$prepared_post->post_author = $user_id;

		// Vendors cannot self-publish a new/unpublished product — but editing an
		// already-approved (published) product must keep it live; reverting it to
		// pending on every edit would pull an approved product off the store.
		$existing_status = ! empty( $prepared_post->ID ) ? get_post_status( $prepared_post->ID ) : '';

		if ( 'publish' === $existing_status ) {
			$prepared_post->post_status = 'publish';
		} else {
			// New / unpublished: allow an explicit draft (save-as-draft),
			// otherwise force pending review — unless the vendor was created with
			// auto_approve_products, in which case publish immediately.
			$incoming = ! empty( $prepared_post->post_status ) ? $prepared_post->post_status : '';
			if ( in_array( $incoming, [ 'draft', 'auto-draft' ], true ) ) {
				$prepared_post->post_status = $incoming;
			} else {
				$vendor = new Vendor( $user_id );
				$prepared_post->post_status = $vendor->get_auto_approve_products() ? 'publish' : 'pending';
			}
		}

		return $prepared_post;
	}

	/**
	 * Scope the products list endpoint to the vendor's own posts.
	 */
	public function scope_list_query( array $args, $request ): array {
		unset( $request );
		if ( ! $this->current_user_is_approved_vendor() ) {
			return $args;
		}
		$args['author']      = get_current_user_id();
		$args['post_status'] = [ 'publish', 'pending', 'draft', 'private' ];
		return $args;
	}

	/**
	 * Block edit/delete of OTHER vendors' products by mapping the meta cap to do_not_allow.
	 */
	public function restrict_others_products( array $caps, string $cap, int $user_id, array $args ): array {
		if ( ! in_array( $cap, [ 'edit_post', 'delete_post', 'read_post' ], true ) ) {
			return $caps;
		}

		if ( empty( $args[0] ) ) {
			return $caps;
		}

		$post = get_post( (int) $args[0] );
		if ( ! $post || Helper::PRODUCT_POST_TYPE !== $post->post_type ) {
			return $caps;
		}

		$user = get_user_by( 'id', $user_id );
		if ( ! $user || ! in_array( Role::ROLE, (array) $user->roles, true ) ) {
			return $caps;
		}

		// If the user can edit others' products via another role, leave it alone.
		if ( user_can( $user, 'edit_others_storeengine_products' ) ) {
			return $caps;
		}

		if ( (int) $post->post_author !== (int) $user_id ) {
			return [ 'do_not_allow' ];
		}

		return $caps;
	}
}
