<?php

namespace StoreEngine\Addons\Webhooks\Incoming\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Webhooks\Interfaces\IncomingHandlerInterface;
use StoreEngine\Utils\Helper;

/**
 * Set a product's stock to an absolute quantity — the ERP / warehouse / POS is
 * the system of record and pushes the authoritative on-hand count. Payload:
 * { product_id, quantity }.
 */
class StockSet implements IncomingHandlerInterface {

	public function handle( array $payload, array $context ): array {
		$product_id = (int) ( $payload['product_id'] ?? $payload['id'] ?? 0 );

		if ( ! $product_id || ! isset( $payload['quantity'] ) || ! is_numeric( $payload['quantity'] ) ) {
			return [ 'success' => false, 'status' => 422, 'message' => __( 'product_id and a numeric quantity are required.', 'storeengine' ) ];
		}

		$product = Helper::get_product( $product_id );
		if ( ! $product ) {
			return [ 'success' => false, 'status' => 404, 'message' => __( 'Product not found.', 'storeengine' ) ];
		}

		$qty = max( 0, (int) $payload['quantity'] );

		// Ensure the product tracks stock so the quantity is actually honoured.
		if ( ! $product->manages_stock() ) {
			update_post_meta( $product_id, '_storeengine_manage_stock', 1 );
		}

		$product->set_stock_quantity( $qty );
		$product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
		$product->save();

		return [
			'success' => true,
			/* translators: 1: product id, 2: quantity */
			'message' => sprintf( __( 'Product #%1$d stock set to %2$d.', 'storeengine' ), $product_id, $qty ),
			'data'    => [ 'product_id' => $product_id, 'quantity' => $qty ],
		];
	}
}
