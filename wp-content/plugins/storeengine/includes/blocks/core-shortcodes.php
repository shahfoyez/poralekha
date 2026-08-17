<?php
/**
 * Registers StoreEngine's core shortcodes with the shortcode → block bridge, so
 * they're all available as configurable blocks in the editor.
 *
 * @package StoreEngine\Blocks
 */

namespace StoreEngine\Blocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CoreShortcodes {

	public static function register() {
		add_action( 'init', [ __CLASS__, 'register_descriptors' ], 20 );
	}

	public static function register_descriptors() {
		if ( ! function_exists( 'storeengine_register_shortcode_block' ) ) {
			return;
		}

		$owner    = 'storeengine';
		$category = __( 'StoreEngine', 'storeengine' );

		// Self-closing, attribute-less page shortcodes: [ title, icon, aBlocks block
		// it converts to ('' when none) ].
		$simple = [
			'storeengine_checkout_form'         => [ __( 'Checkout Form', 'storeengine' ), 'cart', 'ablocks/storeengine-checkout-form' ],
			'storeengine_order_summary'         => [ __( 'Order Summary', 'storeengine' ), 'list-view', '' ],
			'storeengine_cart_list_table'       => [ __( 'Cart Items', 'storeengine' ), 'cart', 'ablocks/storeengine-cart-list' ],
			'storeengine_cart_sub_total_table'  => [ __( 'Cart Totals', 'storeengine' ), 'money-alt', 'ablocks/storeengine-cart-sub-table' ],
			'storeengine_apply_coupon_form'     => [ __( 'Apply Coupon', 'storeengine' ), 'tag', 'ablocks/storeengine-coupon-form' ],
			'storeengine_proceed_to_checkout'   => [ __( 'Proceed to Checkout', 'storeengine' ), 'arrow-right-alt', 'ablocks/storeengine-checkout-button' ],
			'storeengine_mini_cart'             => [ __( 'Mini Cart', 'storeengine' ), 'cart', 'ablocks/storeengine-mini-cart' ],
			'storeengine_order_details'         => [ __( 'Order Details', 'storeengine' ), 'clipboard', 'ablocks/storeengine-order-details' ],
			'storeengine_order_billing_address' => [ __( 'Billing Address', 'storeengine' ), 'admin-home', 'ablocks/storeengine-billing-info' ],
			'storeengine_order_shipping_address'=> [ __( 'Shipping Address', 'storeengine' ), 'admin-home', 'ablocks/storeengine-shipping-info' ],
			'storeengine_thankyou_order_info'   => [ __( 'Thank-You Order Info', 'storeengine' ), 'yes-alt', 'ablocks/storeengine-order-info' ],
			'storeengine_thankyou_payment_instructions' => [ __( 'Payment Instructions', 'storeengine' ), 'info', '' ],
			'storeengine_order_downloads'       => [ __( 'Order Downloads', 'storeengine' ), 'download', '' ],
			'storeengine_login_form'            => [ __( 'Login Form', 'storeengine' ), 'admin-users', 'ablocks/storeengine-login-form' ],
			'storeengine_dashboard'             => [ __( 'Customer Dashboard', 'storeengine' ), 'dashboard', '' ],
			'storeengine_continue_shopping'     => [ __( 'Continue Shopping', 'storeengine' ), 'arrow-left-alt', 'ablocks/storeengine-continue-button' ],
			// Single-product sections — usable inside the FSE product template.
			'storeengine_single_product_faq'      => [ __( 'Product FAQ', 'storeengine' ), 'editor-help', '' ],
			'storeengine_single_product_reviews'  => [ __( 'Product Reviews', 'storeengine' ), 'star-filled', '' ],
			'storeengine_single_product_comments' => [ __( 'Product Comments', 'storeengine' ), 'admin-comments', '' ],
		];

		foreach ( $simple as $tag => $meta ) {
			storeengine_register_shortcode_block( [
				'tag'           => $tag,
				'owner'         => $owner,
				'title'         => $meta[0],
				'category'      => $category,
				'icon'          => $meta[1],
				'ablocks_block' => $meta[2],
			] );
		}

		// Product grid — a few common presentation attributes.
		storeengine_register_shortcode_block( [
			'tag'           => 'storeengine_products',
			'owner'         => $owner,
			'title'         => __( 'Products', 'storeengine' ),
			'category'      => $category,
			'icon'          => 'grid-view',
			'keywords'      => [ 'products', 'grid', 'shop' ],
			'ablocks_block' => 'ablocks/storeengine-products',
			'attributes'  => [
				[
					'name'    => 'columns',
					'label'   => __( 'Columns', 'storeengine' ),
					'type'    => 'range',
					'default' => 3,
					'min'     => 1,
					'max'     => 6,
					'step'    => 1,
					'group'   => __( 'Layout', 'storeengine' ),
					'sanitize' => 'int',
				],
				[
					'name'    => 'per_page',
					'label'   => __( 'Products per page', 'storeengine' ),
					'type'    => 'number',
					'default' => 9,
					'min'     => 1,
					'group'   => __( 'Layout', 'storeengine' ),
					'sanitize' => 'int',
				],
				[
					'name'    => 'orderby',
					'label'   => __( 'Order by', 'storeengine' ),
					'type'    => 'select',
					'default' => 'date',
					'group'   => __( 'Query', 'storeengine' ),
					'sanitize' => 'key',
					'options' => [
						[ 'label' => __( 'Newest', 'storeengine' ), 'value' => 'date' ],
						[ 'label' => __( 'Title', 'storeengine' ), 'value' => 'title' ],
						[ 'label' => __( 'Price', 'storeengine' ), 'value' => 'price' ],
						[ 'label' => __( 'Popularity', 'storeengine' ), 'value' => 'popularity' ],
					],
				],
				[
					'name'    => 'order',
					'label'   => __( 'Order', 'storeengine' ),
					'type'    => 'select',
					'default' => 'DESC',
					'group'   => __( 'Query', 'storeengine' ),
					'sanitize' => 'key',
					'options' => [
						[ 'label' => __( 'Descending', 'storeengine' ), 'value' => 'DESC' ],
						[ 'label' => __( 'Ascending', 'storeengine' ), 'value' => 'ASC' ],
					],
				],
				[
					'name'     => 'ids',
					'label'    => __( 'Specific product IDs', 'storeengine' ),
					'type'     => 'csv',
					'default'  => '',
					'group'    => __( 'Query', 'storeengine' ),
					'sanitize' => 'csv',
				],
			],
		] );
	}
}
