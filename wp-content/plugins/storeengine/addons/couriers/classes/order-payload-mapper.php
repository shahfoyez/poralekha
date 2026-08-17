<?php

namespace StoreEngine\Addons\Couriers\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Maps a StoreEngine order to the unified shipment payload shape used by
 * every courier provider. Single source of truth for both AutoPush and the
 * manual create-shipment REST endpoint, so the two flows stay in lockstep.
 */
final class OrderPayloadMapper {

	/**
	 * Build the standard payload (recipient, address, COD, weight, item summary).
	 *
	 * @param object $order  StoreEngine order (AbstractOrder).
	 * @param array  $cfg    Auto-push config (default_weight_kg, cod_methods, use_cod_when).
	 */
	public static function build_payload( $order, array $cfg = [] ): array {
		$method = method_exists( $order, 'get_payment_method' )
			? (string) $order->get_payment_method()
			: '';

		$cod_methods = (array) ( $cfg['cod_methods'] ?? [] );
		$is_cod      = ! empty( $cod_methods ) && in_array( $method, $cod_methods, true );
		$cod_amount  = $is_cod && method_exists( $order, 'get_total' ) ? (float) $order->get_total() : 0.0;

		$first = $last = $phone = $line1 = $line2 = $city = $state = $postcode = $country = '';
		if ( method_exists( $order, 'get_billing_address' ) ) {
			$addr     = $order->get_billing_address();
			$first    = isset( $addr->first_name ) ? (string) $addr->first_name : '';
			$last     = isset( $addr->last_name ) ? (string) $addr->last_name : '';
			$phone    = isset( $addr->phone ) ? (string) $addr->phone : '';
			$line1    = isset( $addr->address_1 ) ? (string) $addr->address_1 : '';
			$line2    = isset( $addr->address_2 ) ? (string) $addr->address_2 : '';
			$city     = isset( $addr->city ) ? (string) $addr->city : '';
			$state    = isset( $addr->state ) ? (string) $addr->state : '';
			$postcode = isset( $addr->postcode ) ? (string) $addr->postcode : '';
			$country  = isset( $addr->country ) ? (string) $addr->country : '';
		}

		$item_summary = [];
		if ( method_exists( $order, 'get_items' ) ) {
			foreach ( $order->get_items( 'line_item' ) as $item ) {
				$name = method_exists( $item, 'get_name' ) ? (string) $item->get_name() : '';
				$qty  = method_exists( $item, 'get_quantity' ) ? (int) $item->get_quantity() : 1;
				if ( $name ) $item_summary[] = $name . ' x' . max( 1, $qty );
			}
		}

		return [
			'customer_name'    => trim( $first . ' ' . $last ),
			'customer_phone'   => $phone,
			'customer_address' => trim( $line1 . ' ' . $line2 ),
			'city'             => $city,
			'state'            => $state,
			'postcode'         => $postcode,
			'country'          => $country,
			'cod_amount'       => $cod_amount,
			'weight_kg'        => (float) ( $cfg['default_weight_kg'] ?? 0.5 ),
			'item_description' => implode( ', ', array_slice( $item_summary, 0, 5 ) ),
		];
	}

	/**
	 * Shiprocket-shaped line items: [{name, sku, units, selling_price}, ...].
	 */
	public static function build_line_items( $order ): array {
		$out = [];
		if ( ! method_exists( $order, 'get_items' ) ) return $out;

		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$name  = method_exists( $item, 'get_name' ) ? (string) $item->get_name() : '';
			$qty   = method_exists( $item, 'get_quantity' ) ? max( 1, (int) $item->get_quantity() ) : 1;
			$total = method_exists( $item, 'get_total' ) ? (float) $item->get_total() : 0.0;
			$sku   = '';
			if ( method_exists( $item, 'get_product' ) ) {
				$product = $item->get_product();
				if ( $product && method_exists( $product, 'get_sku' ) ) {
					$sku = (string) $product->get_sku();
				}
			}
			$out[] = [
				'name'          => $name,
				'sku'           => $sku,
				'units'         => $qty,
				'selling_price' => $qty > 0 ? round( $total / $qty, 2 ) : 0.0,
			];
		}

		return $out;
	}

	/**
	 * Provider-specific extras the admin shouldn't have to type. Currently
	 * only Shiprocket needs anything beyond the standard payload.
	 *
	 * @return array<string,mixed>
	 */
	public static function build_provider_extras( $order, string $provider_id ): array {
		if ( 'shiprocket' === $provider_id ) {
			$subtotal = method_exists( $order, 'get_subtotal' )
				? (float) $order->get_subtotal()
				: ( method_exists( $order, 'get_total' ) ? (float) $order->get_total() : 0.0 );

			return [
				'line_items' => self::build_line_items( $order ),
				'subtotal'   => $subtotal,
			];
		}

		return [];
	}
}
