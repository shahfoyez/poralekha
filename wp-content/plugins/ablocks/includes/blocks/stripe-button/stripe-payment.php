<?php

namespace ABlocks\Blocks\StripeButton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StripePayment {

	const API_BASE_URL = 'https://api.stripe.com/v1';

	private string $api;

	private array $headers = [];
	private array $body    = [];

	public function __construct( string $api, array $data ) {
		$this->api     = $api;
		$this->headers = [
			'Authorization' => 'Bearer ' . $this->api,
			'Content-Type'  => 'application/x-www-form-urlencoded',
		];
		$this->set_body( $data );
	}

	private function set_body( array $data ) : void {
		$unit_amount = floatval( $data['unit_amount'] ?? 0 );
		$quantity    = intval( $data['quantity'] ?? 0 );
		// process will be failed if unit_amount & quantity invalid
		if (
			empty( $data ) ||
			empty( $unit_amount ) ||
			empty( $quantity )
		) {
			return;
		}

		$currency = $data['currency'] ?? 'usd';

		$this->body    = [
			'payment_method_types' => [ 'card' ],
			'mode'                 => 'payment',
			'line_items[0][quantity]' => $quantity,
			'line_items[0][price_data][currency]' => $currency,
			'line_items[0][price_data][unit_amount]' => $unit_amount,
			'line_items[0][price_data][product_data][name]' => $data['product_name'] ?? 'PRDUCT NAME',
		];
		$shipping_charge = floatval( $data['shipping_charge'] ?? 0 );
		if ( ! empty( $shipping_charge ) ) {
			$this->body['shipping_options'] = [
				[
					'shipping_rate_data' => [
						'type'         => 'fixed_amount',
						'display_name' => __( 'Shipping Charge: ', 'ablocks' ),
						'fixed_amount' => [
							'amount'   => $shipping_charge,
							'currency' => $currency,
						],
					]
				]
			];
		}
		$tax_rate = ( $data['tax_rate'] ?? [] );
		if ( ! empty( $tax_rate ) ) {
			// exclusive, inclusive, unspecified
			$this->body['line_items'][0]['price_data']['tax_behavior'] = $data['tax_behavior'] ?? 'unspecified';
			$this->body['line_items'][0]['tax_rates']                  = [
				$tax_rate
			];
		}

		if ( ! empty( $data['success_url'] ?? 0 ) ) {
			$this->body['success_url'] = esc_url( $data['success_url'] );
		}

		if ( ! empty( $data['cancel_url'] ?? 0 ) ) {
			$this->body['cancel_url'] = esc_url( $data['cancel_url'] );
		}
	}

	public function process() : ?array {
		if ( empty( $this->body ) ) {
			return null;
		}

		$res = wp_remote_post(self::API_BASE_URL . '/checkout/sessions', [
			'headers' => $this->headers,
			'body'    => http_build_query( $this->body ),
		]);
		$response = wp_remote_retrieve_body( $res );
		if (
			is_wp_error( $res ) ||
			empty( $response )
		) {
			return null;
		}

		return json_decode(
			$response,
			true
		);
	}


	public function taxe_rates() : ?array {
		$res = wp_remote_get(self::API_BASE_URL . '/tax_rates', [
			'headers' => $this->headers
		]);
		$response = wp_remote_retrieve_body( $res );
		if (
			is_wp_error( $res ) ||
			empty( $response )
		) {
			return null;
		}

		return json_decode(
			$response,
			true
		);
	}
}
