<?php

namespace StoreEngine\Addons\Paypal;

use StoreEngine;
use StoreEngine\Payment_Gateways;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

class Assets {
	protected GatewayPaypal $gateway;

	public static function init( $gateway ) {
		$self          = new self();
		$self->gateway = $gateway;
		add_action( 'wp_enqueue_scripts', [ $self, 'load_paypal_js_frontend' ], 10 );
	}

	public function load_paypal_js_frontend() {
		if ( Helper::is_checkout() ) {
			if ( $this->gateway->is_available() ) {
				$key_type = $this->gateway->get_option( 'is_production', true ) ? 'production' : 'sandbox';
				$args     = [
					'client-id'  => $this->gateway->get_option( 'client_id_' . $key_type, '' ),
					'components' => 'buttons',
					'currency'   => Formatting::get_currency(),
				];

				// Now rendering buttons only on checkout, for vault & save payment method add `|| Helper::is_add_payment_method_page()` in top level if.
				/*if ( StoreEngine::init()->get_cart() && StoreEngine::init()->get_cart()->has_subscription_product() ) {
					$args['vault']  = 'true';
					$args['intent'] = 'subscription';
				}

				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					$args['debug'] = 'true';
				}*/

				wp_enqueue_script( 'storeengine-paypal-script', add_query_arg( $args, 'https://www.paypal.com/sdk/js' ), [], null, true ); // phpcs:ignore WordPress.WP.EnqueuedResourceParameters.MissingVersion
			}
		}
	}
}
