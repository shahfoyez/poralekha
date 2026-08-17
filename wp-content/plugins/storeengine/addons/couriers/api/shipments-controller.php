<?php

namespace StoreEngine\Addons\Couriers\Api;

use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Couriers\Classes\AutoPush;
use StoreEngine\Addons\Couriers\Classes\OrderPayloadMapper;
use StoreEngine\Addons\Couriers\Classes\ShipmentsService;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ShipmentsController {

	const NS = 'storeengine/v1';

	public static function init(): void {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes(): void {
		register_rest_route( self::NS, '/couriers/shipments', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'list_all' ],
				'permission_callback' => [ __CLASS__, 'permission' ],
				'args'                => [
					'order_id' => [ 'type' => 'integer' ],
					'provider' => [ 'type' => 'string' ],
					'status'   => [ 'type' => 'string' ],
					'per_page' => [ 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 100 ],
					'page'     => [ 'type' => 'integer', 'default' => 1 ],
				],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'create' ],
				'permission_callback' => [ __CLASS__, 'permission' ],
				'args'                => [
					'order_id'         => [ 'type' => 'integer', 'required' => true ],
					'provider'         => [ 'type' => 'string', 'required' => true ],
					'customer_name'    => [ 'type' => 'string' ],
					'customer_phone'   => [ 'type' => 'string' ],
					'customer_address' => [ 'type' => 'string' ],
					'city'             => [ 'type' => 'string' ],
					'state'            => [ 'type' => 'string' ],
					'postcode'         => [ 'type' => 'string' ],
					'country'          => [ 'type' => 'string' ],
					'cod_amount'       => [ 'type' => 'number' ],
					'weight_kg'        => [ 'type' => 'number' ],
					'item_description' => [ 'type' => 'string' ],
					'city_id'          => [ 'type' => 'integer' ],
					'zone_id'          => [ 'type' => 'integer' ],
					'delivery_type'    => [ 'type' => 'integer' ],
					'vendor_id'        => [ 'type' => 'integer' ],
				],
			],
		] );

		register_rest_route( self::NS, '/couriers/shipments/(?P<id>\d+)/track', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ __CLASS__, 'track' ],
			'permission_callback' => [ __CLASS__, 'permission' ],
		] );

		register_rest_route( self::NS, '/couriers/shipments/(?P<id>\d+)/cancel', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ __CLASS__, 'cancel' ],
			'permission_callback' => [ __CLASS__, 'permission' ],
		] );

		register_rest_route( self::NS, '/couriers/orders/(?P<id>\d+)/shipment-defaults', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'shipment_defaults' ],
			'permission_callback' => [ __CLASS__, 'permission' ],
		] );
	}

	public static function permission(): bool {
		return current_user_can( 'manage_options' )
			|| current_user_can( 'edit_storeengine_orders' )
			|| current_user_can( 'manage_storeengine_settings' );
	}

	public static function list_all( WP_REST_Request $request ) {
		return rest_ensure_response( ShipmentsService::all( $request->get_params() ) );
	}

	public static function create( WP_REST_Request $request ) {
		$payload  = $request->get_params();
		$order_id = (int) $payload['order_id'];
		$provider = (string) $payload['provider'];
		unset( $payload['order_id'], $payload['provider'] );

		$order = Helper::get_order( $order_id );
		if ( $order && ! is_wp_error( $order ) ) {
			$defaults = OrderPayloadMapper::build_payload( $order, AutoPush::get_auto_push_config() );
			foreach ( $defaults as $k => $v ) {
				if ( ! isset( $payload[ $k ] ) || '' === $payload[ $k ] || null === $payload[ $k ] ) {
					$payload[ $k ] = $v;
				}
			}
			// Provider extras (line items, subtotal) — admin doesn't type these.
			$payload = array_merge( $payload, OrderPayloadMapper::build_provider_extras( $order, $provider ) );
		}

		$result = ShipmentsService::create_for_order( $order_id, $provider, $payload );
		if ( empty( $result['ok'] ) ) {
			return new WP_Error( 'create_failed', implode( ', ', $result['errors'] ?? [] ), [ 'status' => 400 ] );
		}

		return rest_ensure_response( ShipmentsService::get( (int) $result['shipment_id'] ) );
	}

	public static function track( WP_REST_Request $request ) {
		$result = ShipmentsService::refresh_status( (int) $request['id'] );
		if ( empty( $result['ok'] ) ) {
			return new WP_Error( 'track_failed', implode( ', ', $result['errors'] ?? [] ), [ 'status' => 400 ] );
		}
		return rest_ensure_response( ShipmentsService::get( (int) $request['id'] ) );
	}

	public static function cancel( WP_REST_Request $request ) {
		$result = ShipmentsService::cancel( (int) $request['id'] );
		if ( empty( $result['ok'] ) ) {
			return new WP_Error( 'cancel_failed', implode( ', ', $result['errors'] ?? [] ), [ 'status' => 400 ] );
		}
		return rest_ensure_response( ShipmentsService::get( (int) $request['id'] ) );
	}

	public static function shipment_defaults( WP_REST_Request $request ) {
		$order = Helper::get_order( (int) $request['id'] );
		if ( ! $order || is_wp_error( $order ) ) {
			return new WP_Error( 'order_not_found', __( 'Order not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$defaults = OrderPayloadMapper::build_payload( $order, AutoPush::get_auto_push_config() );
		$line_items = OrderPayloadMapper::build_line_items( $order );

		$subtotal = method_exists( $order, 'get_subtotal' )
			? (float) $order->get_subtotal()
			: ( method_exists( $order, 'get_total' ) ? (float) $order->get_total() : 0.0 );

		$status = method_exists( $order, 'get_status' ) ? (string) $order->get_status() : '';
		$currency = method_exists( $order, 'get_currency' ) ? (string) $order->get_currency() : '';

		return rest_ensure_response( array_merge( $defaults, [
			'_meta' => [
				'order_id'        => (int) ( method_exists( $order, 'get_id' ) ? $order->get_id() : $request['id'] ),
				'order_status'    => $status,
				'currency'        => $currency,
				'subtotal'        => $subtotal,
				'line_item_count' => count( $line_items ),
				'line_items'      => $line_items,
			],
		] ) );
	}
}
