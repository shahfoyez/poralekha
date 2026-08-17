<?php

namespace StoreEngine\Addons\Inventory\Api;

use StoreEngine\Addons\Inventory\Classes\Authorization;
use StoreEngine\Classes\StockManager;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single-location stock adjustment.
 *
 * Calls free-core StockManager::adjust_stock() which writes to
 * `variation.stock_quantity` (or simple-product post meta) and the
 * `storeengine_stock_movements` table. The Pro inventory-pro addon adds a
 * separate `/inventory/locations-stock` endpoint that adjusts at a chosen
 * location.
 */
final class AdjustController {

	const NS = 'storeengine/v1';

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes() {
		register_rest_route( self::NS, '/inventory/adjust', [
			'methods'             => WP_REST_Server::CREATABLE,
			'callback'            => [ __CLASS__, 'adjust' ],
			'permission_callback' => [ __CLASS__, 'permission' ],
			'args'                => [
				'product_id'   => [ 'type' => 'integer', 'required' => true ],
				'variation_id' => [ 'type' => 'integer', 'default' => 0 ],
				'action'       => [ 'type' => 'string', 'enum' => [ 'add', 'remove', 'set' ], 'default' => 'set' ],
				'qty'          => [ 'type' => 'integer', 'required' => true ],
				'reason'       => [ 'type' => 'string', 'default' => 'manual' ],
				'note'         => [ 'type' => 'string', 'default' => '' ],
			],
		] );
	}

	public static function permission(): bool {
		return Authorization::can_access_inventory();
	}

	public static function adjust( WP_REST_Request $request ) {
		$product_id = (int) $request['product_id'];

		// Vendor users may only adjust their own products.
		if ( ! Authorization::can_modify_product( $product_id ) ) {
			return new WP_Error( 'forbidden', __( 'You do not have permission to adjust stock for this product.', 'storeengine' ), [ 'status' => 403 ] );
		}

		$result = StockManager::adjust_stock(
			$product_id,
			(int) ( $request['variation_id'] ?? 0 ),
			(string) $request['action'],
			(int) $request['qty'],
			(string) ( $request['reason'] ?? 'manual' ),
			(string) ( $request['note'] ?? '' )
		);

		if ( empty( $result['ok'] ) ) {
			return new WP_Error(
				'adjust_failed',
				$result['message'] ?? __( 'Could not adjust stock.', 'storeengine' ),
				[ 'status' => 400 ]
			);
		}

		return rest_ensure_response( $result );
	}
}
