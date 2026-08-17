<?php

namespace StoreEngine\Addons\MultiVendor\Api;

use StoreEngine\Addons\MultiVendor\Classes\Vendor as VendorEntity;
use StoreEngine\Addons\MultiVendor\Role;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Vendor {

	const NS = 'storeengine/v1';

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( self::NS, '/vendor/me', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_me' ],
				'permission_callback' => [ $this, 'is_vendor' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_me' ],
				'permission_callback' => [ $this, 'is_vendor' ],
			],
		] );
	}

	public function is_vendor(): bool {
		$user = wp_get_current_user();
		return $user && $user->ID && in_array( Role::ROLE, (array) $user->roles, true );
	}

	public function get_me( WP_REST_Request $request ) {
		unset( $request );
		$vendor = new VendorEntity( get_current_user_id() );
		if ( ! $vendor->exists() ) {
			return new WP_Error( 'no_vendor', __( 'No vendor record found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		return new WP_REST_Response( $vendor->to_array() );
	}

	public function update_me( WP_REST_Request $request ) {
		$vendor = new VendorEntity( get_current_user_id() );
		if ( ! $vendor->exists() ) {
			return new WP_Error( 'no_vendor', __( 'No vendor record found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$store_name   = $request->get_param( 'store_name' );
		$payout_email = $request->get_param( 'payout_email' );

		if ( null !== $store_name ) {
			$vendor->set_store_name( sanitize_text_field( (string) $store_name ) );
		}
		if ( null !== $payout_email ) {
			$vendor->set_payout_email( (string) $payout_email );
		}

		// Vendors cannot change their own status, slug, or commission rate.
		$vendor->save();

		return new WP_REST_Response( $vendor->to_array() );
	}
}
