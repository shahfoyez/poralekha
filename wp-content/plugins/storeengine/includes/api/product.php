<?php

namespace StoreEngine\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\classes\AbstractProduct;
use StoreEngine\Classes\Price;
use StoreEngine\Classes\Product\VariableProduct;
use StoreEngine\Classes\SkuGenerator;
use StoreEngine\Classes\Variation;
use StoreEngine\Utils\Helper;
use Throwable;
use WP_Post;
use WP_REST_Request;

class Product {

	public static function init() {
		$self = new self();

		add_filter( 'rest_prepare_' . Helper::PRODUCT_POST_TYPE, [ $self, 'extend_product_rest_response' ], 10, 3 );
		add_action( 'rest_insert_storeengine_product', [ $self, 'save_product_data' ], 10, 3 );
		add_action( 'rest_api_init', [ $self, 'register_stock_routes' ] );
	}

	public function register_stock_routes() {
		register_rest_route( 'storeengine/v1', '/products/(?P<id>\d+)/stock-adjust', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rest_stock_adjust' ],
			'permission_callback' => [ $this, 'rest_stock_permission' ],
			'args'                => [
				'variation_id' => [ 'type' => 'integer', 'default' => 0 ],
				'action'       => [ 'type' => 'string', 'required' => true, 'enum' => [ 'add', 'remove', 'set' ] ],
				'quantity'     => [ 'type' => 'integer', 'required' => true, 'minimum' => 0 ],
				'reason'       => [ 'type' => 'string', 'default' => 'manual' ],
				'note'         => [ 'type' => 'string', 'default' => '' ],
			],
		] );

