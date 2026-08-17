<?php

namespace StoreEngine;

use StoreEngine\Admin\Notices;
use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Product\VariableProduct;
use StoreEngine\Utils\ArrayUtil;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\PaymentUtil;
use StoreEngine\Utils\Pixel;

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly.
}

class Assets {

	public static function init() {
		$self = new self();
		add_action( 'admin_enqueue_scripts', array( $self, 'enqueue_admin_menu_css' ) );
		add_action( 'admin_enqueue_scripts', [ $self, 'backend_scripts' ] );
		add_action( 'admin_enqueue_scripts', [ $self, 'backend_inline_style' ] );
		add_action( 'wp_enqueue_scripts', [ $self, 'frontend_scripts' ] );
		add_action( 'enqueue_block_editor_assets', [ $self, 'block_editor_assets' ] );
		add_action( 'wp_footer', [ $self, 'display_price_placeholder' ] );
	}

	public function web_fonts_url( $font ): string {
		$font_url = add_query_arg( 'family', rawurlencode( $font ), '//fonts.googleapis.com/css2' );

		return add_query_arg( 'display', 'swap', $font_url );
	}

	public function enqueue_admin_menu_css( $hook ) {
		// Single combined admin stylesheet (menu chrome + notices) on every admin
		// screen — replaces the old menu.css / notices.css / icon-font trio.
		// Notice icons are inline SVG (see Notices::get_svg_icon), so the icon
		// font is no longer needed on this always-loaded path.
		wp_enqueue_style( 'storeengine-admin', STOREENGINE_ASSETS_URI . 'css/admin.css', [], STOREENGINE_VERSION, 'all' );

		if ( strpos( $hook, '_page_' . STOREENGINE_PLUGIN_SLUG ) !== false ) {
			return;
		}

		Notices::init()->dispatch_notices();
		if ( ! empty( Admin\Notices::get_notices() ) ) {
			wp_add_inline_style( 'storeengine-admin', $this->get_dynamic_css() );
		}
	}

