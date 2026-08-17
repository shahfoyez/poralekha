<?php

namespace StoreEngine\Addons\MultiVendor\Api;

use StoreEngine\Addons\MultiVendor\Classes\Balance;
use StoreEngine\Addons\MultiVendor\Classes\Vendor as VendorEntity;
use StoreEngine\Addons\MultiVendor\Role;
use StoreEngine\Addons\MultiVendor\Settings;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Withdrawals {

	const NS = 'storeengine/v1';

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	protected static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'storeengine_vendor_withdrawals';
	}

	public function register_routes() {
		register_rest_route( self::NS, '/withdrawals', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list' ],
				'permission_callback' => [ $this, 'list_permission' ],
				'args'                => [
					'status'    => [ 'type' => 'string', 'default' => '' ],
					'vendor_id' => [ 'type' => 'integer', 'default' => 0 ],
					'page'      => [ 'type' => 'integer', 'default' => 1 ],
					'per_page'  => [ 'type' => 'integer', 'default' => 20 ],
				],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create' ],
				'permission_callback' => [ $this, 'vendor_only' ],
				'args'                => [
					'amount'      => [ 'type' => 'number', 'required' => true ],
					'vendor_note' => [ 'type' => 'string' ],
				],
			],
		] );

		register_rest_route( self::NS, '/withdrawals/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_status' ],
				'permission_callback' => [ $this, 'admin_only' ],
				'args'                => [
					'status'     => [ 'type' => 'string', 'required' => true ],
					'admin_note' => [ 'type' => 'string' ],
				],
			],
		] );

		// Admin-initiated payout for a custom amount (<= the vendor's available
		// balance). Lets an admin pay a large balance in instalments when they
		// can't settle it all at once, without waiting for a vendor request.
		register_rest_route( self::NS, '/withdrawals/pay', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'admin_pay' ],
				'permission_callback' => [ $this, 'admin_only' ],
				'args'                => [
					'vendor_id'  => [ 'type' => 'integer', 'required' => true ],
					'amount'     => [ 'type' => 'number', 'required' => true ],
					'admin_note' => [ 'type' => 'string' ],
				],
			],
		] );

		// A specific vendor's available balance — admin-side counterpart of
		// /vendor/balance, so the payout UI can validate/display before paying.
		register_rest_route( self::NS, '/withdrawals/vendor-balance', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_vendor_balance' ],
				'permission_callback' => [ $this, 'admin_only' ],
				'args'                => [
					'vendor_id' => [ 'type' => 'integer', 'required' => true ],
				],
			],
		] );

		// Vendor's own payment method (read/write).
		register_rest_route( self::NS, '/vendor/payment-method', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_payment_method' ],
				'permission_callback' => [ $this, 'vendor_only' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_payment_method' ],
				'permission_callback' => [ $this, 'vendor_only' ],
				'args'                => [
					'method' => [ 'type' => 'string', 'required' => true ],
					'data'   => [ 'type' => 'object', 'default' => [] ],
				],
			],
		] );

		// Balance summary for the current vendor.
		register_rest_route( self::NS, '/vendor/balance', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_balance' ],
			'permission_callback' => [ $this, 'vendor_only' ],
		] );
	}

	public function admin_only(): bool {
		// Real admin gate — `manage_storeengine_vendor` is ALSO held by the
		// storeengine_vendor role, so using it here would let a vendor mark
		// their own withdrawal paid. Payout status changes require an admin.
		return current_user_can( 'manage_options' );
	}

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

	public function list_permission( WP_REST_Request $request ) {
		if ( $this->admin_only() ) {
			return true;
		}
		// Vendors can list their own.
		if ( $this->vendor_only() ) {
			$asked = (int) $request->get_param( 'vendor_id' );
			return ! $asked || $asked === (int) get_current_user_id();
		}
		return false;
	}

	public function list( WP_REST_Request $request ) {
		global $wpdb;

		$status   = (string) $request->get_param( 'status' );
		$vendorId = (int) $request->get_param( 'vendor_id' );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		// Vendors can only list their own; admins can pass a vendor_id or omit it.
		if ( ! $this->admin_only() ) {
			$vendorId = (int) get_current_user_id();
		}

		$where    = [];
		$values   = [];
		if ( $status ) {
			$where[]  = 'status = %s';
			$values[] = $status;
		}
		if ( $vendorId ) {
			$where[]  = 'user_id = %d';
			$values[] = $vendorId;
		}
		$where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';
		$values[]  = $per_page;
		$values[]  = $offset;

		$table  = self::table();
		$sql    = "SELECT * FROM %i {$where_sql} ORDER BY requested_at DESC LIMIT %d OFFSET %d";
		$params = array_merge( [ $table ], $values );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared (%i/%d/%s) query on a custom StoreEngine withdrawals table; WHERE built from literals only.
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );

		$count_sql    = 'SELECT COUNT(*) FROM %i';
		$count_values = [ $table ];
		if ( $where ) {
			$count_sql   .= ' WHERE ' . implode( ' AND ', $where );
			$count_values = array_merge( $count_values, array_slice( $values, 0, count( $values ) - 2 ) );
		}
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $count_values ) );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter

		$items = [];
		foreach ( (array) $rows as $row ) {
			$items[] = $this->shape( $row );
		}

		return new WP_REST_Response( [
			'items' => $items,
			'total' => $total,
			'page'  => $page,
		] );
	}

	public function create( WP_REST_Request $request ) {
		global $wpdb;

		$user_id = (int) get_current_user_id();
		$vendor  = new VendorEntity( $user_id );
		$amount  = round( (float) $request->get_param( 'amount' ), 2 );
		$note    = (string) $request->get_param( 'vendor_note' );

		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Amount must be greater than zero.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$min = (float) Settings::get( 'min_withdraw_amount', 0 );
		if ( $amount < $min ) {
			return new WP_Error( 'below_minimum', sprintf(
				/* translators: %s: minimum amount */
				__( 'Minimum withdrawal amount is %s.', 'storeengine' ),
				number_format_i18n( $min, 2 )
			), [ 'status' => 400 ] );
		}

		$balance = Balance::for_vendor( $user_id );
		if ( $amount > $balance ) {
			return new WP_Error( 'insufficient_balance', __( 'Requested amount exceeds your available balance.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$method = $vendor->get_payment_method();
		$data   = $vendor->get_payment_data();
		if ( empty( $method ) || empty( $data ) ) {
			return new WP_Error( 'no_payment_method', __( 'Add a payment method before requesting a withdrawal.', 'storeengine' ), [ 'status' => 400 ] );
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert( self::table(), [
			'user_id'        => $user_id,
			'amount'         => $amount,
			'status'         => 'pending',
			'payment_method' => $method,
			'payment_data'   => wp_json_encode( $data ),
			'vendor_note'    => sanitize_textarea_field( $note ),
			'requested_at'   => current_time( 'mysql', 1 ),
		] );
		// phpcs:enable

		if ( ! $inserted ) {
			return new WP_Error( 'create_failed', __( 'Could not create withdrawal request.', 'storeengine' ), [ 'status' => 500 ] );
		}

		$id = (int) $wpdb->insert_id;
		do_action( 'storeengine/multi_vendor/withdrawal_requested', $id, $user_id, $amount );

		return new WP_REST_Response( $this->fetch( $id ), 201 );
	}

	/**
	 * Allowed forward transitions for withdrawal state. `paid` is terminal —
	 * once a payout has been issued, the admin can't flip back to pending
	 * (which would re-fire status-changed listeners and risk double-payouts
	 * by gateway hooks). Reversal of a mistaken `paid` must go through a
	 * separate refund flow that's auditable, not a quiet status edit.
	 */
	const WITHDRAWAL_TRANSITIONS = [
		'pending'   => [ 'approved', 'rejected', 'cancelled' ],
		'approved'  => [ 'paid', 'rejected', 'cancelled' ],
		'rejected'  => [ 'pending' ],
		'cancelled' => [ 'pending' ],
		'paid'      => [], // terminal — no admin-side back-out
	];

	public function update_status( WP_REST_Request $request ) {
		global $wpdb;

		$id     = (int) $request['id'];
		$status = sanitize_key( (string) $request->get_param( 'status' ) );
		$note   = (string) $request->get_param( 'admin_note' );

		$allowed = [ 'pending', 'approved', 'rejected', 'paid', 'cancelled' ];
		if ( ! in_array( $status, $allowed, true ) ) {
			return new WP_Error( 'bad_status', __( 'Invalid status.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$row = $this->fetch( $id );
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'Withdrawal not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$from = (string) ( $row['status'] ?? 'pending' );
		// Idempotent re-apply of the same status is allowed (some admin UIs
		// re-submit on save) and doesn't re-fire the hook below.
		if ( $from !== $status ) {
			$allowed_next = self::WITHDRAWAL_TRANSITIONS[ $from ] ?? [];
			if ( ! in_array( $status, $allowed_next, true ) ) {
				return new WP_Error(
					'bad_transition',
					/* translators: 1: from status, 2: to status */
					sprintf( __( 'Cannot change status from %1$s to %2$s.', 'storeengine' ), $from, $status ),
					[ 'status' => 409 ]
				);
			}
		}

		$update = [
			'status'       => $status,
			'admin_note'   => sanitize_textarea_field( $note ),
			'processed_at' => current_time( 'mysql', 1 ),
			'processed_by' => (int) get_current_user_id(),
		];

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$wpdb->update( self::table(), $update, [ 'id' => $id ] );
		// phpcs:enable

		// Only fire the status-changed action on actual transitions —
		// reapplying the same status (idempotent save) must not re-fire
		// payout-issuing listeners hooked to this action.
		if ( $from !== $status ) {
			do_action( 'storeengine/multi_vendor/withdrawal_status_changed', $id, $status, $from );
		}

		return new WP_REST_Response( $this->fetch( $id ) );
	}

	/**
	 * Admin-initiated payout of a custom amount to a vendor.
	 *
	 * Records a `paid` withdrawal directly (no vendor request needed), capped at
	 * the vendor's available balance. Because Balance::for_vendor() treats paid
	 * rows as held, the amount is debited immediately and the remaining balance
	 * stays available for a later payout — so a large balance can be settled in
	 * instalments.
	 */
	public function admin_pay( WP_REST_Request $request ) {
		global $wpdb;

		$vendor_id = (int) $request->get_param( 'vendor_id' );
		$amount    = round( (float) $request->get_param( 'amount' ), 2 );
		$note      = (string) $request->get_param( 'admin_note' );

		if ( $vendor_id <= 0 ) {
			return new WP_Error( 'invalid_vendor', __( 'A valid vendor is required.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$user = get_user_by( 'id', $vendor_id );
		if ( ! $user || ! in_array( Role::ROLE, (array) $user->roles, true ) ) {
			return new WP_Error( 'not_a_vendor', __( 'That user is not a vendor.', 'storeengine' ), [ 'status' => 404 ] );
		}

		if ( $amount <= 0 ) {
			return new WP_Error( 'invalid_amount', __( 'Amount must be greater than zero.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$balance = Balance::for_vendor( $vendor_id );
		if ( $amount > $balance ) {
			return new WP_Error( 'insufficient_balance', __( 'Payout amount exceeds the vendor’s available balance.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$vendor = new VendorEntity( $vendor_id );
		$data   = $vendor->get_payment_data();
		$now    = current_time( 'mysql', 1 );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery
		$inserted = $wpdb->insert( self::table(), [
			'user_id'        => $vendor_id,
			'amount'         => $amount,
			'status'         => 'paid',
			'payment_method' => $vendor->get_payment_method() ?: 'manual',
			'payment_data'   => wp_json_encode( is_array( $data ) ? $data : [] ),
			'vendor_note'    => '',
			'admin_note'     => sanitize_textarea_field( $note ),
			'requested_at'   => $now,
			'processed_at'   => $now,
			'processed_by'   => (int) get_current_user_id(),
		] );
		// phpcs:enable

		if ( ! $inserted ) {
			return new WP_Error( 'create_failed', __( 'Could not record the payout.', 'storeengine' ), [ 'status' => 500 ] );
		}

		$id = (int) $wpdb->insert_id;

		// Guard the check-then-insert race: two concurrent admin payouts could
		// each pass the balance check above and together overdraw. Re-verify that
		// total holds don't exceed lifetime earnings; if they do, roll back.
		if ( Balance::held_total( $vendor_id ) > Balance::lifetime_earned( $vendor_id ) ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery
			$wpdb->delete( self::table(), [ 'id' => $id ] );
			return new WP_Error( 'insufficient_balance', __( 'Payout amount exceeds the vendor’s available balance.', 'storeengine' ), [ 'status' => 409 ] );
		}

		do_action( 'storeengine/multi_vendor/withdrawal_status_changed', $id, 'paid', $vendor_id );
		do_action( 'storeengine/multi_vendor/payout_recorded', $vendor_id, $amount );

		return new WP_REST_Response( $this->fetch( $id ), 201 );
	}

	/**
	 * A specific vendor's available balance (admin view) for the payout UI.
	 */
	public function get_vendor_balance( WP_REST_Request $request ) {
		$vendor_id = (int) $request->get_param( 'vendor_id' );
		if ( $vendor_id <= 0 ) {
			return new WP_Error( 'invalid_vendor', __( 'A valid vendor is required.', 'storeengine' ), [ 'status' => 400 ] );
		}

		return new WP_REST_Response( [
			'balance'  => Balance::for_vendor( $vendor_id ),
			'lifetime' => Balance::lifetime_earned( $vendor_id ),
			'paid'     => Balance::paid_total( $vendor_id ),
		] );
	}

	public function get_payment_method() {
		$vendor = new VendorEntity( (int) get_current_user_id() );
		return new WP_REST_Response( [
			'method' => $vendor->get_payment_method() ?: 'paypal',
			'data'   => (object) $vendor->get_payment_data(),
		] );
	}

	public function update_payment_method( WP_REST_Request $request ) {
		$vendor = new VendorEntity( (int) get_current_user_id() );
		$method = sanitize_key( (string) $request->get_param( 'method' ) );

		$enabled = (array) Settings::get( 'enabled_payment_methods', [ 'paypal', 'bank' ] );
		if ( ! in_array( $method, $enabled, true ) ) {
			return new WP_Error( 'method_disabled', __( 'That payment method is not currently enabled.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$data = (array) $request->get_param( 'data' );
		$vendor->set_payment_method( $method );
		$vendor->set_payment_data( $data );
		$vendor->save();

		return new WP_REST_Response( [
			'method' => $vendor->get_payment_method(),
			'data'   => (object) $vendor->get_payment_data(),
		] );
	}

	public function get_balance() {
		$user_id = (int) get_current_user_id();
		return new WP_REST_Response( [
			'balance'  => Balance::for_vendor( $user_id ),
			'lifetime' => Balance::lifetime_earned( $user_id ),
			'paid'     => Balance::paid_total( $user_id ),
			'minimum'  => (float) Settings::get( 'min_withdraw_amount', 0 ),
		] );
	}

	protected function fetch( int $id ): ?array {
		global $wpdb;
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', self::table(), $id ), ARRAY_A );
		// phpcs:enable
		return $row ? $this->shape( $row ) : null;
	}

	protected function shape( array $row ): array {
		$user           = get_user_by( 'id', (int) $row['user_id'] );
		$row['amount']  = (float) $row['amount'];
		$row['user_email']   = $user ? $user->user_email : '';
		$row['user_login']   = $user ? $user->user_login : '';
		$row['display_name'] = $user ? $user->display_name : '';
		$decoded = json_decode( (string) ( $row['payment_data'] ?? '' ), true );
		$row['payment_data'] = is_array( $decoded ) ? $decoded : [];
		return $row;
	}
}
