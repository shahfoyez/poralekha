<?php

namespace StoreEngine\Addons\Webhooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Webhooks\Events\Dispatch;
use StoreEngine\Addons\Webhooks\Incoming\Incoming;
use StoreEngine\Addons\Webhooks\Incoming\Registry;

final class Webhooks extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'webhooks';

	/**
	 * Defines CONSTANTS for Whole Addon.
	 */
	public function define_constants() {
		define( 'STOREENGINE_WEBHOOKS_VERSION', '1.0' );
		define( 'STOREENGINE_WEBHOOKS_DIR_PATH', STOREENGINE_ADDONS_DIR_PATH . 'webhooks/' );
	}

	public static function get_events() {
		return apply_filters( 'storeengine/webhooks_events', [
			'product_created',
			'product_updated',
			'product_deleted',
			'product_restored',
			'coupon_created',
			'coupon_updated',
			'coupon_deleted',
			'coupon_restored',
			'order_created',
			'order_updated',
			'order_deleted',
			'order_restored',
			'customer_created',
			'customer_updated',
			'customer_deleted',

			// CMS content — useful for headless storefronts that need to
			// revalidate Next.js / Nuxt / Astro caches when WP content changes.
			'page_published',
			'page_updated',
			'page_deleted',
			'post_published',
			'post_updated',
			'post_deleted',

			// Block-theme / Site Editor — fired on template + part saves so
			// headless renderers can refresh layout changes without a redeploy.
			'template_saved',
			'template_part_saved',
			'global_styles_saved',

			// Navigation
			'nav_menu_updated',
		] );
	}
	public static function get_event_labels() {
		return apply_filters( 'storeengine/webhooks_event_labels', [
			[
				'label' => __( 'Product Created', 'storeengine' ),
				'value' => 'product_created',
			],
			[
				'label' => __( 'Product Updated', 'storeengine' ),
				'value' => 'product_updated',
			],
			[
				'label' => __( 'Product Deleted', 'storeengine' ),
				'value' => 'product_deleted',
			],
			[
				'label' => __( 'Product Restored', 'storeengine' ),
				'value' => 'product_restored',
			],
			[
				'label' => __( 'Coupon Created', 'storeengine' ),
				'value' => 'coupon_created',
			],
			[
				'label' => __( 'Coupon Updated', 'storeengine' ),
				'value' => 'coupon_updated',
			],
			[
				'label' => __( 'Coupon Deleted', 'storeengine' ),
				'value' => 'coupon_deleted',
			],
			[
				'label' => __( 'Coupon Restored', 'storeengine' ),
				'value' => 'coupon_restored',
			],
			[
				'label' => __( 'Order Created', 'storeengine' ),
				'value' => 'order_created',
			],
			[
				'label' => __( 'Order Updated', 'storeengine' ),
				'value' => 'order_updated',
			],
			[
				'label' => __( 'Order Deleted', 'storeengine' ),
				'value' => 'order_deleted',
			],
			[
				'label' => __( 'Order Restored', 'storeengine' ),
				'value' => 'order_restored',
			],
			[
				'label' => __( 'Customer Created', 'storeengine' ),
				'value' => 'customer_created',
			],
			[
				'label' => __( 'Customer Updated', 'storeengine' ),
				'value' => 'customer_updated',
			],
			[
				'label' => __( 'Customer Deleted', 'storeengine' ),
				'value' => 'customer_deleted',
			],

			// CMS content
			[ 'label' => __( 'Page Published', 'storeengine' ), 'value' => 'page_published' ],
			[ 'label' => __( 'Page Updated', 'storeengine' ),   'value' => 'page_updated' ],
			[ 'label' => __( 'Page Deleted', 'storeengine' ),   'value' => 'page_deleted' ],
			[ 'label' => __( 'Post Published', 'storeengine' ), 'value' => 'post_published' ],
			[ 'label' => __( 'Post Updated', 'storeengine' ),   'value' => 'post_updated' ],
			[ 'label' => __( 'Post Deleted', 'storeengine' ),   'value' => 'post_deleted' ],

			// Block-theme / Site Editor
			[ 'label' => __( 'Block Template Saved', 'storeengine' ),      'value' => 'template_saved' ],
			[ 'label' => __( 'Block Template Part Saved', 'storeengine' ), 'value' => 'template_part_saved' ],
			[ 'label' => __( 'Global Styles Saved', 'storeengine' ),       'value' => 'global_styles_saved' ],

			// Navigation
			[ 'label' => __( 'Navigation Menu Updated', 'storeengine' ),   'value' => 'nav_menu_updated' ],
		] );
	}

	/**
	 * Grouped event list for the React event picker (react-select group format).
	 *
	 * react-select renders `{ label, options: [{ label, value }] }` items as
	 * collapsible-style groups, so adding ~10 new events doesn't bury the
	 * existing 15 in a flat list. Each group is filterable so Pro addons can
	 * register their own group label and slot events into it without
	 * disturbing core.
	 *
	 * @return array<int,array{label:string,options:array<int,array{label:string,value:string}>}>
	 */
	public static function get_event_groups(): array {
		return apply_filters( 'storeengine/webhooks_event_groups', [
			[
				'label'   => __( 'Products', 'storeengine' ),
				'options' => [
					[ 'label' => __( 'Created', 'storeengine' ),  'value' => 'product_created' ],
					[ 'label' => __( 'Updated', 'storeengine' ),  'value' => 'product_updated' ],
					[ 'label' => __( 'Deleted', 'storeengine' ),  'value' => 'product_deleted' ],
					[ 'label' => __( 'Restored', 'storeengine' ), 'value' => 'product_restored' ],
				],
			],
			[
				'label'   => __( 'Orders', 'storeengine' ),
				'options' => [
					[ 'label' => __( 'Created', 'storeengine' ),  'value' => 'order_created' ],
					[ 'label' => __( 'Updated', 'storeengine' ),  'value' => 'order_updated' ],
					[ 'label' => __( 'Deleted', 'storeengine' ),  'value' => 'order_deleted' ],
					[ 'label' => __( 'Restored', 'storeengine' ), 'value' => 'order_restored' ],
				],
			],
			[
				'label'   => __( 'Coupons', 'storeengine' ),
				'options' => [
					[ 'label' => __( 'Created', 'storeengine' ),  'value' => 'coupon_created' ],
					[ 'label' => __( 'Updated', 'storeengine' ),  'value' => 'coupon_updated' ],
					[ 'label' => __( 'Deleted', 'storeengine' ),  'value' => 'coupon_deleted' ],
					[ 'label' => __( 'Restored', 'storeengine' ), 'value' => 'coupon_restored' ],
				],
			],
			[
				'label'   => __( 'Customers', 'storeengine' ),
				'options' => [
					[ 'label' => __( 'Created', 'storeengine' ), 'value' => 'customer_created' ],
					[ 'label' => __( 'Updated', 'storeengine' ), 'value' => 'customer_updated' ],
					[ 'label' => __( 'Deleted', 'storeengine' ), 'value' => 'customer_deleted' ],
				],
			],
			[
				'label'   => __( 'Pages', 'storeengine' ),
				'options' => [
					[ 'label' => __( 'Published', 'storeengine' ), 'value' => 'page_published' ],
					[ 'label' => __( 'Updated', 'storeengine' ),   'value' => 'page_updated' ],
					[ 'label' => __( 'Deleted', 'storeengine' ),   'value' => 'page_deleted' ],
				],
			],
			[
				'label'   => __( 'Posts', 'storeengine' ),
				'options' => [
					[ 'label' => __( 'Published', 'storeengine' ), 'value' => 'post_published' ],
					[ 'label' => __( 'Updated', 'storeengine' ),   'value' => 'post_updated' ],
					[ 'label' => __( 'Deleted', 'storeengine' ),   'value' => 'post_deleted' ],
				],
			],
			[
				'label'   => __( 'Block Theme / Site Editor', 'storeengine' ),
				'options' => [
					[ 'label' => __( 'Template Saved', 'storeengine' ),      'value' => 'template_saved' ],
					[ 'label' => __( 'Template Part Saved', 'storeengine' ), 'value' => 'template_part_saved' ],
					[ 'label' => __( 'Global Styles Saved', 'storeengine' ), 'value' => 'global_styles_saved' ],
				],
			],
			[
				'label'   => __( 'Navigation', 'storeengine' ),
				'options' => [
					[ 'label' => __( 'Menu Updated', 'storeengine' ), 'value' => 'nav_menu_updated' ],
				],
			],
		] );
	}

	public function init_addon() {
		Database::init();
		Dispatch::init();
		Incoming::init();

		add_filter( 'storeengine/admin_menu_list', [ $this, 'admin_menu_items' ] );
		add_filter( 'storeengine/backend_scripts_data', [ $this, 'backend_scripts_data' ] );
	}

	public function admin_menu_items( array $items ): array {
		return array_merge( $items, [
			STOREENGINE_PLUGIN_SLUG . '-webhooks' => [
				'title'      => __( 'WebHooks', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 91,
			],
		] );
	}

	public function backend_scripts_data( array $data ): array {
		$data['webhooks']         = self::get_event_labels(); // back-compat (flat)
		$data['webhooks_groups']  = self::get_event_groups(); // react-select group shape
		$data['incoming_actions'] = Registry::get_actions();  // incoming action picker

		return $data;
	}

	public function addon_activation_hook() {
		Database::init();
		Helper::flush_rewire_rules();
	}

	public function addon_deactivation_hook() {
		Helper::flush_rewire_rules();
	}
}
