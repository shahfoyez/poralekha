<?php

namespace StoreEngine\Addons\Paddle;

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Addons\Subscription\Classes\SubscriptionCollection;
use StoreEngine\Addons\Subscription\Classes\Utils as SubscriptionUtils;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderStatus\OrderStatus;
use StoreEngine\Utils\Helper;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Paddle webhook receiver.
 *
 * Paddle confirms payments client-side, but the browser can close / lose its
 * network / hit a still-`ready` transaction before `/checkout/place` finishes —
 * leaving a paid order stranded in `draft` (invisible in the customer's
 * dashboard). This server-to-server endpoint is the safety net: Paddle posts the
 * event regardless of the browser, and we reconcile the order to a paid state.
 *
 * It also records subscription renewals (Paddle is merchant-of-record and bills
 * renewals itself) and basic subscription lifecycle changes.
 *
 * Authoritative source of truth is always a fresh API read of the transaction
 * (PaddleService::get_transaction) — the raw webhook body is never trusted to
 * decide paid status, resolve the order, or read the customer email. On top of
 * that, a valid Paddle-Signature HMAC is mandatory: a store with no signing
 * secret configured for the active mode rejects webhooks outright.
 */
class API {

	protected GatewayPaddle $gateway;

	public static function init( $gateway ): void {
		$self          = new self();
		$self->gateway = $gateway;

		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route(
			STOREENGINE_PLUGIN_SLUG . '/v1',
			'/payment/paddle/webhook',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handle_webhook' ],
					// External caller (Paddle). Authenticity is established by the
					// mandatory signature check + the authoritative transaction
					// re-fetch, not by a WP capability.
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	/**
	 * @param WP_REST_Request $request
	 *
	 * @return WP_REST_Response
	 */
	public function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		$raw = $request->get_body();

		// Webhook authenticity is mandatory. The signing secret is the only thing
		// that proves a request genuinely came from Paddle, so a store with no
		// secret configured for the active mode cannot accept webhooks — reject
		// rather than silently process an unsigned body that could mutate payment
		// or subscription state.
		$secret = $this->get_webhook_secret();
		if ( ! $secret ) {
			$this->log_failure( 'webhook_secret_missing', [
				'mode' => $this->gateway->get_option( 'is_production', true ) ? 'live' : 'sandbox',
				'hint' => 'Paddle webhooks are rejected until the destination "Secret key" (starts with pdl_) for THIS mode is saved into the matching live/sandbox webhook-secret field.',
			] );

			return new WP_REST_Response( [ 'error' => 'webhook_secret_not_configured' ], 401 );
		}

		if ( ! $this->verify_signature( $request, $raw, $secret ) ) {
			$this->log_failure( 'invalid_signature', [
				'mode'       => $this->gateway->get_option( 'is_production', true ) ? 'live' : 'sandbox',
				'has_sig'    => $request->get_header( 'paddle_signature' ) ? 'yes' : 'no',
				'secret_len' => strlen( $secret ),
				'hint'       => 'Signature mismatch. Paste the Paddle destination "Secret key" (starts with pdl_) for THIS mode — not the ntfset_ id — into the matching live/sandbox webhook-secret field.',
			] );

			return new WP_REST_Response( [ 'error' => 'invalid_signature' ], 401 );
		}

		$event = json_decode( $raw );
		$type  = is_object( $event ) ? ( $event->event_type ?? '' ) : '';
		$data  = is_object( $event ) ? ( $event->data ?? null ) : null;

		if ( ! $data ) {
			return $this->ack( 'no_data' );
		}

		try {
			switch ( $type ) {
				case 'transaction.completed':
				case 'transaction.paid':
					return $this->handle_transaction( $data );

				case 'subscription.canceled':
				case 'subscription.cancelled':
					return $this->update_subscription_status( $data, 'cancelled' );

				case 'subscription.past_due':
				case 'subscription.paused':
					return $this->update_subscription_status( $data, 'on_hold' );

				default:
					return $this->ack( 'ignored' );
			}
		} catch ( \Throwable $e ) {
			Helper::log_error( $e );

			// 200 on internal errors so Paddle doesn't retry-storm; the failure is
			// logged for manual reconciliation (and the admin can force-mark paid).
			return $this->ack( 'error' );
		}
	}

	/**
	 * Reconcile a completed/paid Paddle transaction — either the initial order
	 * (the core bug fix) or a subscription renewal.
	 *
	 * @param object $data Paddle transaction payload.
	 *
	 * @return WP_REST_Response
	 */
	protected function handle_transaction( $data ): WP_REST_Response {
		$transaction_id    = $data->id ?? '';
		$subscription_id   = $data->subscription_id ?? '';
		$origin            = $data->origin ?? '';
		$merchant_order_id = isset( $data->custom_data->merchant_order_id )
			? (int) $data->custom_data->merchant_order_id
			: 0;

		if ( ! $transaction_id ) {
			return $this->fail( 'no_transaction_id' );
		}

		// A recurring charge has a subscription id and no originating merchant
		// order id (Paddle generates it server-side, outside our checkout).
		$is_renewal = $subscription_id && ( 0 === strpos( (string) $origin, 'subscription' ) || ! $merchant_order_id );

		if ( $is_renewal ) {
			return $this->handle_renewal( $subscription_id, $transaction_id );
		}

		// Authoritative re-fetch FIRST — never trust the webhook body for paid
		// status, the merchant order id, or the customer email. Order resolution
		// and completion below all key off this server-fetched transaction.
		$transaction = PaddleService::init()->get_transaction( $transaction_id );
		if ( is_wp_error( $transaction ) ) {
			return $this->fail( 'fetch_failed', [ 'order_id' => $merchant_order_id, 'transaction_id' => $transaction_id, 'error' => $transaction->get_error_message() ] );
		}

		// Primary: resolve by the StoreEngine order id we embedded as Paddle
		// custom_data.merchant_order_id at checkout — read from the AUTHORITATIVE
		// transaction, not the (spoofable) webhook body.
		$authoritative_order_id = isset( $transaction->custom_data->merchant_order_id )
			? (int) $transaction->custom_data->merchant_order_id
			: 0;
		$order = $authoritative_order_id ? Helper::get_order( $authoritative_order_id ) : null;

		// Fallback: a transaction with no (resolvable) merchant_order_id — e.g.
		// created directly in Paddle (payment link / Retain). Match it to a
		// StoreEngine order by the customer's email — derived from the verified
		// transaction's customer, never from the body — and ONLY when exactly one
		// unpaid Paddle order matches (never risk completing the wrong order).
		$matched_by_email = false;
		if ( ! $order instanceof Order ) {
			$order = $this->resolve_order_by_email( $transaction, $transaction_id );

			if ( ! $order instanceof Order ) {
				return $this->fail( 'order_not_found', [ 'order_id' => $authoritative_order_id, 'transaction_id' => $transaction_id ] );
			}

			$matched_by_email = true;
		}

		if ( $order->is_paid() ) {
			return $this->ack( 'already_paid' );
		}

		// $matched_by_email orders won't carry a matching custom_data.merchant_order_id,
		// so tell complete_order_from_transaction to skip that ownership guard (the
		// email + single-unpaid-order match is the established link).
		$result = $this->gateway->complete_order_from_transaction( $order, $transaction, $matched_by_email );

		if ( ! empty( $result['result'] ) && 'success' === $result['result'] ) {
			$order->add_order_note(
				$matched_by_email
					? __( 'Reconciled via Paddle webhook (matched by customer email).', 'storeengine' )
					: __( 'Reconciled via Paddle webhook.', 'storeengine' )
			);

			return $this->ack( 'ok' );
		}

		return $this->fail( 'not_authorized', [ 'order_id' => $order->get_id(), 'transaction_id' => $transaction_id ] );
	}

	/**
	 * Fallback order resolution: find a StoreEngine order by the Paddle
	 * customer's email when the transaction carries no usable merchant_order_id.
	 *
	 * The email is resolved from the AUTHORITATIVE (server-fetched) transaction's
	 * customer id via the Paddle API — never from the webhook body — so a spoofed
	 * body email can't point the match at someone else's order.
	 *
	 * Safety: returns an order ONLY when EXACTLY ONE unpaid Paddle order matches
	 * the email. Zero or multiple matches log and return null, so an ambiguous
	 * email can never auto-complete the wrong order.
	 *
	 * @param object $transaction    Authoritative Paddle transaction (->customer_id).
	 * @param string $transaction_id For log context.
	 *
	 * @return Order|null
	 */
	protected function resolve_order_by_email( $transaction, string $transaction_id ) {
		// Resolve the email from the verified transaction's customer id via the
		// Paddle API. We deliberately ignore any customer email present on the
		// raw webhook body, which is attacker-controllable on an unsigned request.
		$customer_id = isset( $transaction->customer_id ) ? (string) $transaction->customer_id : '';
		$email       = $customer_id ? PaddleService::init()->get_customer_email( $customer_id ) : '';

		if ( ! $email ) {
			$this->log_failure( 'email_unavailable', [ 'customer_id' => $customer_id, 'transaction_id' => $transaction_id ] );

			return null;
		}

		global $wpdb;

		// The orders table `billing_email` column holds the order's primary email
		// (the `order_email` prop maps to it — see AbstractOrder::prepare_for_db()).
		$statuses     = [ OrderStatus::DRAFT, OrderStatus::PAYMENT_PENDING, OrderStatus::PAYMENT_FAILED ];
		$placeholders = implode( ', ', array_fill( 0, count( $statuses ), '%s' ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}storeengine_orders
				WHERE type = 'order'
				  AND payment_method = 'paddle'
				  AND billing_email = %s
				  AND status IN ( $placeholders )
				LIMIT 2",
				array_merge( [ $email ], $statuses )
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( 1 !== count( $ids ) ) {
			$this->log_failure( 'email_match_ambiguous', [ 'email' => $email, 'matches' => count( $ids ), 'transaction_id' => $transaction_id ] );

			return null;
		}

		$order = Helper::get_order( (int) $ids[0] );

		return $order instanceof Order ? $order : null;
	}

	/**
	 * Record a subscription renewal charged by Paddle.
	 *
	 * @param string $paddle_subscription_id
	 * @param string $transaction_id
	 *
	 * @return WP_REST_Response
	 */
	protected function handle_renewal( string $paddle_subscription_id, string $transaction_id ): WP_REST_Response {
		if ( ! class_exists( Subscription::class ) ) {
			return $this->fail( 'subscriptions_unavailable', [ 'subscription_id' => $paddle_subscription_id, 'transaction_id' => $transaction_id ] );
		}

		// Idempotency: a renewal order already exists for this Paddle charge.
		if ( Helper::get_order_id_by_meta( '_paddle_transaction_id', $transaction_id ) ) {
			return $this->ack( 'already_processed' );
		}

		// Authoritative re-fetch — never trust the webhook body for paid status.
		// A renewal is recorded only against a genuinely completed/paid Paddle
		// transaction that actually belongs to this subscription (mirrors
		// handle_transaction); a forged body alone can't record a renewal.
		$transaction = PaddleService::init()->get_transaction( $transaction_id );
		if ( is_wp_error( $transaction ) ) {
			return $this->fail( 'fetch_failed', [ 'subscription_id' => $paddle_subscription_id, 'transaction_id' => $transaction_id, 'error' => $transaction->get_error_message() ] );
		}

		if ( ! in_array( $transaction->status ?? '', [ 'completed', 'paid' ], true ) ) {
			return $this->fail( 'transaction_not_paid', [ 'subscription_id' => $paddle_subscription_id, 'transaction_id' => $transaction_id, 'status' => $transaction->status ?? '' ] );
		}

		// The verified transaction must reference the same subscription the body
		// claims — otherwise an unrelated (but real) transaction id could be used
		// to settle this subscription's renewal.
		$txn_subscription_id = isset( $transaction->subscription_id ) ? (string) $transaction->subscription_id : '';
		if ( $txn_subscription_id && $txn_subscription_id !== $paddle_subscription_id ) {
			return $this->fail( 'subscription_mismatch', [ 'subscription_id' => $paddle_subscription_id, 'transaction_subscription_id' => $txn_subscription_id, 'transaction_id' => $transaction_id ] );
		}

		$subscription = $this->resolve_subscription( $paddle_subscription_id );
		if ( ! $subscription ) {
			return $this->fail( 'subscription_not_found', [ 'subscription_id' => $paddle_subscription_id, 'transaction_id' => $transaction_id ] );
		}

		// Reuse the pending renewal order the scheduler already generated for this
		// cycle (Paddle subscriptions are externally-managed, so the scheduler puts
		// the subscription on-hold and creates an awaiting-payment renewal order).
		// Only create a fresh one if there isn't a pending renewal to settle.
		$renewal  = $subscription->get_last_order( 'all', 'renewal' );
		$reusable = $renewal && ! is_wp_error( $renewal ) && $renewal->has_status( 'pending_payment' );

		if ( ! $reusable ) {
			// Create the renewal order via the canonical path.
			$renewal = SubscriptionUtils::create_renewal_order( $subscription );
			if ( is_wp_error( $renewal ) ) {
				return $this->fail( 'renewal_create_failed', [ 'subscription_id' => $paddle_subscription_id, 'transaction_id' => $transaction_id, 'error' => $renewal->get_error_message() ] );
			}
		}

		// Stamp the Paddle txn id for dedup + traceability, then let the
		// subscription record the payment (advances dates, reactivates, runs the
		// full order payment-complete flow on its last order).
		$renewal->set_transaction_id( $transaction_id );
		$renewal->update_meta_data( '_paddle_transaction_id', $transaction_id );
		$renewal->update_meta_data( '_paddle_subscription_id', $paddle_subscription_id );
		$renewal->save();

		$subscription->payment_complete( $transaction_id );
		$subscription->add_order_note( __( 'Subscription renewal recorded via Paddle webhook.', 'storeengine' ) );

		return $this->ack( 'renewal_recorded' );
	}

	/**
	 * Apply a Paddle subscription lifecycle change to the StoreEngine subscription.
	 *
	 * @param object $data       Paddle subscription payload (->id is the Paddle sub id).
	 * @param string $new_status StoreEngine subscription status to move to.
	 *
	 * @return WP_REST_Response
	 */
	protected function update_subscription_status( $data, string $new_status ): WP_REST_Response {
		if ( ! class_exists( Subscription::class ) ) {
			return $this->fail( 'subscriptions_unavailable', [ 'new_status' => $new_status ] );
		}

		$paddle_subscription_id = $data->id ?? '';
		if ( ! $paddle_subscription_id ) {
			return $this->fail( 'no_subscription_id', [ 'new_status' => $new_status ] );
		}

		$subscription = $this->resolve_subscription( $paddle_subscription_id );
		if ( ! $subscription ) {
			return $this->fail( 'subscription_not_found', [ 'subscription_id' => $paddle_subscription_id, 'new_status' => $new_status ] );
		}

		if ( $subscription->has_status( $new_status ) || ! $subscription->can_be_updated_to( $new_status ) ) {
			return $this->ack( 'no_change' );
		}

		$subscription->update_status(
			$new_status,
			sprintf(
				/* translators: %s: Paddle subscription status event */
				__( 'Status updated via Paddle webhook (%s).', 'storeengine' ),
				$new_status
			)
		);
		$subscription->save();

		return $this->ack( 'subscription_updated' );
	}

	/**
	 * Resolve a Paddle subscription id to a StoreEngine Subscription.
	 *
	 * Prefers the subscription entity (stamped with `_paddle_subscription_id` when
	 * created — see Hooks::link_paddle_subscription). Falls back to the initial
	 * order → subscription lookup.
	 *
	 * @param string $paddle_subscription_id
	 *
	 * @return Subscription|null
	 */
	protected function resolve_subscription( string $paddle_subscription_id ): ?Subscription {
		$id = Helper::get_order_id_by_meta( '_paddle_subscription_id', $paddle_subscription_id );
		if ( ! $id ) {
			return null;
		}

		$entity = Helper::get_order( $id );
		if ( is_wp_error( $entity ) || ! $entity ) {
			return null;
		}

		if ( 'subscription' === $entity->get_type() ) {
			return Subscription::get_subscription( $id );
		}

		// `$id` is the initial order — find the subscription created from it.
		$query = new SubscriptionCollection( [
			'where' => [
				[
					'relation' => 'AND',
					'key'      => 'parent_order_id',
					'value'    => $id,
					'compare'  => '=',
					'type'     => 'NUMERIC',
				],
			],
		] );

		foreach ( $query as $subscription ) {
			return $subscription;
		}

		return null;
	}

	protected function get_webhook_secret(): string {
		$is_live = $this->gateway->get_option( 'is_production', true );
		$secret  = $is_live
			? $this->gateway->get_option( 'webhook_secret', '' )
			: $this->gateway->get_option( 'test_webhook_secret', '' );

		// Trim: a pasted secret commonly carries a trailing newline/space, which
		// silently breaks the HMAC — and therefore every live webhook delivery.
		return trim( (string) $secret );
	}

	/**
	 * Verify the Paddle-Signature header.
	 *
	 * Header format: `ts=<unix>;h1=<hmac_sha256(ts:rawbody)>`.
	 *
	 * @link https://developer.paddle.com/webhooks/signature-verification
	 */
	protected function verify_signature( WP_REST_Request $request, string $raw, string $secret ): bool {
		// WordPress normalises "Paddle-Signature" to the "paddle_signature" key.
		$header = $request->get_header( 'paddle_signature' );
		if ( ! $header ) {
			return false;
		}

		$parts = [];
		foreach ( explode( ';', $header ) as $segment ) {
			$pair = array_pad( explode( '=', $segment, 2 ), 2, '' );
			$parts[ trim( $pair[0] ) ] = trim( $pair[1] );
		}

		if ( empty( $parts['ts'] ) || empty( $parts['h1'] ) ) {
			return false;
		}

		$expected = hash_hmac( 'sha256', $parts['ts'] . ':' . $raw, $secret );

		return hash_equals( $expected, $parts['h1'] );
	}

	/**
	 * Acknowledge a webhook with HTTP 200 so Paddle does not retry-storm logical
	 * no-ops (order not found, already paid, etc.).
	 */
	protected function ack( string $status ): WP_REST_Response {
		return new WP_REST_Response( [ 'status' => $status ], 200 );
	}

	/**
	 * Acknowledge a webhook that could NOT be processed, and write a log line so
	 * the failure is discoverable (StoreEngine logs / debug.log) for manual
	 * reconciliation. Still returns HTTP 200 so Paddle doesn't retry-storm — the
	 * log is the signal, not the status code.
	 *
	 * @param string $status  Short machine status (also returned in the body).
	 * @param array  $context Extra key=value pairs to include in the log line.
	 */
	protected function fail( string $status, array $context = [] ): WP_REST_Response {
		$this->log_failure( $status, $context );

		return $this->ack( $status );
	}

	protected function log_failure( string $status, array $context = [] ): void {
		$suffix = '';
		foreach ( $context as $key => $value ) {
			$suffix .= sprintf( ' %s=%s', $key, is_scalar( $value ) ? $value : wp_json_encode( $value ) );
		}

		Helper::log_error( sprintf( '[StoreEngine][Paddle] Webhook not processed: %s.%s', $status, $suffix ), false );
	}
}
