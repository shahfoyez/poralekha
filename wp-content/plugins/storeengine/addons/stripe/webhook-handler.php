<?php
/**
 * Native Stripe Billing webhook receiver.
 *
 * When native Stripe subscriptions are enabled, Stripe (not StoreEngine's
 * scheduler) bills renewals. This server-to-server endpoint records those
 * renewals and syncs subscription lifecycle changes back into StoreEngine.
 *
 * Mirrors the Paddle externally-managed pattern (addons/paddle/api.php): resolve
 * the StoreEngine subscription from the provider subscription id, reuse/create a
 * renewal order, mark it paid (which advances the cycle), and map provider
 * statuses to StoreEngine statuses.
 *
 * Signature is verified with the Stripe SDK (Webhook::constructEvent) using the
 * endpoint signing secret stored when the webhook was registered.
 */

namespace StoreEngine\Addons\Stripe;

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Addons\Subscription\Classes\SubscriptionCollection;
use StoreEngine\Addons\Subscription\Classes\Utils as SubscriptionUtils;
use StoreEngine\Stripe\Webhook as StripeWebhook;
use StoreEngine\Utils\Helper;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WebhookHandler {

	protected GatewayStripe $gateway;

	public static function init( $gateway ): void {
		$self          = new self();
		$self->gateway = $gateway;

		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route(
			STOREENGINE_PLUGIN_SLUG . '/v1',
			'/stripe/trigger',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'handle_webhook' ],
					// External caller (Stripe). Authenticity is the signature check.
					'permission_callback' => '__return_true',
				],
			]
		);
	}

	public function handle_webhook( WP_REST_Request $request ): WP_REST_Response {
		$raw    = $request->get_body();
		$secret = $this->get_webhook_secret();
		$sig    = $request->get_header( 'stripe_signature' );

		if ( ! $secret ) {
			return $this->fail( 'no_webhook_secret', [ 'hint' => 'Save the Stripe webhook signing secret in the gateway settings.' ] );
		}

		try {
			$event = StripeWebhook::constructEvent( $raw, (string) $sig, $secret );
		} catch ( \Throwable $e ) {
			$this->log_failure( 'invalid_signature', [ 'error' => $e->getMessage() ] );

			return new WP_REST_Response( [ 'error' => 'invalid_signature' ], 401 );
		}

		$type   = $event->type ?? '';
		$object = $event->data->object ?? null;

		if ( ! $object ) {
			return $this->ack( 'no_data' );
		}

		try {
			switch ( $type ) {
				case 'invoice.paid':
				case 'invoice.payment_succeeded':
					return $this->handle_invoice_paid( $object );

				case 'invoice.payment_failed':
					return $this->handle_invoice_failed( $object );

				case 'customer.subscription.updated':
					return $this->handle_subscription_updated( $object );

				case 'customer.subscription.deleted':
					return $this->update_subscription_status( $object->id ?? '', 'cancelled' );

				default:
					return $this->ack( 'ignored' );
			}
		} catch ( \Throwable $e ) {
			Helper::log_error( $e );

			// 200 on internal errors so Stripe doesn't retry-storm; the failure is
			// logged for manual reconciliation.
			return $this->ack( 'error' );
		}
	}

	/**
	 * Record a renewal charge. Only `subscription_cycle` invoices are renewals —
	 * the very first invoice (`subscription_create`) is the initial order, which
	 * is already recorded at checkout.
	 *
	 * @param object $invoice Stripe Invoice object.
	 */
	protected function handle_invoice_paid( $invoice ): WP_REST_Response {
		$stripe_subscription_id = $invoice->subscription ?? '';
		$billing_reason         = $invoice->billing_reason ?? '';
		$invoice_id             = $invoice->id ?? '';
		$transaction_id         = $invoice->payment_intent ?? $invoice_id;

		if ( ! $stripe_subscription_id ) {
			return $this->ack( 'not_a_subscription_invoice' );
		}

		// The initial subscription invoice is recorded at checkout — skip it.
		if ( 'subscription_cycle' !== $billing_reason ) {
			return $this->ack( 'not_a_renewal' );
		}

		if ( ! class_exists( Subscription::class ) ) {
			return $this->fail( 'subscriptions_unavailable', [ 'subscription_id' => $stripe_subscription_id ] );
		}

		// Idempotency: this Stripe invoice was already recorded.
		if ( $invoice_id && Helper::get_order_id_by_meta( '_stripe_invoice_id', $invoice_id ) ) {
			return $this->ack( 'already_processed' );
		}

		$subscription = $this->resolve_subscription( $stripe_subscription_id );
		if ( ! $subscription ) {
			return $this->fail( 'subscription_not_found', [ 'subscription_id' => $stripe_subscription_id, 'invoice_id' => $invoice_id ] );
		}

		// Reuse the pending renewal order the scheduler generated for this cycle;
		// otherwise create a fresh one.
		$renewal  = $subscription->get_last_order( 'all', 'renewal' );
		$reusable = $renewal && ! is_wp_error( $renewal ) && $renewal->has_status( 'pending_payment' );

		if ( ! $reusable ) {
			$renewal = SubscriptionUtils::create_renewal_order( $subscription );
			if ( is_wp_error( $renewal ) ) {
				return $this->fail( 'renewal_create_failed', [ 'subscription_id' => $stripe_subscription_id, 'error' => $renewal->get_error_message() ] );
			}
		}

		$renewal->set_transaction_id( $transaction_id );
		$renewal->update_meta_data( '_stripe_invoice_id', $invoice_id );
		$renewal->update_meta_data( '_stripe_subscription_id', $stripe_subscription_id );
		$renewal->save();

		$subscription->payment_complete( $transaction_id );
		$subscription->add_order_note( __( 'Subscription renewal recorded via Stripe webhook.', 'storeengine' ) );

		return $this->ack( 'renewal_recorded' );
	}

	/**
	 * @param object $invoice Stripe Invoice object.
	 */
	protected function handle_invoice_failed( $invoice ): WP_REST_Response {
		$stripe_subscription_id = $invoice->subscription ?? '';
		if ( ! $stripe_subscription_id ) {
			return $this->ack( 'not_a_subscription_invoice' );
		}

		// Stripe runs its own dunning/retries; put the subscription on hold. On
		// final failure Stripe sends customer.subscription.deleted → cancelled.
		return $this->update_subscription_status( $stripe_subscription_id, 'on_hold' );
	}

	/**
	 * @param object $subscription Stripe Subscription object.
	 */
	protected function handle_subscription_updated( $subscription ): WP_REST_Response {
		$status_map = [
			'active'             => 'active',
			'trialing'           => 'active',
			'past_due'           => 'on_hold',
			'unpaid'             => 'on_hold',
			'paused'             => 'on_hold',
			'incomplete'         => 'on_hold',
			'incomplete_expired' => 'cancelled',
			'canceled'           => 'cancelled',
		];

		$stripe_status = $subscription->status ?? '';
		$new_status    = $status_map[ $stripe_status ] ?? '';

		if ( ! $new_status ) {
			return $this->ack( 'ignored_status' );
		}

		return $this->update_subscription_status( $subscription->id ?? '', $new_status );
	}

	protected function update_subscription_status( string $stripe_subscription_id, string $new_status ): WP_REST_Response {
		if ( ! class_exists( Subscription::class ) || ! $stripe_subscription_id ) {
			return $this->fail( 'subscriptions_unavailable', [ 'new_status' => $new_status ] );
		}

		$subscription = $this->resolve_subscription( $stripe_subscription_id );
		if ( ! $subscription ) {
			return $this->fail( 'subscription_not_found', [ 'subscription_id' => $stripe_subscription_id, 'new_status' => $new_status ] );
		}

		if ( $subscription->has_status( $new_status ) || ! $subscription->can_be_updated_to( $new_status ) ) {
			return $this->ack( 'no_change' );
		}

		// Suppress the cancel-sync feedback loop (a webhook-driven cancel must not
		// call Stripe to cancel again).
		SubscriptionSync::set_syncing_from_webhook( true );
		$subscription->update_status(
			$new_status,
			sprintf(
				/* translators: %s: Stripe subscription status */
				__( 'Status updated via Stripe webhook (%s).', 'storeengine' ),
				$new_status
			)
		);
		$subscription->save();
		SubscriptionSync::set_syncing_from_webhook( false );

		return $this->ack( 'subscription_updated' );
	}

	/**
	 * Resolve a Stripe subscription id to a StoreEngine Subscription, via the
	 * `_stripe_subscription_id` meta stamped at checkout. Mirrors Paddle.
	 */
	protected function resolve_subscription( string $stripe_subscription_id ): ?Subscription {
		$id = Helper::get_order_id_by_meta( '_stripe_subscription_id', $stripe_subscription_id );
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

		// $id is the initial order — find the subscription created from it.
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

		// Fall back to the secret captured when the endpoint was auto-registered.
		if ( ! $secret ) {
			$secret = get_option( 'storeengine_stripe_webhook_secret', '' );
		}

		return trim( (string) $secret );
	}

	protected function ack( string $status ): WP_REST_Response {
		return new WP_REST_Response( [ 'status' => $status ], 200 );
	}

	protected function fail( string $status, array $context = [] ): WP_REST_Response {
		$this->log_failure( $status, $context );

		return $this->ack( $status );
	}

	protected function log_failure( string $status, array $context = [] ): void {
		$suffix = '';
		foreach ( $context as $key => $value ) {
			$suffix .= sprintf( ' %s=%s', $key, is_scalar( $value ) ? $value : wp_json_encode( $value ) );
		}

		Helper::log_error( sprintf( '[StoreEngine][Stripe] Webhook not processed: %s.%s', $status, $suffix ), false );
	}
}
