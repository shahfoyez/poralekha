<?php
/**
 * Maps a customer / order address into Stripe Tax customer_details payload.
 */

namespace StoreEngine\Addons\Stripe\Tax;

use StoreEngine\Classes\Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AddressMapper {

	public static function from_customer( $customer = null, ?Order $order = null ): array {
		$address_source = self::pick_address( $customer, $order );

		$details = [
			'address'        => [
				'line1'       => $address_source['address_1'] ?? '',
				'line2'       => $address_source['address_2'] ?? '',
				'city'        => $address_source['city'] ?? '',
				'state'       => $address_source['state'] ?? '',
				'postal_code' => $address_source['postcode'] ?? '',
				'country'     => $address_source['country'] ?? '',
			],
			'address_source' => 'shipping',
		];

		$user_id = self::resolve_user_id( $customer, $order );
		if ( $user_id ) {
			$status = (string) get_user_meta( $user_id, '_stripe_tax_status', true );
			if ( in_array( $status, [ 'exempt', 'reverse' ], true ) ) {
				$details['taxability_override'] = $status;
			}
		}

		$tax_id = self::pick_tax_id( $customer, $order );
		if ( $tax_id ) {
			$details['tax_ids'] = [ $tax_id ];
		}

		return apply_filters( 'storeengine/stripe_tax/customer_details', $details, $customer, $order );
	}

	private static function pick_address( $customer, ?Order $order ): array {
		if ( $order instanceof Order ) {
			return [
				'address_1' => $order->get_shipping_address_1() ?: $order->get_billing_address_1(),
				'address_2' => $order->get_shipping_address_2() ?: $order->get_billing_address_2(),
				'city'      => $order->get_shipping_city() ?: $order->get_billing_city(),
				'state'     => $order->get_shipping_state() ?: $order->get_billing_state(),
				'postcode'  => $order->get_shipping_postcode() ?: $order->get_billing_postcode(),
				'country'   => $order->get_shipping_country() ?: $order->get_billing_country(),
			];
		}

		if ( $customer && method_exists( $customer, 'get_shipping_country' ) ) {
			$ship = [
				'address_1' => (string) $customer->get_shipping_address_1(),
				'address_2' => (string) $customer->get_shipping_address_2(),
				'city'      => (string) $customer->get_shipping_city(),
				'state'     => (string) $customer->get_shipping_state(),
				'postcode'  => (string) $customer->get_shipping_postcode(),
				'country'   => (string) $customer->get_shipping_country(),
			];
			if ( $ship['country'] ) {
				return $ship;
			}
			return [
				'address_1' => (string) $customer->get_billing_address_1(),
				'address_2' => (string) $customer->get_billing_address_2(),
				'city'      => (string) $customer->get_billing_city(),
				'state'     => (string) $customer->get_billing_state(),
				'postcode'  => (string) $customer->get_billing_postcode(),
				'country'   => (string) $customer->get_billing_country(),
			];
		}

		return [];
	}

	private static function resolve_user_id( $customer, ?Order $order ): int {
		if ( $order instanceof Order ) {
			return (int) $order->get_customer_id();
		}
		if ( $customer && method_exists( $customer, 'get_id' ) ) {
			return (int) $customer->get_id();
		}

		return 0;
	}

	private static function pick_tax_id( $customer, ?Order $order ): ?array {
		$value = '';
		$type  = '';

		if ( $order instanceof Order && method_exists( $order, 'get_meta' ) ) {
			$value = (string) $order->get_meta( '_stripe_tax_id_value' );
			$type  = (string) $order->get_meta( '_stripe_tax_id_type' );
		}

		if ( ! $value && $customer && method_exists( $customer, 'get_id' ) ) {
			$user_id = (int) $customer->get_id();
			$value   = (string) get_user_meta( $user_id, '_stripe_tax_id_value', true );
			$type    = (string) get_user_meta( $user_id, '_stripe_tax_id_type', true );
		}

		if ( ! $value || ! $type ) {
			return null;
		}

		return [
			'type'  => $type,
			'value' => $value,
		];
	}
}