	public function frontend_scripts() {
		// Icon Library.
		wp_enqueue_style(
			'storeengine-frontend-icon',
			STOREENGINE_ASSETS_URI . 'library/icons/storeengine-icons.css',
			[ 'wp-components' ],
			filemtime(
				STOREENGINE_ASSETS_DIR_PATH .
				'library/icons/storeengine-icons.css'
			),
			'all'
		);

		// Main Stylesheet.
		wp_enqueue_style(
			'storeengine-frontend-style',
			STOREENGINE_ASSETS_URI . 'build/frontend.css',
			[],
			filemtime( STOREENGINE_ASSETS_DIR_PATH . 'build/frontend.css' ),
			'all'
		);

		// Add dynamic CSS variables.
		wp_add_inline_style( 'storeengine-frontend-style', $this->get_dynamic_css() );

		// Add Flickity CSS.
		if ( Helper::is_product() ) {
			wp_enqueue_style(
				'flickity',
				STOREENGINE_ASSETS_URI . 'library/flickity/flickity.min.css',
				[],
				STOREENGINE_VERSION,
				null
			);
		}

		// JS — use `include` (not `include_once`) so a second hook invocation in
		// the same request still gets the array; `*_once` returns int(1) after
		// the first include and breaks the array_merge below.
		$asset_file   = STOREENGINE_ASSETS_DIR_PATH . sprintf( 'build/frontend.%s.asset.php', STOREENGINE_VERSION );
		$dependencies = is_file( $asset_file ) ? include $asset_file : null;
		if ( ! is_array( $dependencies ) ) {
			$dependencies = [ 'dependencies' => [], 'version' => STOREENGINE_VERSION ];
		}

		do_action( 'storeengine/enqueue_frontend_scripts' );

		// Note: the storefront bundle is small enough that the standard string
		// extractor parses it fine, so w.org generates its language pack normally
		// and it needs no i18n companion — we deliberately don't add that
		// multi-KB manifest to every storefront page.
		wp_enqueue_script(
			'storeengine-frontend-scripts',
			STOREENGINE_ASSETS_URI .
			sprintf( 'build/frontend.%s.js', STOREENGINE_VERSION ),
			// The storefront bundle is jQuery-free (vanilla JS via StoreEngineDQ);
			// `wp-util` was unused and only pulled jQuery in transitively.
			(array) ( $dependencies['dependencies'] ?? [] ),
			$dependencies['version'] ?? STOREENGINE_VERSION,
			true
		);

		if ( Helper::is_product() ) {
			global $product;

			if ( $product instanceof VariableProduct && 'variable' === $product->get_type() ) {
				wp_localize_script(
					'storeengine-frontend-scripts',
					'StoreEngineProductVariations',
					self::get_product_variations( $product )
				);
			}

		}

		$analytics = (array) Helper::get_settings( 'analytics', [] );
		if ( ArrayUtil::any( $analytics, fn( $stat ) => true === $stat ) ) {
			$data = [];
			if ( Helper::is_product() ) {
				$data = Pixel::get_single_product_data();
			}
			if ( Helper::is_shop() ) {
				$data = Pixel::get_product_archive_data();
			}
			if ( Helper::is_cart() || Helper::is_checkout() ) {
				$data = [ 'cart_info' => Pixel::get_cart_info() ];
			}
			if ( Helper::is_thank_you() ) {
				// Provide the completed order so the GA4 `purchase` event can
				// fire on the thank-you page (see google.ts), attributing the
				// conversion + revenue to this URL instead of the checkout page.
				$data = $this->get_thankyou_pixel_data();
			}
			wp_localize_script(
				'storeengine-frontend-scripts',
				'SEPixelData',
				array_merge(
					[
						'page_title' => wp_get_document_title(),
						'currency'   => Formatting::get_currency(),
					],
					$data
				)
			);
		}

		wp_localize_script(
			'storeengine-frontend-scripts',
			'StoreEngineGlobal',
			$this->get_frontend_script_data()
		);

		if ( Helper::get_settings( 'enable_wishlist', false ) ) {
			wp_localize_script(
				'storeengine-frontend-scripts',
				'StoreEngineWishlist',
				[
					'enabled'    => true,
					'isLoggedIn' => is_user_logged_in(),
					'ids'        => is_user_logged_in() ? array_map( 'strval', storeengine_get_user_wishlist() ) : [],
					'restBase'   => esc_url_raw( rest_url( 'storeengine/v1/wishlist/' ) ),
					'nonce'      => wp_create_nonce( 'wp_rest' ),
					'i18n'       => [
						'add'    => __( 'Add to wishlist', 'storeengine' ),
						'remove' => __( 'Remove from wishlist', 'storeengine' ),
					],
				]
			);
		}

		if ( Helper::get_settings( 'enable_product_compare', false ) ) {
			wp_localize_script(
				'storeengine-frontend-scripts',
				'StoreEngineCompare',
				[
					'enabled'    => true,
					'isLoggedIn' => is_user_logged_in(),
					'ids'        => is_user_logged_in() ? array_map( 'strval', storeengine_get_user_compare() ) : [],
					'max'        => \StoreEngine\Classes\ProductCompare::max(),
					'restBase'   => esc_url_raw( rest_url( 'storeengine/v1/compare/' ) ),
					'nonce'      => wp_create_nonce( 'wp_rest' ),
					'i18n'       => [
						'add'    => __( 'Compare', 'storeengine' ),
						'added'  => __( 'Comparing', 'storeengine' ),
						'remove' => __( 'Remove from comparison', 'storeengine' ),
						'title'  => __( 'Compare products', 'storeengine' ),
						'close'  => __( 'Close', 'storeengine' ),
					],
				]
			);
		}

		wp_set_script_translations(
			'storeengine-frontend-scripts',
			'storeengine',
			STOREENGINE_ROOT_DIR_PATH . 'i18n/languages'
		);

		// Add Flickity JS
		if ( Helper::is_product() ) {
			wp_enqueue_script(
				'flickity',
				STOREENGINE_ASSETS_URI . 'library/flickity/flickity.pkgd.min.js',
				[],
				STOREENGINE_VERSION,
				true
			);
		}

		do_action( 'storeengine/assets/after_frontend_scripts' );
	}

