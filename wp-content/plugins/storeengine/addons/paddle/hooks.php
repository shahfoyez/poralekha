<?php

namespace StoreEngine\Addons\Paddle;

use StoreEngine\Classes\Order\OrderItemProduct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {

	public static function init() {
		add_action( 'storeengine/checkout/create_order_line_item', [ __CLASS__, 'save_order_item_meta' ] );
		add_filter( 'storeengine/checkout/gateway/paddle/data', [ __CLASS__, 'expose_gateway_data' ], 10, 2 );
		add_action( 'storeengine/subscription/checkout_subscription_created', [ __CLASS__, 'link_paddle_subscription' ], 10, 2 );
	}

	/**
	 * Flag Paddle-backed subscriptions as externally managed and carry the Paddle
	 * subscription id onto the subscription entity.
	 *
	 * Paddle is the merchant of record and bills renewals itself, so StoreEngine's
	 * internal scheduler must NOT try to charge them (it can't — Paddle has no
	 * `process_scheduled_payment`). Marking the subscription as manual-renewal
	 * keeps the scheduler from dispatching a gateway charge; the renewal is
	 * recorded instead by the Paddle webhook (StoreEngine\Addons\Paddle\API).
	 *
	 * The Paddle subscription id is stamped here (when the originating order's
	 * payment already captured it) so recurring-renewal webhooks can resolve back
	 * to this subscription.
	 *
	 * @param mixed $subscription The newly created Subscription.
	 * @param mixed $order        The originating order.
	 */
	public static function link_paddle_subscription( $subscription, $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_payment_method' ) || 'paddle' !== $order->get_payment_method() ) {
			return;
		}

		if ( method_exists( $subscription, 'set_requires_manual_renewal' ) ) {
			$subscription->set_requires_manual_renewal( true );
		}

		$paddle_subscription_id = $order->get_meta( '_paddle_subscription_id' );
		if ( $paddle_subscription_id ) {
			$subscription->update_meta_data( '_paddle_subscription_id', $paddle_subscription_id );
		}

		$subscription->save();
	}

	public static function save_order_item_meta( OrderItemProduct $item ) {
		$product_tax_category = get_post_meta( $item->get_product_id(), '_storeengine_paddle_tax_category', true );
		if ( empty( $product_tax_category ) ) {
			return;
		}

		$item->add_meta_data( '_paddle_tax_category', $product_tax_category );
	}

	/**
	 * Expose Paddle config to the shared client adapter.
	 *
	 * No nonce is generated here — Paddle now talks to the server via the
	 * publishable-key-authenticated REST endpoint
	 * `/storeengine/v1/checkout/payment-intent/paddle` (see
	 * GatewayPaddle::create_intent()).
	 */
	public static function expose_gateway_data( array $data, $gateway ): array {
		if ( ! is_object( $gateway ) || ! method_exists( $gateway, 'get_option' ) ) {
			return $data;
		}

		$is_production            = (bool) $gateway->get_option( 'is_production', true );
		$data['sandbox_mode']     = ! $is_production;
		$data['client_token']     = (string) $gateway->get_option( $is_production ? 'client_token' : 'test_client_token', '' );
		$data['checkout_theme']   = (string) $gateway->get_option( 'checkout_theme', 'light' );
		$data['checkout_variant'] = (string) $gateway->get_option( 'checkout_variant', 'one-page' );

		return $data;
	}

}
