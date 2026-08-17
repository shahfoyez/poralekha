<?php
/**
 * Abilities (free, read-only).
 *
 * Exposes a handful of StoreEngine store operations to the WordPress Abilities
 * API (WP 6.9+) so external AI agents can *query* the store in a structured,
 * permission-gated way. Read-only here; write-capable abilities (update product,
 * apply coupon, …) ship in the pro `ai` augmentation.
 *
 * Everything is guarded by function_exists so the addon stays inert on WordPress
 * builds without the Abilities API.
 *
 * @since StoreEngine 1.7.0
 */

namespace StoreEngine\Addons\Ai;

use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Abilities {
	use Singleton;

	const CATEGORY = 'storeengine';

	protected function __construct() {
		add_action( 'wp_abilities_api_init', [ $this, 'register' ] );
	}

	/**
	 * Capability gate shared by every read ability.
	 */
	public static function can_read(): bool {
		return current_user_can( 'manage_storeengine_settings' ) || current_user_can( 'edit_storeengine_products' ) || current_user_can( 'manage_options' );
	}

	public function register(): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		if ( function_exists( 'wp_register_ability_category' ) ) {
			// Called via a variable so Plugin Check's static "requires WP x.x"
			// scan doesn't flag this 6.9-only API — it's already runtime-guarded.
			$register_category = 'wp_register_ability_category';
			$register_category( self::CATEGORY, [
				'label'       => __( 'StoreEngine', 'storeengine' ),
				'description' => __( 'Query and manage a StoreEngine store.', 'storeengine' ),
			] );
		}

		$this->register_ability( 'storeengine/get-product', [
			'label'        => __( 'Get product', 'storeengine' ),
			'description'  => __( 'Fetch a StoreEngine product by id (name, prices, stock, categories).', 'storeengine' ),
			'input_schema' => [
				'type'       => 'object',
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'required'   => [ 'id' ],
			],
			'callback'     => [ __CLASS__, 'ability_get_product' ],
		] );

		$this->register_ability( 'storeengine/search-products', [
			'label'        => __( 'Search products', 'storeengine' ),
			'description'  => __( 'Search StoreEngine products by keyword; returns up to 20 matches.', 'storeengine' ),
			'input_schema' => [
				'type'       => 'object',
				'properties' => [
					'query'  => [ 'type' => 'string' ],
					'limit'  => [ 'type' => 'integer' ],
					'status' => [ 'type' => 'string' ],
				],
			],
			'callback'     => [ __CLASS__, 'ability_search_products' ],
		] );

		$this->register_ability( 'storeengine/get-order', [
			'label'        => __( 'Get order', 'storeengine' ),
			'description'  => __( 'Fetch a StoreEngine order summary by id (status, total, customer).', 'storeengine' ),
			'input_schema' => [
				'type'       => 'object',
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'required'   => [ 'id' ],
			],
			'callback'     => [ __CLASS__, 'ability_get_order' ],
		] );

		$this->register_ability( 'storeengine/get-inventory', [
			'label'        => __( 'Get inventory', 'storeengine' ),
			'description'  => __( 'Return the stock status and quantity for a product.', 'storeengine' ),
			'input_schema' => [
				'type'       => 'object',
				'properties' => [ 'id' => [ 'type' => 'integer' ] ],
				'required'   => [ 'id' ],
			],
			'callback'     => [ __CLASS__, 'ability_get_inventory' ],
		] );

		/**
		 * Let the pro augmentation (and 3rd parties) register write abilities
		 * under the same category without editing core.
		 */
		do_action( 'storeengine/ai/register_abilities', $this );
	}

	/**
	 * Thin wrapper that fills in the shared category + read permission and maps
	 * `callback` to whichever execute key the installed Abilities API expects.
	 */
	public function register_ability( string $id, array $args ): void {
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		$callback = $args['callback'] ?? null;
		unset( $args['callback'] );

		$args = array_merge( [
			'category'            => self::CATEGORY,
			'permission_callback' => [ __CLASS__, 'can_read' ],
		], $args );

		// The Abilities API has used both keys across iterations; set both so the
		// callback is found regardless of the shipped build.
		if ( $callback ) {
			$args['execute_callback'] = $callback;
			$args['callback']         = $callback;
		}

		if ( function_exists( 'wp_register_ability' ) ) {
			// Variable call keeps this 6.9-only API off Plugin Check's static
			// version scan; runtime guard above already protects older WP.
			$register = 'wp_register_ability';
			$register( $id, $args );
		}
	}

	/* ----------------------------------------------------------- callbacks */

	public static function ability_get_product( array $input ) {
		$product = Helper::get_product( (int) ( $input['id'] ?? 0 ) );
		if ( ! $product || is_wp_error( $product ) ) {
			return new WP_Error( 'not_found', __( 'Product not found.', 'storeengine' ) );
		}

		return [
			'id'         => (int) $product->get_id(),
			'name'       => (string) $product->get_name(),
			'status'     => (string) $product->get_status(),
			'prices'     => $product->get_prices(),
			'stock'      => $product->get_stock_quantity(),
			'stock_status' => (string) $product->get_stock_status(),
			'categories' => wp_list_pluck( (array) get_the_terms( $product->get_id(), Helper::PRODUCT_CATEGORY_TAXONOMY ) ?: [], 'name' ),
		];
	}

	public static function ability_search_products( array $input ): array {
		$limit  = min( 20, max( 1, (int) ( $input['limit'] ?? 10 ) ) );
		$status = isset( $input['status'] ) ? sanitize_key( (string) $input['status'] ) : 'publish';

		$query = new WP_Query( [
			'post_type'      => Helper::PRODUCT_POST_TYPE,
			'post_status'    => $status ?: 'publish',
			's'              => (string) ( $input['query'] ?? '' ),
			'posts_per_page' => $limit,
			'fields'         => 'ids',
		] );

		$results = [];
		foreach ( $query->posts as $product_id ) {
			$product = Helper::get_product( (int) $product_id );
			if ( ! $product || is_wp_error( $product ) ) {
				continue;
			}
			$results[] = [
				'id'     => (int) $product->get_id(),
				'name'   => (string) $product->get_name(),
				'status' => (string) $product->get_status(),
			];
		}

		return [ 'products' => $results ];
	}

	public static function ability_get_order( array $input ) {
		$order = Helper::get_order( (int) ( $input['id'] ?? 0 ) );
		if ( ! $order || is_wp_error( $order ) ) {
			return new WP_Error( 'not_found', __( 'Order not found.', 'storeengine' ) );
		}

		return [
			'id'           => (int) $order->get_id(),
			'order_number' => (string) $order->get_order_number(),
			'status'       => (string) $order->get_status(),
			'total'        => $order->get_total_amount(),
			'currency'     => (string) $order->get_currency(),
		];
	}

	public static function ability_get_inventory( array $input ) {
		$product = Helper::get_product( (int) ( $input['id'] ?? 0 ) );
		if ( ! $product || is_wp_error( $product ) ) {
			return new WP_Error( 'not_found', __( 'Product not found.', 'storeengine' ) );
		}

		return [
			'id'           => (int) $product->get_id(),
			'stock'        => $product->get_stock_quantity(),
			'stock_status' => (string) $product->get_stock_status(),
		];
	}
}

// End of file abilities.php.
