<?php
/**
 * Brand addon.
 *
 * Adds a flat product "Brand" taxonomy that works just like the core Category /
 * Tag taxonomies, but ships as a toggleable addon. While active it:
 *
 *   - registers the `storeengine_product_brand` taxonomy (term CRUD comes free
 *     via WP_REST_Terms_Controller at /storeengine/v1/storeengine_product_brand),
 *   - registers two term-meta images (logo + banner) exposed in the REST `meta`,
 *   - injects a "Brands" sub-item under the Products admin menu (React SPA route
 *     `?page=storeengine-products&path=brands`),
 *   - renders brands on the single-product page and as a shop-archive filter
 *     widget (see Frontend).
 *
 * Everything is gated by the addon's active status — AbstractAddon::run() only
 * calls init_addon() when the merchant has enabled the addon, so disabling it
 * removes the taxonomy, menu item, editor field, and frontend output. Existing
 * brand terms stay in the database (no data loss on deactivation).
 */

namespace StoreEngine\Addons\Brand;

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Brand extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'brand';

	/**
	 * Flat product-brand taxonomy slug. Kept local to the addon so the brand
	 * concept stays fully self-contained (no edit to the core Helper).
	 */
	const TAXONOMY = 'storeengine_product_brand';

	/**
	 * Term-meta keys for the brand images (both store WP attachment IDs).
	 */
	const META_IMAGE  = 'storeengine_brand_image';
	const META_BANNER = 'storeengine_brand_banner';

	public function define_constants() {
		define( 'STOREENGINE_BRAND_VERSION', '1.0.0' );
	}

	/**
	 * Registered only while the addon is active (AbstractAddon::run() gates this).
	 *
	 * The taxonomy must be registered on `init` (priority 5, matching core
	 * Database) — NOT inline here, because Addons::init() runs at plugins_loaded,
	 * before `init` and before $wp_rewrite is ready.
	 */
	public function init_addon() {
		add_action( 'init', [ $this, 'register_taxonomy' ], 5 );
		add_action( 'init', [ $this, 'register_term_metas' ], 6 );
		add_action( 'rest_api_init', [ $this, 'register_term_metas' ] );
		add_filter( 'storeengine/admin_menu_list', [ $this, 'inject_menu_item' ] );

		Frontend::init();
	}

	/**
	 * Flush rewrite rules once when the merchant enables the addon so the
	 * /product-brand/<slug>/ archive permalink works immediately.
	 */
	public function addon_activation_hook() {
		$this->register_taxonomy();
		flush_rewrite_rules();
	}

	public function addon_deactivation_hook() {
		flush_rewrite_rules();
	}

	/**
	 * Register the flat product-brand taxonomy. Mirrors the core Category/Tag
	 * registration (see includes/database.php) but non-hierarchical.
	 */
	public function register_taxonomy() {
		register_taxonomy( self::TAXONOMY, Helper::PRODUCT_POST_TYPE, [
			'labels'                => [
				'name'              => __( 'Brands', 'storeengine' ),
				'singular_name'     => __( 'Brand', 'storeengine' ),
				'search_items'      => __( 'Search Brands', 'storeengine' ),
				'all_items'         => __( 'All Brands', 'storeengine' ),
				'edit_item'         => __( 'Edit Brand', 'storeengine' ),
				'update_item'       => __( 'Update Brand', 'storeengine' ),
				'add_new_item'      => __( 'Add New Brand', 'storeengine' ),
				'new_item_name'     => __( 'New Brand Name', 'storeengine' ),
				'menu_name'         => __( 'Brands', 'storeengine' ),
				'not_found'         => __( 'No brands found.', 'storeengine' ),
			],
			'hierarchical'          => false,
			'query_var'             => true,
			'public'                => true,
			'show_ui'               => false,
			'show_admin_column'     => false,
			'capabilities'          => [
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'edit_categories',
				'delete_terms' => 'delete_categories',
				'assign_terms' => 'assign_categories',
			],
			'show_in_rest'          => true,
			'rest_base'             => self::TAXONOMY,
			'rest_namespace'        => STOREENGINE_PLUGIN_SLUG . '/v1',
			'rest_controller_class' => 'WP_REST_Terms_Controller',
			'rewrite'               => [
				'slug'       => 'product-brand',
				'with_front' => false,
			],
		] );
	}

	/**
	 * Register the brand logo + banner term meta. Both are attachment IDs and
	 * are surfaced inside the WP_REST_Terms_Controller `meta` object, so the
	 * React form can read/write them with no custom REST controller.
	 */
	public function register_term_metas() {
		$args = [
			'type'              => 'integer',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'absint',
			'default'           => 0,
			'auth_callback'     => static function () {
				return current_user_can( 'manage_categories' );
			},
		];

		register_term_meta( self::TAXONOMY, self::META_IMAGE, $args );
		register_term_meta( self::TAXONOMY, self::META_BANNER, $args );
	}

	/**
	 * Append a "Brands" sub-item to the existing Products menu. Because this
	 * filter is only added while the addon is active, the menu entry (and its
	 * SPA route) disappears when the addon is disabled.
	 *
	 * @param array $menu Admin menu list (see Admin\Menu::get_menu_lists()).
	 * @return array
	 */
	public function inject_menu_item( array $menu ): array {
		// Brands is its own raw-route page (?page=storeengine-brands); the
		// Products group folds it in as a child — see Menu::menu_groups().
		$menu[ STOREENGINE_PLUGIN_SLUG . '-brands' ] = [
			'title'      => __( 'Brands', 'storeengine' ),
			'capability' => 'manage_options',
			'priority'   => 14,
		];

		return $menu;
	}
}

// End of file brand.php
