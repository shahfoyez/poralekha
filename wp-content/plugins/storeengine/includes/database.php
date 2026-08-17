<?php

namespace StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use StoreEngine\Database\{CreateApiKeys,
	CreateAttributeTaxonomies,
	CreateCart,
	CreateCustomerLookup,
	CreateDownloadableProductPermissions,
	CreateDownloadLog,
	CreateEmailLog,
	CreateIntegration,
	CreateLog,
	CreateOrderAddresses,
	CreateOrderItems,
	CreateOrderMetaTable,
	CreateOrderOperationalData,
	CreateOrderProductLookup,
	CreateOrderTable,
	CreatePaymentTokenMeta,
	CreatePaymentTokens,
	CreatePayouts,
	CreateProductVariationMetaTable,
	CreateShippingZoneLocations,
	CreateVariationsTermsRelationTable,
	CreateProductDownloadDirectories,
	CreateProductPriceTable,
	CreateProductVariationsTable,
	CreateReservedStockTable,
	CreateStockMovementsTable,
	CreateShippingZoneMethods,
	CreateShippingZones,
	CreateTaxRateLocations,
	CreateTaxRates
};
use StoreEngine\Classes\enums\ProductDownloadType;
use StoreEngine\Classes\enums\ProductTaxStatus;
use StoreEngine\Classes\enums\PurchaseRedirectType;
use StoreEngine\database\CreateOrderItemMeta;
use StoreEngine\Traits\Singleton;
use StoreEngine\Classes\Attributes;
use StoreEngine\Utils\Helper;

class Database {

	use Singleton;

	protected function __construct() {
		$this->register_database_table_name();

		add_action( 'switch_blog', [ $this, 'wpdb_table_fix' ], 0 );

		add_action( 'init', [ __CLASS__, 'maybe_sync_schema' ], 4 );
		add_action( 'init', [ $this, 'register_product_post_type' ], 5 );
		add_action( 'init', [ $this, 'register_coupon_post_type' ], 5 );
		add_action( 'init', [ $this, 'register_faq_post_type' ], 5 );
		// FAQ-group meta must also be registered on rest_api_init so the CPT's
		// REST controller includes it in its meta schema (same pattern as
		// register_product_meta below).
		add_action( 'rest_api_init', [ $this, 'register_faq_group_meta' ] );
		add_action( 'init', [ $this, 'register_size_chart_post_type' ], 5 );
		add_action( 'rest_api_init', [ $this, 'register_size_chart_meta' ] );
		add_action( 'get_avatar_comment_types', [ $this, 'add_avatar_support_product_comment' ] );

		// Invalidate resolved-FAQ caches whenever a group or product changes.
		add_action( 'save_post_' . Helper::FAQ_POST_TYPE, [ '\StoreEngine\Classes\Faq', 'flush_cache' ] );
		add_action( 'deleted_post', [ '\StoreEngine\Classes\Faq', 'flush_cache' ] );
		add_action( 'save_post_' . Helper::PRODUCT_POST_TYPE, [ '\StoreEngine\Classes\Faq', 'flush_cache' ] );

		// Same for resolved size charts (chart edits, product term changes).
		add_action( 'save_post_' . Helper::SIZE_CHART_POST_TYPE, [ '\StoreEngine\Classes\SizeChart', 'flush_cache' ] );
		add_action( 'deleted_post', [ '\StoreEngine\Classes\SizeChart', 'flush_cache' ] );
		add_action( 'save_post_' . Helper::PRODUCT_POST_TYPE, [ '\StoreEngine\Classes\SizeChart', 'flush_cache' ] );

		add_action( 'init', [ $this, 'maybe_flush_rewrite_rules' ], 5 );
		add_action( 'storeengine/flush_rewrite_rules', [ __CLASS__, 'flush_rewrite_rules' ] );

		add_action( 'rest_api_init', [ $this, 'register_product_meta' ] );
		add_action( 'rest_api_init', [ $this, 'register_coupon_meta' ] );
		add_action( 'rest_api_init', [ $this, 'register_category_rest_fields' ] );
	}

	const SCHEMA_HASH_OPTION = 'storeengine_schema_hash';

	/**
	 * Re-runs dbDelta when any schema file in includes/database/ changes.
	 * Avoids the need to bump STOREENGINE_DB_VERSION on every column addition.
	 */
	public static function maybe_sync_schema(): void {
		$hash = self::compute_schema_hash( STOREENGINE_ROOT_DIR_PATH . 'includes/database' );
		if ( ! $hash || $hash === get_option( self::SCHEMA_HASH_OPTION ) ) {
			return;
		}

		self::create_initial_custom_table();
		update_option( self::SCHEMA_HASH_OPTION, $hash, true );

		/**
		 * Fires after core schema files were re-applied via dbDelta.
		 * Addons should hook here to re-sync their own tables.
		 */
		do_action( 'storeengine/schema_synced' );
	}

	public static function compute_schema_hash( string $dir ): string {
		$files = glob( rtrim( $dir, '/' ) . '/*.php' );
		if ( empty( $files ) ) {
			return '';
		}
		sort( $files );
		$parts = [];
		foreach ( $files as $file ) {
			$parts[] = md5_file( $file );
		}
		return md5( implode( '', $parts ) );
	}

	public function maybe_flush_rewrite_rules() {
		if ( 'yes' === get_option( 'storeengine_required_rewrite_flush' ) ) {
			update_option( 'storeengine_required_rewrite_flush', 'no' );
			self::flush_rewrite_rules();
		}
	}

	public static function flush_rewrite_rules() {
		flush_rewrite_rules();
	}

	public function register_database_table_name() {
		global $wpdb;

		// @TODO add all the tables.
		$tables = [
			'payment_tokenmeta'   => 'storeengine_payment_tokenmeta',
			'order_itemmeta'      => 'storeengine_order_item_meta',
			'product_meta_lookup' => 'storeengine_product_meta_lookup',
			'tax_rate_classes'    => 'storeengine_tax_rate_classes',
			'reserved_stock'      => 'storeengine_reserved_stock',
			'store_orders'        => 'storeengine_orders',
			'ordermeta'           => 'storeengine_orders_meta',
		];

		/**
		 * @XXX make sure to add the meta types for handling cache last change.
		 *
		 * @see Hooks::handle_cache_last_changed()
		 * @see wp_cache_set_last_changed()
		 */

		foreach ( $tables as $name => $table ) {
			$wpdb->$name    = $wpdb->prefix . $table;
			$wpdb->tables[] = $table;
		}
	}

	public function wpdb_table_fix() {
		$this->register_database_table_name();
	}

