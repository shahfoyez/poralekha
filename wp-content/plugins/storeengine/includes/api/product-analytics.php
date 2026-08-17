<?php
/**
 * Per-product analytics REST controller.
 *
 * Serves the "Product Analytics" drill-down screen (one product at a time):
 * revenue / units / orders / average-order-value tiles, a day-by-day growth
 * chart, a per-price breakdown, top customers and recent orders — all scoped
 * to a single product and filterable by date range and currency.
 *
 * Mirrors the conventions of {@see \StoreEngine\API\Analytics} (the store-wide
 * dashboard endpoint): same from/to/compare/currency params, the same
 * `_order_placed_date_gmt` + order-item-meta aggregation, and the same
 * per-query wp_cache pattern. Revenue is summed from `_line_total` order-item
 * meta (not the order_product_lookup table, whose revenue columns are not
 * populated) so figures stay consistent with the dashboard's Top Products.
 */

namespace StoreEngine\API;

use DateTime;
use StoreEngine\Utils\Caching;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ProductAnalytics extends WP_REST_Controller {

	/**
	 * Order statuses that count as a completed sale.
	 */
	const PAID_STATUSES = "'processing','payment_confirmed','completed'";

	public static function init() {
		$self            = new self();
		$self->namespace = STOREENGINE_PLUGIN_SLUG . '/v1';
		$self->rest_base = 'product-analytics';

		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	public function register_routes() {
		// Collection route — analytics for ALL products (the "Show all products"
		// overview). Paginated + searchable, ordered by revenue.
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_products_list' ],
				'permission_callback' => [ $this, 'get_permission_check' ],
				'args'                => [
					'context'  => $this->get_context_param( [ 'default' => 'view' ] ),
					'from'     => [
						'type'    => 'string',
						'default' => gmdate( 'Y-m-d', strtotime( '- 1 month' ) ),
					],
					'to'       => [
						'type'    => 'string',
						'default' => gmdate( 'Y-m-d' ),
					],
					'currency' => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'search'   => [
						'type'              => 'string',
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'page'     => [
						'type'    => 'integer',
						'default' => 1,
						'minimum' => 1,
					],
					'per_page' => [
						'type'    => 'integer',
						'default' => 20,
						'minimum' => 1,
						'maximum' => 100,
					],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_analytics' ],
				'permission_callback' => [ $this, 'get_permission_check' ],
				'args'                => [
					'context'  => $this->get_context_param( [ 'default' => 'view' ] ),
					'id'       => [
						'description' => __( 'Product ID.', 'storeengine' ),
						'type'        => 'integer',
						'required'    => true,
					],
					'from'     => [
						'title'       => __( 'From Date', 'storeengine' ),
						'type'        => 'string',
						'description' => __( 'Range start date (Y-m-d).', 'storeengine' ),
						'default'     => gmdate( 'Y-m-d', strtotime( '- 1 month' ) ),
					],
					'to'       => [
						'title'       => __( 'To Date', 'storeengine' ),
						'type'        => 'string',
						'description' => __( 'Range end date (Y-m-d).', 'storeengine' ),
						'default'     => gmdate( 'Y-m-d' ),
					],
					'compare'  => [
						'title'       => __( 'Compare days', 'storeengine' ),
						'type'        => 'integer',
						'description' => __( 'Compare data with the preceding xx days.', 'storeengine' ),
						'default'     => 30,
					],
					'currency' => [
						'title'             => __( 'Currency', 'storeengine' ),
						'type'              => 'string',
						'description'       => __( 'ISO 4217 currency to filter by. Defaults to store base currency.', 'storeengine' ),
						'default'           => '',
						'sanitize_callback' => 'sanitize_text_field',
					],
				],
			],
		] );
	}

	public function get_permission_check() {
		return Helper::check_rest_user_cap( 'manage_options' );
	}

	public function get_analytics( WP_REST_Request $request ) {
		$product_id = absint( $request->get_param( 'id' ) );

		if ( ! $product_id || 'storeengine_product' !== get_post_type( $product_id ) ) {
			return new WP_Error( 'invalid_product', __( 'Invalid product.', 'storeengine' ), [ 'status' => 404 ] );
		}

		if ( ! rest_parse_date( $request->get_param( 'from' ) . ' 00:00:00' ) ) {
			return new WP_Error( 'invalid_from_date', __( 'Invalid from date.', 'storeengine' ) );
		}

		if ( ! rest_parse_date( $request->get_param( 'to' ) . ' 00:00:00' ) ) {
			return new WP_Error( 'invalid_to_date', __( 'Invalid to date.', 'storeengine' ) );
		}

		if ( strtotime( Helper::get_first_order_date( 'Y-m-d' ) ) > strtotime( $request->get_param( 'from' ) ) ) {
			$request->set_param( 'from', Helper::get_first_order_date( 'Y-m-d' ) );
		}

		$from    = $request->get_param( 'from' );
		$to      = $request->get_param( 'to' );
		$compare = (int) $request->get_param( 'compare' );

		$base_currency = strtoupper( Helper::get_settings( 'store_currency', 'USD' ) );
		$currency      = strtoupper( trim( $request->get_param( 'currency' ) ) ?: $base_currency );

		$revenue = $this->get_revenue_stats( $product_id, $from, $to, $compare, $currency );
		$units   = $this->get_units_stats( $product_id, $from, $to, $compare, $currency );
		$orders  = $this->get_orders_stats( $product_id, $from, $to, $compare, $currency );
		$aov     = $this->get_aov_stats( $revenue, $orders, $currency );

		return rest_ensure_response( [
			'product'              => $this->get_product_summary( $product_id ),
			'currency'             => $currency,
			'currencies_in_period' => $this->get_currencies_in_period( $product_id, $from, $to ),
			// Tile list — addons (e.g. license-management, cost-profit) may append
			// more product-scoped tiles via the filter below.
			'stats'                => apply_filters(
				'storeengine/analytics/product_stats',
				[
					[
						'label' => __( 'Revenue', 'storeengine' ),
						'icon'  => 'money-receive',
						'data'  => $revenue,
					],
					[
						'label' => __( 'Units Sold', 'storeengine' ),
						'icon'  => 'bag',
						'data'  => $units,
					],
					[
						'label' => __( 'Orders', 'storeengine' ),
						'icon'  => 'invoice',
						'data'  => $orders,
					],
					[
						'label' => __( 'Avg. Order Value', 'storeengine' ),
						'icon'  => 'chart-alt',
						'data'  => $aov,
					],
				],
				$product_id,
				$from,
				$to,
				$compare,
				$currency
			),
			'growth'               => $this->growth_report( $product_id, $from, $to, $currency ),
			'price_breakdown'      => $this->get_price_breakdown( $product_id, $from, $to, $currency ),
			'top_customers'        => $this->get_top_customers( $product_id, $from, $to, $currency ),
			'recent_orders'        => $this->get_recent_orders( $product_id, $currency ),
		] );
	}

	// ── Collection: all products overview ─────────────────────────────────────────

	public function get_products_list( WP_REST_Request $request ) {
		global $wpdb;

		if ( ! rest_parse_date( $request->get_param( 'from' ) . ' 00:00:00' ) ) {
			return new WP_Error( 'invalid_from_date', __( 'Invalid from date.', 'storeengine' ) );
		}
		if ( ! rest_parse_date( $request->get_param( 'to' ) . ' 00:00:00' ) ) {
			return new WP_Error( 'invalid_to_date', __( 'Invalid to date.', 'storeengine' ) );
		}

		$from     = $request->get_param( 'from' );
		$to       = $request->get_param( 'to' );
		$search   = trim( (string) $request->get_param( 'search' ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$offset   = ( $page - 1 ) * $per_page;

		$base_currency = strtoupper( Helper::get_settings( 'store_currency', 'USD' ) );
		$currency      = strtoupper( trim( $request->get_param( 'currency' ) ) ?: $base_currency );
		$statuses      = self::PAID_STATUSES;

		$like = '';
		if ( '' !== $search ) {
			$like = $wpdb->prepare( ' AND p.post_title LIKE %s', '%' . $wpdb->esc_like( $search ) . '%' );
		}

		// Total published products (respecting search) for pagination.
		$count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}posts AS p
			WHERE p.post_type = 'storeengine_product' AND p.post_status = 'publish'{$like}";
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- $like is a pre-prepared LIKE fragment; only $wpdb->prefix (safe) is interpolated; aggregate report, not cacheable per request.
		$total = (int) $wpdb->get_var( $count_sql );

		// Every product (LEFT JOIN the period aggregate) so zero-sale products
		// still appear, ordered by revenue then title.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Values passed as %s/%d via prepare(); interpolated tokens are $wpdb->prefix, the fixed PAID_STATUSES constant and a pre-prepared LIKE fragment (no user input); aggregate report over custom StoreEngine tables, not cacheable per request.
		$query = $wpdb->prepare(
			"SELECT
				p.ID AS product_id,
				p.post_title AS name,
				COALESCE( agg.revenue, 0 ) AS revenue,
				COALESCE( agg.units, 0 ) AS units,
				COALESCE( agg.orders, 0 ) AS orders
			FROM {$wpdb->prefix}posts AS p
			LEFT JOIN (
				SELECT
					pid.meta_value AS product_id,
					SUM( CAST( lt.meta_value AS DECIMAL(18,2) ) ) AS revenue,
					SUM( CAST( qty.meta_value AS UNSIGNED ) ) AS units,
					COUNT( DISTINCT oi.order_id ) AS orders
				FROM {$wpdb->prefix}storeengine_order_items AS oi
				INNER JOIN {$wpdb->prefix}storeengine_order_item_meta AS pid
					ON pid.order_item_id = oi.order_item_id AND pid.meta_key = '_product_id'
				LEFT JOIN {$wpdb->prefix}storeengine_order_item_meta AS lt
					ON lt.order_item_id = oi.order_item_id AND lt.meta_key = '_line_total'
				LEFT JOIN {$wpdb->prefix}storeengine_order_item_meta AS qty
					ON qty.order_item_id = oi.order_item_id AND qty.meta_key = '_quantity'
				INNER JOIN {$wpdb->prefix}storeengine_orders AS o
					ON o.id = oi.order_id AND o.type = 'order' AND o.currency = %s
					AND o.status IN ( {$statuses} )
				INNER JOIN {$wpdb->prefix}storeengine_orders_meta AS dm
					ON dm.order_id = o.id AND dm.meta_key = '_order_placed_date_gmt'
				WHERE DATE( dm.meta_value ) BETWEEN %s AND %s
				GROUP BY pid.meta_value
			) AS agg ON agg.product_id = p.ID
			WHERE p.post_type = 'storeengine_product' AND p.post_status = 'publish'{$like}
			ORDER BY revenue DESC, p.post_title ASC
			LIMIT %d OFFSET %d",
			$currency, $from, $to, $per_page, $offset
		);

		$rows     = $wpdb->get_results( $query );
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter
		$products = [];

		foreach ( (array) $rows as $row ) {
			$pid        = (int) $row->product_id;
			$is_license = Formatting::string_to_bool( get_post_meta( $pid, '_storeengine_product_enable_license_creation', true ) );

			$products[] = [
				'product_id' => $pid,
				'name'       => $row->name,
				'thumbnail'  => get_the_post_thumbnail_url( $pid, 'thumbnail' ) ?: '',
				'is_license' => $is_license,
				'revenue'    => (float) $row->revenue,
				'units'      => (int) $row->units,
				'orders'     => (int) $row->orders,
			];
		}

		return rest_ensure_response( [
			'currency'             => $currency,
			'currencies_in_period' => $this->get_currencies_in_period_all( $from, $to ),
			'products'             => $products,
			'total'                => $total,
			'total_pages'          => (int) ceil( $total / $per_page ),
			'page'                 => $page,
		] );
	}

	/**
	 * Distinct currencies with orders in the range across ALL products.
	 * Drives the currency filter on the overview list.
	 */
	protected function get_currencies_in_period_all( string $from, string $to ): array {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT DISTINCT o.currency
				FROM {$wpdb->prefix}storeengine_orders AS o
				INNER JOIN {$wpdb->prefix}storeengine_orders_meta AS dm
					ON dm.order_id = o.id AND dm.meta_key = '_order_placed_date_gmt'
				WHERE o.type = 'order' AND o.currency IS NOT NULL AND o.currency <> ''
					AND CAST( dm.meta_value AS DATE ) BETWEEN %s AND %s
				ORDER BY o.currency ASC",
			$from, $to
		);

		$key    = $this->get_cache_key( 'storeengine_orders', $query, $from, $to );
		$cached = wp_cache_get( $key, 'storeengine_orders-queries' );
		if ( false !== $cached ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows       = $wpdb->get_col( $query );
		$base       = strtoupper( Helper::get_settings( 'store_currency', 'USD' ) );
		$currencies = array_map( 'strtoupper', $rows ?: [] );

		if ( in_array( $base, $currencies, true ) ) {
			$currencies = array_merge( [ $base ], array_diff( $currencies, [ $base ] ) );
		}

		wp_cache_set( $key, $currencies, 'storeengine_orders-queries' );

		return $currencies;
	}

	// ── Product meta ────────────────────────────────────────────────────────────

	protected function get_product_summary( int $product_id ): array {
		// `_storeengine_product_enable_license_creation` is written by StoreEngine
		// Pro's license-management addon. Reading it here is harmless when Pro is
		// inactive (returns '' → is_license false) and lets the frontend decide
		// whether to request the license analytics section.
		$is_license = Formatting::string_to_bool( get_post_meta( $product_id, '_storeengine_product_enable_license_creation', true ) );

		return [
			'id'         => $product_id,
			'name'       => get_the_title( $product_id ),
			'thumbnail'  => get_the_post_thumbnail_url( $product_id, 'thumbnail' ) ?: '',
			'status'     => get_post_status( $product_id ),
			'is_license' => $is_license,
			'permalink'  => get_permalink( $product_id ) ?: '',
		];
	}

	// ── Currency helper (product-scoped) ─────────────────────────────────────────

	protected function get_currencies_in_period( int $product_id, string $from, string $to ): array {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT DISTINCT o.currency
				FROM {$wpdb->prefix}storeengine_order_items AS oi
				INNER JOIN {$wpdb->prefix}storeengine_order_item_meta AS pid
					ON pid.order_item_id = oi.order_item_id AND pid.meta_key = '_product_id'
				INNER JOIN {$wpdb->prefix}storeengine_orders AS o
					ON o.id = oi.order_id AND o.type = 'order'
				INNER JOIN {$wpdb->prefix}storeengine_orders_meta AS dm
					ON dm.order_id = o.id AND dm.meta_key = '_order_placed_date_gmt'
				WHERE pid.meta_value = %d
					AND o.currency IS NOT NULL AND o.currency <> ''
					AND CAST( dm.meta_value AS DATE ) BETWEEN %s AND %s
				ORDER BY o.currency ASC",
			$product_id, $from, $to
		);

		$key    = $this->get_cache_key( 'storeengine_orders', $query, $product_id, $from, $to );
		$cached = wp_cache_get( $key, 'storeengine_orders-queries' );

		if ( false !== $cached ) {
			return $cached;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
		$rows       = $wpdb->get_col( $query );
		$base       = strtoupper( Helper::get_settings( 'store_currency', 'USD' ) );
		$currencies = array_map( 'strtoupper', $rows ?: [] );

		if ( in_array( $base, $currencies, true ) ) {
			$currencies = array_merge( [ $base ], array_diff( $currencies, [ $base ] ) );
		}

		wp_cache_set( $key, $currencies, 'storeengine_orders-queries' );

		return $currencies;
	}

	// ── Stat tiles ───────────────────────────────────────────────────────────────

	/**
	 * A period-over-period aggregate for a single product-scoped column.
	 *
	 * $expr is a SQL aggregate expression evaluated over the joined order-item
	 * rows (e.g. SUM(_line_total) or COUNT(DISTINCT order_id)).
	 */
	protected function period_stat( string $expr, int $product_id, string $from, string $to, int $compare, string $currency ): array {
		global $wpdb;

		$statuses = self::PAID_STATUSES;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Values passed as %s/%d via prepare(); interpolated tokens are $wpdb->prefix, the fixed PAID_STATUSES constant and the internal $expr aggregate literal (no user input); custom StoreEngine tables. Result is cached below.
		$query = $wpdb->prepare(
			"SELECT
				curr.val AS current_val,
				prev.val AS previous_val,
				CASE
					WHEN prev.val = 0 AND curr.val > 0 THEN 100
					WHEN prev.val = 0 THEN 0
					ELSE ROUND( ( ( curr.val - prev.val ) / prev.val ) * 100, 2 )
				END AS rate
			FROM (
				SELECT COALESCE( {$expr}, 0 ) AS val
				FROM {$wpdb->prefix}storeengine_order_items AS oi
				INNER JOIN {$wpdb->prefix}storeengine_order_item_meta AS pid
					ON pid.order_item_id = oi.order_item_id AND pid.meta_key = '_product_id'
				LEFT JOIN {$wpdb->prefix}storeengine_order_item_meta AS lt
					ON lt.order_item_id = oi.order_item_id AND lt.meta_key = '_line_total'
				LEFT JOIN {$wpdb->prefix}storeengine_order_item_meta AS qty
					ON qty.order_item_id = oi.order_item_id AND qty.meta_key = '_quantity'
				INNER JOIN {$wpdb->prefix}storeengine_orders AS o
					ON o.id = oi.order_id AND o.type = 'order' AND o.currency = %s
					AND o.status IN ( {$statuses} )
				INNER JOIN {$wpdb->prefix}storeengine_orders_meta AS dm
					ON dm.order_id = o.id AND dm.meta_key = '_order_placed_date_gmt'
				WHERE pid.meta_value = %d
					AND DATE( dm.meta_value ) BETWEEN %s AND %s
			) AS curr
			CROSS JOIN (
				SELECT COALESCE( {$expr}, 0 ) AS val
				FROM {$wpdb->prefix}storeengine_order_items AS oi
				INNER JOIN {$wpdb->prefix}storeengine_order_item_meta AS pid
					ON pid.order_item_id = oi.order_item_id AND pid.meta_key = '_product_id'
				LEFT JOIN {$wpdb->prefix}storeengine_order_item_meta AS lt
					ON lt.order_item_id = oi.order_item_id AND lt.meta_key = '_line_total'
				LEFT JOIN {$wpdb->prefix}storeengine_order_item_meta AS qty
					ON qty.order_item_id = oi.order_item_id AND qty.meta_key = '_quantity'
				INNER JOIN {$wpdb->prefix}storeengine_orders AS o
					ON o.id = oi.order_id AND o.type = 'order' AND o.currency = %s
					AND o.status IN ( {$statuses} )
				INNER JOIN {$wpdb->prefix}storeengine_orders_meta AS dm
					ON dm.order_id = o.id AND dm.meta_key = '_order_placed_date_gmt'
				WHERE pid.meta_value = %d
					AND DATE( dm.meta_value ) BETWEEN DATE_SUB( %s, INTERVAL %d DAY ) AND DATE_SUB( %s, INTERVAL 1 DAY )
			) AS prev",
			$currency, $product_id, $from, $to,
			$currency, $product_id, $from, $compare, $from
		);

		$key  = $this->get_cache_key( 'storeengine_orders', $query, $product_id, $from, $to, $compare, $currency );
		$data = wp_cache_get( $key, 'storeengine_orders-queries' );

		if ( false === $data ) {
			$result = $wpdb->get_row( $query );
			// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, PluginCheck.Security.DirectDB.UnescapedDBParameter

			$data = [
				'current' => $result ? (float) $result->current_val : 0.0,
				'rate'    => ( $result && null !== $result->rate ) ? (float) $result->rate : 0.0,
			];

			wp_cache_set( $key, $data, 'storeengine_orders-queries' );
		}

		return $data;
	}

	protected function get_revenue_stats( int $product_id, string $from, string $to, int $compare, string $currency ): array {
		$r = $this->period_stat( 'SUM( CAST( lt.meta_value AS DECIMAL(18,2) ) )', $product_id, $from, $to, $compare, $currency );

		return [
			'count'    => (float) $r['current'],
			'rate'     => $r['rate'],
			'format'   => true,
			'currency' => $currency,
		];
	}

	protected function get_units_stats( int $product_id, string $from, string $to, int $compare, string $currency ): array {
		$r = $this->period_stat( 'SUM( CAST( qty.meta_value AS UNSIGNED ) )', $product_id, $from, $to, $compare, $currency );

		return [
			'count'    => (int) $r['current'],
			'rate'     => $r['rate'],
			'currency' => $currency,
		];
	}

	protected function get_orders_stats( int $product_id, string $from, string $to, int $compare, string $currency ): array {
		$r = $this->period_stat( 'COUNT( DISTINCT oi.order_id )', $product_id, $from, $to, $compare, $currency );

		return [
			'count'    => (int) $r['current'],
			'rate'     => $r['rate'],
			'currency' => $currency,
		];
	}

	protected function get_aov_stats( array $revenue, array $orders, string $currency ): array {
		$order_count = (int) $orders['count'];
		$value       = $order_count > 0 ? round( (float) $revenue['count'] / $order_count, 2 ) : 0.0;

		return [
			'count'    => $value,
			'rate'     => null,
			'format'   => true,
			'currency' => $currency,
		];
	}

	// ── Growth chart ──────────────────────────────────────────────────────────────

	protected function growth_report( int $product_id, string $from, string $to, string $currency ): array {
		global $wpdb;

		$statuses = self::PAID_STATUSES;

		$chart_data = [
			'datasets' => [
				[
					'label'           => __( 'Revenue', 'storeengine' ),
					'format'          => true,
					'data'            => [],
					'borderColor'     => '#006BFF',
					'backgroundColor' => '#006BFF',
				],
				[
					'label'           => __( 'Units', 'storeengine' ),
					'data'            => [],
					'borderColor'     => '#16A34A',
					'backgroundColor' => '#16A34A',
				],
			],
		];

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated token is the fixed PAID_STATUSES constant (and $wpdb->prefix); all values passed as %s/%d via prepare(). No user input.
		$query = $wpdb->prepare(
			"SELECT
				DATE( dm.meta_value ) AS date,
				COALESCE( SUM( CAST( lt.meta_value AS DECIMAL(18,2) ) ), 0 ) AS revenue,
				COALESCE( SUM( CAST( qty.meta_value AS UNSIGNED ) ), 0 ) AS units
			FROM {$wpdb->prefix}storeengine_order_items AS oi
			INNER JOIN {$wpdb->prefix}storeengine_order_item_meta AS pid
				ON pid.order_item_id = oi.order_item_id AND pid.meta_key = '_product_id'
			LEFT JOIN {$wpdb->prefix}storeengine_order_item_meta AS lt
				ON lt.order_item_id = oi.order_item_id AND lt.meta_key = '_line_total'
			LEFT JOIN {$wpdb->prefix}storeengine_order_item_meta AS qty
				ON qty.order_item_id = oi.order_item_id AND qty.meta_key = '_quantity'
			INNER JOIN {$wpdb->prefix}storeengine_orders AS o
				ON o.id = oi.order_id AND o.type = 'order' AND o.currency = %s
				AND o.status IN ( {$statuses} )
			INNER JOIN {$wpdb->prefix}storeengine_orders_meta AS dm
				ON dm.order_id = o.id AND dm.meta_key = '_order_placed_date_gmt'
			WHERE pid.meta_value = %d
				AND dm.meta_value <> ''
				AND DATE( dm.meta_value ) BETWEEN %s AND %s
			GROUP BY DATE( dm.meta_value )
			ORDER BY date ASC",
			$currency, $product_id, $from, $to
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$key  = $this->get_cache_key( 'storeengine_orders', $query, $product_id, $from, $to, $currency );
		$data = wp_cache_get( $key, 'storeengine_orders-queries' );

		if ( false === $data ) {
			$results = $wpdb->get_results( $query, OBJECT_K ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared

			$start = new DateTime( $from );
			$end   = new DateTime( $to );
			$end->modify( '+1 day' );

			$data = [ 'labels' => [], 'revenue' => [], 'units' => [] ];

			for ( $date = $start; $date < $end; $date->modify( '+1 day' ) ) {
				$day              = $date->format( 'Y-m-d' );
				$data['labels'][] = $date->format( 'M j, Y' );

				if ( isset( $results[ $day ] ) ) {
					$data['revenue'][] = abs( (float) $results[ $day ]->revenue );
					$data['units'][]   = (int) $results[ $day ]->units;
				} else {
					$data['revenue'][] = 0;
					$data['units'][]   = 0;
				}
			}

			$data['totals'] = [
				'revenue' => array_sum( $data['revenue'] ),
				'units'   => array_sum( $data['units'] ),
			];

			wp_cache_set( $key, $data, 'storeengine_orders-queries' );
		}

		$chart_data['labels']              = $data['labels'];
		$chart_data['totals']              = $data['totals'];
		$chart_data['currency']            = $currency;
		$chart_data['datasets'][0]['data'] = $data['revenue'];
		$chart_data['datasets'][1]['data'] = $data['units'];

		return $chart_data;
	}

	// ── Price / variation breakdown ───────────────────────────────────────────────

	protected function get_price_breakdown( int $product_id, string $from, string $to, string $currency ): array {
		global $wpdb;

		$statuses = self::PAID_STATUSES;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated token is the fixed PAID_STATUSES constant (and $wpdb->prefix); all values passed as %s/%d via prepare(). No user input.
		$query = $wpdb->prepare(
			"SELECT
				price_id.meta_value AS price_id,
				COALESCE( SUM( CAST( lt.meta_value AS DECIMAL(18,2) ) ), 0 ) AS revenue,
				COALESCE( SUM( CAST( qty.meta_value AS UNSIGNED ) ), 0 ) AS units,
				COUNT( DISTINCT oi.order_id ) AS orders
			FROM {$wpdb->prefix}storeengine_order_items AS oi
			INNER JOIN {$wpdb->prefix}storeengine_order_item_meta AS pid
				ON pid.order_item_id = oi.order_item_id AND pid.meta_key = '_product_id'
			LEFT JOIN {$wpdb->prefix}storeengine_order_item_meta AS price_id
				ON price_id.order_item_id = oi.order_item_id AND price_id.meta_key = '_price_id'
			LEFT JOIN {$wpdb->prefix}storeengine_order_item_meta AS lt
				ON lt.order_item_id = oi.order_item_id AND lt.meta_key = '_line_total'
			LEFT JOIN {$wpdb->prefix}storeengine_order_item_meta AS qty
				ON qty.order_item_id = oi.order_item_id AND qty.meta_key = '_quantity'
			INNER JOIN {$wpdb->prefix}storeengine_orders AS o
				ON o.id = oi.order_id AND o.type = 'order' AND o.currency = %s
				AND o.status IN ( {$statuses} )
			INNER JOIN {$wpdb->prefix}storeengine_orders_meta AS dm
				ON dm.order_id = o.id AND dm.meta_key = '_order_placed_date_gmt'
			WHERE pid.meta_value = %d
				AND DATE( dm.meta_value ) BETWEEN %s AND %s
			GROUP BY price_id.meta_value
			ORDER BY revenue DESC",
			$currency, $product_id, $from, $to
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$key     = $this->get_cache_key( 'storeengine_orders', $query, $product_id, $from, $to, $currency );
		$results = wp_cache_get( $key, 'storeengine_orders-queries' );

		if ( false === $results ) {
			$rows    = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$results = [];

			foreach ( (array) $rows as $row ) {
				$price_id = (int) $row->price_id;
				$results[] = [
					'price_id' => $price_id,
					'name'     => $price_id ? ( get_the_title( $price_id ) ?: sprintf( /* translators: %d: price id */ __( 'Price #%d', 'storeengine' ), $price_id ) ) : __( 'Default', 'storeengine' ),
					'revenue'  => (float) $row->revenue,
					'units'    => (int) $row->units,
					'orders'   => (int) $row->orders,
				];
			}

			wp_cache_set( $key, $results, 'storeengine_orders-queries' );
		}

		return $results;
	}

	// ── Top customers for this product ────────────────────────────────────────────

	protected function get_top_customers( int $product_id, string $from, string $to, string $currency ): array {
		global $wpdb;

		$statuses = self::PAID_STATUSES;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Interpolated token is the fixed PAID_STATUSES constant (and $wpdb->prefix); all values passed as %s/%d via prepare(). No user input.
		$query = $wpdb->prepare(
			"SELECT
				o.customer_id AS customer_id,
				COALESCE( SUM( CAST( lt.meta_value AS DECIMAL(18,2) ) ), 0 ) AS revenue,
				COUNT( DISTINCT oi.order_id ) AS orders
			FROM {$wpdb->prefix}storeengine_order_items AS oi
			INNER JOIN {$wpdb->prefix}storeengine_order_item_meta AS pid
				ON pid.order_item_id = oi.order_item_id AND pid.meta_key = '_product_id'
			LEFT JOIN {$wpdb->prefix}storeengine_order_item_meta AS lt
				ON lt.order_item_id = oi.order_item_id AND lt.meta_key = '_line_total'
			INNER JOIN {$wpdb->prefix}storeengine_orders AS o
				ON o.id = oi.order_id AND o.type = 'order' AND o.currency = %s
				AND o.status IN ( {$statuses} )
			INNER JOIN {$wpdb->prefix}storeengine_orders_meta AS dm
				ON dm.order_id = o.id AND dm.meta_key = '_order_placed_date_gmt'
			WHERE pid.meta_value = %d
				AND o.customer_id IS NOT NULL AND o.customer_id > 0
				AND DATE( dm.meta_value ) BETWEEN %s AND %s
			GROUP BY o.customer_id
			ORDER BY revenue DESC
			LIMIT 5",
			$currency, $product_id, $from, $to
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		$key     = $this->get_cache_key( 'storeengine_orders', $query, $product_id, $from, $to, $currency );
		$results = wp_cache_get( $key, 'storeengine_orders-queries' );

		if ( false === $results ) {
			$rows    = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$results = [];

			foreach ( (array) $rows as $row ) {
				$user = get_userdata( (int) $row->customer_id );

				$results[] = [
					'customer_id' => (int) $row->customer_id,
					'name'        => $user ? $user->display_name : __( 'Guest', 'storeengine' ),
					'email'       => $user ? $user->user_email : '',
					'avatar'      => get_avatar_url( (int) $row->customer_id, [ 'size' => 40 ] ),
					'revenue'     => (float) $row->revenue,
					'orders'      => (int) $row->orders,
				];
			}

			wp_cache_set( $key, $results, 'storeengine_orders-queries' );
		}

		return $results;
	}

	// ── Recent orders containing this product ─────────────────────────────────────

	protected function get_recent_orders( int $product_id, string $currency ): array {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT DISTINCT
				o.id AS order_id,
				o.status AS status,
				o.total_amount AS total,
				o.currency AS currency,
				o.customer_id AS customer_id,
				dm.meta_value AS placed_at
			FROM {$wpdb->prefix}storeengine_order_items AS oi
			INNER JOIN {$wpdb->prefix}storeengine_order_item_meta AS pid
				ON pid.order_item_id = oi.order_item_id AND pid.meta_key = '_product_id'
			INNER JOIN {$wpdb->prefix}storeengine_orders AS o
				ON o.id = oi.order_id AND o.type = 'order' AND o.currency = %s
			LEFT JOIN {$wpdb->prefix}storeengine_orders_meta AS dm
				ON dm.order_id = o.id AND dm.meta_key = '_order_placed_date_gmt'
			WHERE pid.meta_value = %d
			ORDER BY o.id DESC
			LIMIT 6",
			$currency, $product_id
		);

		$key     = $this->get_cache_key( 'storeengine_orders', $query, $product_id, $currency );
		$results = wp_cache_get( $key, 'storeengine_orders-queries' );

		if ( false === $results ) {
			$rows    = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared
			$results = [];

			foreach ( (array) $rows as $row ) {
				$user = $row->customer_id ? get_userdata( (int) $row->customer_id ) : null;

				$results[] = [
					'order_id' => (int) $row->order_id,
					'customer' => $user ? $user->display_name : __( 'Guest', 'storeengine' ),
					'status'   => $row->status,
					'amount'   => (float) $row->total,
					'currency' => $row->currency ?: $currency,
					'date'     => $row->placed_at ? gmdate( 'M j, Y', strtotime( $row->placed_at ) ) : '',
				];
			}

			wp_cache_set( $key, $results, 'storeengine_orders-queries' );
		}

		return $results;
	}

	// ── Helpers ───────────────────────────────────────────────────────────────────

	protected function get_cache_key( $group, $sql, ...$args ): string {
		return get_class( $this ) . ':' . Caching::get_query_cache_key( $group, $sql, ...$args );
	}
}
