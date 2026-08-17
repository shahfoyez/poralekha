<?php

namespace StoreEngine\Addons\Webhooks\Incoming\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Webhooks\Interfaces\IncomingHandlerInterface;
use StoreEngine\Classes\OrderStatus\OrderStatus as OrderStatusEnum;
use StoreEngine\Utils\Helper;

/**
 * Set an order's status from an external system (3PL "shipped", helpdesk
 * "cancelled", …). Payload: { order_id, status, note?, tracking_number? }.
 */
class OrderStatus implements IncomingHandlerInterface {

	public function handle( array $payload, array $context ): array {
		$order_id = (int) ( $payload['order_id'] ?? $payload['id'] ?? 0 );
		$status   = sanitize_key( (string) ( $payload['status'] ?? '' ) );

		if ( ! $order_id || '' === $status ) {
			return [ 'success' => false, 'status' => 422, 'message' => __( 'order_id and status are required.', 'storeengine' ) ];
		}

		if ( ! OrderStatusEnum::is_order_status( $status ) ) {
			return [
				'success' => false,
				'status'  => 422,
				/* translators: %s: status slug */
				'message' => sprintf( __( 'Unknown order status "%s".', 'storeengine' ), $status ),
			];
		}

		$order = Helper::get_order( $order_id );
		if ( is_wp_error( $order ) || ! $order ) {
			return [ 'success' => false, 'status' => 404, 'message' => __( 'Order not found.', 'storeengine' ) ];
		}

		$note = sanitize_text_field( (string) ( $payload['note'] ?? __( 'Status updated via incoming webhook.', 'storeengine' ) ) );

		// Persist a tracking number if the caller sent one (common for shipping
		// providers) so downstream notifications can surface it.
		if ( ! empty( $payload['tracking_number'] ) ) {
			$tracking = sanitize_text_field( (string) $payload['tracking_number'] );
			update_post_meta( $order_id, '_storeengine_tracking_number', $tracking );
			$order->add_order_note(
				/* translators: %s: tracking number */
				sprintf( __( 'Tracking number: %s', 'storeengine' ), $tracking )
			);
		}

		$order->update_status( $status, $note, false );

		return [
			'success' => true,
			/* translators: 1: order id, 2: status */
			'message' => sprintf( __( 'Order #%1$d status set to "%2$s".', 'storeengine' ), $order_id, $status ),
			'data'    => [ 'order_id' => $order_id, 'status' => $status ],
		];
	}
}