		register_rest_route( 'storeengine/v1', '/products/(?P<id>\d+)/stock-movements', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_stock_movements' ],
			'permission_callback' => [ $this, 'rest_stock_permission' ],
			'args'                => [
				'variation_id' => [ 'type' => 'integer', 'default' => 0 ],
				'per_page'     => [ 'type' => 'integer', 'default' => 25, 'minimum' => 1, 'maximum' => 100 ],
				'page'         => [ 'type' => 'integer', 'default' => 1, 'minimum' => 1 ],
			],
		] );

		// Public: rendered Quick View (mini single-product) markup for the shop
		// archive modal.
		register_rest_route( 'storeengine/v1', '/products/(?P<id>\d+)/quick-view', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_quick_view' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'id' => [ 'type' => 'integer' ],
			],
		] );

		// Recently-viewed cards for a client-supplied list of product ids (order
		// preserved). The list lives in the shopper's browser (localStorage); this
		// only renders the cards.
		register_rest_route( 'storeengine/v1', '/products/recently-viewed', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_recently_viewed' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'ids'     => [ 'type' => 'string', 'default' => '' ],
				'exclude' => [ 'type' => 'integer', 'default' => 0 ],
			],
		] );

		// Wishlist. Toggle/merge persist to user meta (logged-in only); guests
		// keep their list in the browser. Cards renders for a given id list.
		register_rest_route( 'storeengine/v1', '/wishlist/toggle', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rest_wishlist_toggle' ],
			'permission_callback' => fn() => is_user_logged_in(),
			'args'                => [
				'product_id' => [ 'type' => 'integer', 'required' => true ],
			],
		] );
		register_rest_route( 'storeengine/v1', '/wishlist/merge', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rest_wishlist_merge' ],
			'permission_callback' => fn() => is_user_logged_in(),
			'args'                => [
				'ids' => [ 'type' => 'string', 'default' => '' ],
			],
		] );
		register_rest_route( 'storeengine/v1', '/wishlist/cards', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_wishlist_cards' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'ids' => [ 'type' => 'string', 'default' => '' ],
			],
		] );

		// Product compare. Same split as the wishlist: toggle/merge persist to
		// user meta for logged-in shoppers, guests keep the list in the browser,
		// and table renders the comparison for a given id list.
		register_rest_route( 'storeengine/v1', '/compare/toggle', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rest_compare_toggle' ],
			'permission_callback' => fn() => is_user_logged_in(),
			'args'                => [
				'product_id' => [ 'type' => 'integer', 'required' => true ],
			],
		] );
		register_rest_route( 'storeengine/v1', '/compare/clear', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rest_compare_clear' ],
			'permission_callback' => fn() => is_user_logged_in(),
		] );
		register_rest_route( 'storeengine/v1', '/compare/merge', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rest_compare_merge' ],
			'permission_callback' => fn() => is_user_logged_in(),
			'args'                => [
				'ids' => [ 'type' => 'string', 'default' => '' ],
			],
		] );
		register_rest_route( 'storeengine/v1', '/compare/table', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_compare_table' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'ids' => [ 'type' => 'string', 'default' => '' ],
			],
		] );
		// Minimal id/title/thumb for the docked tray — the full table would be
		// far too much payload just to draw a row of thumbnails.
		register_rest_route( 'storeengine/v1', '/compare/items', [
			'methods'             => 'GET',
			'callback'            => [ $this, 'rest_compare_items' ],
			'permission_callback' => '__return_true',
			'args'                => [
				'ids' => [ 'type' => 'string', 'default' => '' ],
			],
		] );

		// On-demand SKU / barcode generation for the "Generate" buttons in the
		// product editor. Uses the same engine + pattern as auto-on-save.
		register_rest_route( 'storeengine/v1', '/inventory/generate-code', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rest_generate_code' ],
			'permission_callback' => fn() => current_user_can( 'edit_storeengine_products' ),
			'args'                => [
				'type'        => [ 'type' => 'string', 'required' => true, 'enum' => [ 'sku', 'barcode' ] ],
				'name'        => [ 'type' => 'string', 'default' => '' ],
				'category_id' => [ 'type' => 'integer', 'default' => 0 ],
			],
		] );

		// Batch resolve pasted SKUs / barcodes to product name + price for the
		// Barcode Labels page. The standard product collection `search` only
		// matches title/content, so it can't auto-fill a label from a raw code.
		register_rest_route( 'storeengine/v1', '/inventory/resolve-codes', [
			'methods'             => 'POST',
			'callback'            => [ $this, 'rest_resolve_codes' ],
			'permission_callback' => fn() => current_user_can( 'edit_storeengine_products' ),
			'args'                => [
				'codes' => [
					'type'     => 'array',
					'required' => true,
					'items'    => [ 'type' => 'string' ],
				],
			],
		] );
	}

	public function rest_generate_code( WP_REST_Request $request ) {
		$type = (string) $request['type'];

		if ( 'barcode' === $type ) {
			return rest_ensure_response( [ 'value' => SkuGenerator::generate_barcode() ] );
		}

		$category = '';
		$cat_id   = (int) ( $request['category_id'] ?? 0 );
		if ( $cat_id > 0 ) {
			$term = get_term( $cat_id, Helper::PRODUCT_CATEGORY_TAXONOMY );
			if ( $term && ! is_wp_error( $term ) ) {
				$category = (string) $term->slug;
			}
		}

		return rest_ensure_response( [
			'value' => SkuGenerator::generate_sku( [
				'name'     => sanitize_text_field( (string) ( $request['name'] ?? '' ) ),
				'category' => $category,
			] ),
		] );
	}

	/**
	 * Resolve a batch of SKUs / barcodes to printable label data
	 * (name, sku, barcode, selling price). Exact match only — keyed by the
	 * original code so the Barcode Labels page can auto-fill pasted entries.
	 * Codes with no match are simply omitted from the response map.
	 */
	public function rest_resolve_codes( WP_REST_Request $request ) {
		global $wpdb;

		$codes = $request->get_param( 'codes' );
		$codes = is_array( $codes ) ? $codes : [];
		$codes = array_values( array_unique( array_filter(
			array_map( static fn( $c ) => trim( sanitize_text_field( (string) $c ) ), $codes ),
			static fn( $c ) => '' !== $c
		) ) );
		$codes = array_slice( $codes, 0, 200 );

		$out = [];
		if ( empty( $codes ) ) {
			return rest_ensure_response( (object) $out );
		}

		$variations_table = $wpdb->prefix . 'storeengine_product_variations';

		foreach ( $codes as $code ) {
			// Variant exact match (barcode or SKU). Variations store a price
			// increment; the selling price is base + increment (mirrors the
			// POS lookup controller).
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- %i identifier + %s values bound via prepare() on a custom StoreEngine table joined to core posts; per-code barcode/SKU lookup, not cacheable.
			$row = $wpdb->get_row( $wpdb->prepare(
				"SELECT v.id, v.product_id, v.sku, v.barcode, v.price, p.post_title AS product_title
				   FROM %i v
				   LEFT JOIN {$wpdb->posts} p ON p.ID = v.product_id
				  WHERE ( v.barcode = %s OR v.sku = %s )
				    AND p.post_status <> 'trash'
				  LIMIT 1",
				$variations_table, $code, $code
			) );
			// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter

			if ( $row ) {
				// Object-level scope: never disclose a product the caller can't edit.
				if ( ! $this->can_resolve_product( (int) $row->product_id ) ) {
					continue;
				}
				$name   = (string) ( $row->product_title ?? '' );
				$vlabel = $this->resolve_variant_label( (int) $row->id );
				if ( '' !== $vlabel ) {
					$name = '' !== $name ? $name . ' – ' . $vlabel : $vlabel;
				}
				$extra = null === $row->price ? 0.0 : (float) $row->price;
				$out[ $code ] = [
					'name'    => $name,
					'sku'     => (string) $row->sku,
					'barcode' => $row->barcode ? (string) $row->barcode : '',
					'price'   => $this->resolve_base_price( (int) $row->product_id ) + $extra,
				];
				continue;
			}

			// Simple-product exact match by SKU / barcode postmeta.
			// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$simple = $wpdb->get_row( $wpdb->prepare(
				"SELECT p.ID AS product_id,
				        p.post_title AS product_title,
				        sku.meta_value AS sku,
				        bc.meta_value  AS barcode
				   FROM {$wpdb->posts} p
				   LEFT JOIN {$wpdb->postmeta} sku ON sku.post_id = p.ID AND sku.meta_key = '_storeengine_sku'
				   LEFT JOIN {$wpdb->postmeta} bc  ON bc.post_id  = p.ID AND bc.meta_key  = '_storeengine_barcode'
				  WHERE p.post_type = %s
				    AND p.post_status <> 'trash'
				    AND ( sku.meta_value = %s OR bc.meta_value = %s )
				  LIMIT 1",
				Helper::PRODUCT_POST_TYPE, $code, $code
			) );
			// phpcs:enable

			if ( $simple ) {
				// Object-level scope: never disclose a product the caller can't edit.
				if ( ! $this->can_resolve_product( (int) $simple->product_id ) ) {
					continue;
				}
				$prices = Helper::get_prices_array_by_product_id( (int) $simple->product_id );
				$out[ $code ] = [
					'name'    => (string) ( $simple->product_title ?? '' ),
					'sku'     => (string) ( $simple->sku ?? '' ),
					'barcode' => $simple->barcode ? (string) $simple->barcode : '',
					'price'   => isset( $prices[0]['price'] ) ? (float) $prices[0]['price'] : null,
				];
			}
		}

		return rest_ensure_response( (object) $out );
	}

	/**
	 * Default (lowest-order) base price for a product, from the price table.
	 */
	protected function resolve_base_price( int $product_id ): float {
		if ( ! $product_id ) {
			return 0.0;
		}
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$base = $wpdb->get_var( $wpdb->prepare(
			"SELECT price FROM {$wpdb->prefix}storeengine_product_price WHERE product_id = %d ORDER BY `order` ASC LIMIT 1",
			$product_id
		) );
		// phpcs:enable
		return null === $base ? 0.0 : (float) $base;
	}

	/**
	 * Human-readable variant attribute label, e.g. "Black / M".
	 */
	protected function resolve_variant_label( int $variation_id ): string {
		if ( ! $variation_id ) {
			return '';
		}
		try {
			$variation = ( new Variation( $variation_id ) )->get();
		} catch ( Throwable $e ) {
			return '';
		}
		if ( ! $variation ) {
			return '';
		}
		$parts = [];
		foreach ( $variation->get_attributes() as $attribute ) {
			if ( ! empty( $attribute->name ) ) {
				$parts[] = $attribute->name;
			}
		}
		return implode( ' / ', $parts );
	}

	/**
	 * Permission gate for the per-product stock routes. The old gate just
	 * checked the plural `edit_storeengine_products` cap, which the multi-
	 * vendor addon grants to EVERY vendor — so any vendor could POST to any
	 * other vendor's product id and adjust their stock (or read their
	 * movement history). Now we also verify the caller owns the product
	 * being targeted.
	 *
	 * Uses the inventory addon's Authorization helper when available (same
	 * helper the sibling /inventory/adjust route uses, so behavior stays
	 * consistent across endpoints). When the addon isn't loaded the IDOR
	 * surface doesn't exist either — multi-vendor needs inventory — but we
	 * fall back to a direct post_author check to be safe.
	 */
	/**
	 * Render the Quick View (mini single-product) markup for the archive modal.
	 */
	public function rest_quick_view( \WP_REST_Request $request ) {
		$product_id = absint( $request['id'] );
		$product    = Helper::get_product( $product_id );

		if ( ! $product || 'publish' !== get_post_status( $product_id ) ) {
			return new \WP_Error( 'storeengine_not_found', __( 'Product not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		global $post;
		$post               = get_post( $product_id ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$GLOBALS['product'] = $product; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
		setup_postdata( $post );

		ob_start();
		Helper::get_template( 'single-product/quick-view.php', [ 'product' => $product ] );
		$html = ob_get_clean();

		wp_reset_postdata();

		$response = [
			'id'        => $product_id,
			'permalink' => get_permalink( $product_id ),
			'html'      => $html,
		];

		// Variable products need their variation matrix client-side to wire the
		// picker + variation-aware price placeholder. On a full product page this
		// is localized as `StoreEngineProductVariations`, but the archive page
		// (which hosts Quick View) never localizes it, so ship it in the payload.
		if ( $product instanceof \StoreEngine\Classes\Product\VariableProduct && 'variable' === $product->get_type() ) {
			$response['variations'] = \StoreEngine\Assets::get_product_variations( $product );
		}

		return rest_ensure_response( $response );
	}

	/**
	 * Render "recently viewed" product cards for a client-supplied id list.
	 * Order follows the given ids (most-recent first); the current product is
	 * excluded. Returns rendered loop-card HTML.
	 */
	public function rest_recently_viewed( \WP_REST_Request $request ) {
		if ( ! Helper::get_settings( 'enable_recently_viewed', true ) ) {
			return rest_ensure_response( [ 'html' => '' ] );
		}

		$ids = array_values( array_filter( array_map(
			'absint',
			explode( ',', (string) $request->get_param( 'ids' ) )
		) ) );

		$exclude = absint( $request->get_param( 'exclude' ) );
		if ( $exclude ) {
			$ids = array_values( array_diff( $ids, [ $exclude ] ) );
		}
		$ids = array_slice( array_unique( $ids ), 0, 12 );

		return rest_ensure_response( [ 'html' => $this->render_product_cards( $ids ) ] );
	}

	/**
	 * Render loop-card HTML for the given product ids, in the order given.
	 * Shared by Recently Viewed and Wishlist.
	 *
	 * @param int[] $ids
	 */
	private function render_product_cards( array $ids ): string {
		$ids = array_values( array_filter( array_map( 'absint', $ids ) ) );
		if ( empty( $ids ) ) {
			return '';
		}

		$products_per_row = Helper::get_settings( 'product_archive_products_per_row', (object) [
			'desktop' => 3,
			'tablet'  => 2,
			'mobile'  => 1,
		] );
		$grid_class = Helper::get_responsive_column( [
			'desktop' => (int) ( $products_per_row->desktop ?? 3 ),
			'tablet'  => (int) ( $products_per_row->tablet ?? 2 ),
			'mobile'  => (int) ( $products_per_row->mobile ?? 1 ),
		] );

		$query = new \WP_Query( [
			'post_type'           => Helper::PRODUCT_POST_TYPE,
			'post_status'         => 'publish',
			'post__in'            => $ids,
			'orderby'             => 'post__in',
			'posts_per_page'      => count( $ids ),
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
		] );

		ob_start();
		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				Helper::get_template( 'content-product.php', [ 'grid_class' => $grid_class ] );
			}
		}
		wp_reset_postdata();

		return (string) ob_get_clean();
	}

	/** Toggle a product in the logged-in user's wishlist (user meta). */
	public function rest_wishlist_toggle( \WP_REST_Request $request ) {
		if ( ! Helper::get_settings( 'enable_wishlist', false ) ) {
			return new \WP_Error( 'storeengine_wishlist_disabled', __( 'Wishlist is disabled.', 'storeengine' ), [ 'status' => 403 ] );
		}
		$product_id = absint( $request->get_param( 'product_id' ) );
		if ( ! $product_id || Helper::PRODUCT_POST_TYPE !== get_post_type( $product_id ) ) {
			return new \WP_Error( 'storeengine_not_found', __( 'Product not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$ids = storeengine_get_user_wishlist();
		$in  = in_array( $product_id, $ids, true );
		$ids = $in ? array_values( array_diff( $ids, [ $product_id ] ) ) : array_merge( $ids, [ $product_id ] );
		$ids = storeengine_set_user_wishlist( $ids );

		return rest_ensure_response( [
			'ids'         => array_map( 'strval', $ids ),
			'count'       => count( $ids ),
			'in_wishlist' => ! $in,
		] );
	}

	/** Merge a guest's browser wishlist into the account on login. */
	public function rest_wishlist_merge( \WP_REST_Request $request ) {
		if ( ! Helper::get_settings( 'enable_wishlist', false ) ) {
			return new \WP_Error( 'storeengine_wishlist_disabled', __( 'Wishlist is disabled.', 'storeengine' ), [ 'status' => 403 ] );
		}
		$incoming = array_filter( array_map( 'absint', explode( ',', (string) $request->get_param( 'ids' ) ) ) );
		$ids      = storeengine_set_user_wishlist( array_merge( storeengine_get_user_wishlist(), $incoming ) );

		return rest_ensure_response( [
			'ids'   => array_map( 'strval', $ids ),
			'count' => count( $ids ),
		] );
	}

	/** Toggle a product in the logged-in user's compare list (user meta). */
	public function rest_compare_toggle( \WP_REST_Request $request ) {
		if ( ! Helper::get_settings( 'enable_product_compare', false ) ) {
			return new \WP_Error( 'storeengine_compare_disabled', __( 'Product compare is disabled.', 'storeengine' ), [ 'status' => 403 ] );
		}
		$product_id = absint( $request->get_param( 'product_id' ) );
		if ( ! $product_id || Helper::PRODUCT_POST_TYPE !== get_post_type( $product_id ) ) {
			return new \WP_Error( 'storeengine_not_found', __( 'Product not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$ids = storeengine_get_user_compare();
		$in  = in_array( $product_id, $ids, true );
		$ids = $in ? array_values( array_diff( $ids, [ $product_id ] ) ) : array_merge( $ids, [ $product_id ] );
		// set_user_compare() applies the cap, so the response is authoritative
		// even when the shopper just pushed past the limit.
		$ids = storeengine_set_user_compare( $ids );

		return rest_ensure_response( [
			'ids'        => array_map( 'strval', $ids ),
			'count'      => count( $ids ),
			'in_compare' => in_array( $product_id, $ids, true ),
			'max'        => \StoreEngine\Classes\ProductCompare::max(),
		] );
	}

	/** Empty the logged-in user's compare list. */
	public function rest_compare_clear() {
		if ( ! Helper::get_settings( 'enable_product_compare', false ) ) {
			return new \WP_Error( 'storeengine_compare_disabled', __( 'Product compare is disabled.', 'storeengine' ), [ 'status' => 403 ] );
		}

		storeengine_set_user_compare( [] );

		return rest_ensure_response( [ 'ids' => [], 'count' => 0 ] );
	}

	/** Merge a guest's browser compare list into the account on login. */
	public function rest_compare_merge( \WP_REST_Request $request ) {
		if ( ! Helper::get_settings( 'enable_product_compare', false ) ) {
			return new \WP_Error( 'storeengine_compare_disabled', __( 'Product compare is disabled.', 'storeengine' ), [ 'status' => 403 ] );
		}
		$incoming = array_filter( array_map( 'absint', explode( ',', (string) $request->get_param( 'ids' ) ) ) );
		$ids      = storeengine_set_user_compare( array_merge( storeengine_get_user_compare(), $incoming ) );

		return rest_ensure_response( [
			'ids'   => array_map( 'strval', $ids ),
			'count' => count( $ids ),
		] );
	}

	/** Render the comparison table for a client-supplied id list. */
	public function rest_compare_table( \WP_REST_Request $request ) {
		if ( ! Helper::get_settings( 'enable_product_compare', false ) ) {
			return rest_ensure_response( [ 'html' => '' ] );
		}
		$ids = array_values( array_unique( array_filter( array_map(
			'absint',
			explode( ',', (string) $request->get_param( 'ids' ) )
		) ) ) );

		return rest_ensure_response( [
			'html' => \StoreEngine\Classes\ProductCompare::get_table_html( $ids ),
		] );
	}

	/** Minimal product info for the docked compare tray. */
	public function rest_compare_items( \WP_REST_Request $request ) {
		if ( ! Helper::get_settings( 'enable_product_compare', false ) ) {
			return rest_ensure_response( [ 'items' => [] ] );
		}

		$ids = array_slice(
			array_values( array_unique( array_filter( array_map(
				'absint',
				explode( ',', (string) $request->get_param( 'ids' ) )
			) ) ) ),
			0,
			\StoreEngine\Classes\ProductCompare::max()
		);

		$items = [];
		foreach ( $ids as $id ) {
			if ( Helper::PRODUCT_POST_TYPE !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
				continue;
			}
			$thumb   = get_the_post_thumbnail_url( $id, 'thumbnail' );
			$items[] = [
				'id'    => (string) $id,
				'title' => get_the_title( $id ),
				'url'   => get_permalink( $id ),
				'thumb' => $thumb ?: storeengine_placeholder_image_src(),
			];
		}

		return rest_ensure_response( [ 'items' => $items ] );
	}

	/** Render wishlist product cards for a client-supplied id list. */
	public function rest_wishlist_cards( \WP_REST_Request $request ) {
		if ( ! Helper::get_settings( 'enable_wishlist', false ) ) {
			return rest_ensure_response( [ 'html' => '' ] );
		}
		$ids = array_slice(
			array_values( array_unique( array_filter( array_map(
				'absint',
				explode( ',', (string) $request->get_param( 'ids' ) )
			) ) ) ),
			0,
			100
		);

		return rest_ensure_response( [ 'html' => $this->render_product_cards( $ids ) ] );
	}

	public function rest_stock_permission( WP_REST_Request $request ): bool {
		if ( ! current_user_can( 'edit_storeengine_products' ) ) {
			return false;
		}
		$product_id = (int) $request['id'];
		if ( $product_id <= 0 ) {
			return false;
		}
		if ( class_exists( '\\StoreEngine\\Addons\\Inventory\\Classes\\Authorization' ) ) {
			return \StoreEngine\Addons\Inventory\Classes\Authorization::can_modify_product( $product_id );
		}
		// Fallback: privileged roles bypass; everyone else must own the product.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return (int) get_post_field( 'post_author', $product_id ) === (int) get_current_user_id();
	}

	/**
	 * Object-level gate for cross-product code lookups (Barcode Labels resolve).
	 *
	 * The route only checks the broad `edit_storeengine_products` cap, which the
	 * multi-vendor addon grants to EVERY vendor — so on its own it is not a
	 * tenancy boundary. Without this check a vendor could resolve another
	 * seller's product (name / SKU / barcode / price) by guessing a code. Mirror
	 * rest_stock_permission's ownership logic so resolution is scoped to products
	 * the caller may actually edit.
	 */
	protected function can_resolve_product( int $product_id ): bool {
		if ( $product_id <= 0 ) {
			return false;
		}
		if ( class_exists( '\\StoreEngine\\Addons\\Inventory\\Classes\\Authorization' ) ) {
			return \StoreEngine\Addons\Inventory\Classes\Authorization::can_modify_product( $product_id );
		}
		// Fallback: privileged roles bypass; everyone else must own the product.
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}
		return (int) get_post_field( 'post_author', $product_id ) === (int) get_current_user_id();
	}

	public function rest_stock_adjust( \WP_REST_Request $request ) {
		$product_id   = (int) $request['id'];
		$variation_id = (int) ( $request['variation_id'] ?? 0 );
		$action       = (string) $request['action'];
		$quantity     = (int) $request['quantity'];
		$reason       = sanitize_text_field( (string) ( $request['reason'] ?? 'manual' ) );
		$note         = sanitize_textarea_field( (string) ( $request['note'] ?? '' ) );

		$result = \StoreEngine\Classes\StockManager::adjust_stock(
			$product_id,
			$variation_id,
			$action,
			$quantity,
			$reason,
			$note
		);

		if ( ! $result['ok'] ) {
			return new \WP_Error( 'stock_adjust_failed', $result['message'] ?? 'Failed to adjust stock', [ 'status' => 400 ] );
		}

		return new \WP_REST_Response( $result, 200 );
	}

	public function rest_stock_movements( \WP_REST_Request $request ) {
		$product_id   = (int) $request['id'];
		$variation_id = (int) ( $request['variation_id'] ?? 0 );
		$per_page     = max( 1, min( 100, (int) ( $request['per_page'] ?? 25 ) ) );
		$page         = max( 1, (int) ( $request['page'] ?? 1 ) );
		$offset       = ( $page - 1 ) * $per_page;

		$rows = \StoreEngine\Classes\StockManager::get_movements( $product_id, $variation_id, $per_page, $offset );

		return new \WP_REST_Response( [ 'items' => $rows ], 200 );
	}

	public function extend_product_rest_response( $item, $post, $request ) {
		$context = $request->get_param( 'context' );

		// Defence-in-depth: never expose downloadable-file URLs / attachment ids
		// in public (view / embed) REST responses. The meta is registered
		// edit-only (see Database::register_product_meta), so core normally
		// strips it here already — this guarantees it even if that context filter
		// is ever bypassed. Files are delivered through a permission-checked
		// download handler, never this product object.
		if ( 'edit' !== $context && isset( $item->data['meta']['_storeengine_product_downloadable_files'] ) ) {
			unset( $item->data['meta']['_storeengine_product_downloadable_files'] );
		}

		$product                    = Helper::get_product( $item->data['id'] );
		$item->data['product_type'] = $product->get_type();
		// Admin edit context (product editor, coupon price picker, etc.) must
		// see frontend-hidden prices so they remain selectable/manageable.
		$item->data['prices']       = Helper::get_prices_array_by_product_id( $item->data['id'], $context, 'edit' === $context );

		$can_see_inventory          = current_user_can( 'edit_storeengine_products' );
		$item->data['stock']        = self::build_stock_payload( $product, $can_see_inventory );

		// Simple-product SKU / barcode. Variable products carry these per-
		// variant in the `variants` array further down; simple products read
		// from postmeta (legacy convention also used by inventory queries
		// and SkuGenerator).
		if ( 'simple' === $item->data['product_type'] ) {
			$item->data['sku']     = (string) get_post_meta( $item->data['id'], '_storeengine_sku', true );
			$item->data['barcode'] = (string) get_post_meta( $item->data['id'], '_storeengine_barcode', true );
		}

		$item->data['integrations'] = array_map( fn( $integration ) => [
			'id'             => $integration->integration->get_id(),
			'product_id'     => $integration->price->get_product_id(),
			'price_id'       => $integration->price->get_id(),
			'integration_id' => $integration->integration->get_integration_id(),
			'provider'       => $integration->integration->get_provider(),
			'course_ids'     => 'storeengine/course-bundle' === $integration->integration->get_provider() ? get_post_meta( $integration->integration->get_integration_id(), 'academy_course_bundle_courses_ids', true ) ?? [] : [],
		], Helper::get_integrations_by_product_id( $item->data['id'] ) );

		$attributes = [];

		foreach ( $product->get_attributes() as $taxonomy => $terms ) {
			$taxonomyKey  = Helper::strip_attribute_taxonomy_name( $taxonomy );
			$attributes[] = [
				'label' => $taxonomyKey,
				'ids'   => array_map( fn( $term ) => $term->term_id, $terms ),
			];
		}

		$item->data['attributes'] = $attributes;

		if ( 'bundled' === $item->data['product_type'] ) {
			$item->data['bundles'] = $product->get_bundles();
		}

		if ( 'variable' === $item->data['product_type'] ) {
			$item->data['variants'] = array_map( function ( $variant ) use ( $can_see_inventory ) {
				$data       = [];
				$taxonomies = [];
				foreach ( $variant->get_attributes() as $attribute ) {
					if ( ! taxonomy_exists( $attribute->taxonomy ) ) {
						continue;
					}
					$data[] = [
						'label' => get_taxonomy( $attribute->taxonomy )->label,
						'value' => $attribute->name,
					];

					$taxonomies[ Helper::strip_attribute_taxonomy_name( $attribute->taxonomy ) ] = $attribute->term_id;
				}

				return [
					'id'                => $variant->get_id(),
					'taxonomies'        => $taxonomies,
					'data'              => $data,
					'featured_image_id' => $variant->get_featured_image(),
					'pricing_id'        => $variant->get_pricing_id(),
					'price'             => $variant->get_price(),
					'cost_price'        => method_exists( $variant, 'get_cost_price' ) ? $variant->get_cost_price() : null,
					'sku'               => $variant->get_sku(),
					'barcode'           => method_exists( $variant, 'get_barcode' ) ? $variant->get_barcode() : null,
					'stock'             => self::build_stock_payload( $variant, $can_see_inventory ),
				];
			}, $product->get_variants() );
		}

		return $item;
	}

	public static function build_stock_payload( $entity, bool $expose_inventory = false ): array {
		$payload = [
			'manages_stock' => false,
			'stock_status'  => 'instock',
			'is_in_stock'   => true,
			'low_stock'     => false,
			'backorders'    => 'no',
		];

		if ( ! is_object( $entity ) ) {
			return $payload;
		}

		if ( method_exists( $entity, 'manages_stock' ) ) {
			$payload['manages_stock'] = (bool) $entity->manages_stock();
		}

		if ( method_exists( $entity, 'get_stock_status' ) ) {
			$payload['stock_status'] = $entity->get_stock_status();
		}

		if ( method_exists( $entity, 'is_in_stock' ) ) {
			$payload['is_in_stock'] = (bool) $entity->is_in_stock();
		}

		if ( method_exists( $entity, 'is_low_stock' ) ) {
			$payload['low_stock'] = (bool) $entity->is_low_stock();
		}

		if ( method_exists( $entity, 'get_backorders' ) ) {
			$payload['backorders'] = $entity->get_backorders();
		}

		if ( $expose_inventory ) {
			if ( method_exists( $entity, 'get_stock_quantity' ) ) {
				$payload['stock_quantity'] = $entity->get_stock_quantity();
			}

			if ( method_exists( $entity, 'get_low_stock_threshold' ) ) {
				$payload['low_stock_threshold'] = $entity->get_low_stock_threshold();
			}

			if ( method_exists( $entity, 'is_sold_individually' ) ) {
				$payload['sold_individually'] = (bool) $entity->is_sold_individually();
			}
		}

		return $payload;
	}

	public function save_product_data( WP_Post $post, WP_REST_Request $request, bool $creating ) {
		$this->save_attributes( $post, $request );
		$this->save_stock_fields( $post, $request );

		// @TODO Update price props, this will reduces extra ajax endpoint for saving/creating price
		//       Also, improve ux as adding price will no longer need product id.

		// Updating custom sort-order.
		if ( ! $creating ) {
			$prices = $request->get_param( 'prices' );
			if ( ! empty( $prices ) && is_array( $prices ) ) {
				foreach ( $prices as $index => [ 'id' => $id, 'price_name' => $price_name ] ) {
					$price = new Price( $id );
					$price->set_name( $price_name );
					$price->set_order( $index );
					$price->save();
				}
			}
		}
	}


	public function save_attributes( WP_Post $post, WP_REST_Request $request ) {
		// Can be simple, variable, bundled, etc.
		$old_type     = get_post_meta( $post->ID, '_storeengine_product_type', true );
		$product_type = $request->get_param( 'product_type' ) ?? 'simple';
		$variants     = $request->get_param( 'variants' );
		$bundles      = $request->get_param( 'bundles' );

		// Handle variable/variations.
		if ( 'variable' === $old_type && ( $old_type !== $product_type || empty( $variants ) || ! is_array( $variants ) ) ) {
			$variants     = [];
			$product_type = 'simple';
			$product      = new VariableProduct( $post->ID );
			foreach ( $product->get_variants() as $variation ) {
				$variation->delete();
			}
		}

		if ( $variants && is_array( $variants ) ) {
			$product_type         = 'variable';
			$product              = new VariableProduct( $post->ID );
			$new_variations_data  = [];
			$edit_variations_data = [];

			foreach ( $variants as $variant ) {
				if ( ! isset( $variant['taxonomies'] ) || ! is_array( $variant['taxonomies'] ) ) {
					continue;
				}

				if ( isset( $variant['id'] ) ) {
					$edit_variations_data[ $variant['id'] ] = $variant;
				} else {
					$new_variations_data[] = $variant;
				}
			}

			foreach ( $product->get_variants() as $variation ) {
				if ( isset( $edit_variations_data[ $variation->get_id() ] ) ) {
					$this->save_variation_data( $variation, $product->get_id(), $edit_variations_data[ $variation->get_id() ] );
				} else {
					$variation->delete();
				}
			}

			if ( ! empty( $new_variations_data ) ) {
				foreach ( $new_variations_data as $new_variation_data ) {
					$this->save_variation_data( new Variation(), $product->get_id(), $new_variation_data );
				}
			}
		}

		// Handle product bundles.
		if ( 'bundled' === $old_type && ( $old_type !== $product_type || empty( $bundles ) || ! is_array( $bundles ) ) ) {
			$bundles = [];
			delete_post_meta( $post->ID, '_storeengine_product_bundles' );
			$product_type = 'simple';
		}

		if ( $bundles && is_array( $bundles ) ) {
			$data   = [];
			$prices = [];

			foreach ( $bundles as $bundle ) {
				$product_id = absint( $bundle['product_id'] ?? 0 );
				$price_id   = absint( $bundle['price_id'] ?? 0 );
				$quantity   = absint( $bundle['quantity'] ?? 1 );

				if ( ! $product_id || ! $price_id || ! $quantity ) {
					continue;
				}

				try {
					$price = new Price( $price_id );

					$prices[] = $price->get_price();

					$data[] = [
						'product_id' => $price->get_product_id(),
						'price_id'   => $price->get_id(),
						'quantity'   => $quantity,
					];
				} catch ( Throwable $e ) {
					Helper::log_error( $e );
				}
			}

			if ( ! empty( $data ) ) {
				$min_max = array_unique( [ min( $prices ), max( $prices ) ] );
				update_post_meta( $post->ID, '_storeengine_product_bundle_max_min_prices', $min_max );
				update_post_meta( $post->ID, '_storeengine_product_bundles', $data );

				$product_type = 'bundled';
			}
		}

		if ( $product_type ) {
			update_post_meta( $post->ID, '_storeengine_product_type', $product_type );
		}

		// Simple-product SKU + barcode. Variable products store these per-
		// variant inside the variations save loop above; for simple products
		// we mirror the legacy convention used by inventory queries and the
		// POS lookup-controller — postmeta keys _storeengine_sku /
		// _storeengine_barcode.
		if ( 'simple' === $product_type ) {
			if ( $request->has_param( 'sku' ) ) {
				$sku = sanitize_text_field( (string) $request->get_param( 'sku' ) );
				if ( '' === $sku ) {
					delete_post_meta( $post->ID, '_storeengine_sku' );
				} else {
					update_post_meta( $post->ID, '_storeengine_sku', $sku );
				}
			}
			if ( $request->has_param( 'barcode' ) ) {
				$barcode = sanitize_text_field( (string) $request->get_param( 'barcode' ) );
				if ( '' === $barcode ) {
					delete_post_meta( $post->ID, '_storeengine_barcode' );
				} else {
					update_post_meta( $post->ID, '_storeengine_barcode', $barcode );
				}
			}

			// Auto-generate when enabled and the field is still empty.
			if ( Helper::get_settings( 'auto_generate_sku' ) && '' === (string) get_post_meta( $post->ID, '_storeengine_sku', true ) ) {
				update_post_meta( $post->ID, '_storeengine_sku', SkuGenerator::generate_sku( [
					'name'     => $post->post_title,
					'category' => SkuGenerator::product_category_slug( $post->ID ),
				] ) );
			}
			if ( Helper::get_settings( 'auto_generate_barcode' ) && '' === (string) get_post_meta( $post->ID, '_storeengine_barcode', true ) ) {
				update_post_meta( $post->ID, '_storeengine_barcode', SkuGenerator::generate_barcode() );
			}
		}

		$product = Helper::get_product( $post->ID );

		$prices = [];

		foreach ( $product->get_prices() as $price ) {
			$prices[] = $price->get_price();
		};

		if ( $prices ) {
			$min_max = array_unique( [ min( $prices ), max( $prices ) ] );

			update_post_meta( $post->ID, '_storeengine_product_max_min_prices', $min_max );
		}

		$unformatted_attributes = $request->get_param( 'attributes' );
		if ( ! is_array( $unformatted_attributes ) ) {
			return;
		}

		$attributes = [];

		foreach ( $unformatted_attributes as $unformatted_attribute ) {
			$attributes[ $unformatted_attribute['label'] ] = $unformatted_attribute['ids'];
		}

		$unformatted_existence_attributes = $product->get_attributes();
		$existence_attributes             = [];

		foreach ( $unformatted_existence_attributes as $taxonomy => $terms ) {
			$taxonomyKey                          = Helper::strip_attribute_taxonomy_name( $taxonomy );
			$existence_attributes[ $taxonomyKey ] = array_map( fn( $term ) => $term->term_id, $terms );
		}

		if ( $attributes !== $existence_attributes ) {
			$product->set_attributes_order( array_map( fn( $taxonomy ) => Helper::get_attribute_taxonomy_name( $taxonomy ), array_keys( $attributes ) ) );

			foreach ( $attributes as $key => $value ) {
				wp_set_object_terms( $post->ID, array_map( fn( $val ) => (int) sanitize_text_field( $val ), $value ), Helper::get_attribute_taxonomy_name( sanitize_text_field( $key ) ) );
			}

			// Update the order.
			$update_values = [];
			$update_cases  = [];

			foreach ( $attributes as $taxonomy => $terms ) {
				foreach ( $terms as $order => $term_id ) {
					$update_cases[]  = "WHEN tr.term_taxonomy_id = $term_id THEN $order";
					$update_values[] = $term_id;
				}
			}

			// Execute bulk UPDATE if there are items to update
			if ( ! empty( $update_cases ) ) {
				global $wpdb;
				$update_query = "UPDATE {$wpdb->term_relationships} tr
							INNER JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
							SET tr.term_order = CASE " . implode( ' ', $update_cases ) . ' END
							WHERE tr.object_id = %d
							AND tr.term_taxonomy_id IN
							(' . implode( ',', array_fill( 0, count( $update_values ), '%d' ) ) . ')';
				// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
				$wpdb->query( $wpdb->prepare( $update_query, $post->ID, ...$update_values ) );
				// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
			}
			wp_cache_flush_group( AbstractProduct::CACHE_GROUP );
		}
	}

	private function save_stock_fields( WP_Post $post, WP_REST_Request $request ) {
		$stock = $request->get_param( 'stock' );

		if ( ! is_array( $stock ) ) {
			return;
		}

		$old_status = get_post_meta( $post->ID, '_storeengine_stock_status', true );

		if ( array_key_exists( 'manages_stock', $stock ) ) {
			update_post_meta( $post->ID, '_storeengine_manage_stock', (bool) $stock['manages_stock'] );
		}

		if ( array_key_exists( 'stock_quantity', $stock ) ) {
			$qty = $stock['stock_quantity'];
			if ( '' === $qty || null === $qty ) {
				delete_post_meta( $post->ID, '_storeengine_stock_quantity' );
			} else {
				update_post_meta( $post->ID, '_storeengine_stock_quantity', (int) $qty );
			}
		}

		if ( array_key_exists( 'stock_status', $stock ) ) {
			$status = sanitize_text_field( $stock['stock_status'] );
			if ( in_array( $status, [ 'instock', 'outofstock', 'onbackorder' ], true ) ) {
				update_post_meta( $post->ID, '_storeengine_stock_status', $status );
			}
		}

		if ( array_key_exists( 'backorders', $stock ) ) {
			$backorders = sanitize_text_field( $stock['backorders'] );
			if ( in_array( $backorders, [ 'no', 'notify', 'yes' ], true ) ) {
				update_post_meta( $post->ID, '_storeengine_backorders', $backorders );
			}
		}

		if ( array_key_exists( 'low_stock_threshold', $stock ) ) {
			$threshold = $stock['low_stock_threshold'];
			if ( '' === $threshold || null === $threshold ) {
				delete_post_meta( $post->ID, '_storeengine_low_stock_threshold' );
			} else {
				update_post_meta( $post->ID, '_storeengine_low_stock_threshold', (int) $threshold );
			}
		}

		if ( array_key_exists( 'sold_individually', $stock ) ) {
			update_post_meta( $post->ID, '_storeengine_sold_individually', (bool) $stock['sold_individually'] );
		}

		// Sync stock_status from stock_quantity when manage_stock=true.
		$manage_stock = (bool) get_post_meta( $post->ID, '_storeengine_manage_stock', true );

		if ( $manage_stock ) {
			$qty        = (int) get_post_meta( $post->ID, '_storeengine_stock_quantity', true );
			$backorders = get_post_meta( $post->ID, '_storeengine_backorders', true ) ?: 'no';
			$allowed    = in_array( $backorders, [ 'yes', 'notify' ], true );

			if ( $qty <= 0 && ! $allowed ) {
				$new_status = 'outofstock';
			} elseif ( $qty <= 0 && $allowed ) {
				$new_status = 'onbackorder';
			} else {
				$new_status = 'instock';
			}

			update_post_meta( $post->ID, '_storeengine_stock_status', $new_status );

			if ( $old_status && $old_status !== $new_status ) {
				do_action( 'storeengine/stock_status_changed', $post->ID, 0, $old_status, $new_status );
			}
		} elseif ( $old_status ) {
			$current_status = get_post_meta( $post->ID, '_storeengine_stock_status', true );
			if ( $current_status && $current_status !== $old_status ) {
				do_action( 'storeengine/stock_status_changed', $post->ID, 0, $old_status, $current_status );
			}
		}

		// Mirror simple-product aggregate qty into per-location stock at the
		// default location when the inventory-pro addon is on. Listener:
		// `storeengine/inventory/stock_quantity_set` action.
		if ( $manage_stock ) {
			$qty_for_mirror = (int) get_post_meta( $post->ID, '_storeengine_stock_quantity', true );
			/**
			 * @see Variation::save() — same hook fires from variation saves.
			 */
			do_action(
				'storeengine/inventory/stock_quantity_set',
				(int) $post->ID,
				0,
				$qty_for_mirror,
				'editor'
			);
		}
	}

	private function save_variation_data( Variation $variation, int $product_id, array $data ) {
		$variation->set_product_id( $product_id );
		$price = isset( $data['price'] ) && is_numeric( $data['price'] ) ? (float) sanitize_text_field( $data['price'] ) : null;
		$variation->set_price( $price );
		$pricing_id = (int) sanitize_text_field( $data['pricing_id'] ?? 0 );
		$variation->set_price_id( $pricing_id > 0 ? $pricing_id : null );
		$featured_image_id = (int) sanitize_text_field( $data['featured_image_id'] ?? 0 );
		$variation->set_featured_image( $featured_image_id > 0 ? $featured_image_id : null );
		$variation->set_sku( sanitize_text_field( $data['sku'] ) );

		if ( array_key_exists( 'barcode', $data ) ) {
			$barcode = $data['barcode'];
			$variation->set_barcode( ( null === $barcode || '' === $barcode ) ? null : sanitize_text_field( (string) $barcode ) );
		}

		if ( array_key_exists( 'cost_price', $data ) ) {
			$cost = $data['cost_price'];
			$variation->set_cost_price( ( '' === $cost || null === $cost ) ? null : (float) $cost );
		}

		$term_ids = array_map( fn( $term_id ) => (int) sanitize_text_field( $term_id ), $data['taxonomies'] );
		$term_ids = array_values( $term_ids );
		$variation->set_attributes( $term_ids );

		$stock = $data['stock'] ?? [];

		$old_status = method_exists( $variation, 'get_stock_status' ) ? $variation->get_stock_status() : null;

		if ( is_array( $stock ) ) {
			if ( array_key_exists( 'manages_stock', $stock ) ) {
				$variation->set_manage_stock( (bool) $stock['manages_stock'] );
			}

			if ( array_key_exists( 'stock_quantity', $stock ) ) {
				$qty = $stock['stock_quantity'];
				$variation->set_stock_quantity( ( '' === $qty || null === $qty ) ? null : (int) $qty );
			}

			if ( array_key_exists( 'backorders', $stock ) ) {
				$variation->set_backorders( sanitize_text_field( $stock['backorders'] ) );
			}

			if ( array_key_exists( 'low_stock_threshold', $stock ) ) {
				$threshold = $stock['low_stock_threshold'];
				$variation->set_low_stock_threshold( ( '' === $threshold || null === $threshold ) ? null : (int) $threshold );
			}

			$manage_stock_now = isset( $stock['manages_stock'] ) ? (bool) $stock['manages_stock'] : $variation->manages_stock();

			if ( $manage_stock_now ) {
				$qty_now    = isset( $stock['stock_quantity'] ) ? (int) $stock['stock_quantity'] : (int) $variation->get_stock_quantity();
				$backorders = isset( $stock['backorders'] ) ? sanitize_text_field( $stock['backorders'] ) : $variation->get_backorders();
				$allowed    = in_array( $backorders, [ 'yes', 'notify' ], true );

				if ( $qty_now <= 0 && ! $allowed ) {
					$new_status = 'outofstock';
				} elseif ( $qty_now <= 0 && $allowed ) {
					$new_status = 'onbackorder';
				} else {
					$new_status = 'instock';
				}

				$variation->new_data_set_stock_status( $new_status );
			} elseif ( array_key_exists( 'stock_status', $stock ) ) {
				$status = sanitize_text_field( $stock['stock_status'] );
				if ( in_array( $status, [ 'instock', 'outofstock', 'onbackorder' ], true ) ) {
					$variation->new_data_set_stock_status( $status );
				}
			}
		}

		// Auto-generate SKU/barcode for this variation when enabled and empty.
		if ( '' === (string) $variation->get_sku() && Helper::get_settings( 'auto_generate_sku' ) ) {
			$variation->set_sku( SkuGenerator::generate_sku( [
				'name'     => get_the_title( $product_id ),
				'category' => SkuGenerator::product_category_slug( $product_id ),
			] ) );
		}
		if ( ! $variation->get_barcode() && Helper::get_settings( 'auto_generate_barcode' ) ) {
			$variation->set_barcode( SkuGenerator::generate_barcode() );
		}

		$variation->save();

		if ( $old_status && method_exists( $variation, 'get_stock_status' ) ) {
			$new_status_after_save = $variation->get_stock_status();
			if ( $new_status_after_save !== $old_status ) {
				do_action( 'storeengine/stock_status_changed', $product_id, $variation->get_id(), $old_status, $new_status_after_save );
			}
		}
	}
}
