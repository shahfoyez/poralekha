<?php
/**
 * GatewayAdapter — single contract for client-side payment-intent creation.
 *
 * Each payment gateway (Stripe, PayPal, Square, Razorpay, …) optionally
 * implements this interface so both the traditional /checkout/ page (vanilla JS)
 * and the embedded React checkout can share one REST endpoint
 * (`POST /storeengine/v1/checkout/payment-intent/{gateway_id}`) for
 * pre-confirmation client-side flows (Stripe PaymentIntents, PayPal order
 * creation, etc.).
 *
 * For gateways that don't need a client-confirmation step (offline / COD),
 * implementing this interface is unnecessary — the place_order endpoint runs
 * gateway->process_payment() directly.
 *
 * @since 1.8.2
 */

namespace StoreEngine\Interfaces;

use StoreEngine\Classes\Cart;
use StoreEngine\Classes\Order;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface GatewayAdapterInterface {

	/**
	 * Create the gateway's client-side intent for the given order.
	 *
	 * @return array|WP_Error Normalised payload the React adapter / vanilla-JS client
	 *                        understands. Common keys (any may be omitted):
	 *                          - client_secret    string  Stripe/Square style.
	 *                          - intent_id        string  PaymentIntent / Order id.
	 *                          - redirect_url     string  Hosted-page redirect (PayPal redirect flow, etc.).
	 *                          - requires_action  array   Gateway-specific extra step.
	 */
	public function create_intent( Order $order, Cart $cart );
}
