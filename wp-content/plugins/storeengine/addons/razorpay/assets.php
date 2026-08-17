<?php

namespace StoreEngine\Addons\Razorpay;

use StoreEngine;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

class Assets {

	protected GatewayRazorpay $gateway;

	public static function init( $gateway ) {
		$self          = new self();
		$self->gateway = $gateway;
		if ( $self->gateway->is_available() ) {
			add_action( 'storeengine/enqueue_frontend_scripts', [ $self, 'load_js_frontend' ] );
			add_filter( 'storeengine/frontend_scripts_payment_method_data', [ $self, 'gateway_javascript_params' ] );
			add_filter( 'storeengine/checkout/gateway/razorpay/data', [ $self, 'expose_gateway_data' ], 10, 2 );
			add_action( 'storeengine/checkout/before_place_order_payload/razorpay', [ self::class, 'remap_react_payment_payload' ] );
			add_action( 'storeengine/checkout/before_pay_order_payload/razorpay', [ self::class, 'remap_react_payment_payload_for_pay_order' ], 10, 2 );
		}
	}

	public function load_js_frontend() {
		if ( Helper::is_checkout() ) {
			wp_enqueue_script( 'storeengine-razorpay-script', 'https://checkout.razorpay.com/v1/checkout.js', [], false, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
		}
	}

	public function gateway_javascript_params( $payment_method ) {
		global $storeengine_settings;

		if ( $this->gateway->is_available() ) {
			$cart       = StoreEngine::init()->get_cart();
			$order      = Helper::get_recent_draft_order( 0, null, false );
			$cart_total = $cart ? $cart->get_total( 'razorpay-js' ) : 0;
			$order_id   = $order ? $order->get_id() : null;

			if ( Formatting::string_to_bool( get_query_var( 'order_pay' ) ) ) {
				$order = Helper::get_order( absint( get_query_var( 'order_id' ) ) );
				if ( ! is_wp_error( $order ) && $order->get_id() ) {
					$cart_total = $order->get_total( 'razorpay-js' );
					$order_id   = $order->get_id();
				}
			}

			$payment_method['razorpay'] = [
				'key_id'              => $this->gateway->get_option( 'key_id' ),
				'cart_total'          => RazorpayService::get_razorpay_amount( $cart_total ),
				'store_name'          => $storeengine_settings->store_name,
				'store_logo'          => wp_get_attachment_url( $storeengine_settings->store_logo ),
				'order_id'            => $order_id,
				'store_primary_color' => $storeengine_settings->global_primary_color,
			];
		}

		return $payment_method;
	}

	/**
	 * React adapter forwards the captured `razorpay_payment_id` in
	 * payment_payload. Persist it on the draft order so
	 * GatewayRazorpay::process_payment() (which reads
	 * $order->get_meta('_razorpay_payment_id')) can verify + capture it.
	 */
	public static function remap_react_payment_payload( array $payment_data ) {
		if ( empty( $payment_data['razorpay_payment_id'] ) ) {
			return;
		}
		$razorpay_payment_id = sanitize_text_field( (string) $payment_data['razorpay_payment_id'] );
		$draft               = Helper::get_recent_draft_order( get_current_user_id(), null, true );
		if ( $draft ) {
			$draft->update_meta_data( '_razorpay_payment_id', $razorpay_payment_id );
			$draft->set_transaction_id( $razorpay_payment_id );
			$draft->save();
		}
	}

	/**
	 * Pay-order variant: persist the Razorpay payment id on the existing
	 * order being re-paid instead of guessing via the most-recent draft.
	 */
	public static function remap_react_payment_payload_for_pay_order( array $payment_data, Order $order ) {
		if ( empty( $payment_data['razorpay_payment_id'] ) ) {
			return;
		}
		$razorpay_payment_id = sanitize_text_field( (string) $payment_data['razorpay_payment_id'] );
		$order->update_meta_data( '_razorpay_payment_id', $razorpay_payment_id );
		$order->set_transaction_id( $razorpay_payment_id );
		$order->save();
	}

	/**
	 * Expose Razorpay branding to the shared client adapter (React Quick
	 * Checkout + future legacy migration). Per-order intent details (amount,
	 * currency, razorpay_order_id) come from GatewayRazorpay::create_intent()
	 * via the unified payment-intent REST endpoint.
	 */
	public function expose_gateway_data( array $data, $gateway ): array {
		global $storeengine_settings;
		if ( ! is_object( $gateway ) || ! method_exists( $gateway, 'get_option' ) ) {
			return $data;
		}
		$data['key_id']              = (string) $gateway->get_option( 'key_id' );
		$data['store_name']          = $storeengine_settings->store_name ?? '';
		$data['store_logo']          = ! empty( $storeengine_settings->store_logo ) ? wp_get_attachment_url( $storeengine_settings->store_logo ) : '';
		$data['store_primary_color'] = $storeengine_settings->global_primary_color ?? '';

		return $data;
	}
}