	/**
	 * Completed-order data for the thank-you page GA4 `purchase` event.
	 *
	 * Returned under an `order` key inside SEPixelData and consumed by
	 * dev_storeengine/pixel-integrations/google.ts. The shape mirrors the
	 * checkout REST response's `order`, limited to the fields the analytics
	 * integration reads. Building it here (rather than re-running the checkout
	 * response pipeline) avoids re-firing payment-success side effects.
	 *
	 * @return array Either [ 'order' => [...] ] or [] when no valid order.
	 */
	private function get_thankyou_pixel_data(): array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$order_hash = isset( $_GET['order_hash'] ) ? sanitize_text_field( wp_unslash( $_GET['order_hash'] ) ) : '';
		if ( '' === $order_hash ) {
			return [];
		}

		try {
			$order = Helper::get_order_by_key( $order_hash );
		} catch ( \Throwable $e ) {
			return [];
		}

		if ( ! $order instanceof Order || is_wp_error( $order ) || ! $order->get_id() ) {
			return [];
		}

		$line_items = [];
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			$meta = [];
			foreach ( (array) $item->get_meta_data() as $m ) {
				if ( is_object( $m ) && isset( $m->key ) ) {
					$meta[] = [
						'key'   => $m->key,
						'value' => $m->value ?? '',
					];
				}
			}

