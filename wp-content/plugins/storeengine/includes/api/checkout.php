<?php
/**
 * Core Checkout REST controller.
 *
 * Single REST surface used by:
 *   1. The traditional /checkout/ page (cookie-authed via X-WP-Nonce).
 *   2. The Embedded Checkout React app (cross-origin via publishable key + origin allow-list).
 *
 * The auth strategy is selected per-request: if the X-StoreEngine-Pk header is
 * present we delegate the permission check to the Embedded Checkout addon's
 * publishable-key middleware (via filter); otherwise we fall back to the
 * standard WordPress cookie/nonce check. This keeps the cross-origin path
 * intact while making the same routes consumable by the same-site vanilla-JS client.
 */

namespace StoreEngine\API;

use StoreEngine;
use StoreEngine\Classes\CheckoutService;
use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\CheckoutFields;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\PaymentUtil;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Checkout extends AbstractRestApiController {

	protected $rest_base = 'checkout';

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
		add_filter( 'rest_pre_serve_request', [ $self, 'maybe_send_cors_headers' ], 10, 4 );
	}

	public function register_routes() {
		$args_session = [
			'session_id' => [ 'type' => 'string', 'required' => false ],
		];

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/state', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_state' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => $args_session,
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/update', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'update_checkout' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => array_merge( $args_session, [
					'fields' => [ 'type' => 'object', 'required' => true ],
				] ),
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/place', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'place_order' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => array_merge( $args_session, [
					'fields'          => [ 'type' => 'object', 'required' => true ],
					'payment_method'  => [ 'type' => 'string', 'required' => true ],
					'payment_payload' => [ 'type' => 'object', 'required' => false ],
				] ),
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/states', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_states' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'country_code' => [ 'type' => 'string', 'required' => true ],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/pay-order', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'pay_order' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'order_id'        => [ 'type' => 'integer', 'required' => true ],
					'order_key'       => [ 'type' => 'string', 'required' => false ],
					'payment_method'  => [ 'type' => 'string', 'required' => true ],
					'payment_payload' => [ 'type' => 'object', 'required' => false ],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/payment-intent/(?P<gateway_id>[a-zA-Z0-9_-]+)', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_payment_intent' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => array_merge( $args_session, [
					'gateway_id' => [ 'type' => 'string', 'required' => true ],
					'order_id'   => [ 'type' => 'integer', 'required' => false ],
				] ),
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/coupon/apply', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'apply_coupon' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => array_merge( $args_session, [
					'coupon_code' => [ 'type' => 'string', 'required' => true ],
				] ),
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/coupon/remove', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'remove_coupon' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => array_merge( $args_session, [
					'coupon_code' => [ 'type' => 'string', 'required' => true ],
				] ),
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/check-contact', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'check_contact' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'email' => [ 'type' => 'string', 'required' => true ],
				],
			],
		] );
	}

	/**
	 * Dual-auth permission check.
	 *
	 *   - If `X-StoreEngine-Embed-Key` (or legacy `X-StoreEngine-Pk`) header is
	 *     present: the request originates from a cross-origin embed. We let the
	 *     embed-key middleware (registered by the Embeddable Checkout addon via
	 *     the `storeengine/checkout/publishable_key_auth` filter) decide.
	 *   - Otherwise: same-site request. We require the standard WP REST nonce.
	 */
	public function permission_callback( WP_REST_Request $request ) {
		$pk = $request->get_header( 'x_storeengine_embed_key' );
		if ( ! $pk ) {
			$pk = $request->get_header( 'x_storeengine_pk' ); // legacy
		}
		if ( ! $pk ) {
			$pk = $request->get_param( 'pk' );
		}

		if ( $pk ) {
			$result = apply_filters( 'storeengine/checkout/publishable_key_auth', null, $request );
			// Filter returns true (allow), WP_Error (deny), or null (no handler installed).
			if ( null === $result ) {
				return new WP_Error(
					'storeengine_checkout_pk_unsupported',
					__( 'Embed-key authentication is not active. Enable the Embeddable Checkout addon.', 'storeengine' ),
					[ 'status' => 401 ]
				);
			}
			return $result;
		}

		// Same-site path: require an authenticated REST request (nonce already validated by WP).
		// Allow guests on the public checkout flow — same as the legacy admin-ajax handler.
		return true;
	}

	/**
	 * Send CORS headers for our own routes only when the publishable-key auth path is in use.
	 */
	public function maybe_send_cors_headers( $served, $result, $request, $server ) {
		if ( ! ( $request instanceof WP_REST_Request ) ) {
			return $served;
		}

		$route = $request->get_route();
		if ( strpos( $route, '/' . $this->namespace . '/' . $this->rest_base ) !== 0 ) {
			return $served;
		}

		// Only the addon middleware sets this, and only on success.
		$origin = apply_filters( 'storeengine/checkout/cors_origin', null, $request );
		if ( $origin ) {
			header( 'Access-Control-Allow-Origin: ' . $origin );
			header( 'Vary: Origin' );
			header( 'Access-Control-Allow-Methods: GET, POST, OPTIONS' );
			header( 'Access-Control-Allow-Headers: Content-Type, X-StoreEngine-Embed-Key, X-StoreEngine-Pk, X-WP-Nonce' );
			header( 'Access-Control-Allow-Credentials: false' );
		}

		return $served;
	}

	// ---- Routes ------------------------------------------------------------

	public function get_state( WP_REST_Request $request ) {
		$prep = $this->prepare_request( $request );
		if ( is_wp_error( $prep ) ) {
			return $prep;
		}

		// Wrap the snapshot build so any exception (e.g. a null-date ->format(),
		// a null price object, or a hook throwing on a licensed/deployment
		// product) surfaces as a clean 500 JSON error instead of a fatal that
		// truncates an already-committed 200 response — which the embedded React
		// checkout would otherwise read as `null` and crash on with a cryptic
		// "Cannot read properties of null (reading 'declared_items')".
		try {
			return rest_ensure_response( $this->snapshot( $prep['session'] ) );
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'storeengine_checkout_state_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * "Does an account already use this email?" probe for the checkout contact
	 * field. Lets the UI warn the shopper — with a login link — the moment they
	 * enter an email that belongs to an existing account, instead of only
	 * failing at Place Order (create_customer() refuses to let a guest attach an
	 * order to someone else's account). Returns { exists, login_url }.
	 *
	 * The login_url is only populated when flagging a *different* account: a
	 * logged-in shopper entering their own email needs no login prompt (that
	 * request falls through to the normal update path server-side).
	 */
	public function check_contact( WP_REST_Request $request ) {
		$email = sanitize_email( (string) $request->get_param( 'email' ) );

		// Never confirm existence for a malformed address. Keeps this endpoint
		// from acting as a bulk validity oracle and mirrors the client, which
		// only calls it once the address is well-formed.
		if ( ! $email || ! is_email( $email ) ) {
			return rest_ensure_response( [
				'exists'    => false,
				'login_url' => '',
			] );
		}

		$user_id = email_exists( $email );
		$exists  = $user_id && get_current_user_id() !== (int) $user_id;

		return rest_ensure_response( [
			'exists'    => (bool) $exists,
			'login_url' => $exists ? storeengine_checkout_login_url( $email ) : '',
		] );
	}

	public function update_checkout( WP_REST_Request $request ) {
		$prep = $this->prepare_request( $request );
		if ( is_wp_error( $prep ) ) {
			return $prep;
		}

		$fields = (array) $request->get_param( 'fields' );

		// Fingerprint the totals BEFORE applying the change (the cart was already
		// calculated at request bootstrap). Compared after the recalc below so
		// ANY field that moves a total — shipping, tax, fees, addon-driven
		// surcharges, not just the address — flips `refresh` and re-renders the
		// summary. Without this, only address/shipping-method edits refreshed.
		$old_totals = self::totals_fingerprint( Helper::cart() );

		// Push the submitted address + shipping choice onto the cart and
		// recalculate, so shipping/tax reflect the current location.
		self::sync_cart_from_fields( $fields );

		$new_totals = self::totals_fingerprint( Helper::cart() );

		// CheckoutService::update_checkout returns the legacy "refresh" payload
		// shape (refresh, refresh_payment_methods, hash, …) that
		// CheckoutManager.js already understands.
		// Wrap in try/catch so any per-field validation exception thrown by
		// a custom hook surfaces as a clean 422 instead of an unhandled
		// PHP fatal — this endpoint runs on every debounced keystroke from
		// the React Quick Checkout while the shopper is typing.
		try {
			$response = CheckoutService::update_checkout( $fields );
		} catch ( \StoreEngine\Classes\Exceptions\StoreEngineException $e ) {
			return new WP_Error(
				$e->get_wp_error_code() ?: 'storeengine_checkout_update_failed',
				$e->getMessage(),
				[ 'status' => 422 ]
			);
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'storeengine_checkout_update_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}

		// Re-render the summary whenever the totals moved, regardless of which
		// field caused it (belt-and-suspenders over the address diff inside
		// CheckoutService::update_checkout).
		if ( $old_totals !== $new_totals ) {
			$response['refresh'] = true;
		}

		// Same protection as get_state(): never let a snapshot-build failure
		// truncate the /update response (which carries the refresh payload the
		// checkout depends on). If it fails, omit the snapshot and skip the
		// re-render rather than 500-ing the whole update.
		try {
			$response['snapshot'] = $this->snapshot( $prep['session'] );
		} catch ( \Throwable $e ) {
			unset( $response['snapshot'] );
			$response['refresh'] = false;
		}

		return rest_ensure_response( $response );
	}

	public function place_order( WP_REST_Request $request ) {
		$prep = $this->prepare_request( $request );
		if ( is_wp_error( $prep ) ) {
			return $prep;
		}

		$session        = $prep['session'];
		$fields         = (array) $request->get_param( 'fields' );
		$payment_method = sanitize_text_field( (string) $request->get_param( 'payment_method' ) );
		$payment_data   = (array) $request->get_param( 'payment_payload' );

		// Re-hydrate cart from the multi-item session if it was emptied between
		// /state and /place (cross-origin cookies, page reload, etc.). This is
		// only meaningful for the addon's session-driven flow; same-site cookie
		// callers usually have the cart still populated.
		if ( $session ) {
			$cart = Helper::cart();
			if ( $cart && method_exists( $cart, 'is_cart_empty' ) && $cart->is_cart_empty() ) {
				$this->hydrate_cart_from_session( $cart, $session );
			}
		}

		// Recalculate against the exact address + shipping method being
		// submitted before we snapshot totals onto the order and charge the
		// gateway. Guarantees the customer is charged the amount for the address
		// they're checking out with, even if a debounced /update didn't land
		// (fast submit, race) or the totals fragment was stale.
		self::sync_cart_from_fields( $fields );

		// Pipe the form `fields` into $_POST/$_REQUEST so legacy gateway code
		// paths (which were written against the admin-ajax flow) can read
		// scalars like `storeengine-stripe-payment-token` and
		// `storeengine-stripe-save-new-payment-method` via $_REQUEST. WP REST
		// doesn't auto-populate these from JSON bodies.
		self::pipe_legacy_post_keys( $fields );

		// Pipe the gateway-specific payload into $_POST so legacy gateways pick it up.
		self::pipe_legacy_post_keys( $payment_data );

		/**
		 * Per-gateway opportunity to remap / persist anything the React
		 * adapter sent in `payment_payload` before CheckoutService::place_order()
		 * runs. Gateway addons hook here to copy intent ids, transaction ids,
		 * etc. from $payment_data onto the draft order so their server-side
		 * process_payment() can verify the payment.
		 *
		 * Generic action — fires for every place_order call regardless of
		 * gateway. Specific action — fires only when this particular gateway
		 * was selected, so addons can scope their handler cheaply.
		 *
		 * @param array  $payment_data    Raw payload from the React adapter.
		 * @param string $payment_method  Selected gateway id.
		 */
		do_action( 'storeengine/checkout/before_place_order_payload', $payment_data, $payment_method );
		do_action( "storeengine/checkout/before_place_order_payload/{$payment_method}", $payment_data );

		// Build the canonical place_order payload and delegate.
		$payload                   = $fields;
		$payload['payment_method'] = $payment_method;

		$result = CheckoutService::place_order( $payload );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// REST callers don't follow the legacy `redirect` URL — the React app
		// (and any future SPA client) handle navigation themselves. The
		// traditional /checkout/ page consumes this same response and treats
		// `redirect` as the URL to navigate to.
		return rest_ensure_response( $result );
	}

	/**
	 * Pay an existing failed/pending order. Mirrors the legacy admin-ajax
	 * `pay_order` action — different validation semantics from /place because
	 * the order already exists and we just need to charge a (potentially
	 * different) gateway against it.
	 *
	 * Request body:
	 *   { order_id: int, payment_method: string, payment_payload?: object }
	 *
	 * Returns the gateway's process_payment() response (typically
	 * `{ result: 'success', redirect: string }`).
	 */
	public function pay_order( WP_REST_Request $request ) {
		if ( ! defined( 'STOREENGINE_DOING_CHECKOUT' ) ) {
			define( 'STOREENGINE_DOING_CHECKOUT', true );
		}

		$order_id       = (int) $request->get_param( 'order_id' );
		$payment_method = sanitize_text_field( (string) $request->get_param( 'payment_method' ) );
		$payment_data   = (array) $request->get_param( 'payment_payload' );

		if ( ! $order_id ) {
			return new WP_Error( 'storeengine_pay_order_missing_id', __( 'Order ID is required.', 'storeengine' ), [ 'status' => 422 ] );
		}
		if ( '' === $payment_method ) {
			return new WP_Error( 'storeengine_pay_order_missing_method', __( 'Payment method is required.', 'storeengine' ), [ 'status' => 422 ] );
		}

		$order = Helper::get_order( $order_id );
		if ( ! $order || is_wp_error( $order ) ) {
			return new WP_Error( 'storeengine_pay_order_not_found', __( 'Order not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		// Mirror is_valid_order_pay_page() authorization: the customer owns the
		// order, OR has the explicit pay_for_order capability, OR carries a
		// matching order_key (guest-pay link). The legacy check rejected any
		// logged-in user trying to pay a guest order (customer_id=0), which
		// broke POS QR sales whenever the customer happened to be logged in
		// on the site (since the cashier intentionally leaves customer_id=0
		// so the order-pay page accepts guest pay).
		$current_user_id = get_current_user_id();
		$order_customer  = (int) $order->get_customer_id();
		$is_owner        = $order_customer > 0 && $order_customer === $current_user_id;
		$has_cap         = current_user_can( 'pay_for_order', $order->get_id() );
		$provided_key    = (string) ( $request->get_param( 'order_key' )
			?: ( isset( $_GET['key'] ) ? sanitize_text_field( wp_unslash( $_GET['key'] ) ) : '' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$key_matches     = $provided_key && hash_equals( (string) $order->get_order_key(), $provided_key );

		if ( ! $is_owner && ! $has_cap && ! $key_matches ) {
			return new WP_Error( 'storeengine_pay_order_not_found', __( 'Order not found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		if ( ! $order->needs_payment() ) {
			return new WP_Error( 'storeengine_pay_order_not_payable', __( "Order isn't available for payment!", 'storeengine' ), [ 'status' => 422 ] );
		}

		$gateway = Helper::get_payment_gateway( $payment_method );
		if ( ! $gateway ) {
			return new WP_Error( 'storeengine_pay_order_invalid_gateway', __( 'Invalid payment gateway.', 'storeengine' ), [ 'status' => 422 ] );
		}

		// `PaymentGateway::is_available()` only sees a subscription via the
		// cart, which is absent here (this endpoint charges an existing order
		// directly). Re-check against the order itself so a stale/forged
		// `payment_method` can't settle a subscription renewal through a
		// gateway that doesn't support recurring payments (COD, BACS, Check).
		if ( ! PaymentUtil::gateway_can_pay_order( $gateway, $order ) ) {
			return new WP_Error( 'storeengine_pay_order_gateway_not_supported', __( 'The selected payment method cannot be used to pay this subscription renewal. Please choose a different payment method.', 'storeengine' ), [ 'status' => 422 ] );
		}

		// Pipe the payment payload into $_POST so legacy gateway code paths can
		// read scalars (intent ids, transaction ids, etc.) via $_REQUEST.
		self::pipe_legacy_post_keys( $payment_data );

		/**
		 * Mirror of the place_order remap hook for the pay-order flow. Gateway
		 * addons hook here to translate React-adapter payload keys (e.g.
		 * `stripe_payment_intent_id` → legacy `payment_intent_id`) and persist
		 * intent ids on the existing order — unlike the place_order variant,
		 * the order already exists and is passed explicitly, so handlers must
		 * not fall back to a draft-order lookup.
		 *
		 * @param array  $payment_data   Raw payload from the client adapter.
		 * @param string $payment_method Selected gateway id.
		 * @param Order  $order          The existing order being paid.
		 */
		do_action( 'storeengine/checkout/before_pay_order_payload', $payment_data, $payment_method, $order );
		do_action( "storeengine/checkout/before_pay_order_payload/{$payment_method}", $payment_data, $order );

		// Gateways may throw (e.g. Stripe's process_payment rethrows on failure).
		// Catch here so a payment error returns a clean REST error instead of an
		// uncaught exception / fatal 500 for the customer paying the order.
		try {
			$result = $gateway->process_payment( $order );
		} catch ( \Throwable $e ) {
			\StoreEngine\Classes\Logger::log(
				sprintf( 'Payment Failed - Order #%d', $order->get_id() ),
				[
					'customer_email' => $order->get_billing_email(),
					'message'        => $e->getMessage(),
					'payment_method' => $payment_method,
				],
				\StoreEngine\Classes\Logger::ERROR,
				'payment'
			);
			return new WP_Error( 'storeengine_pay_order_failed', $e->getMessage(), [ 'status' => 422 ] );
		}

		if ( is_wp_error( $result ) ) {
			\StoreEngine\Classes\Logger::log(
				sprintf( 'Payment Failed - Order #%d', $order->get_id() ),
				[
					'customer_email' => $order->get_billing_email(),
					'message'        => $result->get_error_message(),
					'payment_method' => $payment_method,
				],
				\StoreEngine\Classes\Logger::ERROR,
				'payment'
			);
			return $result;
		}

		$order->set_payment_method( $gateway );
		$order->save();

		do_action( 'storeengine/checkout/after_pay_order', $order );

		$result['order_id'] = $order->get_id();

		// Same response shape as /place — order data + a fallback redirect to
		// the order-received page when the gateway didn't supply one.
		if ( isset( $result['result'] ) && 'success' === $result['result'] ) {
			$result = CheckoutService::prepare_checkout_response( $order, $result );
		}

		return rest_ensure_response( $result );
	}

	/**
	 * Generalised payment-intent creation. Two flows:
	 *
	 *   1. New checkout — no `order_id` in payload. Requires a non-empty cart;
	 *      resolves/creates a draft order and snapshots the cart onto it.
	 *   2. Pay-for-existing-order — caller passes `order_id` (and is
	 *      authorised to pay it). The existing order is used as-is, no cart
	 *      sync, no draft creation. Drives the frontend-dashboard "Pay Order"
	 *      flow for failed / scheduled / installment orders.
	 *
	 * Either way, the gateway's GatewayAdapterInterface::create_intent() (or
	 * a soft fallback filter) builds the actual client-side intent.
	 */
	public function create_payment_intent( WP_REST_Request $request ) {
		$prep = $this->prepare_request( $request );
		if ( is_wp_error( $prep ) ) {
			return $prep;
		}

		$gateway_id = sanitize_key( (string) $request->get_param( 'gateway_id' ) );
		$gateway    = Helper::get_payment_gateway( $gateway_id );
		if ( ! $gateway ) {
			return new WP_Error( 'storeengine_checkout_invalid_gateway', __( 'Unknown payment gateway.', 'storeengine' ), [ 'status' => 422 ] );
		}

		$cart     = Helper::cart();
		$order_id = absint( $request->get_param( 'order_id' ) );

		if ( $order_id ) {
			// Pay-for-existing-order path. Skip the cart-empty guard, draft
			// lookup and cart→order sync entirely — the order already exists
			// and its totals are authoritative.
			$order = Helper::get_order( $order_id );
			if ( is_wp_error( $order ) ) {
				return new WP_Error( 'storeengine_checkout_invalid_order', __( 'Order not found.', 'storeengine' ), [ 'status' => 404 ] );
			}
			if ( ! current_user_can( 'pay_for_order', $order->get_id() ) ) {
				return new WP_Error( 'storeengine_checkout_forbidden', __( 'You are not allowed to pay for this order.', 'storeengine' ), [ 'status' => 403 ] );
			}
			if ( ! $order->needs_payment() ) {
				return new WP_Error( 'storeengine_checkout_not_payable', __( 'This order is not awaiting payment.', 'storeengine' ), [ 'status' => 422 ] );
			}
			if ( ! PaymentUtil::gateway_can_pay_order( $gateway, $order ) ) {
				return new WP_Error( 'storeengine_checkout_gateway_not_supported', __( 'The selected payment method cannot be used to pay this subscription renewal. Please choose a different payment method.', 'storeengine' ), [ 'status' => 422 ] );
			}
		} else {
			// New-checkout path. Requires a non-empty cart and creates/uses a
			// draft order with the cart snapshot.
			if ( ! $cart || $cart->is_cart_empty() ) {
				return new WP_Error( 'storeengine_checkout_empty_cart', __( 'Cart is empty.', 'storeengine' ), [ 'status' => 422 ] );
			}
			// Sync the submitted address + shipping choice (when the gateway
			// adapter forwards them) and recalculate, so the intent amount the
			// gateway is about to create matches the address being checked out
			// with. Falls back to a plain recalc when no fields are sent.
			self::sync_cart_from_fields( (array) $request->get_param( 'fields' ) );

			$order = Helper::get_recent_draft_order( get_current_user_id(), null, true );
			if ( ! $order ) {
				return new WP_Error( 'storeengine_checkout_no_order', __( 'Could not create order.', 'storeengine' ), [ 'status' => 500 ] );
			}

			// Snapshot the cart onto the draft order so every gateway adapter sees
			// a fully populated order (line items, coupons, fees, shipping, tax).
			// Without this, gateways like Paddle — which build their discount from
			// $order->get_coupons() — would create the intent against the original
			// totals because the draft has no coupon items yet. Mirrors
			// CheckoutService::place_order() minus the status transition; the
			// order stays a DRAFT and gets re-synced when place_order runs.
			try {
				$order->clear_items();
				$order->set_currency( Formatting::get_currency() );
				CheckoutService::add_product( $order, $cart );
				CheckoutService::add_fee( $order, $cart );
				CheckoutService::add_shipping( $order, $cart );
				CheckoutService::apply_coupon( $order, $cart );
				CheckoutService::add_tax( $order, $cart );
				$order->set_cart_hash( $cart->get_cart_hash() );
				$order->set_total( (float) $cart->get_total( 'edit' ) );
				$order->save();
			} catch ( StoreEngineException $e ) {
				return new WP_Error(
					$e->get_wp_error_code() ?: 'storeengine_checkout_sync_failed',
					$e->getMessage(),
					[ 'status' => 422 ]
				);
			} catch ( \Throwable $e ) {
				return new WP_Error( 'storeengine_checkout_sync_failed', $e->getMessage(), [ 'status' => 500 ] );
			}
		}

		// GatewayAdapterInterface::create_intent() requires a Cart instance.
		// In the order-pay path the user has no cart, but the contract still
		// needs *something* — instantiate an empty one so gateways which only
		// read the order (Stripe, Square) work, and gateways which read the
		// cart (PayPal, Razorpay, Paddle) get a defined empty-state.
		if ( ! $cart ) {
			$cart = new \StoreEngine\Classes\Cart();
		}

		// Pipe scalar JSON-body params into $_REQUEST so create_intent
		// implementations can read gateway-specific knobs (e.g. Stripe's
		// `mode=setup`, `save_method=true`) without us threading the WP_REST_Request
		// through the GatewayAdapterInterface signature.
		self::pipe_legacy_post_keys( $request->get_params() );

		// Preferred path: gateway implements GatewayAdapterInterface::create_intent.
		if ( $gateway instanceof \StoreEngine\Interfaces\GatewayAdapterInterface ) {
			try {
				$intent = $gateway->create_intent( $order, $cart );
			} catch ( \Throwable $e ) {
				return new WP_Error( 'storeengine_checkout_intent_failed', $e->getMessage(), [ 'status' => 500 ] );
			}
			if ( is_wp_error( $intent ) ) {
				return $intent;
			}

			return rest_ensure_response( (array) $intent );
		}

		// Soft fallback: let an addon hook in a per-gateway intent creator without
		// implementing the interface. Filter signature: ( $intent_or_null, $gateway, $order, $cart ).
		$intent = apply_filters( 'storeengine/checkout/create_intent', null, $gateway, $order, $cart );
		if ( is_wp_error( $intent ) ) {
			return $intent;
		}
		if ( is_array( $intent ) ) {
			return rest_ensure_response( $intent );
		}

		return new WP_Error(
			'storeengine_checkout_intent_unsupported',
			sprintf(
				/* translators: %s: gateway id */
				__( 'Gateway "%s" does not support client-side payment intents.', 'storeengine' ),
				$gateway_id
			),
			[ 'status' => 422 ]
		);
	}

	/**
	 * Apply a coupon to the cart and return the refreshed snapshot. Mirrors
	 * the legacy admin-ajax `apply_coupon_form` action but speaks REST.
	 */
	public function apply_coupon( WP_REST_Request $request ) {
		$prep = $this->prepare_request( $request );
		if ( is_wp_error( $prep ) ) {
			return $prep;
		}

		$code = sanitize_text_field( (string) $request->get_param( 'coupon_code' ) );
		if ( '' === $code ) {
			return new WP_Error( 'storeengine_coupon_empty', __( 'Please enter a coupon code.', 'storeengine' ), [ 'status' => 422 ] );
		}

		$cart = Helper::cart();
		if ( ! $cart ) {
			return new WP_Error( 'storeengine_no_cart', __( 'Cart unavailable.', 'storeengine' ), [ 'status' => 500 ] );
		}

		$result = $cart->apply_coupon( $code );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), [ 'status' => 422 ] );
		}

		$cart->calculate_totals();

		return rest_ensure_response( [
			'applied'  => true,
			'code'     => $code,
			'snapshot' => $this->snapshot( $prep['session'] ),
		] );
	}

	/**
	 * Remove a previously applied coupon and return the refreshed snapshot.
	 */
	public function remove_coupon( WP_REST_Request $request ) {
		$prep = $this->prepare_request( $request );
		if ( is_wp_error( $prep ) ) {
			return $prep;
		}

		$code = sanitize_text_field( (string) $request->get_param( 'coupon_code' ) );
		if ( '' === $code ) {
			return new WP_Error( 'storeengine_coupon_empty', __( 'Missing coupon code.', 'storeengine' ), [ 'status' => 422 ] );
		}

		$cart = Helper::cart();
		if ( ! $cart ) {
			return new WP_Error( 'storeengine_no_cart', __( 'Cart unavailable.', 'storeengine' ), [ 'status' => 500 ] );
		}

		$cart->remove_coupon( $code );
		$cart->calculate_totals();

		return rest_ensure_response( [
			'removed'  => true,
			'code'     => $code,
			'snapshot' => $this->snapshot( $prep['session'] ),
		] );
	}

	public function get_states( WP_REST_Request $request ) {
		$cc      = strtoupper( sanitize_text_field( (string) $request->get_param( 'country_code' ) ) );
		$states  = Countries::init()->get_states( $cc );
		$locales = Countries::init()->get_country_locale();
		$locale  = $locales[ $cc ] ?? [];

		return rest_ensure_response( [
			'country_code' => $cc,
			'states'       => $states ?: (object) [],
			'label'        => $locale['state']['label'] ?? __( 'State / County', 'storeengine' ),
			'required'     => $locale['state']['required'] ?? false,
		] );
	}

	// ---- Internals ---------------------------------------------------------

	/**
	 * Resolve the optional embed session, bootstrap cart/customer, hydrate cart.
	 * Returns either { session: array|null } or WP_Error.
	 */
	protected function prepare_request( WP_REST_Request $request ) {
		StoreEngine::init()->load_cart();

		$session_id = sanitize_text_field( (string) $request->get_param( 'session_id' ) );
		$session    = null;
		if ( $session_id ) {
			$session = apply_filters( 'storeengine/checkout/resolve_session', null, $session_id, $request );
			if ( is_wp_error( $session ) ) {
				return $session;
			}
			if ( is_array( $session ) ) {
				$cart = Helper::cart();
				if ( $cart && method_exists( $cart, 'is_cart_empty' ) && $cart->is_cart_empty() ) {
					$this->hydrate_cart_from_session( $cart, $session );
				}
			}
		}

		return [ 'session' => $session ];
	}

	/**
	 * Replace cart contents with the session's `selection` (multi-item) or fall
	 * back to legacy single product/price/qty fields. This is identical to the
	 * helper used by the addon controller and is exposed via
	 * `storeengine/checkout/hydrate_cart` so the addon's `OneClickService` and
	 * `Stripe-intent` endpoint can call the same logic without a fork.
	 */
	/**
	 * Reserved superglobal keys that downstream auth/admin code trusts. We
	 * never let a REST-body caller inject values for these via the legacy
	 * gateway-compat $_POST piping below, even if a gateway author somehow
	 * named a parameter the same way.
	 */
	const RESERVED_REQUEST_KEYS = [
		'action',
		'_wpnonce',
		'_wp_http_referer',
		'_method',
		'storeengine_admin_action',
		'storeengine_nonce',
		'user_id',
		'user_login',
		'user_email',
		'pwd',
	];

	/**
	 * Copy scalar values from a REST-body array into $_POST/$_REQUEST so
	 * legacy gateway code paths (written against admin-ajax) can still read
	 * them via $_REQUEST. Two guards:
	 *
	 *   1. Skip RESERVED_REQUEST_KEYS — caller can't override WP/StoreEngine
	 *      internal keys that downstream code trusts for auth/admin flows.
	 *   2. Skip keys already set in $_POST — caller can't overwrite values
	 *      the framework already populated for this request.
	 *
	 * Centralized so all three pipe-points (place_order, pay_order,
	 * create_payment_intent) stay consistent.
	 */
	protected static function pipe_legacy_post_keys( array $source ): void {
		$reserved = array_flip( self::RESERVED_REQUEST_KEYS );
		foreach ( $source as $k => $v ) {
			if ( ! is_scalar( $v ) ) continue;
			if ( isset( $reserved[ $k ] ) ) continue;
			if ( isset( $_POST[ $k ] ) ) continue; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_POST[ $k ]    = $v; // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$_REQUEST[ $k ] = $v; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}
	}

	/**
	 * Push the submitted address + chosen shipping method onto the session
	 * cart/customer and recalculate totals.
	 *
	 * Shared by /update (live, on every field change) and /place (final, right
	 * before payment) so shipping + tax always reflect the address the shopper
	 * is actually checking out with — and the amount charged matches the amount
	 * shown. The cart's customer is the same instance as StoreEngine::customer
	 * (Cart sets it in its constructor), so writing here updates what
	 * get_shipping_packages() reads during calculate_totals().
	 *
	 * @param array $fields Checkout field payload (REST `fields`).
	 */
	private static function sync_cart_from_fields( array $fields ): void {
		$cart = Helper::cart();
		if ( ! $cart ) {
			return;
		}

		// Apply shipping method choice so totals pick the right rate.
		if ( ! empty( $fields['shipping_method'] ) ) {
			$cart->set_meta( 'chosen_shipping_methods', [ sanitize_text_field( $fields['shipping_method'] ) ] );
		}

		// Persist address bits to the customer so totals see the right location.
		$customer = StoreEngine::init()->get_customer();
		if ( $customer ) {
			$map = [
				'billing_country', 'billing_state', 'billing_city', 'billing_postcode',
				'shipping_country', 'shipping_state', 'shipping_city', 'shipping_postal_code',
			];
			foreach ( $map as $key ) {
				if ( ! isset( $fields[ $key ] ) ) {
					continue;
				}
				$setter = 'set_' . str_replace( 'shipping_postal_code', 'shipping_postcode', $key );
				if ( method_exists( $customer, $setter ) ) {
					$customer->{$setter}( sanitize_text_field( $fields[ $key ] ) );
				}
			}
		}

		$cart->calculate_totals();
	}

	/**
	 * Stable fingerprint of the money-bearing cart totals. Used to decide whether
	 * a field change actually moved a total (and therefore the summary needs a
	 * re-render). Rounded so float noise doesn't cause spurious refreshes.
	 *
	 * @param \StoreEngine\Classes\Cart|null $cart
	 *
	 * @return string
	 */
	private static function totals_fingerprint( $cart ): string {
		if ( ! $cart ) {
			return '';
		}

		return wp_json_encode( [
			round( (float) $cart->get_total( 'edit' ), 4 ),
			round( (float) $cart->get_shipping_total(), 4 ),
			round( (float) $cart->get_shipping_tax(), 4 ),
			round( (float) $cart->get_taxes_total( true, false ), 4 ),
			round( (float) $cart->get_discount_total(), 4 ),
			round( (float) $cart->get_subtotal(), 4 ),
		] );
	}

	/**
	 * Collect the available shipping rates for the current cart and the chosen
	 * rate id, for the embedded checkout's shipping-method selector.
	 *
	 * Rates come from the packages the cart computed during calculate_totals()
	 * (single-package model — package 0). Returns [ rates[], chosenId ]; rates
	 * is empty when the cart doesn't need shipping or the address isn't complete
	 * enough to quote yet.
	 *
	 * @param \StoreEngine\Classes\Cart|null $cart
	 *
	 * @return array{0: array, 1: string}
	 */
	private static function collect_shipping_rates( $cart ): array {
		if ( ! $cart || ! $cart->needs_shipping() ) {
			return [ [], '' ];
		}

		$packages       = \StoreEngine\Shipping\Shipping::init()->get_packages();
		$chosen_methods = (array) $cart->get_meta( 'chosen_shipping_methods' );

		$rates  = [];
		$chosen = '';

		foreach ( $packages as $i => $package ) {
			$package_rates = $package['rates'] ?? [];
			if ( is_array( $package_rates ) ) {
				foreach ( $package_rates as $rate ) {
					if ( ! is_object( $rate ) || ! method_exists( $rate, 'get_id' ) ) {
						continue;
					}
					$rates[] = [
						'id'        => $rate->get_id(),
						'label'     => $rate->get_label(),
						'cost'      => (float) $rate->get_cost(),
						'tax'       => (float) $rate->get_shipping_tax(),
						'method_id' => $rate->get_method_id(),
					];
				}
			}
			$chosen = (string) ( $chosen_methods[ $i ] ?? '' );
			break; // Single-package model — only package 0 is surfaced.
		}

		// Fall back to the first rate so the UI always has a selection to show.
		if ( '' === $chosen && ! empty( $rates ) ) {
			$chosen = $rates[0]['id'];
		}

		return [ $rates, $chosen ];
	}

	public static function hydrate_cart_from_session( $cart, array $session ): void {
		$selection = isset( $session['selection'] ) ? (array) $session['selection'] : [];

		if ( ! $selection ) {
			$price_id = (int) ( $session['price_id'] ?? 0 );
			$quantity = max( 1, (int) ( $session['quantity'] ?? 1 ) );
			if ( ! $price_id && ! empty( $session['product_id'] ) ) {
				$product = Helper::get_product( (int) $session['product_id'] );
				if ( $product ) {
					$prices = $product->get_prices();
					if ( $prices ) {
						$price_id = (int) reset( $prices )->get_id();
					}
				}
			}
			if ( $price_id ) {
				$selection[] = [ 'price_id' => $price_id, 'quantity' => $quantity ];
			}
		}

		$cart->clear_cart();
		foreach ( $selection as $row ) {
			$row      = (array) $row;
			$price_id = (int) ( $row['price_id'] ?? 0 );
			$qty      = max( 1, (int) ( $row['quantity'] ?? 1 ) );
			if ( ! $price_id && ! empty( $row['product_id'] ) ) {
				$product = Helper::get_product( (int) $row['product_id'] );
				if ( $product ) {
					$prices = $product->get_prices();
					if ( $prices ) {
						$price_id = (int) reset( $prices )->get_id();
					}
				}
			}
			if ( $price_id ) {
				$cart->add_product_to_cart( $price_id, $qty );
			}
		}
	}

	protected function snapshot( ?array $session = null ): array {
		$cart     = Helper::cart();
		$gateways = [];
		$order    = Helper::get_recent_draft_order( get_current_user_id(), null, true );
		$avail    = Helper::get_payment_gateways()->get_available_payment_gateways();
		foreach ( $avail as $gateway ) {
			$gateways[] = $this->present_gateway( $gateway );
		}

		$per_item_discounts = method_exists( $cart, 'get_coupon_discount_per_item' ) ? $cart->get_coupon_discount_per_item() : [];
		$reward_units       = method_exists( $cart, 'get_reward_units' ) ? $cart->get_reward_units() : [];

		$items = [];
		foreach ( $cart->get_cart_items() as $cart_item_key => $item ) {
			$product = Helper::get_product( $item->product_id );

			$free_units  = 0;
			$free_coupon = '';
			foreach ( $reward_units as $code => $keys ) {
				if ( ! empty( $keys[ $cart_item_key ] ) ) {
					$free_units += (int) $keys[ $cart_item_key ];
					$free_coupon = (string) $code;
				}
			}

			$items[] = [
				'item_key'          => $cart_item_key,
				'product_id'        => (int) $item->product_id,
				'price_id'          => (int) ( $item->price_id ?? 0 ),
				'name'              => $product ? $product->get_name() : '',
				'image'             => $product ? ( get_the_post_thumbnail_url( $product->get_id(), 'thumbnail' ) ?: null ) : null,
				'quantity'          => (int) $item->quantity,
				'price'             => (float) ( $item->price ?? 0 ),
				'subtotal'          => (float) ( $item->subtotal ?? ( $item->price * $item->quantity ) ),
				'coupon_discount'   => (float) array_sum( $per_item_discounts[ $cart_item_key ] ?? [] ),
				'free_units'        => $free_units,
				'free_units_coupon' => $free_coupon,
				// Add-on line markers (e.g. an auto-added gift line).
				'item_data'         => (array) ( $item->item_data ?? [] ),
			];
		}

		// Optional declared items + selection from a session (only meaningful
		// when called from the embedded React app via the addon).
		$declared  = [];
		$selection = [];
		if ( $session && ! empty( $session['items'] ) ) {
			foreach ( (array) $session['items'] as $row ) {
				$pid = (int) ( $row['product_id'] ?? 0 );
				if ( ! $pid ) {
					continue;
				}
				$product   = Helper::get_product( $pid );
				$pid_price = (int) ( $row['price_id'] ?? 0 );
				$price     = null;
				if ( $product ) {
					$prices = $product->get_prices();
					if ( $pid_price ) {
						foreach ( $prices as $p ) {
							if ( (int) $p->get_id() === $pid_price ) {
								$price = $p;
								break;
							}
						}
					}
					if ( ! $price && $prices ) {
						$price     = reset( $prices );
						$pid_price = (int) $price->get_id();
					}
				}
				$declared[] = [
					'product_id' => $pid,
					'price_id'   => $pid_price,
					'name'       => ! empty( $row['label'] ) ? (string) $row['label'] : ( $product ? $product->get_name() : '' ),
					'image'      => ! empty( $row['image'] ) ? (string) $row['image'] : ( $product ? ( get_the_post_thumbnail_url( $pid, 'thumbnail' ) ?: null ) : null ),
					'price'      => $price ? (float) $price->get_price() : 0.0,
					'optional'   => ! empty( $row['optional'] ),
					'default'    => ! empty( $row['default'] ),
					'quantity'   => max( 1, (int) ( $row['quantity'] ?? 1 ) ),
				];
			}
		}
		if ( $session && ! empty( $session['selection'] ) ) {
			foreach ( (array) $session['selection'] as $row ) {
				$selection[] = [
					'product_id' => (int) ( $row['product_id'] ?? 0 ),
					'price_id'   => (int) ( $row['price_id'] ?? 0 ),
					'quantity'   => (int) ( $row['quantity'] ?? 1 ),
				];
			}
		}

		$snapshot = [
			'cart'               => [
				'items'          => $items,
				'needs_shipping' => $cart->needs_shipping(),
				'needs_payment'  => $cart->needs_payment(),
				'currency'       => Formatting::get_currency(),
			],
			'totals'             => apply_filters( 'storeengine/api/checkout/totals', [
				'subtotal' => (float) $cart->get_cart_subtotal(),
				'shipping' => (float) $cart->get_shipping_total(),
				'tax'      => (float) $cart->get_taxes_total( true, false ),
				'discount' => (float) $cart->get_discount_total(),
				'total'    => (float) $cart->get_total( 'edit' ),
			], $cart ),
			'available_gateways' => $gateways,
			'order_id'           => $order ? (int) $order->get_id() : 0,
			'declared_items'     => $declared,
			'selection'          => $selection,
			'checkout_fields'    => array_values( CheckoutFields::all() ),
			'saved_fields'       => $this->collect_saved_fields(),
			'branding'           => $this->collect_branding(),
			'applied_coupons'    => $this->collect_applied_coupons( $cart ),
			'place_order_label'  => apply_filters( 'storeengine/checkout/place_order_button_text', __( 'Place order', 'storeengine' ), $cart->needs_payment() ),
			'consent_checkboxes' => apply_filters( 'storeengine/checkout/consent_checkboxes', [] ),
		];

		// Available shipping methods (so the embedded React checkout can render a
		// rate selector instead of silently using the first method) + the
		// currently chosen one.
		[ $snapshot['shipping_rates'], $snapshot['chosen_shipping_method'] ] = self::collect_shipping_rates( $cart );

		/**
		 * Allow addons to enrich the checkout state snapshot (e.g. expose the
		 * verification mode + whether OTP is required before placing the order,
		 * split rewarded units into a FREE line, or inject coupon suggestions).
		 *
		 * @param array $snapshot
		 * @param Cart  $cart
		 */
		return apply_filters( 'storeengine/checkout/state_snapshot', $snapshot, $cart );
	}

	/**
	 * Surface applied coupons (code + discount) for the React order summary so
	 * it can render them as removable pills.
	 */
	protected function collect_applied_coupons( $cart ): array {
		$out = [];
		if ( ! $cart || ! method_exists( $cart, 'get_coupons' ) ) {
			return $out;
		}
		foreach ( $cart->get_coupons() as $coupon ) {
			if ( ! is_object( $coupon ) ) {
				continue;
			}
			$code = '';
			if ( method_exists( $coupon, 'get_code' ) ) {
				$code = (string) $coupon->get_code();
			} elseif ( property_exists( $coupon, 'code' ) ) {
				$code = (string) $coupon->code;
			}
			if ( '' === $code ) {
				continue;
			}
			$out[] = [
				'code'     => $code,
				'discount' => method_exists( $cart, 'get_coupon_discount_amount' ) ? (float) $cart->get_coupon_discount_amount( $code ) : 0.0,
			];
		}

		return $out;
	}

	/**
	 * Storefront branding for the React checkout header — store logo + name.
	 *
	 * Logo source order:
	 *   1. StoreEngine settings → `store_logo` (attachment ID).
	 *   2. Plugin shipped fallback (`assets/images/full-logo.svg`).
	 *
	 * Returns `logo_url = ''` when the merchant has no logo configured AND
	 * the plugin asset is missing — the React header just hides the logo
	 * tag in that case.
	 */
	protected function collect_branding(): array {
		$logo_id  = (int) Helper::get_settings( 'store_logo' );
		$logo_url = $logo_id ? (string) wp_get_attachment_url( $logo_id ) : '';
		if ( ! $logo_url && defined( 'STOREENGINE_ASSETS_URI' ) ) {
			$logo_url = STOREENGINE_ASSETS_URI . 'images/full-logo.svg';
		}

		return [
			'logo_url'   => $logo_url,
			'store_name' => (string) ( Helper::get_settings( 'store_name' ) ?: get_bloginfo( 'name' ) ),
			'site_url'   => home_url( '/' ),
		];
	}

	/**
	 * Pull the current customer's saved billing/shipping into the React state
	 * shape so the Quick Checkout form can pre-fill the same way the legacy
	 * /checkout/ template does.
	 *
	 * @return array<string, string>
	 */
	protected function collect_saved_fields(): array {
		$customer = StoreEngine::init()->get_customer();
		if ( ! $customer ) {
			return [];
		}

		$get = static function ( string $method ) use ( $customer ): string {
			if ( ! method_exists( $customer, $method ) ) {
				return '';
			}
			$value = $customer->{$method}();

			return is_string( $value ) ? $value : (string) ( $value ?? '' );
		};

		$out = [
			'user_email'           => $get( 'get_billing_email' ),
			'billing_email'        => $get( 'get_billing_email' ),
			'billing_first_name'   => $get( 'get_billing_first_name' ),
			'billing_last_name'    => $get( 'get_billing_last_name' ),
			'billing_address_1'    => $get( 'get_billing_address_1' ),
			'billing_address_2'    => $get( 'get_billing_address_2' ),
			'billing_city'         => $get( 'get_billing_city' ),
			'billing_state'        => $get( 'get_billing_state' ),
			'billing_postcode'     => $get( 'get_billing_postcode' ),
			'billing_country'      => $get( 'get_billing_country' ),
			'billing_phone'        => $get( 'get_billing_phone' ),
			'shipping_first_name'  => $get( 'get_shipping_first_name' ),
			'shipping_last_name'   => $get( 'get_shipping_last_name' ),
			'shipping_address_1'   => $get( 'get_shipping_address_1' ),
			'shipping_city'        => $get( 'get_shipping_city' ),
			'shipping_state'       => $get( 'get_shipping_state' ),
			// Customer object stores it under `postcode`; React state uses `postal_code`.
			'shipping_postal_code' => $get( 'get_shipping_postcode' ),
			'shipping_country'     => $get( 'get_shipping_country' ),
			'shipping_phone'       => $get( 'get_shipping_phone' ),
		];

		// Only return fields that actually have a value so the React side can
		// distinguish "saved" from "blank" with a simple truthiness check.
		return array_filter( $out, static fn( $v ) => '' !== $v );
	}

	/**
	 * Per-gateway payload for the React adapter (publishable keys, etc.).
	 * Filterable so addon authors can add their own fields.
	 */
	protected function present_gateway( $gateway ): array {
		$id   = $gateway->id;
		$data = [
			'id'          => $id,
			'title'       => method_exists( $gateway, 'get_title' ) ? $gateway->get_title() : ( $gateway->title ?? $id ),
			'description' => method_exists( $gateway, 'get_description' ) ? $gateway->get_description() : '',
		];

		if ( method_exists( $gateway, 'get_option' ) ) {
			$is_production         = (bool) $gateway->get_option( 'is_production', true );
			$data['is_production'] = $is_production;

			$key_type = $is_production ? '' : 'test_';
			$pk       = $gateway->get_option( $key_type . 'publishable_key' );
			if ( $pk ) {
				$data['publishable_key'] = $pk;
			}
		}

		return apply_filters( 'storeengine/checkout/gateway/' . $id . '/data', $data, $gateway );
	}
}
