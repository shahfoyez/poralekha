<?php

namespace StoreEngine\Addons\Inventory\Api;

use StoreEngine\Addons\Inventory\Classes\Authorization;
use StoreEngine\Classes\StockManager;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MovementsController {

	const NS = 'storeengine/v1';

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes() {
		register_rest_route( self::NS, '/inventory/movements', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'query' ],
			'permission_callback' => [ __CLASS__, 'permission' ],
			'args'                => [
				'product_id'   => [ 'type' => 'integer' ],
				'variation_id' => [ 'type' => 'integer' ],
				'type'         => [ 'type' => 'string' ],
				'from'         => [ 'type' => 'string' ],
				'to'           => [ 'type' => 'string' ],
				'per_page'     => [ 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 200 ],
				'page'         => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
			],
		] );
	}

	public static function permission(): bool {
		return Authorization::can_access_inventory();
	}

	public static function query( WP_REST_Request $request ) {
		$per_page = max( 1, (int) ( $request['per_page'] ?? 50 ) );
		$page     = max( 1, (int) ( $request['page'] ?? 1 ) );

		$rows = StockManager::query_movements( [
			'product_id'   => (int) ( $request['product_id'] ?? 0 ),
			'variation_id' => (int) ( $request['variation_id'] ?? 0 ),
			'type'         => (string) ( $request['type'] ?? '' ),
			'from'         => (string) ( $request['from'] ?? '' ),
			'to'           => (string) ( $request['to'] ?? '' ),
			'vendor_id'    => Authorization::scope_user_id(),
			'limit'        => $per_page,
			'offset'       => ( $page - 1 ) * $per_page,
		] );

		return rest_ensure_response( $rows );
	}
}
