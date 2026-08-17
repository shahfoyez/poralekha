<?php

namespace StoreEngine\Addons\Paddle;

use StoreEngine;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

class Assets {

	protected GatewayPaddle $gateway;

	public static function init( $gateway ) {
		$self          = new self();
		$self->gateway = $gateway;
		if ( $self->gateway->is_available() ) {
			add_action( 'storeengine/enqueue_frontend_scripts', [ $self, 'load_js_frontend' ] );
			add_filter( 'storeengine/frontend_scripts_payment_method_data', [ $self, 'gateway_javascript_params' ] );
		}
	}

	public function load_js_frontend() {
		if ( Helper::is_checkout() ) {
			wp_enqueue_script( 'storeengine-paddle-script', 'https://cdn.paddle.com/paddle/v2/paddle.js', [], false, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.NoExplicitVersion
		}
	}

	public function gateway_javascript_params( $payment_method ) {
		if ( $this->gateway->is_available() ) {
			$is_production = $this->gateway->get_option( 'is_production', true );
			if ( $is_production ) {
				$client_token = $this->gateway->get_option( 'client_token', '' );
			} else {
				$client_token = $this->gateway->get_option( 'test_client_token', '' );
			}

			$data = [
				'sandbox_mode' => ! $is_production,
				'client_token' => $client_token,
				'theme'        => $this->gateway->get_option( 'checkout_theme', 'light' ),
				'variant'      => $this->gateway->get_option( 'checkout_variant', 'one-page' ),
			];

			if ( Formatting::string_to_bool( get_query_var( 'order_pay' ) ) ) {
				$order = Helper::get_order( absint( get_query_var( 'order_id' ) ) );
				if ( ! is_wp_error( $order ) && $order->get_id() ) {
					$data['order_id']      = $order->get_id();
					$data['billing_email'] = $order->get_billing_email() ?: $order->get_customer()->get_email();
				}
			}

			$payment_method['paddle'] = $data;
		}

		return $payment_method;
	}
}
