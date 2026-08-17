<?php

namespace StoreEngine\Addons\MultiVendor\Api;

use StoreEngine\Addons\MultiVendor\Classes\Vendor as VendorEntity;
use StoreEngine\Addons\MultiVendor\Role;
use StoreEngine\Classes\OrderShipment;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Vendor-scoped order fulfilment.
 *
 * Lets an approved vendor move THEIR OWN order line items through the shipping
 * lifecycle and attach courier + tracking, without ever touching the shared
 * order status (an order can contain items from several vendors). The security
 * boundary is the per-row ownership check: the vendor is always resolved from
 * the lookup row's `vendor_id`, never from the request.
 *
 * Reuses the core per-item `shipping_status` machinery
 * (`order_product_lookup.shipping_status`) and fires the same core hooks the
 * admin shipping AJAX fires, so existing listeners keep working.
 */
class Fulfillment {

	const NS = 'storeengine/v1';

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	protected static function lookup_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'storeengine_order_product_lookup';
	}

	public function register_routes() {
		register_rest_route( self::NS, '/vendor/fulfillment/shipment', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'update_shipment' ],
			'permission_callback' => [ $this, 'vendor_only' ],
			'args'                => [
				'order_item_id'   => [ 'type' => 'integer', 'required' => true ],
				'shipping_status' => [ 'type' => 'string', 'required' => true ],
				'courier'         => [ 'type' => 'string', 'default' => '' ],
				'tracking_number' => [ 'type' => 'string', 'default' => '' ],
				'tracking_url'    => [ 'type' => 'string', 'default' => '' ],
			],
		] );
	}

	/**
	 * Gate: an approved vendor. Per-item ownership is enforced separately in the
	 * callback (resolved from the DB row, never the request).
	 */
	public function vendor_only(): bool {
		$user = wp_get_current_user();
		if ( ! $user || ! $user->ID ) {
			return false;
		}
		if ( ! in_array( Role::ROLE, (array) $user->roles, true ) ) {
			return false;
		}
		$v = new VendorEntity( (int) $user->ID );
		return $v->is_approved();
	}

	public function update_shipment( WP_REST_Request $request ) {
		global $wpdb;

		$order_item_id = (int) $request->get_param( 'order_item_id' );

		// Ownership gate — THE multi-vendor security check. Resolve the owning
		// vendor from the row, never from anything the caller sent. Positive rows
		// only (negative rows are commission refund markers).
		$lookup = self::lookup_table();
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table name is $wpdb->prefix + a literal; order_item_id prepared via %d.
		$owner = $wpdb->get_var( $wpdb->prepare(
			"SELECT vendor_id FROM `{$lookup}` WHERE order_item_id = %d AND order_item_id > 0",
			$order_item_id
		) );
		// phpcs:enable
		if ( null === $owner ) {
			return new WP_Error( 'not_found', __( 'Order item not found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		if ( (int) $owner !== (int) get_current_user_id() ) {
			return new WP_Error( 'rest_forbidden', __( 'You can only fulfil your own products.', 'storeengine' ), [ 'status' => 403 ] );
		}

		$vendor = new VendorEntity( (int) get_current_user_id() );

		$result = OrderShipment::record(
			$order_item_id,
			sanitize_key( (string) $request->get_param( 'shipping_status' ) ),
			[
				'courier'         => (string) $request->get_param( 'courier' ),
				'tracking_number' => (string) $request->get_param( 'tracking_number' ),
				'tracking_url'    => (string) $request->get_param( 'tracking_url' ),
			],
			$vendor->get_store_name() ?: ( '#' . $vendor->get_user_id() )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['message'] = __( 'Shipping status updated.', 'storeengine' );
		return new WP_REST_Response( $result );
	}
}
