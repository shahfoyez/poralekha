<?php
/**
 * Current-user-scoped REST controller for the headless customer dashboard.
 *
 * All routes resolve the user via get_current_user_id() so they work with
 * any WP auth mechanism (JWT, cookies, application passwords).
 *
 * @package StoreEngine\API
 */

namespace StoreEngine\API;

use StoreEngine\Classes\DownloadPermissionRepository;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order as StoreEngineOrder;
use StoreEngine\Classes\OrderCollection;
use StoreEngine\Classes\UrlPresigner;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Me extends AbstractRestApiController {

	protected $rest_base = 'me';

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );

		// `customer_download` is our dl_type. UrlPresigner has already verified
		// the signature + expiry by the time this fires; here we validate the
		// permission row (ownership, remaining, expiry), decrement the counter,
		// and return the file path. DownloadHandler streams it from there.
		add_filter( 'storeengine/secure_downloads/customer_download/file_data', [ __CLASS__, 'resolve_customer_download_file' ], 10, 3 );
	}

	public function register_routes() {
		// /me  — profile + overview, update profile.
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_me' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_me' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
		] );

		// /me/password
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/password', [
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'change_password' ],
			'permission_callback' => [ $this, 'permission_check' ],
			'args'                => [
				'current_password' => [ 'type' => 'string', 'required' => true ],
				'new_password'     => [ 'type' => 'string', 'required' => true ],
			],
		] );

		// /me/menu  — dashboard sidebar (extensible via filter).
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/menu', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_menu' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		// /me/orders
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/orders', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'list_orders' ],
			'permission_callback' => [ $this, 'permission_check' ],
			'args'                => [
				'page'     => [ 'type' => 'integer', 'default' => 1 ],
				'per_page' => [ 'type' => 'integer', 'default' => 10 ],
				'status'   => [ 'type' => 'string' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/orders/(?P<id>[\d]+)', [
			'args'                => [
				'id' => [ 'type' => 'integer' ],
			],
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_order' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/orders/(?P<id>[\d]+)/cancel', [
			'args'                => [
				'id' => [ 'type' => 'integer' ],
			],
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'cancel_order' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/orders/(?P<id>[\d]+)/pay', [
			'args'                => [
				'id' => [ 'type' => 'integer' ],
			],
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'pay_order' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/orders/(?P<id>[\d]+)/invoice', [
			'args'                => [
				'id' => [ 'type' => 'integer' ],
			],
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_invoice' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		// /me/downloads
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/downloads', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'list_downloads' ],
			'permission_callback' => [ $this, 'permission_check' ],
			'args'                => [
				'page'     => [ 'type' => 'integer', 'default' => 1 ],
				'per_page' => [ 'type' => 'integer', 'default' => 20 ],
			],
		] );

		// /me/downloads/{permission_id}/sign — returns a short-lived signed URL.
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/downloads/(?P<permission_id>[\d]+)/sign', [
			'args'                => [
				'permission_id' => [ 'type' => 'integer' ],
				'expires_in'    => [ 'type' => 'integer', 'default' => 300 ],
			],
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'sign_download' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		// /me/addresses
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/addresses', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'get_addresses' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/addresses/(?P<type>billing|shipping)', [
			'args'                => [
				'type' => [ 'type' => 'string' ],
			],
			'methods'             => WP_REST_Server::EDITABLE,
			'callback'            => [ $this, 'update_address' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		// /me/payment-methods
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/payment-methods', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ $this, 'list_payment_methods' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/payment-methods/(?P<id>[\d]+)', [
			'args'                => [
				'id' => [ 'type' => 'integer' ],
			],
			'methods'             => WP_REST_Server::DELETABLE,
			'callback'            => [ $this, 'delete_payment_method' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/payment-methods/(?P<id>[\d]+)/default', [
			'args'                => [
				'id' => [ 'type' => 'integer' ],
			],
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'set_default_payment_method' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );

		// /me/notifications
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/notifications', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_notifications' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_notifications' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
		] );

		// /me/privacy
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/privacy', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_privacy' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_privacy' ],
				'permission_callback' => [ $this, 'permission_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/privacy/erase-request', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ $this, 'request_personal_data_erasure' ],
			'permission_callback' => [ $this, 'permission_check' ],
		] );
	}

	/**
	 * Single permission gate: must be logged in. Per-resource ownership is
	 * enforced inside each handler.
	 */
	public function permission_check() {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'storeengine_rest_not_logged_in', __( 'You must be logged in.', 'storeengine' ), [ 'status' => 401 ] );
		}

		return true;
	}

	// -------------------------------------------------------------------
	// Profile
	// -------------------------------------------------------------------

	public function get_me( WP_REST_Request $request ) {
		$user_id  = get_current_user_id();
		$customer = Helper::get_customer( $user_id );

		if ( ! $customer || ! $customer->get_id() ) {
			return new WP_Error( 'storeengine_rest_customer_not_found', __( 'Customer not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$total_spent = (float) OrderCollection::get_total_spent( $user_id );
		$repo        = ( new DownloadPermissionRepository() )->with_pagination( 1, 1 );

		return rest_ensure_response( [
			'id'                 => $customer->get_id(),
			'username'           => $customer->get_username(),
			'email'              => $customer->get_email(),
			'email_hash'         => md5( $customer->get_email() ),
			'first_name'         => $customer->get_first_name(),
			'last_name'          => $customer->get_last_name(),
			'display_name'       => $customer->get_name(),
			'avatar_url'         => get_avatar_url( $user_id ),
			'user_registered'    => $customer->get_user_registered() ? $customer->get_user_registered()->format( 'Y-m-d H:i:s' ) : null,
			'subscribe_to_email' => $customer->has_subscribe_to_email(),
			'stats'              => [
				'total_orders'    => (int) $customer->get_total_orders(),
				'total_spent'     => $total_spent,
				'total_downloads' => (int) $repo->total_count_by_customer_id( $user_id ),
			],
			'has_billing_address'  => (bool) $customer->get_billing_address_1(),
			'has_shipping_address' => (bool) $customer->get_shipping_address_1(),
		] );
	}

	public function update_me( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$body    = (array) $request->get_json_params();

		$updates = [ 'ID' => $user_id ];

		if ( isset( $body['first_name'] ) ) {
			$updates['first_name'] = sanitize_text_field( $body['first_name'] );
		}
		if ( isset( $body['last_name'] ) ) {
			$updates['last_name'] = sanitize_text_field( $body['last_name'] );
		}
		if ( isset( $body['display_name'] ) ) {
			$updates['display_name'] = sanitize_text_field( $body['display_name'] );
		}
		if ( isset( $body['email'] ) ) {
			$email = sanitize_email( $body['email'] );
			if ( ! is_email( $email ) ) {
				return new WP_Error( 'storeengine_rest_invalid_email', __( 'Invalid email address.', 'storeengine' ), [ 'status' => 400 ] );
			}
			$existing = email_exists( $email );
			if ( $existing && (int) $existing !== $user_id ) {
				return new WP_Error( 'storeengine_rest_email_taken', __( 'Email address already in use.', 'storeengine' ), [ 'status' => 409 ] );
			}
			$updates['user_email'] = $email;
		}

		$result = wp_update_user( $updates );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		if ( array_key_exists( 'subscribe_to_email', $body ) ) {
			$customer = Helper::get_customer( $user_id );
			$customer->set_subscribe_to_email( (bool) $body['subscribe_to_email'] );
			$customer->save();
		}

		return $this->get_me( $request );
	}

	public function change_password( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$current = (string) $request->get_param( 'current_password' );
		$next    = (string) $request->get_param( 'new_password' );

		if ( strlen( $next ) < 8 ) {
			return new WP_Error( 'storeengine_rest_weak_password', __( 'Password must be at least 8 characters.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$user = get_userdata( $user_id );
		if ( ! $user instanceof WP_User || ! wp_check_password( $current, $user->user_pass, $user_id ) ) {
			return new WP_Error( 'storeengine_rest_bad_password', __( 'Current password is incorrect.', 'storeengine' ), [ 'status' => 400 ] );
		}

		wp_set_password( $next, $user_id );

		return rest_ensure_response( [ 'updated' => true ] );
	}

	// -------------------------------------------------------------------
	// Menu (extensible)
	// -------------------------------------------------------------------

	public function get_menu( WP_REST_Request $request ) {
		$default = [
			[ 'slug' => 'dashboard',       'route' => '',                'label' => __( 'Dashboard', 'storeengine' ),       'icon' => 'layout',  'order' => 0 ],
			[ 'slug' => 'orders',          'route' => 'orders',          'label' => __( 'Orders', 'storeengine' ),          'icon' => 'box',     'order' => 10 ],
			[ 'slug' => 'downloads',       'route' => 'downloads',       'label' => __( 'Downloads', 'storeengine' ),       'icon' => 'download','order' => 20 ],
			[ 'slug' => 'addresses',       'route' => 'addresses',       'label' => __( 'Addresses', 'storeengine' ),       'icon' => 'home',    'order' => 30 ],
			[ 'slug' => 'payment-methods', 'route' => 'payment-methods', 'label' => __( 'Payment methods', 'storeengine' ), 'icon' => 'card',    'order' => 40 ],
			[ 'slug' => 'profile',         'route' => 'profile',         'label' => __( 'Account', 'storeengine' ),         'icon' => 'user',    'order' => 50 ],
		];

		// Drop payment-methods if no gateway supports tokenization.
		$supports_tokens = false;
		foreach ( Helper::get_payment_gateways()->get_available_payment_gateways() as $gateway ) {
			if ( $gateway->supports( 'add_payment_method' ) || $gateway->supports( 'tokenization' ) ) {
				$supports_tokens = true;
				break;
			}
		}
		if ( ! $supports_tokens ) {
			$default = array_values( array_filter( $default, fn( $i ) => 'payment-methods' !== $i['slug'] ) );
		}

		/**
		 * Filter the headless dashboard menu. Pro addons (licenses, returns,
		 * subscriptions, etc.) inject their own items here.
		 *
		 * @param array $items Menu items.
		 * @param int   $user_id Current user id.
		 */
		$items = apply_filters( 'storeengine/rest/me/menu_items', $default, get_current_user_id() );

		usort( $items, fn( $a, $b ) => ( $a['order'] ?? 0 ) <=> ( $b['order'] ?? 0 ) );

		return rest_ensure_response( $items );
	}

	// -------------------------------------------------------------------
	// Orders
	// -------------------------------------------------------------------

	public function list_orders( WP_REST_Request $request ) {
		$user_id  = get_current_user_id();
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$status   = $request->get_param( 'status' );

		$where = [
			'relation' => 'AND',
			[ 'key' => 'type', 'value' => 'order' ],
			[ 'key' => 'customer_id', 'value' => $user_id, 'type' => 'NUMERIC' ],
		];

		if ( $status && ! in_array( $status, [ 'all', 'any', 'draft' ], true ) ) {
			$where[] = [ 'key' => 'status', 'value' => $status ];
		} else {
			$where[] = [ 'key' => 'status', 'value' => 'draft', 'compare' => '!=' ];
		}

		$query = new OrderCollection( [
			'per_page' => $per_page,
			'page'     => $page,
			'where'    => $where,
		] );

		$data = [];
		foreach ( $query->get_results() as $order ) {
			$data[] = $this->format_order_summary( $order );
		}

		return $this->prepare_query_response( $data, $query, $request );
	}

	public function get_order( WP_REST_Request $request ) {
		$order = $this->load_owned_order( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$response = rest_ensure_response( $this->format_order_full( $order ) );

		/**
		 * Allow Pro addons (returns, installment plans) to inject extra fields
		 * or actions into the order response. Mirrors the WP-side
		 * `storeengine/dashboard/order/actions` filter.
		 */
		do_action( 'storeengine/rest/me/order_response', $response, $order, $request );

		return $response;
	}

	public function cancel_order( WP_REST_Request $request ) {
		$order = $this->load_owned_order( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		$cancellable = apply_filters( 'storeengine/rest/me/cancellable_statuses', [ 'pending_payment', 'on_hold' ], $order );
		if ( ! in_array( $order->get_status(), $cancellable, true ) ) {
			return new WP_Error( 'storeengine_rest_not_cancellable', __( 'This order can no longer be cancelled.', 'storeengine' ), [ 'status' => 409 ] );
		}

		try {
			$order->update_status( 'cancelled', __( 'Cancelled by customer from headless dashboard.', 'storeengine' ) );
		} catch ( StoreEngineException $e ) {
			return new WP_Error( 'storeengine_rest_cancel_failed', $e->getMessage(), [ 'status' => 500 ] );
		}

		return rest_ensure_response( $this->format_order_full( $order ) );
	}

	public function pay_order( WP_REST_Request $request ) {
		$order = $this->load_owned_order( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		if ( ! $order->needs_payment() ) {
			return new WP_Error( 'storeengine_rest_not_payable', __( 'This order is already paid.', 'storeengine' ), [ 'status' => 409 ] );
		}

		// Return the order's pay URL — the Checkout API at /checkout/pay-order
		// handles the actual payment flow; the storefront redirects there.
		$pay_url = $order->get_checkout_payment_url();

		return rest_ensure_response( [
			'order_id' => $order->get_id(),
			'pay_url'  => $pay_url,
			'amount'   => (float) $order->get_total_amount(),
			'currency' => $order->get_currency(),
		] );
	}

	public function get_invoice( WP_REST_Request $request ) {
		$order = $this->load_owned_order( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $order ) ) {
			return $order;
		}

		// Default invoice URL = order's view URL. Invoice addons (if any) can
		// override via filter to return a PDF URL or signed link.
		$invoice_url = apply_filters( 'storeengine/rest/me/invoice_url', $order->get_view_order_url(), $order );

		return rest_ensure_response( [
			'order_id'    => $order->get_id(),
			'invoice_url' => $invoice_url,
		] );
	}

	/**
	 * @return StoreEngineOrder|WP_Error
	 */
	protected function load_owned_order( int $id ) {
		if ( $id <= 0 ) {
			return new WP_Error( 'storeengine_rest_invalid_id', __( 'Invalid order id.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$order = Helper::get_order( $id );
		if ( is_wp_error( $order ) ) {
			return $order;
		}
		if ( ! $order ) {
			return new WP_Error( 'storeengine_rest_order_not_found', __( 'Order not found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		if ( (int) $order->get_customer_id() !== get_current_user_id() ) {
			// Return 404 (not 403) to avoid leaking existence.
			return new WP_Error( 'storeengine_rest_order_not_found', __( 'Order not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		return $order;
	}

	protected function format_order_summary( StoreEngineOrder $order ): array {
		return [
			'id'               => $order->get_id(),
			'number'           => $order->get_id(),
			'status'           => $order->get_status(),
			'paid_status'      => $order->get_paid_status(),
			'currency'         => $order->get_currency(),
			'total'            => (float) $order->get_total_amount(),
			'item_count'       => count( $order->get_items() ),
			'date_created_gmt' => $this->date_as_string( $order->get_date_created_gmt() ),
			'date_paid_gmt'    => $this->date_as_string( $order->get_date_paid_gmt() ),
			'needs_payment'    => $order->needs_payment(),
		];
	}

	protected function format_order_full( StoreEngineOrder $order ): array {
		$items = [];
		foreach ( $order->get_items() as $item ) {
			$items[] = [
				'id'         => $item->get_id(),
				'name'       => $item->get_name(),
				'product_id' => method_exists( $item, 'get_product_id' ) ? $item->get_product_id() : null,
				'quantity'   => method_exists( $item, 'get_quantity' ) ? $item->get_quantity() : null,
				'subtotal'   => method_exists( $item, 'get_subtotal' ) ? (float) $item->get_subtotal() : null,
				'total'      => method_exists( $item, 'get_total' ) ? (float) $item->get_total() : null,
				'type'       => $item->get_type(),
			];
		}

		$billing  = $order->get_address( 'billing' );
		$shipping = $order->get_address( 'shipping' );
		unset( $billing['address_type'], $shipping['address_type'] );

		return array_merge(
			$this->format_order_summary( $order ),
			[
				'subtotal'             => (float) $order->get_subtotal(),
				'tax_total'            => (float) $order->get_tax_amount(),
				'shipping_total'       => (float) $order->get_shipping_total(),
				'discount_total'       => (float) $order->get_total_discount(),
				'refunded_total'       => (float) $order->get_total_refunded(),
				'payment_method'       => $order->get_payment_method(),
				'payment_method_title' => $order->get_payment_method_title(),
				'transaction_id'       => $order->get_transaction_id(),
				'customer_note'        => $order->get_customer_note(),
				'billing_address'      => $billing,
				'shipping_address'     => $shipping,
				'items'                => $items,
				'downloads'            => array_values( $order->get_downloadable_items() ),
			]
		);
	}

	// -------------------------------------------------------------------
	// Downloads
	// -------------------------------------------------------------------

	public function list_downloads( WP_REST_Request $request ) {
		global $wpdb;

		$user_id  = get_current_user_id();
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$table = $wpdb->prefix . 'storeengine_downloadable_product_permissions';
		//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared (%d) query on a custom StoreEngine permissions table; $table is $wpdb->prefix + a literal; not cacheable.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM $table WHERE user_id = %d", $user_id ) );
		$rows  = $wpdb->get_results( $wpdb->prepare(
			"SELECT id, order_id, product_id, download_id, downloads_remaining, download_count, access_granted, access_expires
			 FROM $table
			 WHERE user_id = %d
			 ORDER BY id DESC
			 LIMIT %d OFFSET %d",
			$user_id, $per_page, $offset
		) );
		//phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching

		$data = [];
		foreach ( (array) $rows as $row ) {
			$file_name = $this->resolve_download_filename( (int) $row->product_id, (string) $row->download_id );

			$data[] = [
				'permission_id'       => (int) $row->id,
				'order_id'            => (int) $row->order_id,
				'product_id'          => (int) $row->product_id,
				'product_name'        => get_the_title( (int) $row->product_id ),
				'download_id'         => (string) $row->download_id,
				'download_name'       => $file_name,
				'downloads_remaining' => is_null( $row->downloads_remaining ) ? null : (int) $row->downloads_remaining,
				'download_count'      => (int) $row->download_count,
				'access_granted'      => $row->access_granted ?: null,
				'access_expires'      => $row->access_expires ?: null,
				'is_expired'          => $row->access_expires && strtotime( $row->access_expires ) < time(),
			];
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', max( 1, (int) ceil( $total / $per_page ) ) );

		return $response;
	}

	protected function resolve_download_filename( int $product_id, string $download_id ): string {
		$files = get_post_meta( $product_id, '_storeengine_product_downloadable_files', true );
		if ( empty( $files ) ) {
			return '';
		}
		$files = maybe_unserialize( $files );
		if ( ! is_array( $files ) ) {
			return '';
		}
		foreach ( $files as $file ) {
			if ( ( $file['id'] ?? null ) === $download_id ) {
				return (string) ( $file['name'] ?? '' );
			}
		}

		return '';
	}

	public function sign_download( WP_REST_Request $request ) {
		global $wpdb;

		$user_id       = get_current_user_id();
		$permission_id = (int) $request->get_param( 'permission_id' );
		$expires_in    = (int) ( $request->get_param( 'expires_in' ) ?: 300 );
		$expires_in    = max( 30, min( 3600, $expires_in ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared (%d) single-row read on a custom StoreEngine permissions table; not cacheable.
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, user_id, order_id, product_id, download_id, downloads_remaining, access_expires
			 FROM {$wpdb->prefix}storeengine_downloadable_product_permissions
			 WHERE id = %d",
			$permission_id
		) );

		if ( ! $row ) {
			return new WP_Error( 'storeengine_rest_download_not_found', __( 'Download not found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		if ( (int) $row->user_id !== $user_id ) {
			return new WP_Error( 'storeengine_rest_download_not_found', __( 'Download not found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		if ( ! is_null( $row->downloads_remaining ) && (int) $row->downloads_remaining <= 0 ) {
			return new WP_Error( 'storeengine_rest_download_exhausted', __( 'No remaining downloads for this file.', 'storeengine' ), [ 'status' => 410 ] );
		}
		if ( $row->access_expires && strtotime( $row->access_expires ) < time() ) {
			return new WP_Error( 'storeengine_rest_download_expired', __( 'Download access has expired.', 'storeengine' ), [ 'status' => 410 ] );
		}

		$args = [
			'se_secure_dl' => (int) $row->product_id,
			'resource'     => (int) $row->id,
			'type'         => 'customer_download',
		];

		try {
			$url = UrlPresigner::init()->signUrl( add_query_arg( $args, home_url( '/' ) ), $expires_in );
		} catch ( StoreEngineException $e ) {
			return new WP_Error( 'storeengine_rest_sign_failed', $e->getMessage(), [ 'status' => 500 ] );
		}

		return rest_ensure_response( [
			'url'           => $url,
			'expires_in'    => $expires_in,
			'expires_at'    => gmdate( 'Y-m-d\TH:i:s\Z', time() + $expires_in ),
			'file_name'     => $this->resolve_download_filename( (int) $row->product_id, (string) $row->download_id ),
		] );
	}

	/**
	 * Hooked at `storeengine/secure_downloads/customer_download/file_data`.
	 *
	 * Called from DownloadHandler::handle_secure_download() AFTER the URL
	 * signature has been verified. Validates the permission row one more time,
	 * atomically decrements remaining, increments count, and returns the
	 * file path/name for DownloadHandler::download() to stream.
	 *
	 * @param array $file_data Default empty.
	 * @param mixed $resource  Permission id from the signed URL's `resource` arg.
	 */
	public static function resolve_customer_download_file( $file_data, $resource, $product ) {
		global $wpdb;

		$permission_id = absint( $resource );
		if ( ! $permission_id ) {
			return $file_data;
		}

		$table = $wpdb->prefix . 'storeengine_downloadable_product_permissions';
		//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared (%d) query on a custom StoreEngine permissions table; $table is $wpdb->prefix + a literal; not cacheable.
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT id, user_id, order_id, product_id, download_id, downloads_remaining, access_expires FROM $table WHERE id = %d",
			$permission_id
		) );
		//phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! $row ) {
			return new WP_Error( 'invalid_download', __( 'Download not found.', 'storeengine' ), 404 );
		}
		if ( $product && (int) $product->get_id() !== (int) $row->product_id ) {
			return new WP_Error( 'invalid_download', __( 'Product mismatch.', 'storeengine' ), 403 );
		}
		if ( $row->access_expires && strtotime( $row->access_expires ) < time() ) {
			return new WP_Error( 'invalid_download', __( 'Download access has expired.', 'storeengine' ), 410 );
		}
		if ( ! is_null( $row->downloads_remaining ) && (int) $row->downloads_remaining <= 0 ) {
			return new WP_Error( 'invalid_download', __( 'No remaining downloads.', 'storeengine' ), 410 );
		}

		// Resolve the file from product downloadables.
		$files = get_post_meta( (int) $row->product_id, '_storeengine_product_downloadable_files', true );
		$files = is_array( $files ) ? $files : (array) maybe_unserialize( $files );
		$file  = null;
		foreach ( $files as $candidate ) {
			if ( ( $candidate['id'] ?? null ) === $row->download_id ) {
				$file = $candidate;
				break;
			}
		}
		if ( ! $file || empty( $file['file'] ) ) {
			return new WP_Error( 'invalid_download', __( 'No file defined.', 'storeengine' ), 404 );
		}

		// Atomic decrement — only fires on initial 200 responses; range
		// requests within the same signed-URL window won't hit this twice
		// because they're served by the streaming code, not re-entered here.
		if ( ! is_null( $row->downloads_remaining ) ) {
			//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared (%d) query on a custom StoreEngine permissions table; $table is $wpdb->prefix + a literal; not cacheable.
			$wpdb->query( $wpdb->prepare(
				"UPDATE $table
				 SET downloads_remaining = downloads_remaining - 1, download_count = download_count + 1
				 WHERE id = %d AND downloads_remaining > 0",
				$permission_id
			) );
			//phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			//phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared (%d) query on a custom StoreEngine permissions table; $table is $wpdb->prefix + a literal; not cacheable.
			$wpdb->query( $wpdb->prepare(
				"UPDATE $table SET download_count = download_count + 1 WHERE id = %d",
				$permission_id
			) );
			//phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		return [
			'file_path' => (string) $file['file'],
			'file_name' => (string) ( $file['name'] ?? basename( $file['file'] ) ),
		];
	}

	// -------------------------------------------------------------------
	// Addresses
	// -------------------------------------------------------------------

	public function get_addresses( WP_REST_Request $request ) {
		$user_id  = get_current_user_id();
		$customer = Helper::get_customer( $user_id );

		return rest_ensure_response( [
			'billing'  => $this->extract_address( $customer, 'billing' ),
			'shipping' => $this->extract_address( $customer, 'shipping' ),
		] );
	}

	public function update_address( WP_REST_Request $request ) {
		$user_id  = get_current_user_id();
		$customer = Helper::get_customer( $user_id );
		$type     = $request->get_param( 'type' );
		$fields   = (array) $request->get_json_params();

		$allowed = [
			'first_name', 'last_name', 'company', 'phone', 'email',
			'address_1', 'address_2', 'city', 'state', 'country', 'postcode',
		];

		foreach ( $allowed as $field ) {
			if ( array_key_exists( $field, $fields ) ) {
				$setter = "set_{$type}_{$field}";
				if ( is_callable( [ $customer, $setter ] ) ) {
					$customer->{$setter}( sanitize_text_field( (string) $fields[ $field ] ) );
				}
			}
		}

		$customer->save();

		return rest_ensure_response( $this->extract_address( $customer, $type ) );
	}

	protected function extract_address( $customer, string $type ): array {
		$fields  = [ 'first_name', 'last_name', 'company', 'phone', 'email', 'address_1', 'address_2', 'city', 'state', 'country', 'postcode' ];
		$address = [];
		foreach ( $fields as $field ) {
			$getter = "get_{$type}_{$field}";
			if ( is_callable( [ $customer, $getter ] ) ) {
				$address[ $field ] = $customer->{$getter}();
			}
		}

		return $address;
	}

	// -------------------------------------------------------------------
	// Payment methods
	// -------------------------------------------------------------------

	public function list_payment_methods( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$tokens  = function_exists( 'storeengine_get_customer_payment_tokens' )
			? storeengine_get_customer_payment_tokens( $user_id )
			: [];

		// Generic shape — concrete fields depend on which gateway issued the
		// token. Storefront should render whatever fields the gateway returns.
		$data = [];
		foreach ( (array) $tokens as $token ) {
			$data[] = apply_filters( 'storeengine/rest/me/payment_method', [
				'id'           => is_callable( [ $token, 'get_id' ] ) ? $token->get_id() : ( $token->id ?? null ),
				'gateway'      => is_callable( [ $token, 'get_gateway_id' ] ) ? $token->get_gateway_id() : ( $token->gateway ?? null ),
				'display_name' => is_callable( [ $token, 'get_display_name' ] ) ? $token->get_display_name() : '',
				'is_default'   => is_callable( [ $token, 'is_default' ] ) ? (bool) $token->is_default() : false,
			], $token );
		}

		return rest_ensure_response( $data );
	}

	public function delete_payment_method( WP_REST_Request $request ) {
		do_action( 'storeengine/rest/me/delete_payment_method', (int) $request->get_param( 'id' ), get_current_user_id() );

		return rest_ensure_response( [ 'deleted' => true ] );
	}

	public function set_default_payment_method( WP_REST_Request $request ) {
		do_action( 'storeengine/rest/me/set_default_payment_method', (int) $request->get_param( 'id' ), get_current_user_id() );

		return rest_ensure_response( [ 'updated' => true ] );
	}

	// -------------------------------------------------------------------
	// Notifications + Privacy
	// -------------------------------------------------------------------

	public function get_notifications( WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		return rest_ensure_response( [
			'subscribe_to_email'  => (bool) get_user_meta( $user_id, 'storeengine_subscribe_to_email', true ),
			'order_email'         => 'no' !== get_user_meta( $user_id, 'storeengine_notify_order_email', true ),
			'marketing_email'     => (bool) get_user_meta( $user_id, 'storeengine_notify_marketing_email', true ),
		] );
	}

	public function update_notifications( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$body    = (array) $request->get_json_params();

		if ( array_key_exists( 'subscribe_to_email', $body ) ) {
			update_user_meta( $user_id, 'storeengine_subscribe_to_email', (bool) $body['subscribe_to_email'] ? 1 : 0 );
		}
		if ( array_key_exists( 'order_email', $body ) ) {
			update_user_meta( $user_id, 'storeengine_notify_order_email', $body['order_email'] ? 'yes' : 'no' );
		}
		if ( array_key_exists( 'marketing_email', $body ) ) {
			update_user_meta( $user_id, 'storeengine_notify_marketing_email', (bool) $body['marketing_email'] ? 1 : 0 );
		}

		return $this->get_notifications( $request );
	}

	public function get_privacy( WP_REST_Request $request ) {
		$user_id = get_current_user_id();

		return rest_ensure_response( [
			'data_sharing_consent' => (bool) get_user_meta( $user_id, 'storeengine_privacy_data_sharing', true ),
			'profiling_consent'    => (bool) get_user_meta( $user_id, 'storeengine_privacy_profiling', true ),
		] );
	}

	public function update_privacy( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$body    = (array) $request->get_json_params();

		if ( array_key_exists( 'data_sharing_consent', $body ) ) {
			update_user_meta( $user_id, 'storeengine_privacy_data_sharing', (bool) $body['data_sharing_consent'] ? 1 : 0 );
		}
		if ( array_key_exists( 'profiling_consent', $body ) ) {
			update_user_meta( $user_id, 'storeengine_privacy_profiling', (bool) $body['profiling_consent'] ? 1 : 0 );
		}

		return $this->get_privacy( $request );
	}

	public function request_personal_data_erasure( WP_REST_Request $request ) {
		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );
		if ( ! $user ) {
			return new WP_Error( 'storeengine_rest_user_not_found', __( 'User not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$request_id = wp_create_user_request( $user->user_email, 'remove_personal_data' );
		if ( is_wp_error( $request_id ) ) {
			return $request_id;
		}

		wp_send_user_request( $request_id );

		return rest_ensure_response( [
			'request_id' => $request_id,
			'status'     => 'requested',
		] );
	}
}