	public static function create_initial_custom_table() {
		global $wpdb;

		if ( ! function_exists( 'dbDelta' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}

		$charset_collate = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

		CreateIntegration::up( $wpdb->prefix, $charset_collate );
		CreateProductPriceTable::up( $wpdb->prefix, $charset_collate );
		CreateAttributeTaxonomies::up( $wpdb->prefix, $charset_collate );
		CreateProductVariationsTable::up( $wpdb->prefix, $charset_collate );
		CreateProductVariationMetaTable::up( $wpdb->prefix, $charset_collate );
		CreateReservedStockTable::up( $wpdb->prefix, $charset_collate );
		CreateStockMovementsTable::up( $wpdb->prefix, $charset_collate );
		CreateVariationsTermsRelationTable::up( $wpdb->prefix, $charset_collate );
		CreateOrderTable::up( $wpdb->prefix, $charset_collate );
		CreateOrderMetaTable::up( $wpdb->prefix, $charset_collate );
		CreateOrderProductLookup::up( $wpdb->prefix, $charset_collate );
		CreateOrderItems::up( $wpdb->prefix, $charset_collate );
		CreateOrderItemMeta::up( $wpdb->prefix, $charset_collate );
		CreateOrderOperationalData::up( $wpdb->prefix, $charset_collate );
		CreateOrderAddresses::up( $wpdb->prefix, $charset_collate );
		CreateShippingZoneMethods::up( $wpdb->prefix, $charset_collate );
		CreateShippingZones::up( $wpdb->prefix, $charset_collate );
		CreateShippingZoneLocations::up( $wpdb->prefix, $charset_collate );
		CreateApiKeys::up( $wpdb->prefix, $charset_collate );
		CreateCart::up( $wpdb->prefix, $charset_collate );
		CreatePaymentTokens::up( $wpdb->prefix, $charset_collate );
		CreatePaymentTokenMeta::up( $wpdb->prefix, $charset_collate );
		CreatePayouts::up( $wpdb->prefix, $charset_collate );
		CreateTaxRateLocations::up( $wpdb->prefix, $charset_collate );
		CreateTaxRates::up( $wpdb->prefix, $charset_collate );
		CreateApiKeys::up( $wpdb->prefix, $charset_collate );
		CreateLog::up( $wpdb->prefix, $charset_collate );
		CreateEmailLog::up( $wpdb->prefix, $charset_collate );
		CreateCustomerLookup::up( $wpdb->prefix, $charset_collate );
		CreateDownloadableProductPermissions::up( $wpdb->prefix, $charset_collate );
		CreateDownloadLog::up( $wpdb->prefix, $charset_collate );
		CreateProductDownloadDirectories::up( $wpdb->prefix, $charset_collate );

		// Backfill new columns on existing tables that dbDelta may have skipped.
		self::ensure_variation_stock_columns();
		self::ensure_stock_movements_vendor_column();

		// Store DB Version
		update_option( 'storeengine_db_version', STOREENGINE_DB_VERSION, false );
	}

	/**
	 * Adds the stock-tracking columns to wp_storeengine_product_variations on
	 * installs that pre-date the inventory feature. Idempotent — silently
	 * skips columns that already exist.
	 */
	public static function ensure_variation_stock_columns(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'storeengine_product_variations';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DESCRIBE on a prepared %i identifier; schema introspection.
		$existing = $wpdb->get_col( $wpdb->prepare( 'DESCRIBE %i', $table ), 0 );

		if ( ! is_array( $existing ) || empty( $existing ) ) {
			return;
		}

		$columns = [
			'manage_stock'        => 'ALTER TABLE %i ADD COLUMN manage_stock TINYINT(1) NOT NULL DEFAULT 0',
			'stock_quantity'      => 'ALTER TABLE %i ADD COLUMN stock_quantity INT(11) DEFAULT NULL',
			'stock_status'        => "ALTER TABLE %i ADD COLUMN stock_status VARCHAR(32) NOT NULL DEFAULT 'instock'",
			'backorders'          => "ALTER TABLE %i ADD COLUMN backorders VARCHAR(16) NOT NULL DEFAULT 'no'",
			'low_stock_threshold' => 'ALTER TABLE %i ADD COLUMN low_stock_threshold INT(11) DEFAULT NULL',
			'cost_price'          => 'ALTER TABLE %i ADD COLUMN cost_price decimal(26,8) DEFAULT NULL',
			'barcode'             => 'ALTER TABLE %i ADD COLUMN barcode VARCHAR(64) DEFAULT NULL',
		];

		foreach ( $columns as $column => $sql ) {
			if ( ! in_array( $column, $existing, true ) ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared %i identifier; column definition is a literal; idempotent schema upgrade.
				$wpdb->query( $wpdb->prepare( $sql, $table ) );
			}
		}

		// Ensure indexes exist.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		foreach ( [ 'stock_status', 'sku', 'barcode' ] as $index_name ) {
			$has_index = $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(1) FROM information_schema.statistics WHERE table_schema = %s AND table_name = %s AND index_name = %s",
				DB_NAME,
				$table,
				$index_name
			) );