			$line_items[] = [
				'product_id'   => $item->get_product_id(),
				'price_id'     => $item->get_price_id(),
				'name'         => $item->get_name(),
				'price'        => $item->get_price(),
				'quantity'     => $item->get_quantity(),
				'subtotal'     => $item->get_subtotal(),
				'total'        => $item->get_total(),
				'variation_id' => $item->get_variation_id(),
				'price_type'   => $item->get_price_type(),
				'meta'         => $meta,
			];
		}

		$coupon_lines = array_map(
			static function ( $code ) {
				return [ 'code' => $code ];
			},
			array_values( array_filter( (array) $order->get_coupon_codes() ) )
		);

		return [
			'order' => [
				'transaction_id'        => $order->get_transaction_id(),
				'order_key'             => $order->get_order_key(),
				'currency'              => $order->get_currency(),
				'total'                 => $order->get_total(),
				'total_tax'             => $order->get_total_tax(),
				'shipping_total_amount' => $order->get_shipping_total(),
				'discount_total_amount' => $order->get_discount_total(),
				'coupon_lines'          => $coupon_lines,
				'line_items'            => $line_items,
			],
		];
	}

	/**
	 * Enqueue Block Editor Assets
	 *
	 * @since 1.3.3
	 */
	public function block_editor_assets() {
		// Check if the aBlocks plugin is active before enqueuing styles.
		if ( ! Helper::is_plugin_active( 'ablocks/ablocks.php' ) ) {
			return;
		}

		// Icon Library.
		wp_enqueue_style(
			'storeengine-frontend-icon',
			STOREENGINE_ASSETS_URI . 'library/icons/storeengine-icons.css',
			[ 'wp-components' ],
			filemtime(
				STOREENGINE_ASSETS_DIR_PATH .
				'library/icons/storeengine-icons.css'
			),
			'all'
		);

		// Main Stylesheet.
		wp_enqueue_style(
			'storeengine-frontend-style',
			STOREENGINE_ASSETS_URI . 'build/frontend.css',
			[],
			filemtime( STOREENGINE_ASSETS_DIR_PATH . 'build/frontend.css' ),
			'all'
		);

		// Add dynamic CSS variables.
		wp_add_inline_style( 'storeengine-frontend-style', $this->get_dynamic_css() );

		// Add Flickity CSS.
		wp_enqueue_style(
			'flickity',
			STOREENGINE_ASSETS_URI . 'library/flickity/flickity.min.css',
			[],
			STOREENGINE_VERSION,
			null
		);
	}

	public static function get_product_variations( VariableProduct $product ): array {
		$pricing    = [];
		$taxonomies = [];
		$variations = array_map( function ( $variant ) use ( &$taxonomies ) {
			$attributes = [];
			foreach ( $variant->get_attributes() as $attribute ) {
				$attributes[ $attribute->taxonomy ] = $attribute->slug;
				$taxonomies[]                       = $attribute->taxonomy;
			}

			return [
				'id'                 => $variant->get_id(),
				'name'               => $variant->get_name(),
				'price'              => $variant->get_price(),
				'sku'                => $variant->get_sku(),
				'pricing_id'         => (int) $variant->get_pricing_id(),
				'featured_image_url' => wp_get_attachment_image_url( $variant->get_featured_image() ),
				'attributes'         => $attributes,
				// Per-variation stock so the picker can disable Add to Cart /
				// relabel "Out of stock" for the selected variation.
				'is_in_stock'        => method_exists( $variant, 'is_in_stock' ) ? $variant->is_in_stock() : true,
				'stock_status'       => method_exists( $variant, 'get_stock_status' ) ? $variant->get_stock_status() : 'instock',
			];
		}, $product->get_available_variants() );

		foreach ( $product->get_prices() as $price ) {
			$pricing[ $price->get_id() ] = $price->get_price();
		}

		return [
			'taxonomies' => array_values( array_unique( $taxonomies ) ),
			'pricing'    => $pricing,
			'variations' => $variations,
		];
	}

	public function get_frontend_script_data() {
		$data = [];

		if ( Helper::is_checkout() || Helper::is_edit_address_page() ) {
			$state_labels = array_filter( array_map( fn( $data ) => [
				'label'    => $data['state']['label'] ?? '',
				'required' => $data['state']['required'] ?? false,
			], Countries::init()->get_country_locale() ) );
			$states       = array_merge( Countries::init()->get_allowed_country_states(), Countries::init()->get_shipping_country_states() );
			$data         = [
				'states'                    => $states,
				'state_labels'              => $state_labels,
				'state_label'               => __( 'State / County', 'storeengine' ),
				'is_required'               => '&nbsp;<abbr class="storeengine-required" title="' . esc_attr__( 'Required', 'storeengine' ) . '">*</abbr>',
				'i18n_select_state_text'    => esc_attr__( 'Select an option&hellip;', 'storeengine' ),
				'i18n_no_matches'           => _x( 'No matches found', 'enhanced select', 'storeengine' ),
				'i18n_ajax_error'           => _x( 'Loading failed', 'enhanced select', 'storeengine' ),
				'i18n_input_too_short_1'    => _x( 'Please enter 1 or more characters', 'enhanced select', 'storeengine' ),
				'i18n_input_too_short_n'    => _x( 'Please enter %qty% or more characters', 'enhanced select', 'storeengine' ),
				'i18n_input_too_long_1'     => _x( 'Please delete 1 character', 'enhanced select', 'storeengine' ),
				'i18n_input_too_long_n'     => _x( 'Please delete %qty% characters', 'enhanced select', 'storeengine' ),
				'i18n_selection_too_long_1' => _x( 'You can only select 1 item', 'enhanced select', 'storeengine' ),
				'i18n_selection_too_long_n' => _x( 'You can only select %qty% items', 'enhanced select', 'storeengine' ),
				'i18n_load_more'            => _x( 'Loading more results&hellip;', 'enhanced select', 'storeengine' ),
				'i18n_searching'            => _x( 'Searching&hellip;', 'enhanced select', 'storeengine' ),
			];
		}

		$analytics    = (array) Helper::get_settings( 'analytics', [] );
		$hasAnalytics = ArrayUtil::any( $analytics, fn( $stat ) => true === $stat );

		return apply_filters(
			'storeengine/frontend_scripts_data',
			array_merge(
				$this->_get_script_data(),
				[
					'checkout_page_url'       => esc_url( Helper::get_checkout_url() ),
					'thankyou_page_url'       => esc_url( Helper::get_thankyou_page_url() ),
					'payment_gateways'        => $this->get_payment_method_script_data(),
					'payment_data'            => [],
					'checkout_with_order_pay' => Formatting::string_to_bool( get_query_var( 'order_pay' ) ),
					'page'                    => [
						'dashboard' => esc_url( Helper::get_dashboard_url() ),
						'checkout'  => esc_url( Helper::get_checkout_url() ),
						'thankyou'  => esc_url( Helper::get_thankyou_page_url() ),
						'terms'     => esc_url( Helper::get_terms_page_url() ),
						'privacy'   => esc_url( Helper::get_privacy_page_url() ),
					],
					'is_page'                 => [
						'is_product'            => Helper::is_product(),
						'is_shop'               => Helper::is_shop(),
						'is_cart'               => Helper::is_cart(),
						'is_dashboard'          => Helper::is_dashboard_index(),
						'is_address_edit'       => Helper::is_edit_address_page(),
						'is_checkout'           => Helper::is_checkout(),
						'is_order_pay'          => Helper::is_checkout() && PaymentUtil::is_valid_order_pay_page(),
						'is_add_payment_method' => Helper::is_add_payment_method_page(),
					],
					'settings'                => [
						'analytics'             => $hasAnalytics ? $analytics : false,
						'auto_open_cart_drawer' => (bool) Helper::get_settings( 'auto_open_cart_drawer', false ),
					]
				],
				$data
			)
		);
	}

	public function _get_script_data(): array {
		global $storeengine_addons;

		$current_user = wp_get_current_user();
		$user_name    = ! is_email( $current_user->display_name ) ? $current_user->display_name : trim( $current_user->first_name . ' ' . $current_user->last_name );
		$user_name    = $user_name ?: $current_user->user_login;

		// Brand logo for barcode labels etc. — StoreEngine store logo, then the
		// theme custom logo, then the site icon.
		$store_logo = Helper::get_settings( 'store_logo' );
		if ( is_numeric( $store_logo ) && (int) $store_logo > 0 ) {
			$store_logo = wp_get_attachment_image_url( (int) $store_logo, 'medium' );
		}
		if ( empty( $store_logo ) && get_theme_mod( 'custom_logo' ) ) {
			$store_logo = wp_get_attachment_image_url( (int) get_theme_mod( 'custom_logo' ), 'medium' );
		}
		if ( empty( $store_logo ) ) {
			$store_logo = get_site_icon_url( 128 );
		}

		$data = [
			'is_admin'           => is_admin(),
			'nonce'              => wp_create_nonce( 'wp_rest' ),
			'storeengine_nonce'  => wp_create_nonce( 'storeengine_nonce' ),
			'rest_url'           => esc_url_raw( rest_url() ),
			'locale'             => get_locale(),
			'user_locale'        => function_exists( 'get_user_locale' ) ? get_user_locale() : get_locale(),
			'namespace'          => STOREENGINE_PLUGIN_SLUG . '/v1/',
			'plugin_root_url'    => STOREENGINE_PLUGIN_ROOT_URI,
			'plugin_root_path'   => STOREENGINE_ROOT_DIR_PATH,
			'root_path'          => STOREENGINE_ROOT_DIR_PATH,
			'root_url'           => STOREENGINE_PLUGIN_ROOT_URI,
			'assets_url'         => STOREENGINE_ASSETS_URI,
			'ajaxurl'            => esc_url( admin_url( 'admin-ajax.php' ) ),
			'admin_url'          => admin_url(),
			'site_url'           => site_url(),
			'route_path'         => wp_parse_url( admin_url(), PHP_URL_PATH ),
			'admin_menu_lists'   => wp_json_encode( Admin\Menu::get_menu_tree() ),
			'current_user_id'    => $current_user->ID,
			'current_user_name'  => $user_name,
			'store_email'        => Helper::get_settings( 'store_email' ),
			'store_name'         => get_bloginfo( 'name' ),
			'store_logo'         => $store_logo ? $store_logo : '',
			'is_rtl'             => is_rtl(),
			'addons'             => $storeengine_addons,
			// Addon cards injected by other plugins (e.g. storeengine-payments
			// gateways) for the Add-ons screen. Each entry mirrors the card shape
			// in the React Addons utils (name, label, details, tags, svgIcon, …).
			'extra_addons'       => apply_filters( 'storeengine/admin/extra_addons', [] ),
			// Install-only teasers for the free companion plugins (StoreEngine
			// Payments / Connectors). Each entry carries the plugin's active state,
			// a download URL, and a catalog of what it unlocks — the React admin
			// shows the teaser only while the companion plugin is inactive.
			'satellite_plugins'  => \StoreEngine\Admin\SatellitePlugins::get_teaser_data(),
			'timezone'           => wp_timezone_string(),
			'current_user_can'   => [
				'manage_options' => current_user_can( 'manage_options' ),
			],
			'screen_options'     => \StoreEngine\Admin\ScreenOptions::get_all(),
			'currency_options'   => Helper::get_currency_options(),
			'localeConv'         => localeconv(),
			'is_tax_enabled'     => Helper::get_settings( 'enable_product_tax' ),
			'enable_product_tax' => Helper::get_settings( 'enable_product_tax' ),
			'is_pro'             => Helper::is_active_storeengine_pro(),
			'ablocks_status'     => Helper::is_plugin_installed( 'ablocks/ablocks.php' ) ? ( Helper::is_plugin_active( 'ablocks/ablocks.php' ) ? 'active' : 'not-active' ) : 'not-installed',
		];

		/**
		 * Filters the admin app's localized data (window.StoreEngineGlobal).
		 *
		 * Addons can inject extra flags here. The Role & Permission addon uses it
		 * to add the current user's per-resource write permissions so the CPT
		 * editors can render read-only (hide Save) for a view-only staff user.
		 *
		 * @param array $data Localized data array.
		 */
		return apply_filters( 'storeengine/admin/script_data', $data );
	}

	public function get_payment_method_script_data() {
		$data = apply_filters( 'storeengine/frontend_scripts_payment_method_data', [] );

		// Also walk every available gateway through the unified per-gateway
		// data filter — `storeengine/checkout/gateway/{id}/data` — so third-
		// party gateways that only hook the new public API still populate the
		// legacy `payment_gateways.{id}` global that the /checkout/ page
		// shells read from. The same filter feeds React Quick Checkout's
		// snapshot, so one hook covers both surfaces.
		try {
			$gateways = Helper::get_payment_gateways()->get_available_payment_gateways();
		} catch ( \Throwable $e ) {
			$gateways = [];
		}
		foreach ( $gateways as $gateway ) {
			$existing = isset( $data[ $gateway->id ] ) && is_array( $data[ $gateway->id ] ) ? $data[ $gateway->id ] : [];
			$merged   = apply_filters( "storeengine/checkout/gateway/{$gateway->id}/data", $existing, $gateway );
			if ( is_array( $merged ) && ! empty( $merged ) ) {
				$data[ $gateway->id ] = $merged;
			}
		}

		return $data;
	}

	/**
	 * Register the JS-translation companion handle.
	 *
	 * The real admin/setup/frontend bundles are multi-megabyte webpack builds
	 * that the standard WP-CLI / translate.wordpress.org string extractor cannot
	 * parse, so on w.org no `storeengine-<locale>-<md5(bundle)>.json` language
	 * pack is ever generated and the screens stay untranslated. We ship one
	 * small, parseable file — assets/build/i18n-strings.js (generated from the
	 * POT by bin/make-i18n-strings.mjs) — that lists every JS string. w.org can
	 * read it and builds `storeengine-<locale>-<md5(i18n-strings.js)>.json`.
	 * Because @wordpress/i18n keys locale data by TEXT-DOMAIN (not by file),
	 * loading that one JSON translates every bundle. The path is unversioned so
	 * the md5 — and the language packs — stay stable across releases.
	 *
	 * Enqueue this handle as a dependency of any StoreEngine bundle; that both
	 * loads the JSON and makes @wordpress/i18n available.
	 */
	public function register_i18n_strings() {
		if ( wp_script_is( 'storeengine-i18n', 'registered' ) ) {
			return;
		}

		$file = STOREENGINE_ASSETS_DIR_PATH . 'build/i18n-strings.js';
		if ( ! is_file( $file ) ) {
			return;
		}

		wp_register_script(
			'storeengine-i18n',
			STOREENGINE_ASSETS_URI . 'build/i18n-strings.js',
			[ 'wp-i18n' ],
			filemtime( $file ),
			true
		);
		wp_set_script_translations(
			'storeengine-i18n',
			'storeengine',
			STOREENGINE_ROOT_DIR_PATH . 'i18n/languages'
		);
	}

	/**
	 * Enqueue Files on Start Plugin
	 *
	 * @param string $hook
	 *
	 * @function backend_scripts
	 */
	public function backend_scripts( string $hook ) {
		if ( ! strpos( $hook, '_page_' . STOREENGINE_PLUGIN_SLUG ) !== false ) {
			return;
		}

		remove_all_actions( 'admin_notices' );

		wp_enqueue_style(
			'storeengine-admin-icon',
			STOREENGINE_ASSETS_URI . 'library/icons/storeengine-icons.css',
			[ 'wp-components' ],
			filemtime(
				STOREENGINE_ASSETS_DIR_PATH .
				'library/icons/storeengine-icons.css'
			),
		);

		wp_enqueue_style(
			'storeengine-admin-style',
			STOREENGINE_ASSETS_URI . 'build/backend.css',
			[ 'wp-components' ],
			filemtime( STOREENGINE_ASSETS_DIR_PATH . 'build/backend.css' ),
			'all'
		);

		wp_add_inline_style( 'storeengine-admin-style', $this->get_dynamic_css() );

		if ( ! did_action( 'wp_enqueue_media' ) ) {
			wp_enqueue_media();
		}

		// js — see note on the frontend equivalent above (use `include` so a
		// second invocation in the same request still returns the array).
		$asset_file   = STOREENGINE_ASSETS_DIR_PATH . sprintf( 'build/backend.%s.asset.php', STOREENGINE_VERSION );
		$dependencies = is_file( $asset_file ) ? include $asset_file : null;
		if ( ! is_array( $dependencies ) ) {
			$dependencies = [ 'dependencies' => [], 'version' => STOREENGINE_VERSION ];
		}

		// Load the JS-translation companion so w.org language packs apply here.
		$this->register_i18n_strings();

		wp_enqueue_script(
			'storeengine-admin-scripts',
			STOREENGINE_ASSETS_URI .
			sprintf( 'build/backend.%s.js', STOREENGINE_VERSION ),
			array_merge( (array) ( $dependencies['dependencies'] ?? [] ), wp_script_is( 'storeengine-i18n', 'registered' ) ? [ 'storeengine-i18n' ] : [] ),
			$dependencies['version'] ?? STOREENGINE_VERSION,
			true
		);
		wp_localize_script(
			'storeengine-admin-scripts',
			'StoreEngineGlobal',
			$this->get_backend_script_data()
		);
		wp_set_script_translations(
			'storeengine-admin-scripts',
			'storeengine',
			STOREENGINE_ROOT_DIR_PATH . 'i18n/languages'
		);
	}

	public function get_backend_script_data(): array {
		$currencies = [];

		foreach ( Helper::get_currencies() as $code => $label ) {
			$currencies[] = [
				'code'   => $code,
				'name'   => $label,
				'symbol' => Helper::get_currency_symbol( $code ),
			];
		}

		return apply_filters(
			'storeengine/backend_scripts_data',
			array_merge(
				$this->_get_script_data(),
				[
					'first_order'                    => Helper::get_first_order_date( 'Y-m-d' ),
					'currency_options'               => Helper::get_currency_options(),
					'localeConv'                     => localeconv(),
					'countries'                      => Countries::init()->get_countries(),
					'currencies'                     => $currencies,
					'enable_after_purchase_redirect' => Helper::get_settings( 'enable_after_purchase_redirect', false ),
					'enable_faqs'                    => (bool) Helper::get_settings( 'enable_faqs', true ),
					'faq_mode'                       => Helper::get_settings( 'faq_mode', 'global' ),
					// Store-wide default for new products. Settings → Products
					// exposes a radio that writes this; the product editor's
					// initial-values builder reads it for new products only.
					'default_product_shipping_type'  => in_array( Helper::get_settings( 'default_product_shipping_type', 'digital' ), [ 'digital', 'physical' ], true ) ? Helper::get_settings( 'default_product_shipping_type', 'digital' ) : 'digital',
					'is_debug'                       => defined( 'WP_DEBUG' ) && WP_DEBUG,
				]
			)
		);
	}

	public function get_dynamic_css(): string {
		global $storeengine_settings;

		/**
		 * Filter css properties
		 */
		$css = apply_filters( 'storeengine/dynamic_css_root_properties', [
			'--storeengine-primary-color'     => esc_attr( $storeengine_settings->global_primary_color ?? '' ),
			'--storeengine-secondary-color'   => esc_attr( $storeengine_settings->global_secondary_color ?? '' ),
			'--storeengine-text-color'        => esc_attr( $storeengine_settings->global_text_color ?? '' ),
			'--storeengine-subtitle-color'    => esc_attr( $storeengine_settings->global_subtitle_color ?? '' ),
			'--storeengine-input-text-color'  => esc_attr( $storeengine_settings->global_input_text_color ?? '' ),
			'--storeengine-placeholder-color' => esc_attr( $storeengine_settings->global_placeholder_color ?? '' ),
			'--storeengine-border-color'      => esc_attr( $storeengine_settings->global_border_color ?? '' ),
			'--storeengine-background-color'  => esc_attr( $storeengine_settings->global_background_color ?? '' ),
		] );

		// Filter out empty values to avoid invalid CSS.
		$filtered_css = array_filter( $css, fn( $value ) => trim( ltrim( rtrim( $value, ';' ), ':' ) ) !== '' );

		return ':root {' . PHP_EOL . implode( PHP_EOL, array_map( fn( $key, $value ) => "\t$key:$value;", array_keys( $filtered_css ), $filtered_css ) ) . PHP_EOL . '}';
	}

	public function display_price_placeholder() {
		?>
		<script type="text/html" id="tmpl-storeengine-price">
			<p class="storeengine-product__price-amount">
				{{{ data.price }}}
			</p>
		</script>
		<?php
	}

	public function backend_inline_style() {
		$custom_css = '
		a[href*="page=storeengine-get-pro"] {
			background: #FFA500 !important;
			color: #000000 !important;
			font-weight: 600;
			box-shadow: none !important;
		}
		';
		wp_add_inline_style( 'admin-bar', $custom_css );
	}
}
