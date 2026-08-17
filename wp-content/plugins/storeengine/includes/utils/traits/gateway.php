<?php

namespace StoreEngine\Utils\traits;

use StoreEngine\Payment\Gateways\PaymentGateway;
use StoreEngine\Payment_Gateways;

trait Gateway {

	public static function get_payment_gateways(): Payment_Gateways {
		return Payment_Gateways::init();
	}

	public static function get_payment_gateway( string $id ): ?PaymentGateway {
		return self::get_payment_gateways()->get_gateway( $id );
	}

	/**
	 * Get payment gateway class by order data.
	 *
	 * @param int|\StoreEngine\Classes\Order $order Order instance.
	 * @return PaymentGateway|bool
	 */
	public static function get_payment_gateway_by_order( $order ) {
		if ( ! is_object( $order ) ) {
			$order = self::get_order( absint( $order ) );
		}

		if ( is_wp_error( $order ) ) {
			return false;
		}

		if ( ! $order->get_payment_method() || ! self::get_payment_gateways() ) {
			return false;
		}

		// Resolve by id so a now-disabled gateway still loads for refunds/renewals.
		return self::get_payment_gateways()->get_gateway( $order->get_payment_method() ) ?: false;
	}
}
