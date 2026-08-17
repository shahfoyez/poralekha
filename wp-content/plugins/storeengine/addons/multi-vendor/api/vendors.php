<?php

namespace StoreEngine\Addons\MultiVendor\Api;

use StoreEngine\Addons\MultiVendor\Classes\Vendor as VendorEntity;
use StoreEngine\Addons\MultiVendor\Classes\Vendors as VendorsCollection;
use StoreEngine\Addons\MultiVendor\OwnerVendor;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-facing REST controller for the React Vendors page.
 * Routes:
 *   GET    /storeengine/v1/vendors            — list (filter by status, search, paged)
 *   GET    /storeengine/v1/vendors/{user_id}  — single
 *   PATCH  /storeengine/v1/vendors/{user_id}  — update store_name / commission / payout_email
 *   POST   /storeengine/v1/vendors/{user_id}/approve
 *   POST   /storeengine/v1/vendors/{user_id}/suspend
 *
 * This is the ADMIN vendor-management controller — it acts on arbitrary
 * vendor accounts (approve/suspend/CRUD/assign products). It must therefore
 * require a real administrator capability, NOT `manage_storeengine_vendor`:
 * that cap is also granted to the `storeengine_vendor` role for vendor
 * self-service (dashboard/settings/withdrawals), so gating these endpoints on
 * it let a self-registered pending vendor approve their own account and manage
 * every other vendor. Vendor self-service controllers keep their own gate.
 */
class Vendors {

	const NS = 'storeengine/v1';

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	public function permission_check() {
		return current_user_can( 'manage_options' );
	}

