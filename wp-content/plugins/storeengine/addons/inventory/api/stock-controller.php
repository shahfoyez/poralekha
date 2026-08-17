<?php

namespace StoreEngine\Addons\Inventory\Api;

use StoreEngine\Addons\Inventory\Classes\Authorization;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Single-location stock listing.
 *
 * Lists variations + simple products with their tracked qty, status, and low
 * threshold. The Pro inventory-pro addon overrides this surface with a
 * per-location version (different REST path: `/inventory/locations-stock`).
 */
final class StockController {

	const NS = 'storeengine/v1';

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes() {
		register_rest_route( self::NS, '/inventory/stock', [
			'methods'             => WP_REST_Server::READABLE,
			'callback'            => [ __CLASS__, 'query' ],
			'permission_callback' => [ __CLASS__, 'permission' ],
			'args'                => [
				'product_id'   => [ 'type' => 'integer' ],
				'variation_id' => [ 'type' => 'integer' ],
				'low_stock'    => [ 'type' => 'boolean', 'default' => false ],
				'per_page'     => [ 'type' => 'integer', 'default' => 50, 'minimum' => 1, 'maximum' => 200 ],
				'page'         => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
			],
		] );
	}

	public static function permission(): bool {
		return Authorization::can_access_inventory();
	}

	public static function query( WP_REST_Request $request ) {
		global $wpdb;

		$variations = $wpdb->prefix . 'storeengine_product_variations';
		$posts      = $wpdb->posts;
		$postmeta   = $wpdb->postmeta;

		// Vendor users see only their own products.
		$scope_user_id = Authorization::scope_user_id();

		$product_id   = (int) ( $request['product_id'] ?? 0 );
		$variation_id = (int) ( $request['variation_id'] ?? 0 );
		$low_stock    = (bool) $request['low_stock'];
		$per_page     = max( 1, (int) ( $request['per_page'] ?? 50 ) );
		$page         = max( 1, (int) ( $request['page'] ?? 1 ) );
		$offset       = ( $page - 1 ) * $per_page;

		// ---- Variations branch (variable products) ----
		$var_where  = [ 'v.manage_stock = 1', 'p.post_status IN ("publish","draft","private")' ];
		$var_values = [];
		if ( $scope_user_id > 0 ) {
			$var_where[]  = 'p.post_author = %d';
			$var_values[] = $scope_user_id;
		}
		if ( $product_id ) {
			$var_where[]  = 'v.product_id = %d';
			$var_values[] = $product_id;
		}
		if ( $variation_id ) {
			$var_where[]  = 'v.id = %d';
			$var_values[] = $variation_id;
		}
		if ( $low_stock ) {
			// Low stock = in stock (qty > 0) AND at/below the row's own threshold.
			// Out-of-stock (qty <= 0) is a separate state, not "low". Mirrors
			// Variation::is_low_stock() using only the per-row threshold.
			$var_where[] = '( COALESCE(v.stock_quantity, 0) > 0 AND v.low_stock_threshold IS NOT NULL AND COALESCE(v.stock_quantity, 0) <= v.low_stock_threshold )';
		}
		$var_where_sql = ' WHERE ' . implode( ' AND ', $var_where );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$variation_sql = "
			SELECT v.id AS variation_id, v.product_id, v.sku, v.barcode,
				   COALESCE(v.stock_quantity, 0) AS stock_quantity,
				   v.stock_status, v.low_stock_threshold,
				   p.post_title AS product_title
			FROM {$variations} v
			INNER JOIN {$posts} p ON p.ID = v.product_id
			{$var_where_sql}
		";
		// phpcs:enable

		// ---- Simple products branch (skip variable parents) ----
		// Variable parents have a `_storeengine_product_type = variable` meta.
		// Their parent-level stock is a fallback the storefront uses only when
		// no variation tracks. Including the parent row would double-count and
		// confuse users, so we exclude it.
		$simple_where  = [
			'p.post_type = %s',
			'p.post_status IN ("publish","draft","private")',
			"ms.meta_value IN ('1','true')",
			"(pt.meta_value IS NULL OR pt.meta_value <> 'variable')",
		];
		$simple_values = [ 'storeengine_product' ];
		if ( $scope_user_id > 0 ) {
			$simple_where[]  = 'p.post_author = %d';
			$simple_values[] = $scope_user_id;
		}
		if ( $product_id ) {
			$simple_where[]  = 'p.ID = %d';
			$simple_values[] = $product_id;
		}
		if ( $variation_id ) {
			// Caller asked for a specific variation_id — simple products have none.
			$simple_where[] = '1=0';
		}
		if ( $low_stock ) {
			// Same per-row-only definition as the variation branch.
			$simple_where[] = '( CAST(COALESCE(sq.meta_value, 0) AS SIGNED) > 0 AND lst.meta_value IS NOT NULL AND lst.meta_value <> "" AND CAST(COALESCE(sq.meta_value, 0) AS SIGNED) <= CAST(lst.meta_value AS SIGNED) )';
		}
		$simple_where_sql = ' WHERE ' . implode( ' AND ', $simple_where );

		// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$simple_sql = "
			SELECT 0 AS variation_id, p.ID AS product_id,
				   sku.meta_value AS sku,
				   bc.meta_value AS barcode,
				   CAST(COALESCE(sq.meta_value, 0) AS SIGNED) AS stock_quantity,
				   COALESCE(ss.meta_value, 'instock') AS stock_status,
				   CAST(NULLIF(lst.meta_value, '') AS SIGNED) AS low_stock_threshold,
				   p.post_title AS product_title
			FROM {$posts} p
			INNER JOIN {$postmeta} ms ON ms.post_id = p.ID AND ms.meta_key = '_storeengine_manage_stock'
			LEFT JOIN  {$postmeta} pt  ON pt.post_id  = p.ID AND pt.meta_key  = '_storeengine_product_type'
			LEFT JOIN  {$postmeta} sku ON sku.post_id = p.ID AND sku.meta_key = '_storeengine_sku'
			LEFT JOIN  {$postmeta} bc  ON bc.post_id  = p.ID AND bc.meta_key  = '_storeengine_barcode'
			LEFT JOIN  {$postmeta} sq  ON sq.post_id  = p.ID AND sq.meta_key  = '_storeengine_stock_quantity'
			LEFT JOIN  {$postmeta} ss  ON ss.post_id  = p.ID AND ss.meta_key  = '_storeengine_stock_status'
			LEFT JOIN  {$postmeta} lst ON lst.post_id = p.ID AND lst.meta_key = '_storeengine_low_stock_threshold'
			{$simple_where_sql}
		";
		// phpcs:enable

		$sql = "SELECT * FROM (
				{$variation_sql}
				UNION ALL
				{$simple_sql}
			) AS combined
			ORDER BY product_title ASC, variation_id ASC
			LIMIT %d OFFSET %d";