			if ( ! (int) $has_index ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Prepared %i identifiers; idempotent index creation on a StoreEngine table.
				$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD KEY %i (%i)', $table, $index_name, $index_name ) );
			}
		}
		// phpcs:enable
	}

	/**
	 * Adds the `vendor_id` column to wp_storeengine_stock_movements on
	 * installs that pre-date the multi-vendor scoping feature, then back-fills
	 * existing rows from the product's post_author. Idempotent.
	 */
	public static function ensure_stock_movements_vendor_column(): void {
		global $wpdb;

		$table = $wpdb->prefix . 'storeengine_stock_movements';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- DESCRIBE on a prepared %i identifier; schema introspection.
		$existing = $wpdb->get_col( $wpdb->prepare( 'DESCRIBE %i', $table ), 0 );

		if ( ! is_array( $existing ) || empty( $existing ) ) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter
		if ( ! in_array( 'vendor_id', $existing, true ) ) {
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD COLUMN vendor_id BIGINT(20) UNSIGNED DEFAULT NULL', $table ) );
			$wpdb->query( $wpdb->prepare( 'ALTER TABLE %i ADD KEY vendor_id (vendor_id)', $table ) );
		}

		// One-shot back-fill from product.post_author. Keyed on a flag option
		// so re-running install() on a populated table is a no-op.
		if ( ! get_option( 'storeengine_stock_movements_vendor_backfill_v1' ) ) {
			$wpdb->query( $wpdb->prepare(
				"UPDATE %i m
				   INNER JOIN {$wpdb->posts} p ON p.ID = m.product_id
				   SET m.vendor_id = p.post_author
				 WHERE m.vendor_id IS NULL",
				$table
			) );
			update_option( 'storeengine_stock_movements_vendor_backfill_v1', 1, false );
		}
		// phpcs:enable
	}

	public function register_product_post_type() {
		$permalinks   = Helper::get_permalink_structure();
		$shop_page_id = Helper::get_settings( 'shop_page' );
		$has_archive  = get_post( $shop_page_id ) ? urldecode( get_page_uri( $shop_page_id ) ) : 'shop';

		// Registering Product CPT
		register_post_type( Helper::PRODUCT_POST_TYPE, [
			'labels'                => [
				'name'               => esc_html__( 'Products', 'storeengine' ),
				'add_new'            => esc_html__( 'Add New Product', 'storeengine' ),
				'singular_name'      => esc_html__( 'Product', 'storeengine' ),
				'search_items'       => esc_html__( 'Search Products', 'storeengine' ),
				'parent_item_colon'  => esc_html__( 'Parent Products:', 'storeengine' ),
				'not_found'          => esc_html__( 'No Products found.', 'storeengine' ),
				'not_found_in_trash' => esc_html__( 'No Products found in Trash.', 'storeengine' ),
				'archives'           => esc_html__( 'Product archives', 'storeengine' ),
			],
			'public'                => true,
			'publicly_queryable'    => true,
			'show_ui'               => true,
			'show_in_menu'          => false,
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => false,
			'hierarchical'          => true,
			'query_var'             => true,
			'delete_with_user'      => false,
			'supports'              => [
				'title',
				'editor',
				'author',
				'thumbnail',
				'excerpt',
				'trackbacks',
				'custom-fields',
				'comments',
				'post-formats',
			],
			'has_archive'           => $has_archive,
			'rewrite'               => [ 'slug' => $permalinks['product_rewrite_slug'] ],
			'show_in_rest'          => true,
			'rest_base'             => Helper::PRODUCT_POST_TYPE,
			'rest_namespace'        => STOREENGINE_PLUGIN_SLUG . '/v1',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'capability_type'       => 'post',
			'capabilities'          => [
				'edit_post'          => 'edit_storeengine_product',
				'read_post'          => 'read_storeengine_product',
				'delete_post'        => 'delete_storeengine_product',
				'edit_posts'         => 'edit_storeengine_products',
				'edit_others_posts'  => 'edit_storeengine_others_products',
				'publish_posts'      => 'publish_storeengine_products',
				'read_private_posts' => 'read_private_storeengine_products',
			],
		] );

		// Registering Product Category Taxonomy
		register_taxonomy( Helper::PRODUCT_CATEGORY_TAXONOMY, Helper::PRODUCT_POST_TYPE, [
			'hierarchical'          => true,
			'query_var'             => true,
			'public'                => true,
			'show_ui'               => false,
			'show_admin_column'     => false,
			'_builtin'              => true,
			'capabilities'          => [
				'manage_terms' => 'manage_categories',
				'edit_terms'   => 'edit_categories',
				'delete_terms' => 'delete_categories',
				'assign_terms' => 'assign_categories',
			],
			'show_in_rest'          => true,
			'rest_base'             => Helper::PRODUCT_CATEGORY_TAXONOMY,
			'rest_namespace'        => STOREENGINE_PLUGIN_SLUG . '/v1',
			'rest_controller_class' => 'WP_REST_Terms_Controller',
			'rewrite'               => [
				'slug'         => $permalinks['category_rewrite_slug'],
				'with_front'   => false,
				'hierarchical' => true,
			],
		] );

		// Registering Product Tag Taxonomy
		register_taxonomy( Helper::PRODUCT_TAG_TAXONOMY, Helper::PRODUCT_POST_TYPE, [
			'hierarchical'          => false,
			'query_var'             => true,
			'public'                => true,
			'show_ui'               => false,
			'show_admin_column'     => false,
			'_builtin'              => true,
			'capabilities'          => [
				'manage_terms' => 'manage_post_tags',
				'edit_terms'   => 'edit_post_tags',
				'delete_terms' => 'delete_post_tags',
				'assign_terms' => 'assign_post_tags',
			],
			'show_in_rest'          => true,
			'rest_base'             => Helper::PRODUCT_TAG_TAXONOMY,
			'rest_namespace'        => STOREENGINE_PLUGIN_SLUG . '/v1',
			'rest_controller_class' => 'WP_REST_Terms_Controller',
			'rewrite'               => [
				'slug'       => $permalinks['tag_rewrite_slug'],
				'with_front' => false,
			],
		] );

		// Registering Product Attributes As Taxonomy
		foreach ( ( new Attributes() )->get_all_names() as $slug => ['label' => $label, 'public' => $public] ) {
			self::register_attribute_taxonomy( $slug, $label, $public );
		}
	}

	public function register_coupon_post_type() {
		register_post_type( Helper::COUPON_POST_TYPE, [
			'labels'                => [
				'name'               => esc_html__( 'Coupon', 'storeengine' ),
				'add_new'            => esc_html__( 'Add New Coupon', 'storeengine' ),
				'singular_name'      => esc_html__( 'Coupon', 'storeengine' ),
				'search_items'       => esc_html__( 'Search Coupon', 'storeengine' ),
				'parent_item_colon'  => esc_html__( 'Parent Coupons:', 'storeengine' ),
				'not_found'          => esc_html__( 'No Coupons found.', 'storeengine' ),
				'not_found_in_trash' => esc_html__( 'No Coupons found in Trash.', 'storeengine' ),
				'archives'           => esc_html__( 'Coupon archives', 'storeengine' ),
			],
			// A coupon is a configuration entity, not front-end content: it has no
			// meaningful single page or archive. Keeping it publicly queryable also
			// registered `storeengine_coupon` as a PUBLIC query var, which collided
			// with the apply-coupon URL param (`/cart/?storeengine_coupon=CODE`) —
			// WP treated the request as a single-coupon query and 404'd instead of
			// loading the cart. Non-public frees that param and removes the dead
			// /coupon/* URLs. Admin (show_ui) + REST (show_in_rest) are unaffected.
			'public'                => false,
			'publicly_queryable'    => false,
			'show_ui'               => true,
			'show_in_menu'          => false,
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => false,
			'hierarchical'          => true,
			'query_var'             => false,
			'delete_with_user'      => false,
			'supports'              => [ 'title', 'editor', 'author', 'custom-fields' ],
			'has_archive'           => false,
			'rewrite'               => false,
			'show_in_rest'          => true,
			'rest_base'             => Helper::COUPON_POST_TYPE,
			'rest_namespace'        => STOREENGINE_PLUGIN_SLUG . '/v1',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'capability_type'       => 'post',
			'capabilities'          => [
				'edit_post'          => 'edit_storeengine_coupon',
				'read_post'          => 'read_storeengine_coupon',
				'delete_post'        => 'delete_storeengine_coupon',
				'edit_posts'         => 'edit_storeengine_coupons',
				'edit_others_posts'  => 'edit_others_storeengine_coupons',
				'publish_posts'      => 'publish_storeengine_coupons',
				'read_private_posts' => 'read_private_storeengine_coupons',
			],
		] );
	}

	/**
	 * FAQ groups — the reusable, rule-targeted unit of the FAQ system.
	 *
	 * A group is a post: name = post_title, Q&A items live in the
	 * `_storeengine_faq_items` meta. Display rules (`_apply_all`, category / brand
	 * / product id metas) decide which products render the group. Products may
	 * also attach groups directly, exclude an inherited group, or add their own
	 * inline Q&A (`_storeengine_product_faqs`). Managed from the StoreEngine admin
	 * (Products → FAQs) via the storeengine/v1 REST controller, so the CPT is
	 * intentionally not shown in the native menu.
	 */
	public function register_faq_post_type() {
		register_post_type( Helper::FAQ_POST_TYPE, [
			'labels'                => [
				'name'               => esc_html__( 'FAQ Groups', 'storeengine' ),
				'add_new'            => esc_html__( 'Add New FAQ Group', 'storeengine' ),
				'singular_name'      => esc_html__( 'FAQ Group', 'storeengine' ),
				'search_items'       => esc_html__( 'Search FAQ Groups', 'storeengine' ),
				'not_found'          => esc_html__( 'No FAQ groups found.', 'storeengine' ),
				'not_found_in_trash' => esc_html__( 'No FAQ groups found in Trash.', 'storeengine' ),
			],
			'public'                => false,
			'publicly_queryable'    => false,
			'show_ui'               => false,
			'show_in_menu'          => false,
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => false,
			'hierarchical'          => false,
			'query_var'             => false,
			'delete_with_user'      => false,
			'supports'              => [ 'title', 'author', 'page-attributes', 'custom-fields' ],
			'has_archive'           => false,
			'rewrite'               => false,
			'show_in_rest'          => true,
			'rest_base'             => 'faq-groups',
			'rest_namespace'        => STOREENGINE_PLUGIN_SLUG . '/v1',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'capability_type'       => 'post',
			'map_meta_cap'          => true,
		] );
	}

	/**
	 * REST-exposed meta for FAQ groups (Q&A items + display rules).
	 *
	 * Registered on rest_api_init (mirrors register_product_meta) so the CPT's
	 * REST controller picks up the full schema — array-of-objects meta only
	 * round-trips reliably when registered this way with an explicit context.
	 */
	public function register_faq_group_meta() {
		// FAQ groups are an admin-managed library: only users who can manage the
		// store may write this meta (the CPT is capability_type 'post', so this
		// auth_callback is what keeps Author/Editor roles from injecting FAQ
		// content via the REST route).
		$auth_callback = static function () {
			return current_user_can( 'manage_options' );
		};

		register_meta( 'post', '_storeengine_faq_items', [
			'object_subtype'    => Helper::FAQ_POST_TYPE,
			'type'              => 'array',
			'single'            => true,
			'auth_callback'     => $auth_callback,
			'sanitize_callback' => static function ( $items ) {
				if ( ! is_array( $items ) ) {
					return [];
				}
				return array_values( array_map( static function ( $item ) {
					return [
						'question' => sanitize_text_field( $item['question'] ?? '' ),
						'answer'   => wp_kses_post( $item['answer'] ?? '' ),
					];
				}, $items ) );
			},
			'show_in_rest'      => [
				'schema' => [
					'title'       => __( 'FAQ items', 'storeengine' ),
					'description' => __( 'Question/answer pairs in this group.', 'storeengine' ),
					'context'     => [ 'view', 'edit' ],
					'type'        => 'array',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'question' => [ 'type' => 'string' ],
							'answer'   => [ 'type' => 'string' ],
						],
					],
				],
			],
		] );

		register_meta( 'post', '_storeengine_faq_apply_all', [
			'object_subtype'    => Helper::FAQ_POST_TYPE,
			'type'              => 'boolean',
			'single'            => true,
			'default'           => false,
			'auth_callback'     => $auth_callback,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'show_in_rest'      => true,
		] );

		foreach ( [ '_storeengine_faq_category_ids', '_storeengine_faq_brand_ids', '_storeengine_faq_product_ids' ] as $key ) {
			register_meta( 'post', $key, [
				'object_subtype'    => Helper::FAQ_POST_TYPE,
				'type'              => 'array',
				'single'            => true,
				'auth_callback'     => $auth_callback,
				'sanitize_callback' => static function ( $ids ) {
					return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : [];
				},
				'show_in_rest'      => [
					'schema' => [
						'context' => [ 'view', 'edit' ],
						'type'    => 'array',
						'items'   => [ 'type' => 'integer' ],
					],
				],
			] );
		}
	}

	/**
	 * Size chart library. An admin-managed set of measurement tables, each with
	 * display rules (all / category / brand / product) — mirrors the FAQ group
	 * CPT above. Deliberately not shown in the native menu; it is managed from
	 * StoreEngine admin (Products → Size Charts) over the storeengine/v1 REST
	 * controller.
	 */
	public function register_size_chart_post_type() {
		register_post_type( Helper::SIZE_CHART_POST_TYPE, [
			'labels'                => [
				'name'               => esc_html__( 'Size Charts', 'storeengine' ),
				'add_new'            => esc_html__( 'Add New Size Chart', 'storeengine' ),
				'singular_name'      => esc_html__( 'Size Chart', 'storeengine' ),
				'search_items'       => esc_html__( 'Search Size Charts', 'storeengine' ),
				'not_found'          => esc_html__( 'No size charts found.', 'storeengine' ),
				'not_found_in_trash' => esc_html__( 'No size charts found in Trash.', 'storeengine' ),
			],
			'public'                => false,
			'publicly_queryable'    => false,
			'show_ui'               => false,
			'show_in_menu'          => false,
			'show_in_admin_bar'     => false,
			'show_in_nav_menus'     => false,
			'hierarchical'          => false,
			'query_var'             => false,
			'delete_with_user'      => false,
			// 'editor' holds the "how to measure" content, so merchants get a
			// real editor with media-library images rather than a plain field.
			'supports'              => [ 'title', 'editor', 'author', 'page-attributes', 'custom-fields' ],
			'has_archive'           => false,
			'rewrite'               => false,
			'show_in_rest'          => true,
			'rest_base'             => 'size-charts',
			'rest_namespace'        => STOREENGINE_PLUGIN_SLUG . '/v1',
			'rest_controller_class' => 'WP_REST_Posts_Controller',
			'capability_type'       => 'post',
			'map_meta_cap'          => true,
		] );
	}

	/**
	 * REST-exposed meta for size charts (measurement tables, the "how to
	 * measure" note, and the display rules).
	 *
	 * Registered on rest_api_init for the same reason as the FAQ meta above:
	 * array-of-objects meta only round-trips reliably when the CPT's REST
	 * controller can see the full schema with an explicit context.
	 */
	public function register_size_chart_meta() {
		// Same rationale as FAQ groups: the CPT is capability_type 'post', so
		// this auth_callback is what stops Author/Editor roles writing charts.
		$auth_callback = static function () {
			return current_user_can( 'manage_options' );
		};

		register_meta( 'post', '_storeengine_size_tables', [
			'object_subtype'    => Helper::SIZE_CHART_POST_TYPE,
			'type'              => 'array',
			'single'            => true,
			'auth_callback'     => $auth_callback,
			'sanitize_callback' => static function ( $tables ) {
				if ( ! is_array( $tables ) ) {
					return [];
				}

				return array_values( array_map( static function ( $table ) {
					$columns = is_array( $table['columns'] ?? null ) ? $table['columns'] : [];
					$rows    = is_array( $table['rows'] ?? null ) ? $table['rows'] : [];

					return [
						'title'   => sanitize_text_field( $table['title'] ?? '' ),
						// Free-text so merchants can use cm / in / EU / UK etc.
						'unit'    => sanitize_text_field( $table['unit'] ?? '' ),
						'columns' => array_values( array_map( 'sanitize_text_field', $columns ) ),
						'rows'    => array_values( array_map( static function ( $row ) {
							return array_values( array_map( 'sanitize_text_field', is_array( $row ) ? $row : [] ) );
						}, $rows ) ),
					];
				}, $tables ) );
			},
			'show_in_rest'      => [
				'schema' => [
					'title'       => __( 'Size tables', 'storeengine' ),
					'description' => __( 'Measurement tables in this chart.', 'storeengine' ),
					'context'     => [ 'view', 'edit' ],
					'type'        => 'array',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'title'   => [ 'type' => 'string' ],
							'unit'    => [ 'type' => 'string' ],
							'columns' => [
								'type'  => 'array',
								'items' => [ 'type' => 'string' ],
							],
							'rows'    => [
								'type'  => 'array',
								'items' => [
									'type'  => 'array',
									'items' => [ 'type' => 'string' ],
								],
							],
						],
					],
				],
			],
		] );

		register_meta( 'post', '_storeengine_size_apply_all', [
			'object_subtype'    => Helper::SIZE_CHART_POST_TYPE,
			'type'              => 'boolean',
			'single'            => true,
			'default'           => false,
			'auth_callback'     => $auth_callback,
			'sanitize_callback' => 'rest_sanitize_boolean',
			'show_in_rest'      => true,
		] );

		foreach ( [ '_storeengine_size_category_ids', '_storeengine_size_brand_ids', '_storeengine_size_product_ids' ] as $key ) {
			register_meta( 'post', $key, [
				'object_subtype'    => Helper::SIZE_CHART_POST_TYPE,
				'type'              => 'array',
				'single'            => true,
				'auth_callback'     => $auth_callback,
				'sanitize_callback' => static function ( $ids ) {
					return is_array( $ids ) ? array_values( array_filter( array_map( 'absint', $ids ) ) ) : [];
				},
				'show_in_rest'      => [
					'schema' => [
						'context' => [ 'view', 'edit' ],
						'type'    => 'array',
						'items'   => [ 'type' => 'integer' ],
					],
				],
			] );
		}
	}

	public function add_avatar_support_product_comment( array $comment_types ): array {
		return array_merge( $comment_types, [ 'storeengine_product' ] );
	}

	public function register_product_meta() {
		$product_meta = [
			'_storeengine_product_shipping_type'           => 'string',
			'_storeengine_product_physical_weight'         => 'string',
			'_storeengine_product_physical_weight_unit'    => 'string',
			'_storeengine_product_physical_length'         => 'string',
			'_storeengine_product_physical_width'          => 'string',
			'_storeengine_product_physical_height'         => 'string',
			'_storeengine_product_physical_dimension_unit' => 'string',
			'_storeengine_product_digital_auto_complete'   => 'boolean',
			'_storeengine_product_hide'                    => 'boolean',
			'_storeengine_product_enable_license_creation' => 'boolean',
			'_storeengine_manage_stock'                    => 'boolean',
			'_storeengine_stock_quantity'                  => 'integer',
			'_storeengine_low_stock_threshold'             => 'integer',
			'_storeengine_sold_individually'               => 'boolean',
			'_storeengine_cost_price'                      => 'number',
			'_storeengine_barcode'                         => 'string',
			// Simple-product SKU (variable products keep per-variant SKUs in
			// the variations table). Registered here so REST treats it as a
			// known meta and POS / inventory queries find it consistently.
			'_storeengine_sku'                             => 'string',
		];

		foreach ( $product_meta as $meta_key => $product_meta_value_type ) {
			register_meta( 'post', $meta_key, [
				'object_subtype' => Helper::PRODUCT_POST_TYPE,
				'type'           => $product_meta_value_type,
				'single'         => true,
				'show_in_rest'   => true,
			] );
		}

		register_meta( 'post', '_storeengine_stock_status', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'title'       => __( 'Stock Status', 'storeengine' ),
					'description' => __( 'Product stock status.', 'storeengine' ),
					'context'     => [ 'view', 'edit' ],
					'type'        => 'string',
					'enum'        => [ 'instock', 'outofstock', 'onbackorder' ],
					'default'     => 'instock',
				],
			],
		] );

		register_meta( 'post', '_storeengine_backorders', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'title'       => __( 'Backorders', 'storeengine' ),
					'description' => __( 'Backorder policy.', 'storeengine' ),
					'context'     => [ 'view', 'edit' ],
					'type'        => 'string',
					'enum'        => [ 'no', 'notify', 'yes' ],
					'default'     => 'no',
				],
			],
		] );

		register_meta( 'post', '_storeengine_product_description_editor_type', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => true,
			'default'        => 'classic',
		] );

		register_meta( 'post', '_storeengine_product_gallery_ids', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'array',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'title'       => __( 'Gallery', 'storeengine' ),
					'description' => __( 'An array of image IDs for the product.', 'storeengine' ),
					'context'     => [ 'view', 'edit' ],
					'type'        => 'array',
					'items'       => [ 'type' => 'integer' ],
				],
			],
		] );

		// Unified gallery — ordered mix of images and videos so the storefront
		// carousel honours the exact order set in the editor. Each item is
		// either { type:'image', id } or { type:'video', url, poster_id }. This
		// is the source of truth; the legacy `_storeengine_product_gallery_ids`
		// (images) and `featured_media` are still kept in sync for consumers
		// that read them (archive thumbnails, pro addons).
		register_meta( 'post', '_storeengine_product_gallery', [
			// Protected-meta parity with _storeengine_product_downloadable_files:
			// the raw REST meta write path bypasses the product setter, so sanitise
			// here and gate writes to users who can edit the product.
			'sanitize_callback' => static function ( $meta_value ) {
				if ( ! is_array( $meta_value ) ) {
					return $meta_value;
				}

				return array_map(
					static function ( $item ) {
						if ( ! is_array( $item ) ) {
							return $item;
						}
						$clean = [];
						if ( isset( $item['type'] ) ) {
							$clean['type'] = 'video' === $item['type'] ? 'video' : 'image';
						}
						if ( isset( $item['id'] ) ) {
							$clean['id'] = absint( $item['id'] );
						}
						if ( isset( $item['url'] ) ) {
							$clean['url'] = esc_url_raw( (string) $item['url'] );
						}
						if ( isset( $item['poster_id'] ) ) {
							$clean['poster_id'] = absint( $item['poster_id'] );
						}

						return $clean;
					},
					$meta_value
				);
			},
			'auth_callback'  => static function ( $allowed, $meta_key, $object_id ) {
				return current_user_can( 'edit_post', $object_id );
			},
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'array',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'title'   => __( 'Gallery', 'storeengine' ),
					'context' => [ 'view', 'edit' ],
					'type'    => 'array',
					'items'   => [
						'type'                 => 'object',
						'properties'           => [
							'type'      => [ 'type' => 'string' ],
							'id'        => [ 'type' => 'integer' ],
							'url'       => [ 'type' => 'string' ],
							'poster_id' => [ 'type' => 'integer' ],
						],
						'additionalProperties' => false,
					],
				],
			],
		] );

		// Product gallery videos — each item is a URL (YouTube / Vimeo / any
		// oEmbed provider, or a direct/media-library video file) plus an
		// optional poster image (WP attachment id). Kept in sync from the
		// unified gallery for backward compatibility.
		register_meta( 'post', '_storeengine_product_gallery_videos', [
			// Protected-meta parity with _storeengine_product_downloadable_files:
			// sanitise the raw REST meta write path and gate writes to editors.
			'sanitize_callback' => static function ( $meta_value ) {
				if ( ! is_array( $meta_value ) ) {
					return $meta_value;
				}

				return array_map(
					static function ( $item ) {
						if ( ! is_array( $item ) ) {
							return $item;
						}
						$clean = [];
						if ( isset( $item['url'] ) ) {
							$clean['url'] = esc_url_raw( (string) $item['url'] );
						}
						if ( isset( $item['poster_id'] ) ) {
							$clean['poster_id'] = absint( $item['poster_id'] );
						}

						return $clean;
					},
					$meta_value
				);
			},
			'auth_callback'  => static function ( $allowed, $meta_key, $object_id ) {
				return current_user_can( 'edit_post', $object_id );
			},
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'array',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'title'       => __( 'Gallery videos', 'storeengine' ),
					'description' => __( 'Product gallery videos (url + optional poster image id).', 'storeengine' ),
					'context'     => [ 'view', 'edit' ],
					'type'        => 'array',
					'items'       => [
						'type'                 => 'object',
						'properties'           => [
							'url'       => [ 'type' => 'string' ],
							'poster_id' => [ 'type' => 'integer' ],
						],
						'additionalProperties' => false,
					],
				],
			],
		] );

		register_meta( 'post', '_storeengine_product_faqs', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'array',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'title'       => __( 'Product FAQs', 'storeengine' ),
					'description' => __( 'Inline question/answer pairs for the product.', 'storeengine' ),
					'context'     => [ 'view', 'edit' ],
					'type'        => 'array',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'question' => [ 'type' => 'string' ],
							'answer'   => [ 'type' => 'string' ],
						],
					],
				],
			],
		] );

		register_meta( 'post', '_storeengine_product_tax_status', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'title'       => __( 'Tax Status', 'storeengine' ),
					'description' => __( 'Product tax status.', 'storeengine' ),
					'context'     => [ 'view', 'edit' ],
					'type'        => 'string',
					'enum'        => [ ProductTaxStatus::TAXABLE, ProductTaxStatus::SHIPPING, ProductTaxStatus::NONE ],
					'default'     => ProductTaxStatus::TAXABLE,
				],
			],
		] );

		register_meta( 'post', '_storeengine_product_download_type', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'title'       => __( 'Download Type', 'storeengine' ),
					'description' => __( 'Download type (e.g. versioned).', 'storeengine' ),
					'context'     => [ 'view', 'edit' ],
					'type'        => 'string',
					'enum'        => [ ProductDownloadType::INSTANT, ProductDownloadType::VERSIONED ],
					'default'     => ProductDownloadType::INSTANT,
				],
			],
		] );

		register_meta( 'post', '_storeengine_product_downloadable_files', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'array',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'type'        => 'array',
					'items'       => [
						'type'       => 'object',
						'properties' => [
							'id'            => [
								'type'        => 'string',
								'description' => __( 'The unique identifier for the downloadable file.', 'storeengine' ),
							],
							'attachment_id' => [
								'type'        => 'integer',
								'description' => __( 'The unique identifier for the downloadable file.', 'storeengine' ),
							],
							'name'          => [
								'type'        => 'string',
								'description' => __( 'The name of the downloadable file.', 'storeengine' ),
							],
							'file'          => [
								'type'        => 'string',
								'format'      => 'uri',
								'description' => __( 'The URL of the downloadable file.', 'storeengine' ),
							],
							'enabled'       => [
								'type'        => 'boolean',
								'description' => __( 'Enable or disable the downloadable file.', 'storeengine' ),
							],
						],
					],
					'description' => __( 'An array of downloadable files for the product.', 'storeengine' ),
					// Edit-only: these are the raw download file URLs / attachment ids.
					// Exposing them in `view` context would let any anonymous reader of a
					// published product pull the file links without a purchase/permission
					// check. The product editor uses `edit` context (capability-gated);
					// the storefront serves files via the permission-checked download
					// handler, not this meta.
					'context'     => [ 'edit' ],
				],
			],
			// The REST meta write path (core WP_REST_Post_Meta_Fields) persists this
			// value directly with update_metadata — it never runs the product object's
			// set_downloadable_files() setter, so the traversal filter there would be
			// bypassed by a raw `meta` payload. Sanitize here too, closing the write
			// path a self-registered vendor uses to plant a `../../wp-config.php` file.
			// (Arbitrary absolute reads remain blocked at serve time by
			// DownloadHandler::is_allowed_local_path().)
			'sanitize_callback' => static function ( $meta_value ) {
				return is_array( $meta_value )
					? \StoreEngine\Classes\AbstractProduct::sanitize_downloadable_files( $meta_value )
					: $meta_value;
			},
			'auth_callback'  => static function ( $allowed, $meta_key, $object_id ) {
				// Protected meta — only users who can edit this product may write it.
				return current_user_can( 'edit_post', $object_id );
			},
		] );

		register_meta( 'post', '_storeengine_upsell_ids', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'array',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'type'        => 'array',
					'items'       => [
						'type' => 'integer',
					],
					'description' => 'An array of upsell product IDs for the product',
					'context'     => [ 'view', 'edit' ],
				],
			],
		] );

		register_meta( 'post', '_storeengine_crosssell_ids', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'array',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'type'        => 'array',
					'items'       => [
						'type' => 'integer',
					],
					'description' => 'An array of cross-sell product IDs for the product',
					'context'     => [ 'view', 'edit' ],
				],
			],
		] );

		register_meta( 'post', '_storeengine_product_purchase_redirect_type', [
			'object_subtype' => Helper::PRODUCT_POST_TYPE,
			'type'           => 'string',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'title'       => __( 'After Purchase Redirect Type', 'storeengine' ),
					'description' => __( 'Purchase Redirect Type (e.g. page)', 'storeengine' ),
					'context'     => [ 'view', 'edit' ],
					'type'        => 'string',
					'enum'        => [
						PurchaseRedirectType::DEFAULT,
						PurchaseRedirectType::PAGE,
						PurchaseRedirectType::URL,
					],
					'default'     => PurchaseRedirectType::DEFAULT,
				],
			],
		] );

		register_meta( 'post', '_storeengine_product_purchase_redirect_url', [
			'object_subtype'    => Helper::PRODUCT_POST_TYPE,
			'type'              => 'string',
			'single'            => true,
			'sanitize_callback' => function ( $value ) {
				// Accept integer IDs
				if ( is_numeric( $value ) ) {
					return (string) intval( $value );
				}

				// Accept valid URLs
				if ( filter_var( $value, FILTER_VALIDATE_URL ) ) {
					return esc_url_raw( $value );
				}

				return '';
			},
			'show_in_rest'      => [
				'schema' => [
					'title'       => __( 'After Purchase Redirect URL', 'storeengine' ),
					'description' => __( 'Redirect specific page/url after product purchase.', 'storeengine' ),
					'context'     => [ 'view', 'edit' ],
					'type'        => 'string',
					'default'     => '',
				],
			],
		] );
	}

	public function register_coupon_meta() {
		$course_meta = [
			'_storeengine_coupon_name'                    => 'string',
			'_storeengine_coupon_type'                    => 'string',
			'_storeengine_coupon_amount'                  => 'number',
			'_storeengine_coupon_time_type'               => 'string',
			'_storeengine_coupon_customer_usage_limit'    => 'integer',
			'_storeengine_coupon_type_of_min_requirement' => 'string',
			'_storeengine_coupon_min_purchase_quantity'   => 'number',
			'_storeengine_coupon_min_purchase_amount'     => 'number',
			'_storeengine_coupon_who_can_use'             => 'string',
			'_storeengine_coupon_usage_count'             => 'number',
		];

		foreach ( $course_meta as $meta_key => $meta_value_type ) {
			register_meta( 'post', $meta_key, [
				'object_subtype' => Helper::COUPON_POST_TYPE,
				'type'           => $meta_value_type,
				'single'         => true,
				'show_in_rest'   => true,
			] );
		}

		// Coupon Hard limits.
		foreach ( [ '_storeengine_coupon_usage_limit', '_storeengine_coupon_customer_usage_limit' ] as $limit_type ) {
			register_meta( 'post', $limit_type, [
				'object_subtype' => Helper::COUPON_POST_TYPE,
				'default'        => 0,
				'type'           => 'integer',
				'single'         => true,
				'show_in_rest'   => true,
			] );
		}

		// Coupon Used by (user ids).
		register_meta( 'post', '_storeengine_coupon_used_by', [
			'object_subtype' => Helper::COUPON_POST_TYPE,
			'type'           => 'number',
			'single'         => false,
			'show_in_rest'   => true,
		] );

		// Coupon Start Time
		register_meta( 'post', '_storeengine_coupon_start_date_time', [
			'object_subtype' => Helper::COUPON_POST_TYPE,
			'type'           => 'object',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'additionalProperties' => true,
					'items'                => [
						'type'       => 'object',
						'properties' => [
							'date'     => [ 'type' => 'string' ],
							'time'     => [ 'type' => 'string' ],
							'timezone' => [ 'type' => 'string' ],
						],
					],
				],
			],
		] );

		// Coupon End Time
		register_meta( 'post', '_storeengine_coupon_end_date_time', [
			'object_subtype' => Helper::COUPON_POST_TYPE,
			'type'           => 'object',
			'single'         => true,
			'show_in_rest'   => [
				'schema' => [
					'additionalProperties' => true,
					'items'                => [
						'type'       => 'object',
						'properties' => [
							'date'     => [ 'type' => 'string' ],
							'time'     => [ 'type' => 'string' ],
							'timezone' => [ 'type' => 'string' ],
						],
					],
				],
			],
		] );

		register_meta( 'post', '_storeengine_coupon_valid_customers', [
			'object_subtype'    => Helper::COUPON_POST_TYPE,
			'type'              => 'array',
			'single'            => true,
			'show_in_rest'      => [
				'schema' => [
					'type'  => 'array',
					'items' => [
						'type' => 'integer',
					],
				],
			],
			'sanitize_callback' => function ( $value ) {
				return array_values(
					array_filter(
						array_map( 'absint', (array) $value )
					)
				);
			}
		] );

		register_meta( 'post', '_storeengine_coupon_valid_prices', [
			'object_subtype'    => Helper::COUPON_POST_TYPE,
			'type'              => 'array',
			'single'            => true,
			'show_in_rest'      => [
				'schema' => [
					'type'  => 'array',
					'items' => [
						'type'       => 'object',
						'properties' => [
							'product_id' => [ 'type' => 'number' ],
							'price_ids'  => [
								'type'  => 'array',
								'items' => [ 'type' => 'number' ]
							],
						],
					],
				],
			],
			'sanitize_callback' => function ( $value ) {
				if ( ! is_array( $value ) ) {
					return [];
				}

				$sanitized = [];
				foreach ( $value as $row ) {
					// Each item must be an array/object
					if ( ! is_array( $row ) ) {
						continue;
					}

					$product_id = absint( $row['product_id'] );

					$price_ids = [];
					if ( isset( $row['price_ids'] ) && is_array( $row['price_ids'] ) ) {
						$price_ids = array_values(
							array_unique(
								array_filter(
									array_map( 'absint', $row['price_ids'] )
								)
							)
						);
					}

					$sanitized[] = [
						'product_id' => $product_id,
						'price_ids'  => $price_ids,
					];
				}

				return $sanitized;
			}
		] );

		// Product / category include & exclude restrictions. Meta keys omit the
		// `coupon_` segment on purpose so Coupon::get() maps them to the
		// settings keys its getters read (see Coupon::$internal_meta_keys).
		$id_list_sanitizer = function ( $value ) {
			return array_values( array_unique( array_filter( array_map( 'absint', (array) $value ) ) ) );
		};
		$id_list_meta = [
			'_storeengine_product_ids',
			'_storeengine_excluded_product_ids',
			'_storeengine_product_categories',
			'_storeengine_excluded_product_categories',
		];
		foreach ( $id_list_meta as $meta_key ) {
			register_meta( 'post', $meta_key, [
				'object_subtype'    => Helper::COUPON_POST_TYPE,
				'type'              => 'array',
				'single'            => true,
				'show_in_rest'      => [
					'schema' => [
						'type'  => 'array',
						'items' => [ 'type' => 'integer' ],
					],
				],
				'sanitize_callback' => $id_list_sanitizer,
			] );
		}

		// Allowed billing emails (exact match or *@domain.com wildcard).
		register_meta( 'post', '_storeengine_email_restrictions', [
			'object_subtype'    => Helper::COUPON_POST_TYPE,
			'type'              => 'array',
			'single'            => true,
			'show_in_rest'      => [
				'schema' => [
					'type'  => 'array',
					'items' => [ 'type' => 'string' ],
				],
			],
			'sanitize_callback' => function ( $value ) {
				return array_values( array_filter( array_map( static function ( $email ) {
					return strtolower( trim( sanitize_text_field( $email ) ) );
				}, (array) $value ) ) );
			},
		] );

		// Exclude on-sale items from this coupon.
		register_meta( 'post', '_storeengine_exclude_sale_items', [
			'object_subtype' => Helper::COUPON_POST_TYPE,
			'type'           => 'boolean',
			'single'         => true,
			'default'        => false,
			'show_in_rest'   => true,
		] );

		// Recurring (subscription) discount: keep applying the discount on
		// renewals. `_limit` is the number of renewals to discount (0 = forever).
		register_meta( 'post', '_storeengine_coupon_recurring_discount', [
			'object_subtype' => Helper::COUPON_POST_TYPE,
			'type'           => 'boolean',
			'single'         => true,
			'default'        => false,
			'show_in_rest'   => true,
		] );
		register_meta( 'post', '_storeengine_coupon_recurring_discount_limit', [
			'object_subtype'    => Helper::COUPON_POST_TYPE,
			'type'              => 'integer',
			'single'            => true,
			'default'           => 0,
			'show_in_rest'      => true,
			'sanitize_callback' => 'absint',
		] );

		// Auto-apply this coupon to every eligible cart (no code entry needed).
		register_meta( 'post', '_storeengine_coupon_auto_apply', [
			'object_subtype' => Helper::COUPON_POST_TYPE,
			'type'           => 'boolean',
			'single'         => true,
			'default'        => false,
			'show_in_rest'   => true,
		] );
	}

	public function register_category_rest_fields() {
		register_rest_field(
			Helper::PRODUCT_CATEGORY_TAXONOMY,
			'parent_name',
			[
				'get_callback' => static function ( array $term ): string {
					if ( empty( $term['parent'] ) ) {
						return '';
					}
					$parent = get_term( (int) $term['parent'], Helper::PRODUCT_CATEGORY_TAXONOMY );
					return ( $parent && ! is_wp_error( $parent ) ) ? $parent->name : '';
				},
				'schema'       => [
					'type'    => 'string',
					'context' => [ 'view', 'edit' ],
				],
			]
		);
	}

	public static function register_attribute_taxonomy( string $name, string $label, bool $public ) {
		return register_taxonomy( Helper::get_attribute_taxonomy_name( $name ), Helper::PRODUCT_POST_TYPE, [
			'label'                 => $label,
			'labels'                => [
				/* translators: %s: attribute name */
				'name'              => sprintf( _x( 'Product %s', 'Product Attribute', 'storeengine' ), $label ),
				'singular_name'     => $label,
				/* translators: %s: attribute name */
				'search_items'      => sprintf( __( 'Search %s', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'all_items'         => sprintf( __( 'All %s', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'parent_item'       => sprintf( __( 'Parent %s', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'parent_item_colon' => sprintf( __( 'Parent %s:', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'edit_item'         => sprintf( _x( 'Edit %s', 'Product attribute edit button label', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'update_item'       => sprintf( __( 'Update %s', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'add_new_item'      => sprintf( __( 'Add new %s', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'new_item_name'     => sprintf( __( 'New %s', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'not_found'         => sprintf( __( 'No &quot;%s&quot; found', 'storeengine' ), $label ),
				/* translators: %s: attribute name */
				'back_to_items'     => sprintf( __( '&larr; Back to "%s" attributes', 'storeengine' ), $label ),
			],
			'hierarchical'          => false,
			'update_count_callback' => '_update_post_term_count',
			'show_ui'               => false,
			'show_in_quick_edit'    => false,
			'show_in_menu'          => false,
			'meta_box_cb'           => false,
			'query_var'             => $public,
			'rewrite'               => ! $public ? false : apply_filters( 'storeengine/attribute/rewrite_rule', [
				'slug'       => Helper::get_attribute_taxonomy_name( $name ),
				'with_front' => false,
			] ),
			'sort'                  => false,
			'public'                => $public,
			'show_in_nav_menus'     => $public && apply_filters( 'storeengine/attribute/show_in_nav_menus', false, $name ),
			'capabilities'          => [
				'manage_terms' => 'manage_post_tags',
				'edit_terms'   => 'edit_post_tags',
				'delete_terms' => 'delete_post_tags',
				'assign_terms' => 'assign_post_tags',
			],
			'show_admin_column'     => false,
			'show_in_rest'          => true,
			'rest_base'             => Helper::get_attribute_taxonomy_name( $name ),
			'rest_namespace'        => STOREENGINE_PLUGIN_SLUG . '/v1',
			'rest_controller_class' => 'WP_REST_Terms_Controller',
		] );
	}
}