	public function register_routes() {
		register_rest_route( self::NS, '/vendors', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list' ],
				'permission_callback' => [ $this, 'permission_check' ],
				'args'                => [
					'status'   => [ 'type' => 'string', 'default' => '' ],
					'search'   => [ 'type' => 'string', 'default' => '' ],
					'page'     => [ 'type' => 'integer', 'default' => 1 ],
					'per_page' => [ 'type' => 'integer', 'default' => 20 ],
				],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create' ],
				'permission_callback' => [ $this, 'permission_check' ],
				'args'                => [
					'email'           => [ 'type' => 'string', 'required' => true ],
					'store_name'      => [ 'type' => 'string', 'required' => true ],
					'password'        => [ 'type' => 'string' ],
					'auto_approve'    => [ 'type' => 'boolean', 'default' => true ],
					'commission_rate' => [ 'type' => [ 'number', 'null' ] ],
					'commission_type' => [ 'type' => 'string', 'default' => 'percent' ],
					'send_email'      => [ 'type' => 'boolean', 'default' => true ],
				],
			],
		] );

		register_rest_route( self::NS, '/vendors/(?P<user_id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_one' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE, // PATCH/PUT/POST
				'callback'            => [ $this, 'update_one' ],
				'permission_callback' => [ $this, 'permission_check' ],
				'args'                => [
					'store_name'      => [ 'type' => 'string' ],
					'store_slug'      => [ 'type' => 'string' ],
					'store_description' => [ 'type' => 'string' ],
					'business_type'   => [ 'type' => 'string' ],
					'phone'           => [ 'type' => 'string' ],
					'payout_email'    => [ 'type' => 'string' ],
					'commission_rate' => [ 'type' => [ 'number', 'null' ] ],
					'commission_type' => [ 'type' => 'string' ],
					'badges'          => [ 'type' => 'array', 'items' => [ 'type' => 'string' ] ],
					'is_visible'      => [ 'type' => 'boolean' ],
				],
			],
		] );

		register_rest_route( self::NS, '/vendors/(?P<user_id>\d+)/approve', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'approve' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		register_rest_route( self::NS, '/vendors/(?P<user_id>\d+)/suspend', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'suspend' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		register_rest_route( self::NS, '/vendors/(?P<user_id>\d+)/assign-products', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'assign_products' ],
			'permission_callback' => [ $this, 'permission_check' ],
			'args'                => [
				'product_ids' => [
					'type'     => 'array',
					'required' => true,
					'items'    => [ 'type' => 'integer' ],
				],
			],
		] );

		register_rest_route( self::NS, '/vendors/(?P<user_id>\d+)/unassign-products', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'unassign_products' ],
			'permission_callback' => [ $this, 'permission_check' ],
			'args'                => [
				'product_ids'  => [
					'type'     => 'array',
					'required' => true,
					'items'    => [ 'type' => 'integer' ],
				],
				'new_owner_id' => [
					'type'     => 'integer',
					'required' => false,
				],
			],
		] );
	}

	public function create( WP_REST_Request $request ) {
		$email      = sanitize_email( (string) $request->get_param( 'email' ) );
		$store_name = sanitize_text_field( (string) $request->get_param( 'store_name' ) );
		$password   = (string) $request->get_param( 'password' );
		$auto       = (bool) $request->get_param( 'auto_approve' );
		$send_email = (bool) $request->get_param( 'send_email' );
		$rate       = $request->get_param( 'commission_rate' );
		$type       = (string) $request->get_param( 'commission_type' );

		if ( ! is_email( $email ) || '' === $store_name ) {
			return new WP_Error( 'invalid_input', __( 'A valid email and store name are required.', 'storeengine' ), [ 'status' => 400 ] );
		}

		if ( email_exists( $email ) || username_exists( $email ) ) {
			return new WP_Error( 'user_exists', __( 'A user with that email already exists.', 'storeengine' ), [ 'status' => 409 ] );
		}

		if ( '' === $password ) {
			$password = wp_generate_password( 16 );
		}

		$user_id = wp_insert_user( [
			'user_login' => $email,
			'user_email' => $email,
			'user_pass'  => $password,
			'role'       => \StoreEngine\Addons\MultiVendor\Role::ROLE,
		] );

		if ( is_wp_error( $user_id ) ) {
			return new WP_Error( 'user_create_failed', $user_id->get_error_message(), [ 'status' => 500 ] );
		}

		$slug   = VendorsCollection::unique_slug( $store_name, (int) $user_id );
		$vendor = new VendorEntity( (int) $user_id );
		$vendor->set_user_id( (int) $user_id );
		$vendor->set_store_name( $store_name );
		$vendor->set_store_slug( $slug );
		$vendor->set_payout_email( $email );
		$vendor->set_status( $auto ? VendorEntity::STATUS_APPROVED : VendorEntity::STATUS_PENDING );
		$vendor->set_auto_approve_products( $auto );
		if ( null !== $rate && '' !== $rate ) {
			$vendor->set_commission_rate( (float) $rate );
			if ( in_array( $type, [ 'percent', 'flat' ], true ) ) {
				$vendor->set_commission_type( $type );
			}
		}
		$vendor->save();

		// `set_status('approved')` doesn't stamp date_approved — call approve() to fire the
		// hook + set the timestamp when the admin chose to auto-approve.
		if ( $auto ) {
			$vendor->approve();
		}

		do_action( 'storeengine/multi_vendor/vendor_registered', $vendor );

		if ( $send_email ) {
			$reset_key = get_password_reset_key( get_user_by( 'id', $user_id ) );
			if ( ! is_wp_error( $reset_key ) ) {
				$reset_url = network_site_url( "wp-login.php?action=rp&key={$reset_key}&login=" . rawurlencode( $email ), 'login' );
				$subject   = sprintf(
					/* translators: %s: site name */
					__( 'Your %s vendor account', 'storeengine' ),
					wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES )
				);
				/* translators: %s: vendor store name */
				$message  = sprintf( __( 'Hi %s,', 'storeengine' ), $store_name ) . "\r\n\r\n";
				$message .= __( 'A vendor account was created for you.', 'storeengine' ) . "\r\n";
				$message .= __( 'Set your password using the link below:', 'storeengine' ) . "\r\n" . $reset_url . "\r\n";
				wp_mail( $email, $subject, $message );
			}
		}

		return new WP_REST_Response( $this->shape( $vendor ), 201 );
	}

	public function list( WP_REST_Request $request ) {
		$status   = (string) $request->get_param( 'status' );
		$search   = (string) $request->get_param( 'search' );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$rows = VendorsCollection::query( [
			'status' => $status,
			'search' => $search,
			'limit'  => $per_page,
			'offset' => $offset,
		] );

		$counts = [
			'pending'   => VendorsCollection::count_by_status( 'pending' ),
			'approved'  => VendorsCollection::count_by_status( 'approved' ),
			'suspended' => VendorsCollection::count_by_status( 'suspended' ),
		];

		// Batch the per-vendor product counts in a single grouped query so the
		// list never fans out into one COUNT(*) per row.
		$count_map = $this->product_counts_for( array_map( static fn( $vendor ) => $vendor->get_user_id(), $rows ) );

		$items = [];
		foreach ( $rows as $vendor ) {
			$items[] = $this->shape( $vendor, $count_map[ $vendor->get_user_id() ] ?? 0 );
		}

		return new WP_REST_Response( [
			'items'            => $items,
			'counts'           => $counts,
			'page'             => $page,
			'total'            => array_sum( $counts ),
			'available_badges' => (array) \StoreEngine\Addons\MultiVendor\Settings::get( 'badges', [] ),
		] );
	}

	public function get_one( WP_REST_Request $request ) {
		$vendor = new VendorEntity( (int) $request['user_id'] );
		if ( ! $vendor->exists() ) {
			return new WP_Error( 'not_found', __( 'Vendor not found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( $this->shape( $vendor ) );
	}

	public function update_one( WP_REST_Request $request ) {
		$vendor = new VendorEntity( (int) $request['user_id'] );
		if ( ! $vendor->exists() ) {
			return new WP_Error( 'not_found', __( 'Vendor not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		if ( null !== $request->get_param( 'store_name' ) ) {
			$vendor->set_store_name( sanitize_text_field( (string) $request->get_param( 'store_name' ) ) );
		}
		if ( null !== $request->get_param( 'store_slug' ) ) {
			$requested = sanitize_title( (string) $request->get_param( 'store_slug' ) );
			// Fall back to the store name when the admin clears the slug, then
			// guarantee uniqueness against every OTHER vendor (excluding self).
			if ( '' === $requested ) {
				$requested = $vendor->get_store_name();
			}
			$vendor->set_store_slug( VendorsCollection::unique_slug( $requested, $vendor->get_user_id() ) );
		}
		if ( null !== $request->get_param( 'store_description' ) ) {
			$vendor->set_store_description( (string) $request->get_param( 'store_description' ) );
		}
		if ( null !== $request->get_param( 'business_type' ) ) {
			$vendor->set_business_type( (string) $request->get_param( 'business_type' ) );
		}
		if ( null !== $request->get_param( 'phone' ) ) {
			$vendor->set_phone( (string) $request->get_param( 'phone' ) );
		}
		if ( null !== $request->get_param( 'payout_email' ) ) {
			$vendor->set_payout_email( (string) $request->get_param( 'payout_email' ) );
		}
		if ( null !== $request->get_param( 'commission_rate' ) ) {
			$rate = $request->get_param( 'commission_rate' );
			$vendor->set_commission_rate( '' === $rate || null === $rate ? null : (float) $rate );
		}
		if ( null !== $request->get_param( 'commission_type' ) ) {
			$vendor->set_commission_type( (string) $request->get_param( 'commission_type' ) );
		}
		if ( null !== $request->get_param( 'badges' ) ) {
			$badges = (array) $request->get_param( 'badges' );
			$vendor->set_badges( $badges );
		}
		if ( null !== $request->get_param( 'is_visible' ) ) {
			$vendor->set_is_visible( (bool) $request->get_param( 'is_visible' ) );
		}

		$vendor->save();
		return new WP_REST_Response( $this->shape( $vendor ) );
	}

	public function approve( WP_REST_Request $request ) {
		$user_id = (int) $request['user_id'];
		// Defence in depth: even with the admin-only permission_check above, never
		// let a caller approve their own vendor account.
		if ( get_current_user_id() === $user_id ) {
			return new WP_Error( 'forbidden', __( 'You cannot approve your own vendor account.', 'storeengine' ), [ 'status' => 403 ] );
		}
		$vendor = new VendorEntity( $user_id );
		if ( ! $vendor->exists() ) {
			return new WP_Error( 'not_found', __( 'Vendor not found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		$vendor->approve();
		return new WP_REST_Response( $this->shape( $vendor ) );
	}

	public function suspend( WP_REST_Request $request ) {
		$vendor = new VendorEntity( (int) $request['user_id'] );
		if ( ! $vendor->exists() ) {
			return new WP_Error( 'not_found', __( 'Vendor not found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		// The default store vendor (the owner-vendor tied to the site admin) is
		// the store itself — suspending it would take the whole storefront
		// offline. The UI hides the button for it; this is the matching guard.
		if ( $vendor->is_default() ) {
			return new WP_Error( 'forbidden', __( 'The default store vendor cannot be suspended.', 'storeengine' ), [ 'status' => 403 ] );
		}
		$vendor->suspend();
		return new WP_REST_Response( $this->shape( $vendor ) );
	}

	/**
	 * Assign one or more existing products to a vendor by changing their `post_author`
	 * and updating the lookup table's vendor_id for any historical order rows tied
	 * to those products. Historical commission_amount values are intentionally NOT
	 * recomputed — those were calculated at order-paid time with the prior owner's
	 * rate, and retroactively rewriting them would be confusing for accounting.
	 */
	public function assign_products( WP_REST_Request $request ) {
		global $wpdb;

		$user_id = (int) $request['user_id'];
		$vendor  = new VendorEntity( $user_id );
		if ( ! $vendor->exists() ) {
			return new WP_Error( 'not_found', __( 'Vendor not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$ids = array_filter( array_map( 'intval', (array) $request->get_param( 'product_ids' ) ) );
		if ( empty( $ids ) ) {
			return new WP_Error( 'no_products', __( 'No products provided.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$post_type = \StoreEngine\Utils\Helper::PRODUCT_POST_TYPE;
		$assigned  = [];
		$skipped   = [];

		foreach ( $ids as $pid ) {
			$post = get_post( $pid );
			if ( ! $post || $post_type !== $post->post_type ) {
				$skipped[] = $pid;
				continue;
			}
			if ( (int) $post->post_author === $user_id ) {
				$assigned[] = $pid;
				continue;
			}

			$updated = wp_update_post( [
				'ID'          => $pid,
				'post_author' => $user_id,
			], true );

			if ( is_wp_error( $updated ) ) {
				$skipped[] = $pid;
				continue;
			}
			$assigned[] = $pid;
		}

		// Update existing lookup rows so per-vendor analytics queries pick up these
		// historical orders under the new owner. Only rewrites vendor_id; revenue
		// and commission_amount are untouched.
		if ( ! empty( $assigned ) ) {
			$lookup       = $wpdb->prefix . 'storeengine_order_product_lookup';
			$placeholders = implode( ',', array_fill( 0, count( $assigned ), '%d' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix + a literal; values prepared via %d ($placeholders is a literal %d list).
			$wpdb->query( $wpdb->prepare(
				"UPDATE `{$lookup}` SET vendor_id = %d WHERE product_id IN ($placeholders)",
				array_merge( [ $user_id ], $assigned )
			) );
			// phpcs:enable
		}

		do_action( 'storeengine/multi_vendor/products_assigned', $user_id, $assigned );

		return new WP_REST_Response( [
			'assigned' => $assigned,
			'skipped'  => $skipped,
			'vendor'   => $this->shape( $vendor ),
		] );
	}

	/**
	 * Remove one or more products from a vendor — the inverse of assign_products.
	 *
	 * Ownership (`post_author`) and the lookup table's vendor_id revert to a
	 * destination user. By default that's the store owner (the admin performing
	 * the action), but an admin may pass an explicit `new_owner_id` to reassign
	 * the products straight to another vendor or admin instead. Only products
	 * currently owned by this vendor are affected; historical commission_amount
	 * values are left untouched (same rationale as assign).
	 */
	public function unassign_products( WP_REST_Request $request ) {
		global $wpdb;

		$user_id = (int) $request['user_id'];
		$vendor  = new VendorEntity( $user_id );
		if ( ! $vendor->exists() ) {
			return new WP_Error( 'not_found', __( 'Vendor not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$ids = array_filter( array_map( 'intval', (array) $request->get_param( 'product_ids' ) ) );
		if ( empty( $ids ) ) {
			return new WP_Error( 'no_products', __( 'No products provided.', 'storeengine' ), [ 'status' => 400 ] );
		}

		// Resolve where the products should go. An explicit destination (chosen
		// by the admin in the UI) wins; otherwise fall back to the store owner,
		// resolved so it's never the vendor we're moving away from.
		$new_owner = (int) $request->get_param( 'new_owner_id' );
		if ( $new_owner ) {
			if ( ! get_user_by( 'id', $new_owner ) ) {
				return new WP_Error( 'invalid_owner', __( 'The selected destination account no longer exists.', 'storeengine' ), [ 'status' => 400 ] );
			}
			$owner_id = $new_owner;
		} else {
			$owner_id = OwnerVendor::owner_user_id( $user_id );
		}

		if ( ! $owner_id || $owner_id === $user_id ) {
			return new WP_Error(
				'no_owner',
				__( 'No destination is available for these products. Pick another vendor to reassign them to, or create a second administrator to act as the store owner.', 'storeengine' ),
				[ 'status' => 400 ]
			);
		}

		$post_type  = \StoreEngine\Utils\Helper::PRODUCT_POST_TYPE;
		$unassigned = [];
		$skipped    = [];

		foreach ( $ids as $pid ) {
			$post = get_post( $pid );
			if ( ! $post || $post_type !== $post->post_type ) {
				$skipped[] = $pid;
				continue;
			}
			// Only unassign products currently owned by THIS vendor.
			if ( (int) $post->post_author !== $user_id ) {
				$skipped[] = $pid;
				continue;
			}

			$updated = wp_update_post( [
				'ID'          => $pid,
				'post_author' => $owner_id,
			], true );

			if ( is_wp_error( $updated ) ) {
				$skipped[] = $pid;
				continue;
			}
			$unassigned[] = $pid;
		}

		if ( ! empty( $unassigned ) ) {
			$lookup       = $wpdb->prefix . 'storeengine_order_product_lookup';
			$placeholders = implode( ',', array_fill( 0, count( $unassigned ), '%d' ) );
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix + a literal; values prepared via %d ($placeholders is a literal %d list).
			$wpdb->query( $wpdb->prepare(
				"UPDATE `{$lookup}` SET vendor_id = %d WHERE product_id IN ($placeholders)",
				array_merge( [ $owner_id ], $unassigned )
			) );
			// phpcs:enable
		}

		do_action( 'storeengine/multi_vendor/products_unassigned', $user_id, $unassigned, $owner_id );

		return new WP_REST_Response( [
			'unassigned' => $unassigned,
			'skipped'    => $skipped,
			'vendor'     => $this->shape( $vendor ),
		] );
	}

	protected function shape( VendorEntity $vendor, ?int $product_count = null ): array {
		$user                 = get_user_by( 'id', $vendor->get_user_id() );
		$data                 = $vendor->to_array();
		$data['user_email']   = $user ? $user->user_email : '';
		$data['user_login']   = $user ? $user->user_login : '';
		$data['display_name'] = $user ? $user->display_name : '';
		$data['store_url']    = \StoreEngine\Addons\MultiVendor\StorePage::url_for( $vendor );

		// Single-vendor callers (get_one / update / approve …) don't pre-compute a
		// count, so resolve it on demand. The list endpoint passes a batched value.
		if ( null === $product_count ) {
			$counts        = $this->product_counts_for( [ $vendor->get_user_id() ] );
			$product_count = $counts[ $vendor->get_user_id() ] ?? 0;
		}
		$data['product_count'] = (int) $product_count;

		return $data;
	}

	/**
	 * Count each vendor's products (by `post_author`) in one grouped query.
	 * Excludes trashed / auto-draft / revision rows so the number matches what
	 * the vendor actually manages.
	 *
	 * @param int[] $user_ids
	 * @return array<int,int> Map of user_id => product count (only non-zero rows present).
	 */
	protected function product_counts_for( array $user_ids ): array {
		global $wpdb;

		$user_ids = array_values( array_unique( array_filter( array_map( 'intval', $user_ids ) ) ) );
		if ( empty( $user_ids ) ) {
			return [];
		}

		$post_type    = \StoreEngine\Utils\Helper::PRODUCT_POST_TYPE;
		$placeholders = implode( ',', array_fill( 0, count( $user_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results( $wpdb->prepare(
			"SELECT post_author, COUNT(*) AS total
			 FROM {$wpdb->posts}
			 WHERE post_type = %s
			   AND post_status NOT IN ( 'trash', 'auto-draft', 'inherit' )
			   AND post_author IN ( {$placeholders} )
			 GROUP BY post_author",
			array_merge( [ $post_type ], $user_ids )
		) );
		// phpcs:enable

		$map = [];
		foreach ( (array) $rows as $row ) {
			$map[ (int) $row->post_author ] = (int) $row->total;
		}

		return $map;
	}
}