		$values = array_merge( $var_values, $simple_values, [ $per_page, $offset ] );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared (%d) query over custom StoreEngine tables; SQL built from literals, values bound via prepare().
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, ...$values ) );

		// Total row count for pagination (same WHERE, no LIMIT).
		$count_sql    = "SELECT COUNT(*) FROM ( {$variation_sql} UNION ALL {$simple_sql} ) AS combined";
		$count_values = array_merge( $var_values, $simple_values );
		$total        = $count_values
			? (int) $wpdb->get_var( $wpdb->prepare( $count_sql, ...$count_values ) )
			: (int) $wpdb->get_var( $count_sql );
		// phpcs:enable

		$rows = self::decorate_low_stock( is_array( $rows ) ? $rows : [] );

		$response = rest_ensure_response( $rows );
		$response->header( 'X-WP-Total', $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );

		return $response;
	}

	/**
	 * Attach an is_low_stock flag to each row so the frontend has a single
	 * source of truth. Mirrors Variation::is_low_stock() using only the row's
	 * own threshold: low = qty > 0 AND threshold set AND qty <= threshold.
	 *
	 * @param array $rows Result rows (objects).
	 *
	 * @return array
	 */
	private static function decorate_low_stock( array $rows ): array {
		foreach ( $rows as $row ) {
			$qty       = (int) ( $row->stock_quantity ?? 0 );
			$threshold = ( null !== ( $row->low_stock_threshold ?? null ) && '' !== $row->low_stock_threshold )
				? (int) $row->low_stock_threshold
				: null;

			$row->is_low_stock = ( null !== $threshold && $qty > 0 && $qty <= $threshold );
		}

		return $rows;
	}
}
