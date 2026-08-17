<?php

namespace StoreEngine\Addons\Webhooks\Incoming\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Webhooks\Interfaces\IncomingHandlerInterface;
use StoreEngine\Utils\Helper;

/**
 * Force an order into a paid state — payment reconciliation from an external
 * processor, accounting system, or a confirmed bank transfer / COD collection.
 * Payload: { order_id, transaction_id?, note? }.
 */
class OrderPaid implements IncomingHandlerInterface {

	public function handle( array $payload, array $context ): array {
		$order_id = (int) ( $payload['order_id'] ?? $payload['id'] ?? 0 );

		if ( ! $order_id ) {
			return [ 'success' => false, 'status' => 422, 'message' => __( 'order_id is required.', 'storeengine' ) ];
		}

		$order = Helper::get_order( $order_id );
		if ( is_wp_error( $order ) || ! $order ) {
			return [ 'success' => false, 'status' => 404, 'message' => __( 'Order not found.', 'storeengine' ) ];
		}

		if ( ! empty( $payload['transaction_id'] ) ) {
			$order->set_transaction_id( sanitize_text_field( (string) $payload['transaction_id'] ) );
			$order->save();
		}

		$note = sanitize_text_field( (string) ( $payload['note'] ?? __( 'Marked paid via incoming webhook.', 'storeengine' ) ) );
		$paid = $order->mark_as_paid_force( $note );

		if ( ! $paid ) {
			return [ 'success' => false, 'status' => 500, 'message' => __( 'Could not mark the order as paid.', 'storeengine' ) ];
		}

		return [
			'success' => true,
			/* translators: %d: order id */
			'message' => sprintf( __( 'Order #%d marked as paid.', 'storeengine' ), $order_id ),
			'data'    => [ 'order_id' => $order_id, 'status' => $order->get_status() ],
		];
	}
}
