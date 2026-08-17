<?php

namespace StoreEngine\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Base {

	public static function get_settings_saved_data(): array {
		$settings = get_option( STOREENGINE_SETTINGS_NAME );
		if ( $settings && ! is_array( $settings ) ) {
			$decoded = json_decode( $settings, true );

			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				return $decoded;
			}
		}

		if ( is_array( $settings ) ) {
			return $settings;
		}

		return [];
	}

	public static function get_settings_default_data() {
		return apply_filters( 'storeengine/admin/settings_default_data', [
			// General
			'store_name'                          => 'StoreEngine',
			'store_email'                         => get_option( 'admin_email' ),
			'store_currency'                      => 'USD',
			'store_currency_position'             => 'left',
			'store_currency_thousand_separator'   => ',',
			'store_currency_decimal_separator'    => '.',
			'store_currency_decimal_limit'        => 2,
			'store_country'                       => '',
			'store_address_1'                     => '',
			'store_address_2'                     => '',
			'store_city'                          => '',
			'store_state'                         => '',
			'store_postcode'                      => '',
			// Brand & Style
			'store_logo'                          => '',
			'global_primary_color'                => '#008DFF',
			'global_secondary_color'              => '#FF5000',
			'global_text_color'                   => '#2C3135',
			'global_subtitle_color'               => '#646C73',
			'global_input_text_color'             => '#434A51',
			'global_border_color'                 => '#ebebeb',
			'global_background_color'             => '#FAFAFA',
			'global_placeholder_color'            => '#8E949A',
			// Product
			'default_product_shipping_type'       => 'digital',
			'enable_direct_checkout'              => false,
			'hide_quantity_selector'              => false,
			'hide_add_to_cart'                    => false,
			'enable_product_reviews'              => false,
			'enable_product_comments'             => false,
			// Who may leave a product review: 'completed_order' (bought & order
			// completed), 'purchased' (ordered, any status), or 'everyone'.
			'review_permission'                   => 'completed_order',
			// Max images/videos a reviewer can attach (0 = unlimited).
			'review_media_max'                    => 5,
			// New reviews: 'auto' approve immediately, or 'pending' for manual
			// approval from Products → Reviews.
			'review_approval'                     => 'auto',
			'enable_related_products'             => false,
			// FAQ system. `faq_mode`: 'global' shows the FAQ Groups library +
			// rule targeting; 'product_only' hides the library and limits the
			// product editor to inline Q&A.
			'enable_faqs'                         => true,
			'faq_mode'                            => 'global',
			'enable_product_tax'                  => false,
			// SKU & Barcode auto-generation
			'auto_generate_sku'                   => false,
			'sku_pattern'                         => '{category}-{number}',
			'sku_number_padding'                  => 4,
			'auto_generate_barcode'               => false,
			'barcode_ean_prefix'                  => '20',
			// Product Archive
			'product_archive_sidebar_position'    => 'right',
			'product_archive_filters'             => [
				'search'   => [
					'status' => true,
					'order'  => 0,
				],
				'category' => [
					'status' => true,
					'order'  => 1,
				],
				'tags'     => [
					'status' => true,
					'order'  => 2,
				],
			],
			'product_archive_products_per_row'    => [
				'desktop' => 3,
				'tablet'  => 2,
				'mobile'  => 1,
			],
			'product_archive_products_per_page'   => 12,
			'product_archive_products_order'      => '',
			'product_archive_multi_price_display' => 'dropdown',
			'product_single_price_display'        => 'radio',
			'single_product_gallery_layout'       => 'carousel',
			// Product archive/shop card enhancements (opt-in).
			'product_archive_card_carousel'       => false,
			'product_archive_card_swatches'       => false,
			'product_archive_quick_view'          => false,
			'quick_view_position'                 => 'center', // center|left|right
			'quick_view_animation'                => 'fade', // fade|zoom|slide|none
			'enable_recently_viewed'              => true,
			'enable_size_guide'                   => false,
			'enable_wishlist'                     => false,
			'enable_product_compare'              => false,
			// Page
			'shop_page'                           => '',
			'cart_page'                           => '',
			'checkout_page'                       => '',
			'thankyou_page'                       => '',
			'dashboard_page'                      => '',
			'membership_pricing_page'             => '',
			'affiliate_registration_page'         => '',
			// Geolocation
			'maxmind_license'                     => '',
			'default_customer_address'            => '',
			'enable_caching_support'              => false,
			// Selling / shipping country restrictions
			'allowed_countries'                   => 'all',
			'all_except_countries'                => [],
			'specific_allowed_countries'          => [],
			'ship_to_countries'                   => '',
			'specific_ship_to_countries'          => [],
			// Tax settings
			'prices_include_tax'                  => false,
			'tax_based_on'                        => 'shipping',
			'shipping_tax_class'                  => '',
			'tax_round_at_subtotal'               => false,
			'tax_classes'                         => '',
			'tax_display_shop'                    => 'excl',
			'tax_display_cart'                    => 'excl',
			'price_display_suffix'                => '',
			'tax_total_display'                   => 'itemized',
			// Stripe Automatic Tax (delegates calculation to Stripe Tax Calculations API).
			'enable_stripe_tax'                   => false,
			'stripe_tax_default_code'             => 'txcd_99999999',
			'stripe_tax_shipping_code'            => 'txcd_92010001',
			'stripe_tax_fallback_to_local'        => false,
			'auth_redirect_type'                  => 'storeengine',
			'auth_redirect_url'                   => '',
			'checkout_default_country'            => 'US',
			'enable_floating_cart'                => true,
			'auto_open_cart_drawer'               => false,
			'sticky_add_to_cart'                  => true,
			'enable_after_purchase_redirect'      => false,
			// checkout customization
			'checkout_fields'                     => [
				'email'                   => [
					'system'   => true,
					'required' => true,
				],
				// billing
				'billing_first_name'      => [
					'required' => true,
					'enabled'  => true,
				],
				'billing_last_name'       => [
					'required' => true,
					'enabled'  => true,
				],
				'billing_address_line'    => [
					'required' => true,
					'enabled'  => true,
				],
				'billing_address_line_2'  => [
					'required' => false,
					'enabled'  => false,
				],
				'billing_country'         => [
					'required' => true,
					'enabled'  => true,
				],
				'billing_city'            => [
					'required' => true,
					'enabled'  => true,
				],
				'billing_state'           => [
					'required' => false,
					'enabled'  => false,
				],
				'billing_apt'             => [
					'required' => false,
					'enabled'  => false,
				],
				'billing_post_code'       => [
					'required' => false,
					'enabled'  => false,
				],
				'billing_phone'           => [
					'required' => false,
					'enabled'  => false,
				],
				// Shipping
				'shipping_first_name'     => [
					'system'  => true,
					'enabled' => true,
				],
				'shipping_last_name'      => [
					'system'  => true,
					'enabled' => true,
				],
				'shipping_address_line'   => [
					'required' => true,
					'enabled'  => true,
				],
				'shipping_address_line_2' => [
					'required' => false,
					'enabled'  => false,
				],
				'shipping_country'        => [
					'required' => true,
					'enabled'  => true,
				],
				'shipping_city'           => [
					'required' => true,
					'enabled'  => true,
				],
				'shipping_state'          => [
					'required' => false,
					'enabled'  => false,
				],
				'shipping_apt'            => [
					'required' => false,
					'enabled'  => false,
				],
				'shipping_post_code'      => [
					'required' => false,
					'enabled'  => false,
				],
				'shipping_phone'          => [
					'required' => false,
					'enabled'  => false,
				],
			],
			// Analytics
			'analytics'                           => [
				'google'   => false,
				'facebook' => false,
			],
		] );
	}

	protected static function prepare_settings_data( array $new_data = [] ): array {
		$default_data  = self::get_settings_default_data();
		$saved_data    = self::get_settings_saved_data();
		$settings_data = wp_parse_args( $saved_data, $default_data );
		if ( $new_data ) {
			$settings_data = wp_parse_args( $new_data, $settings_data );
		}

		return $settings_data;
	}

	public static function save_settings( array $form_data = [] ): bool {
		return update_option( STOREENGINE_SETTINGS_NAME, wp_json_encode( self::prepare_settings_data( $form_data ) ) );
	}

	public static function delete_settings( string $key ): void {
		$settings = self::prepare_settings_data();

		if ( ! array_key_exists( $key, $settings ) ) {
			return;
		}

		unset( $settings[ $key ] );

		update_option( STOREENGINE_SETTINGS_NAME, wp_json_encode( $settings ) );
	}
}
