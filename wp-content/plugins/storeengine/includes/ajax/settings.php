<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Admin\Settings\Base as BaseSettings;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\CheckoutFields;
use StoreEngine\Utils\Geolocation;
use StoreEngine\Utils\Helper;
use WP_Error;

class Settings extends AbstractAjaxHandler {
	protected array $payment_fields;
	protected array $settings_fields;

	public function __construct() {
		$this->load_fields();
		$this->actions = [
			'update_base_settings'         => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'update_base_settings' ],
				'fields'     => $this->settings_fields,
			],
			'update_payments_settings'     => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'update_payments_settings' ],
				'fields'     => [
					'payments' => $this->payment_fields,
				],
			],
			'verify_payment_method_config' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'verify_payment_method_config' ],
				'fields'     => [
					'method' => 'string',
					'config' => array_merge( ...array_values( $this->payment_fields ) ),
				],
			],
		];

		// This handler is constructed on `plugins_loaded` (StoreEngine boots
		// there), which is before `init`. Building the payment schema instantiates
		// every gateway, and their setup() calls __() — tripping WP 6.7+'s
		// `_load_textdomain_just_in_time` notice. So load_fields() skips the
		// payment schema until `init`; on an ajax request rebuild it then, which
		// still runs before the wp_ajax_* action dispatches.
		if ( wp_doing_ajax() && ! did_action( 'init' ) ) {
			add_action( 'init', [ $this, 'refresh_payment_fields' ] );
		}
	}

	/**
	 * Rebuild the payment field schema once `init` has fired and translations
	 * are safe to load. See __construct() for why this can't run at construction.
	 */
	public function refresh_payment_fields(): void {
		$this->payment_fields = apply_filters( 'storeengine/payment_settings_fields', [] );

		$this->actions['update_payments_settings']['fields']['payments']   = $this->payment_fields;
		$this->actions['verify_payment_method_config']['fields']['config'] = array_merge( ...array_values( $this->payment_fields ) );
	}

	protected function load_fields() {
		// The payment field schema is only consumed while handling this handler's
		// wp_ajax_* actions (save / verify). Building it instantiates every payment
		// gateway (whose setup() calls __()), so skip it off-ajax — this
		// constructor runs on every request — and skip it before `init` to avoid
		// loading translations too early. refresh_payment_fields() fills it in on
		// `init` for ajax requests. See __construct().
		$this->payment_fields  = ( wp_doing_ajax() && did_action( 'init' ) ) ? apply_filters( 'storeengine/payment_settings_fields', [] ) : [];
		$this->settings_fields = apply_filters( 'storeengine/ajax/settings_fields', [
			'store_name'                          => 'string',
			'store_email'                         => 'string',
			'store_address_1'                     => 'string',
			'store_address_2'                     => 'string',
			'store_city'                          => 'string',
			'store_state'                         => 'string',
			'store_postcode'                      => 'string',
			'store_country'                       => 'string',
			'store_currency'                      => 'string',
			'store_currency_position'             => 'string',
			'store_currency_thousand_separator'   => 'string',
			'store_currency_decimal_separator'    => 'string',
			'store_currency_decimal_limit'        => 'integer',
			// Brand & Style
			'store_logo'                          => 'absint',
			'global_primary_color'                => 'hex_color',
			'global_secondary_color'              => 'hex_color',
			'global_text_color'                   => 'hex_color',
			'global_subtitle_color'               => 'hex_color',
			'global_input_text_color'             => 'hex_color',
			'global_border_color'                 => 'hex_color',
			'global_background_color'             => 'hex_color',
			'global_placeholder_color'            => 'hex_color',
			// Products
			'default_product_shipping_type'       => 'string',
			'enable_direct_checkout'              => 'boolean',
			'hide_quantity_selector'              => 'boolean',
			'hide_add_to_cart'                    => 'boolean',
			'enable_product_reviews'              => 'boolean',
			'enable_product_comments'             => 'boolean',
			'review_permission'                   => 'string',
			'review_media_max'                    => 'integer',
			'review_approval'                     => 'string',
			'enable_related_products'             => 'boolean',
			'enable_faqs'                         => 'boolean',
			'faq_mode'                            => 'string',
			'enable_product_tax'                  => 'boolean',
			// SKU & Barcode auto-generation
			'auto_generate_sku'                   => 'boolean',
			'sku_pattern'                         => 'string',
			'sku_number_padding'                  => 'integer',
			'auto_generate_barcode'               => 'boolean',
			'barcode_ean_prefix'                  => 'string',
			// Product Archive
			'product_archive_sidebar_position'    => 'string',
			'product_archive_filters'             => [
				'search'   => [
					'status' => 'boolean',
					'order'  => 'integer',
				],
				'category' => [
					'status' => 'boolean',
					'order'  => 'integer',
				],
				'tags'     => [
					'status' => 'boolean',
					'order'  => 'integer',
				],
			],
			'product_archive_products_per_row'    => [
				'desktop' => 'integer',
				'tablet'  => 'integer',
				'mobile'  => 'integer',
			],
			'product_archive_products_per_page'   => 'integer',
			'product_archive_products_order'      => 'string',
			'product_archive_multi_price_display' => 'string',
			'product_single_price_display'        => 'string',
			'single_product_gallery_layout'       => 'string',
			'product_archive_card_carousel'       => 'boolean',
			'product_archive_card_swatches'       => 'boolean',
			'product_archive_quick_view'          => 'boolean',
			'quick_view_position'                 => 'string',
			'quick_view_animation'                => 'string',
			'enable_recently_viewed'              => 'boolean',
			'enable_size_guide'                   => 'boolean',
			'enable_wishlist'                     => 'boolean',
			'enable_product_compare'              => 'boolean',
			// Pages
			'shop_page'                           => 'integer',
			'cart_page'                           => 'integer',
			'checkout_page'                       => 'integer',
			'thankyou_page'                       => 'integer',
			'dashboard_page'                      => 'integer',
			'membership_pricing_page'             => 'integer',
			'affiliate_registration_page'         => 'integer',
			// Geolocation
			'maxmind_license'                     => 'password',
			'default_customer_address'            => 'string',
			'enable_caching_support'              => 'boolean',
			// Selling / shipping country restrictions — conventional storefront parity.
			// `allowed_countries`: 'all' | 'all_except' | 'specific'
			// `ship_to_countries`: ''   | 'all' | 'specific' | 'disabled'  (empty = follow sell-to)
			'allowed_countries'                   => 'string',
			'all_except_countries'                => 'array',
			'specific_allowed_countries'          => 'array',
			'ship_to_countries'                   => 'string',
			'specific_ship_to_countries'          => 'array',
			// Tax
			'prices_include_tax'                  => 'boolean',
			'tax_based_on'                        => 'string',
			'shipping_tax_class'                  => 'string',
			'tax_round_at_subtotal'               => 'boolean',
			'tax_classes'                         => 'string',
			'tax_display_shop'                    => 'string',
			'tax_display_cart'                    => 'string',
			'price_display_suffix'                => 'string',
			'tax_total_display'                   => 'string',
			'auth_redirect_type'                  => 'string',
			'auth_redirect_url'                   => 'url',
			'checkout_default_country'            => 'country',
			'enable_floating_cart'                => 'boolean',
			'auto_open_cart_drawer'               => 'boolean',
			'sticky_add_to_cart'                  => 'boolean',
			'enable_after_purchase_redirect'      => 'boolean',
			'analytics'                           => [
				'google'   => 'boolean',
				'facebook' => 'boolean',
			],
			// Checkout Fields tab — saved as { field_id: { enabled: bool, required: bool } }.
			// Declared as `array` so populate_field_data preserves the shape verbatim
			// instead of stripping unknown sub-keys.
			'checkout_fields'                     => 'array',
			// Instant Checkout addon settings — same passthrough shape.
			'instant_checkout'                    => 'array',
		] );
	}

	protected array $tax_total_display_options = [ 'single', 'itemized' ];
	protected array $tax_display_options       = [ 'incl', 'excl' ];
	protected array $tax_base_options          = [ 'shipping', 'billing', 'base' ];

	protected array $archive_filter_options = [ 'search', 'category', 'tags' ];

	protected function populate_field_data( array $fields, $payload = [], $defaults = [] ): array {
		$output = [];

		foreach ( $fields as $field => $type ) {
			if ( is_array( $type ) ) {
				$_defaults        = array_key_exists( $field, $defaults ) ? $defaults[ $field ] : [];
				$_payload         = array_key_exists( $field, $payload ) ? $payload[ $field ] : $_defaults;
				$_payload         = null === $_payload || '' === $_payload ? [] : $_payload;
				$output[ $field ] = $this->populate_field_data( $type, $_payload, $_defaults );
			} else {
				$output[ $field ] = $payload[ $field ] ?? ( $defaults[ $field ] ?? '' );
			}
		}

		return $output;
	}

	protected function update_base_settings( $payload ) {
		// Filling up blanks with saved data.
		// Don't set from default data as it can reset saved data if field is unset.
		// @XXX needs more testing.
		// @see BaseSettings::get_settings_default_data
		$default = BaseSettings::get_settings_saved_data();
		// Set from default if not set and save the changes.

		// Prepare filter widget settings.
		$payload['product_archive_filters'] = $payload['product_archive_filters'] ?? ( $default['product_archive_filters'] ?? [] );

		foreach ( $this->archive_filter_options as $option ) {
			if ( empty( $payload['product_archive_filters'][ $option ] ) ) {
				$payload['product_archive_filters'][ $option ] = [
					'status' => false,
					'order'  => $default['product_archive_filters'][ $option ]['order'] ?? 0,
				];
			} else {
				$payload['product_archive_filters'][ $option ] = wp_parse_args( $payload['product_archive_filters'][ $option ], [
					'status' => true,
					'order'  => 0,
				] );
			}
		}

		// Validate global tax settings.
		if ( ! empty( $payload['tax_total_display'] ) && ! in_array( $payload['tax_total_display'], $this->tax_total_display_options, true ) ) {
			wp_send_json_error( __( 'Invalid cart & checkout tax total display option.', 'storeengine' ) );
		}

		if ( ! empty( $payload['tax_display_cart'] ) && ! in_array( $payload['tax_display_cart'], $this->tax_display_options, true ) ) {
			wp_send_json_error( __( 'Invalid cart & checkout tax display option.', 'storeengine' ) );
		}

		if ( ! empty( $payload['tax_display_shop'] ) && ! in_array( $payload['tax_display_shop'], $this->tax_display_options, true ) ) {
			wp_send_json_error( __( 'Invalid shop tax display option.', 'storeengine' ) );
		}

		if ( ! empty( $payload['tax_based_on'] ) && ! in_array( $payload['tax_based_on'], $this->tax_base_options, true ) ) {
			wp_send_json_error( __( 'Invalid tax address base.', 'storeengine' ) );
		}

		$payload['auth_redirect_type'] = $payload['auth_redirect_type'] ?? ( $default['auth_redirect_type'] ?? 'storeengine' );
		$payload['auth_redirect_url']  = $payload['auth_redirect_url'] ?? ( $default['auth_redirect_url'] ?? '' );

		if ( ! in_array( $payload['auth_redirect_type'], [ 'default', 'storeengine', 'custom' ], true ) ) {
			wp_send_json_error( __( 'Invalid dashboard login redirect.', 'storeengine' ) );
		}

		if ( 'custom' === $payload['auth_redirect_type'] ) {
			if ( ! $payload['auth_redirect_url'] ) {
				wp_send_json_error( __( 'Login URL is required.', 'storeengine' ) );
			} else {
				if ( filter_var( $payload['auth_redirect_url'], FILTER_VALIDATE_URL ) === false ) {
					wp_send_json_error( __( 'Login URL is invalid.', 'storeengine' ) );
				}

				if ( is_ssl() && ! str_starts_with( $payload['auth_redirect_url'], 'https://' ) ) {
					wp_send_json_error( __( 'Invalid dashboard login redirect URL. Please use secure (https) URL.', 'storeengine' ) );
				}

				if ( str_starts_with( $payload['auth_redirect_url'], Helper::get_dashboard_url() ) ) {
					// Prevent redirect loop.
					wp_send_json_error( __( 'Dashboard URL is not allowed. Please use StoreEngine as auth redirect instead.', 'storeengine' ) );
				}

				// Remove all allowed hosts except the site url.
				remove_all_filters( 'allowed_redirect_hosts' );
				if ( ! wp_validate_redirect( $payload['auth_redirect_url'] ) ) {
					wp_send_json_error( __( 'Login URL is not allowed.', 'storeengine' ) );
				}
			}
		}

		// Prepare Order by.
		$valid_orderby                             = [ 'menu_order', 'title', 'date', 'modified', 'ID' ];
		$payload['product_archive_products_order'] = $payload['product_archive_products_order'] ?? ( $default['product_archive_products_order'] ?? '' );
		$payload['product_archive_products_order'] = in_array( $payload['product_archive_products_order'], $valid_orderby, true ) ? $payload['product_archive_products_order'] : '';

		if ( $payload['product_archive_multi_price_display'] && ! in_array( $payload['product_archive_multi_price_display'], [
			'dropdown',
			'price-range',
		], true ) ) {
			wp_send_json_error( __( 'Invalid multi-price display settings.', 'storeengine' ) );
		}

		if ( ! empty( $payload['product_single_price_display'] ) && ! in_array( $payload['product_single_price_display'], [
			'radio',
			'dropdown',
		], true ) ) {
			wp_send_json_error( __( 'Invalid single product price display settings.', 'storeengine' ) );
		}

		if ( ! empty( $payload['single_product_gallery_layout'] ) && ! in_array( $payload['single_product_gallery_layout'], [
			'carousel',
			'stacked',
			'grid',
		], true ) ) {
			wp_send_json_error( __( 'Invalid product gallery layout settings.', 'storeengine' ) );
		}

		if ( ! empty( $payload['quick_view_position'] ) && ! in_array( $payload['quick_view_position'], [
			'center',
			'left',
			'right',
		], true ) ) {
			wp_send_json_error( __( 'Invalid Quick View position setting.', 'storeengine' ) );
		}

		if ( ! empty( $payload['quick_view_animation'] ) && ! in_array( $payload['quick_view_animation'], [
			'fade',
			'zoom',
			'slide',
			'none',
		], true ) ) {
			wp_send_json_error( __( 'Invalid Quick View animation setting.', 'storeengine' ) );
		}

		$errors = apply_filters( 'storeengine/ajax/validate_settings', new WP_Error(), $payload );

		if ( is_wp_error( $errors ) && $errors->has_errors() ) {
			wp_send_json_error( $errors, 400 );
		}

		$new_key   = $payload['maxmind_license'] ?? '';
		$saved_key = $default['maxmind_license'] ?? '';

		if ( $new_key ) {
			if ( $saved_key !== $new_key ) {
				$is_valid = Geolocation::validate_maxmind_license_key( $new_key );
				if ( is_wp_error( $is_valid ) ) {
					wp_send_json_error( $is_valid->get_error_message() );
				}

				if ( ! file_exists( Geolocation::get_maxmind_db_path() ) ) {
					\StoreEngine::init()->queue()->schedule_single( time() + 1, 'storeengine/geolocation/maxmind/db-update', [], 'storeengine' );
				}
			}
		}

		// Normalise checkout_fields. Form-encoded payloads send "false" as a literal
		// string; coerce to real booleans so reads via (bool) return the correct value.
		if ( isset( $payload['checkout_fields'] ) && is_array( $payload['checkout_fields'] ) ) {
			$normalised = [];
			foreach ( $payload['checkout_fields'] as $id => $row ) {
				$row                               = is_array( $row ) ? $row : [];
				$normalised[ sanitize_key( $id ) ] = [
					'enabled'  => CheckoutFields::to_bool( $row['enabled'] ?? false ),
					'required' => CheckoutFields::to_bool( $row['required'] ?? false ),
				];
			}
			$payload['checkout_fields'] = $normalised;
		}

		// Prepare & save settings.
		$is_update = BaseSettings::save_settings( $this->populate_field_data( $this->settings_fields, $payload, $default ) );

		// Clear any unwanted data and flush rules.
		Helper::flush_rewire_rules();

		do_action( 'storeengine/admin/after_save_settings', $is_update, 'base', $payload );

		wp_send_json_success( $is_update );
	}


	protected function update_payments_settings( $payload ) {
		if ( empty( $payload['payments'] ) ) {
			wp_send_json_error( esc_html__( 'Invalid request.', 'storeengine' ) );
		}

		foreach ( $payload['payments'] as $gateway => $data ) {
			if ( ! array_key_exists( $gateway, $this->payment_fields ) ) {
				continue;
			}

			do_action( 'storeengine/admin/save_gateways/' . $gateway . '/settings', $data );
		}

		wp_send_json_success( true );
	}

	/**
	 * Verify payment verification payload data.
	 *
	 * @param array $payload
	 *
	 * @throws StoreEngineException
	 */
	protected function verify_payment_config_payload( array $payload ) {
		if ( empty( $payload['method'] ) ) {
			throw new StoreEngineException( esc_html__( 'payment method is required.', 'storeengine' ) );
		}
		if ( empty( $payload['config'] ) ) {
			throw new StoreEngineException( esc_html__( 'Missing required fields.', 'storeengine' ) );
		}

		if ( ! array_key_exists( $payload['method'], $this->payment_fields ) ) {
			throw new StoreEngineException( esc_html__( 'Payment method doesnt exists.', 'storeengine' ) );
		}
	}

	protected function verify_payment_method_config( $payload ) {
		try {
			$this->verify_payment_config_payload( $payload );

			$payment_method = $payload['method'];
			do_action( "storeengine/admin/verify_payment_{$payment_method}_config", $payload['config'] );

			wp_send_json_success();
		} catch ( StoreEngineException $e ) {
			/* translators: %s. Error message. */
			wp_send_json_error( sprintf( esc_html__( 'Failed to verify payment method settings. Error: %s', 'storeengine' ), esc_html( $e->getMessage() ) ) );
		}
	}

	protected function sanitize_bank_transfer_settings( $field_settings, int $index = 0 ): array {
		return [
			'type'         => 'bank_transfer',
			'is_enabled'   => (bool) sanitize_text_field( $field_settings['is_enabled'] ),
			'title'        => sanitize_text_field( $field_settings['title'] ),
			'description'  => sanitize_text_field( $field_settings['description'] ),
			'instructions' => sanitize_text_field( $field_settings['instructions'] ),
			'accounts'     => [],
			'index'        => $index,
		];
	}

	protected function sanitize_check_payment_settings( $field_settings, int $index = 0 ): array {
		return [
			'type'         => 'check_payment',
			'is_enabled'   => (bool) sanitize_text_field( $field_settings['is_enabled'] ),
			'title'        => sanitize_text_field( $field_settings['title'] ),
			'description'  => sanitize_text_field( $field_settings['description'] ),
			'instructions' => sanitize_text_field( $field_settings['instructions'] ),
			'index'        => $index,
		];
	}

	protected function sanitize_cash_on_delivery_settings( $field_settings, int $index = 0 ): array {
		return [
			'type'         => 'cash_on_delivery',
			'is_enabled'   => (bool) sanitize_text_field( $field_settings['is_enabled'] ),
			'title'        => sanitize_text_field( $field_settings['title'] ),
			'description'  => sanitize_text_field( $field_settings['description'] ),
			'instructions' => sanitize_text_field( $field_settings['instructions'] ),
			'index'        => $index,
		];
	}

	protected function sanitize_paypal_settings( $field_settings, int $index = 0 ): array {
		return [
			'type'                  => 'paypal',
			'is_enabled'            => (bool) sanitize_text_field( $field_settings['is_enabled'] ),
			'is_enabled_sandbox'    => (bool) sanitize_text_field( $field_settings['is_enabled_sandbox'] ),
			'sandbox_client_id'     => sanitize_text_field( $field_settings['sandbox_client_id'] ),
			'sandbox_client_secret' => sanitize_text_field( $field_settings['sandbox_client_secret'] ),
			'live_client_id'        => sanitize_text_field( $field_settings['live_client_id'] ),
			'live_client_secret'    => sanitize_text_field( $field_settings['live_client_secret'] ),
			'index'                 => $index,
		];
	}

	protected function sanitize_stripe_settings( $field_settings, int $index = 0 ): array {
		return [
			'type'                 => 'stripe',
			'is_enabled'           => (bool) sanitize_text_field( $field_settings['is_enabled'] ),
			'is_enabled_test_mode' => (bool) sanitize_text_field( $field_settings['is_enabled_test_mode'] ),
			'test_publishable_key' => sanitize_text_field( $field_settings['test_publishable_key'] ),
			'test_secret_key'      => sanitize_text_field( $field_settings['test_secret_key'] ),
			'live_publishable_key' => sanitize_text_field( $field_settings['live_publishable_key'] ),
			'live_secret_key'      => sanitize_text_field( $field_settings['live_secret_key'] ),
			'index'                => $index,
		];
	}
}
