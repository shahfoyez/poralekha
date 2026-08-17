<?php

namespace StoreEngine\Addons\Couriers\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractProvider implements ProviderInterface {

	/**
	 * @return array<string,mixed>
	 */
	protected function settings(): array {
		$all = (array) get_option( 'storeengine_courier_settings', [] );
		return isset( $all[ $this->id() ] ) && is_array( $all[ $this->id() ] ) ? $all[ $this->id() ] : [];
	}

	protected function get_setting( string $key, $default = '' ) {
		$s = $this->settings();
		return $s[ $key ] ?? $default;
	}

	protected function http_post( string $url, array $body, array $headers = [] ): array {
		$response = wp_remote_post( $url, [
			'headers'     => array_merge( [ 'Content-Type' => 'application/json', 'Accept' => 'application/json' ], $headers ),
			'body'        => wp_json_encode( $body ),
			'timeout'     => 30,
			'data_format' => 'body',
		] );
		return $this->parse_response( $response );
	}

	protected function http_get( string $url, array $headers = [] ): array {
		$response = wp_remote_get( $url, [
			'headers' => array_merge( [ 'Accept' => 'application/json' ], $headers ),
			'timeout' => 30,
		] );
		return $this->parse_response( $response );
	}

	/**
	 * Default raw → internal status mapper. Each provider should override
	 * to handle its own status vocabulary; this fallback covers the common
	 * lowercase aliases.
	 */
	protected function map_status( string $raw ): string {
		$s = strtolower( trim( $raw ) );

		if ( '' === $s ) return ShipmentStatus::CREATED;

		$exact = [
			'delivered'         => ShipmentStatus::DELIVERED,
			'partial_delivered' => ShipmentStatus::DELIVERED,
			'cancelled'         => ShipmentStatus::CANCELLED,
			'canceled'          => ShipmentStatus::CANCELLED,
			'returned'          => ShipmentStatus::RETURNED,
			'in_transit'        => ShipmentStatus::IN_TRANSIT,
			'in transit'        => ShipmentStatus::IN_TRANSIT,
			'out_for_delivery'  => ShipmentStatus::OUT_FOR_DELIVERY,
			'out for delivery'  => ShipmentStatus::OUT_FOR_DELIVERY,
			'picked_up'         => ShipmentStatus::PICKED_UP,
			'in_review'         => ShipmentStatus::CREATED,
		];
		if ( isset( $exact[ $s ] ) ) return $exact[ $s ];

		if ( false !== strpos( $s, 'return' ) ) return ShipmentStatus::RETURNED;
		if ( false !== strpos( $s, 'cancel' ) ) return ShipmentStatus::CANCELLED;
		if ( false !== strpos( $s, 'pickup' ) ) return ShipmentStatus::PICKED_UP;
		if ( false !== strpos( $s, 'transit' ) || false !== strpos( $s, 'sorting' ) ) return ShipmentStatus::IN_TRANSIT;
		if ( false !== strpos( $s, 'delivery' ) ) return ShipmentStatus::OUT_FOR_DELIVERY;

		return ShipmentStatus::CREATED;
	}

	protected function parse_response( $response ): array {
		if ( is_wp_error( $response ) ) {
			return [ 'ok' => false, 'errors' => [ $response->get_error_message() ] ];
		}
		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $body ) && isset( $body['message'] ) ? $body['message'] : ( 'HTTP ' . $code );
			return [ 'ok' => false, 'errors' => [ $msg ], 'raw' => $body ];
		}

		return [ 'ok' => true, 'raw' => is_array( $body ) ? $body : [] ];
	}
}
