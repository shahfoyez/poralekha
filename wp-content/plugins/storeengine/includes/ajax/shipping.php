<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\models\ShippingZoneMethods;
use StoreEngine\models\ShippingZones;
use StoreEngine\Utils\Constants;

class Shipping extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'create_shipping_zone'   => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'create_shipping_zone' ],
				'fields'     => [
					'zone_name' => 'string',
					'region'    => 'string',
				],
			],
			'get_shipping_zones'     => [
				'callback'   => [ $this, 'get_shipping_zones' ],
				'capability' => 'manage_options',
			],
			'update_shipping_zone'   => [
				'callback'   => [ $this, 'update_shipping_zone' ],
				'capability' => 'manage_options',
				'fields'     => [
					'id'        => 'absint',
					'zone_name' => 'string',
					'region'    => 'string',
				],
			],
			'delete_shipping_zone'   => [
				'callback'   => [ $this, 'delete_shipping_zone' ],
				'capability' => 'manage_options',
				'fields'     => [
					'id' => 'absint',
				],
			],

			'create_shipping_method' => [
				'callback'   => [ $this, 'create_shipping_method' ],
				'capability' => 'manage_options',
				'fields'     => [
					'name'        => 'string',
					'zone_id'     => 'absint',
					'cost'        => 'float',
					'is_enabled'  => 'boolean',
					'type'        => 'string',
					'tax'         => 'float',
					'description' => 'string',
				],
			],
			'get_shipping_methods'   => [
				'callback'   => [ $this, 'get_shipping_methods' ],
				'capability' => 'manage_options',
				'fields'     => [
					'zone_id' => 'absint',
				],
			],
			'update_shipping_method' => [
				'callback'   => [ $this, 'update_shipping_method' ],
				'capability' => 'manage_options',
				'fields'     => [
					'id'          => 'absint',
					'name'        => 'string',
					'zone_id'     => 'absint',
					'cost'        => 'float',
					'is_enabled'  => 'boolean',
					'type'        => 'string',
					'tax'         => 'float',
					'description' => 'string',
				],
			],
			'delete_shipping_method' => [
				'callback'   => [ $this, 'delete_shipping_method' ],
				'capability' => 'manage_options',
				'fields'     => [
					'id' => 'absint',
				],
			],
			'update_shipping_status' => [
				'callback'   => [ $this, 'update_shipping_status' ],
				'capability' => 'manage_options',
				'fields'     => [
					'order_id'        => 'absint',
					'product_id'      => 'absint',
					'order_item_id'   => 'absint',
					'shipping_status' => 'string',
					'courier'         => 'string',
					'tracking_number' => 'string',
					'tracking_url'    => 'string',
				],
			],
		];
	}

	public function update_shipping_status( $payload ) {
		$order_item_id = isset( $payload['order_item_id'] ) ? (int) $payload['order_item_id'] : 0;

		// Backward-compat: older callers send (order_id, product_id) instead of
		// the line's order_item_id. Resolve it (positive rows only).
		if ( ! $order_item_id && ! empty( $payload['order_id'] ) && ! empty( $payload['product_id'] ) ) {
			global $wpdb;
			// phpcs:disable WordPress.DB.DirectDatabaseQuery
			$order_item_id = (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT order_item_id FROM {$wpdb->prefix}storeengine_order_product_lookup WHERE order_id = %d AND product_id = %d AND order_item_id > 0 LIMIT 1",
				(int) $payload['order_id'],
				(int) $payload['product_id']
			) );
			// phpcs:enable
		}

		if ( ! $order_item_id ) {
			wp_send_json_error( esc_html__( 'No Product Is Found.', 'storeengine' ) );
		}

		$result = \StoreEngine\Classes\OrderShipment::record(
			$order_item_id,
			sanitize_key( (string) ( $payload['shipping_status'] ?? '' ) ),
			[
				'courier'         => (string) ( $payload['courier'] ?? '' ),
				'tracking_number' => (string) ( $payload['tracking_number'] ?? '' ),
				'tracking_url'    => (string) ( $payload['tracking_url'] ?? '' ),
			],
			esc_html__( 'Store admin', 'storeengine' )
		);

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	public function create_shipping_zone( $payload ) {
		if ( ! empty( $payload['zone_name'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone name is required.', 'storeengine' ) );
		}

		if ( ! empty( $payload['region'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone region is required.', 'storeengine' ) );
		}

		$result = ( new ShippingZones() )->save( [
			'zone_name' => $payload['zone_name'],
			'region'    => $payload['region'],
		] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	public function get_shipping_zones() {
		$zone = new ShippingZones();

		wp_send_json_success( $zone->all() );
	}


	public function update_shipping_zone( $payload ) {
		if ( ! empty( $payload['id'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone ID is required.', 'storeengine' ) );
		}

		if ( ! empty( $payload['zone_name'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone name is required.', 'storeengine' ) );
		}

		if ( ! empty( $payload['region'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone region is required.', 'storeengine' ) );
		}

		$result = ( new ShippingZones() )->update( absint( $payload['id'] ), [
			'zone_name' => $payload['zone_name'],
			'region'    => $payload['region'],
		] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	public function delete_shipping_zone( $payload ) {
		if ( empty( $payload['id'] ) ) {
			wp_send_json_error( esc_html__( 'Missing ID Parameter', 'storeengine' ) );
		}

		$result = ( new ShippingZones() )->delete( $payload['id'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}

	public function create_shipping_method( $payload ) {
		if ( ! empty( $payload['name'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone name is required.', 'storeengine' ) );
		}

		if ( ! empty( $payload['zone_id'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone id required.', 'storeengine' ) );
		}

		if ( ! empty( $payload['type'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone type required.', 'storeengine' ) );
		}

		$result = ( new ShippingZoneMethods() )->save( [
			'name'        => $payload['name'],
			'zone_id'     => $payload['zone_id'],
			'cost'        => $payload['cost'] ?? 0,
			'is_enabled'  => $payload['is_enabled'] ?? false,
			'type'        => $payload['type'],
			'tax'         => $payload['tax'] ?? 0,
			'description' => $payload['description'] ?? '',
		] );

		if ( $result ) {
			wp_send_json_success( $result );
		}
	}

	public function get_shipping_methods( $payload ) {
		if ( empty( $payload['zone_id'] ) ) {
			wp_send_json_error( esc_html__( 'Missing zone ID Parameter', 'storeengine' ) );
		}

		wp_send_json_success( ( new ShippingZoneMethods() )->get_by_zone_id( $payload['zone_id'] ) );
	}

	public function update_shipping_method( $payload ) {
		if ( ! empty( $payload['name'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone name is required.', 'storeengine' ) );
		}

		if ( ! empty( $payload['zone_id'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone id required.', 'storeengine' ) );
		}

		if ( ! empty( $payload['type'] ) ) {
			wp_send_json_error( esc_html__( 'Shipping zone type required.', 'storeengine' ) );
		}

		$result = ( new ShippingZoneMethods() )->update( $payload['id'], [
			'name'        => $payload['name'],
			'zone_id'     => $payload['zone_id'],
			'cost'        => $payload['cost'] ?? 0,
			'is_enabled'  => $payload['is_enabled'] ?? false,
			'type'        => $payload['type'],
			'tax'         => $payload['tax'] ?? 0,
			'description' => $payload['description'] ?? '',
		] );

		wp_send_json_success( $result );
	}

	public function delete_shipping_method( $payload ) {
		if ( empty( $payload['id'] ) ) {
			wp_send_json_error( esc_html__( 'Missing ID Parameter', 'storeengine' ) );
		}

		$result = ( new ShippingZoneMethods() )->delete( $payload['id'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( $result );
	}
}
