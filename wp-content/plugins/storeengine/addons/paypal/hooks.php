<?php

namespace StoreEngine\Addons\Paypal;

use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusException;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidOrderStatusTransitionException;
use StoreEngine\Classes\OrderContext;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {
	protected static ?Hooks $instance = null;
	protected GatewayPaypal $gateway;

	public static function init( $gateway ) {
		if ( null === self::$instance ) {
			self::$instance          = new self();
			self::$instance->gateway = $gateway;

			if ( $gateway->is_enabled ) {
				add_filter( 'storeengine/frontend_scripts_payment_method_data', [ self::$instance, 'gateway_javascript_params' ] );
				add_filter( 'storeengine/checkout/gateway/paypal/data', [ self::$instance, 'expose_gateway_data' ], 10, 2 );
				add_action( 'storeengine/checkout/before_place_order_payload/paypal', [ self::class, 'remap_react_payment_payload' ] );
				add_action( 'storeengine/checkout/before_pay_order_payload/paypal', [ self::class, 'remap_react_payment_payload_for_pay_order' ], 10, 2 );
				add_action( 'storeengine/payment_gateway/paypal/save_settings', [ self::$instance, 'setup_paypal_webhooks' ] );
			}
		}
	}

	public function gateway_javascript_params( $payment_method ) {
		if ( $this->gateway->is_available() ) {
			$payment_method['paypal'] = [
				'client_id' => $this->gateway->get_option( 'client_id_' . ( $this->gateway->get_option( 'is_production', true ) ? 'production' : 'sandbox' ), '' ),
			];
		}

		return $payment_method;
	}

	/**
	 * React adapter forwards the captured PayPal order id in payment_payload.
	 * Persist it on the draft order so GatewayPaypal::process_payment() can
	 * capture it.
	 */
	public static function remap_react_payment_payload( array $payment_data ) {
		if ( empty( $payment_data['paypal_order_id'] ) ) {
			return;
		}
		$paypal_order_id = sanitize_text_field( (string) $payment_data['paypal_order_id'] );
		$draft           = Helper::get_recent_draft_order( get_current_user_id(), null, true );
		if ( $draft ) {
			$draft->update_meta_data( '_paypal_order_id', $paypal_order_id );
			$draft->save();
		}
	}

	/**
	 * Pay-order variant: persist the PayPal order id on the existing order
	 * being re-paid instead of guessing via the most-recent draft.
	 */
	public static function remap_react_payment_payload_for_pay_order( array $payment_data, Order $order ) {
		if ( empty( $payment_data['paypal_order_id'] ) ) {
			return;
		}
		$paypal_order_id = sanitize_text_field( (string) $payment_data['paypal_order_id'] );
		$order->update_meta_data( '_paypal_order_id', $paypal_order_id );
		$order->save();
	}

	/**
	 * Expose PayPal config to the shared client adapter (React Quick Checkout
	 * + future legacy migration). Same data as gateway_javascript_params plus
	 * the currency Buttons needs.
	 */
	public function expose_gateway_data( array $data, $gateway ): array {
		if ( ! is_object( $gateway ) || ! method_exists( $gateway, 'get_option' ) ) {
			return $data;
		}
		$is_production         = (bool) $gateway->get_option( 'is_production', true );
		$data['client_id']     = (string) $gateway->get_option( 'client_id_' . ( $is_production ? 'production' : 'sandbox' ), '' );
		$data['is_production'] = $is_production;
		$data['currency']      = Formatting::get_currency();

		return $data;
	}

	/**
	 * @param Order $order
	 *
	 * @return void
	 * @throws StoreEngineInvalidOrderStatusException
	 * @throws StoreEngineInvalidOrderStatusTransitionException
	 * @throws StoreEngineException
	 */
	public function change_payment_status( Order $order ) {
		if ( 'paypal' !== $order->get_payment_method() ) {
			return;
		}

		$paypal        = PaypalExpressService::init( $this->gateway );
		$paypal_result = $paypal->get_order( $order->get_meta( '_paypal_order_id', true, 'edit' ) );

		if ( ! empty( $paypal_result ) && 'COMPLETED' !== $paypal_result->status ) {
			return;
		}

		$order_context = new OrderContext( $order->get_status() );
		$order_context->proceed_to_next_status( 'payment_initiate', $order );
		$order_context->proceed_to_next_status( 'payment_confirm', $order );
		$order->save();
	}

	/**
	 * @throws StoreEngineException
	 */
	public function setup_paypal_webhooks( $gateway ) {
		PaypalExpressService::init( $gateway )->create_webhook( [
			'url'         => rest_url( 'storeengine/v1/payment/paypal/webhook' ),
			'event_types' => [
				[ 'name' => 'BILLING.SUBSCRIPTION.ACTIVATED' ],
				[ 'name' => 'PAYMENT.SALE.COMPLETED' ],
				[ 'name' => 'BILLING.SUBSCRIPTION.PAYMENT.FAILED' ],
			],
		] );
	}
}
