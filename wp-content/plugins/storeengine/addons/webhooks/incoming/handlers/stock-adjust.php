<?php

namespace StoreEngine\Addons\Webhooks\Incoming\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Webhooks\Interfaces\IncomingHandlerInterface;
use StoreEngine\Utils\Helper;

/**
 * Increment/decrement a product's stock by a delta — e.g. a supplier restock
 * (+50) or a sale recorded on another channel (-1). Payload:
 * { product_id, delta }.
 */
class StockAdjust implements IncomingHandlerInterface {

	public function handle( array $payload, array $context ): array {
		$product_id = (int) ( $payload['product_id'] ?? $payload['id'] ?? 0 );

		if ( ! $product_id || ! isset( $payload['delta'] ) || ! is_numeric( $payload['delta'] ) ) {
			return [ 'success' => false, 'status' => 422, 'message' => __( 'product_id and a numeric delta are required.', 'storeengine' ) ];
		}

		$product = Helper::get_product( $product_id );
		if ( ! $product ) {
			return [ 'success' => false, 'status' => 404, 'message' => __( 'Product not found.', 'storeengine' ) ];
		}

		if ( ! $product->manages_stock() ) {
			update_post_meta( $product_id, '_storeengine_manage_stock', 1 );
		}

		$current = (int) ( $product->get_stock_quantity() ?? 0 );
		$new_qty = max( 0, $current + (int) $payload['delta'] );

		$product->set_stock_quantity( $new_qty );
		$product->set_stock_status( $new_qty > 0 ? 'instock' : 'outofstock' );
		$product->save();

		return [
			'success' => true,
			/* translators: 1: product id, 2: quantity */
			'message' => sprintf( __( 'Product #%1$d stock adjusted to %2$d.', 'storeengine' ), $product_id, $new_qty ),
			'data'    => [ 'product_id' => $product_id, 'quantity' => $new_qty, 'previous' => $current ],
		];
	}
}
