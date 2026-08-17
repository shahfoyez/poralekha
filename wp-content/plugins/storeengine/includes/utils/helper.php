<?php /** @noinspection PhpUnused */

namespace StoreEngine\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine;
use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Logger;
use StoreEngine\Models\Cart;
use StoreEngine\Shipping\Methods\ShippingMethod;
use StoreEngine\Utils\traits\{Attribute,
	Currency,
	Customer,
	DownloadPermission,
	Gateway,
	Integration,
	Order,
	Pages,
	Product,
	ThemePlugin};
use WP_Error;
use WP_Post;

class Helper extends Template {

	use Customer, Pages, Order, Currency, Gateway,
		Product, Integration, Attribute, DownloadPermission,
		ThemePlugin, StoreEngine\Utils\Traits\Repository;

	const PRODUCT_POST_TYPE = STOREENGINE_PLUGIN_SLUG . '_product';

	const PRODUCT_CATEGORY_TAXONOMY = self::PRODUCT_POST_TYPE . '_category';

	const PRODUCT_ATTRIBUTE_TAXONOMY = self::PRODUCT_POST_TYPE . '_attribute';

	const PRODUCT_TAG_TAXONOMY = self::PRODUCT_POST_TYPE . '_tag';

	const COUPON_POST_TYPE = STOREENGINE_PLUGIN_SLUG . '_coupon';

	// Post-type names are capped at 20 chars; keep this short (storeengine_faqs).
	const FAQ_POST_TYPE = STOREENGINE_PLUGIN_SLUG . '_faqs';

	// Size chart library. Same 20-char cap as above, so '_sizes' rather than
	// '_size_charts' (which would be 23). The REST base is 'size-charts'.
	const SIZE_CHART_POST_TYPE = STOREENGINE_PLUGIN_SLUG . '_sizes';

	const DB_PREFIX = STOREENGINE_PLUGIN_SLUG . '_';

	/**
	 * Amount the payment gateway actually settled, in store currency.
	 */
	const META_GATEWAY_CAPTURED_AMOUNT = '_storeengine_gateway_captured_amount';

	/**
	 * Currency the gateway settled in.
	 */
	const META_GATEWAY_CAPTURED_CURRENCY = '_storeengine_gateway_captured_currency';

	/**
	 * Set to '1' when the captured amount diverges from the order total, so the
	 * invoice may under-report what the customer paid.
	 */
	const META_INVOICE_TAX_MISMATCH = '_storeengine_invoice_tax_mismatch';

	/**
	 * @var null|bool|int
	 */
	protected static $dashboard_index = null;

	public static function get_time() {
		return time() + ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS );
	}

	/**
	 * Record the amount the payment gateway actually settled and flag the order
	 * when it diverges from the StoreEngine order total.
	 *
	 * Invoices render on-demand from the stored order, so when the captured amount
	 * differs from get_total() (e.g. gateway-added tax) the invoice would
	 * under-report what the customer paid. The flag surfaces an admin action to
	 * reconcile the order before (re)sending the invoice.
	 *
	 * @param \StoreEngine\Classes\Order|mixed $order           Paid order.
	 * @param float                            $captured_amount Amount settled by the gateway, in store currency.
	 * @param string                           $currency        Currency the gateway settled in (defaults to order currency).
	 */
	public static function record_order_settlement( $order, float $captured_amount, string $currency = '' ): void {
		if ( ! $order || is_wp_error( $order ) ) {
			return;
		}

		if ( ! $currency ) {
			$currency = $order->get_currency();
		}

		$order->update_meta_data( self::META_GATEWAY_CAPTURED_AMOUNT, $captured_amount );
		$order->update_meta_data( self::META_GATEWAY_CAPTURED_CURRENCY, $currency );

		// Tolerance = one smallest currency unit, so sub-cent rounding never flags.
		$epsilon  = 1 / pow( 10, max( 0, Formatting::get_price_decimals() ) );
		$mismatch = abs( $captured_amount - (float) $order->get_total() ) >= $epsilon;

		if ( $mismatch ) {
			$order->update_meta_data( self::META_INVOICE_TAX_MISMATCH, '1' );
		} else {
			$order->delete_meta_data( self::META_INVOICE_TAX_MISMATCH );
		}

		$order->save();

		/**
		 * Fires after a gateway settlement is recorded against an order.
		 *
		 * @param \StoreEngine\Classes\Order|mixed $order
		 * @param float                            $captured_amount
		 * @param bool                             $mismatch Whether the captured amount diverged from the order total.
		 */
		do_action( 'storeengine/order/settlement_recorded', $order, $captured_amount, $mismatch );
	}

	public static function is_fse_theme() {
		if ( function_exists( 'wp_is_block_theme' ) ) {
			return wp_is_block_theme();
		}
		if ( function_exists( 'gutenberg_is_fse_theme' ) ) {
			return \gutenberg_is_fse_theme();
		}

		return false;
	}

	public static function remove_line_break( string $content ): string {
		$content = preg_replace( '/\s+/', ' ', $content );

		return trim( $content );
	}

	public static function remove_tag_space( string $content ): string {
		return preg_replace( '/>\s+</', '><', $content );
	}

	public static function add_string_quote( $value ) {
		if ( in_array( gettype( $value ), array( 'integer', 'double', 'float' ), true ) ) {
			return $value;
		} elseif ( 'boolean' === gettype( $value ) ) {
			return (int) $value;
		}

		return "'" . $value . "'";
	}

	public static function array_diff_recursive( $array1, $array2 ): array {
		$difference = [];

		foreach ( $array1 as $key => $value ) {
			if ( is_array( $value ) ) {
				if ( ! isset( $array2[ $key ] ) || ! is_array( $array2[ $key ] ) ) {
					$difference[ $key ] = $value;
				} else {
					$newDiff = self::array_diff_recursive( $value, $array2[ $key ] );
					if ( ! empty( $newDiff ) ) {
						$difference[ $key ] = $newDiff;
					}
				}
			} elseif ( ! array_key_exists( $key, $array2 ) || ( $array2[ $key ] !== $value ) ) {
				$difference[ $key ] = $value;
			}
		}

		return $difference;
	}

	public static function get_country_name( string $key ) {
		$countries = Countries::init()->get_countries();

		return $countries[ $key ] ?? '';
	}

	public static function cart(): ?\StoreEngine\Classes\Cart {
		// Prefer the fully-initialized cart held by the main plugin instance.
		// Fall back to the live singleton for the window during Cart::init()
		// where the cart is being calculated (auto-coupon validation runs here)
		// but StoreEngine->cart has not yet received init()'s return value.
		return StoreEngine::init()->get_cart() ?? \StoreEngine\Classes\Cart::get_instance();
	}

	public static function get_price_duration( $price, $duration, $duration_type ): string {
		if ( 1 === $duration ) {
			/* translators: 1. Price 2, duration */
			return sprintf( __( '%1$s Every %2$s', 'storeengine' ), Formatting::price( $price ), ucfirst( $duration_type ) );
		} else {
			return ( Formatting::price( $price ) . ' / ' . $duration . '-' . $duration_type . 's' );
		}
	}

	/**
	 * @deprecated
	 */
	public static function get_enabled_payment_methods(): array {
		$payments_settings       = self::get_payments_settings();
		$enabled_payment_methods = [];
		if ( is_array( $payments_settings ) ) {
			foreach ( $payments_settings as $payment_settings ) {
				if ( $payment_settings['is_enabled'] ) {
					$enabled_payment_methods[] = array(
						'type'         => $payment_settings['type'],
						'title'        => $payment_settings['title'] ?? $payment_settings['type'],
						'instructions' => $payment_settings['instructions'] ?? null,
					);
				}
			}
		}

		return $enabled_payment_methods;
	}

	/**
	 * @deprecated
	 */
	public static function get_payments_settings( $payment_method = '', $default = null ) {
		$payments_settings = \StoreEngine\Admin\Settings\Payments::get_settings_saved_data();

		if ( is_array( $payments_settings ) ) {
			if ( ! $payment_method ) {
				return $payments_settings;
			}

			foreach ( $payments_settings as $payment_settings ) {
				if ( $payment_settings['type'] === $payment_method ) {
					return $payment_settings;
				}
			}

			return [];
		}

		return $default;
	}

	/**
	 * @param string $page
	 * @param ?string $fallback
	 *
	 * @return string
	 */
	public static function get_page_permalink( string $page, string $fallback = null ): string {
		$page_id   = self::get_settings( $page );
		$permalink = 0 < $page_id ? get_permalink( $page_id ) : '';

		if ( ! $permalink ) {
			$permalink = is_null( $fallback ) ? get_home_url() : $fallback;
		}

		$permalink = apply_filters( "storeengine/get_{$page}_permalink", $permalink, $page_id, $fallback );

		return apply_filters( 'storeengine/get_page_permalink', $permalink, $page, $page_id, $fallback );
	}

	public static function get_dashboard_url(): string {
		return self::get_page_permalink( 'dashboard_page' );
	}

	public static function get_preloader_html() {
		ob_start();
		?>
		<div class="storeengine-initial-preloader"><?php esc_html_e( 'Loading...', 'storeengine' ); ?></div>
		<?php
		return ob_get_clean();
	}


	/**
	 * Get endpoint URL.
	 *
	 * Gets the URL for an endpoint, which varies depending on permalink settings.
	 *
	 * @param string $endpoint
	 * @param string|int|float $value
	 * @param string|false $permalink
	 *
	 * @return string
	 */
	public static function get_endpoint_url( string $endpoint, $value = '', $permalink = '' ): string {
		global $wp_query;

		if ( ! $permalink ) {
			$permalink = get_permalink();
		}

		// Map endpoint to options.
		$query_vars    = $wp_query->query_vars;
		$orig_endpoint = $endpoint;
		$endpoint      = ! empty( $query_vars[ $endpoint ] ) ? $query_vars[ $endpoint ] : $endpoint;

		if ( get_option( 'permalink_structure' ) ) {
			if ( strstr( $permalink, '?' ) ) {
				$query_string = '?' . wp_parse_url( $permalink, PHP_URL_QUERY );
				$permalink    = current( explode( '?', $permalink ) );
			} else {
				$query_string = '';
			}

			// Cleanup trailing slash.
			$url = trailingslashit( untrailingslashit( $permalink ) );

			if ( $value ) {
				$url .= trailingslashit( untrailingslashit( $endpoint ) ) . user_trailingslashit( $value );
			} else {
				$url .= user_trailingslashit( $endpoint );
			}

			$url .= $query_string;
		} elseif ( 'order-pay' === $orig_endpoint ) {
			// The registered query vars for this endpoint are `order_pay` +
			// `order_id` (see PermalinkRewrite::register_query_vars()), not a
			// single `order-pay=<id>` pair — plain permalinks need both set
			// explicitly or nothing on the page (is_valid_order_pay_page(),
			// is_available(), etc.) will ever recognise the order-pay context.
			$url = add_query_arg( [
				'order_pay' => 'true',
				'order_id'  => $value,
			], $permalink );
		} else {
			$url = add_query_arg( $endpoint, $value, $permalink );
		}

		$url = apply_filters( "storeengine/get_{$endpoint}_endpoint_url", $url, $value, $permalink );

		return apply_filters( 'storeengine/get_endpoint_url', $url, $endpoint, $value, $permalink );
	}

	/**
	 * @return ?string
	 */
	public static function get_current_dashboard_endpoint(): ?string {
		global $wp;

		return $wp->query_vars['storeengine_dashboard_page'] ?? null;
	}

	public static function get_logout_redirect_url(): string {
		return apply_filters( 'storeengine/logout_default_redirect_url', Helper::get_dashboard_url() );
	}

	public static function get_logout_url( string $redirect = '' ): string {
		$redirect   = $redirect ?: self::get_logout_redirect_url();
		$args       = [
			'redirect_to' => $redirect,
			'action'      => 'logout',
		];
		$logout_url = self::get_endpoint_url( 'customer-logout', '', self::get_dashboard_url() );
		$logout_url = wp_nonce_url( add_query_arg( $args, $logout_url ), 'customer-logout' );

		return apply_filters( 'storeengine/logout_url', $logout_url, $redirect );
	}

	/**
	 * Get account endpoint URL.
	 *
	 * @param string $endpoint Endpoint.
	 *
	 * @return string
	 */
	public static function get_account_endpoint_url( string $endpoint, $value = '' ): ?string {
		if ( 'dashboard' === $endpoint || 'myaccount' === $endpoint || 'index' === $endpoint ) {
			return self::get_dashboard_url();
		}

		if ( 'customer-logout' === $endpoint ) {
			return self::get_logout_url();
		}

		$url = self::get_endpoint_url( $endpoint, $value, self::get_dashboard_url() );

		$url = apply_filters( "storeengine/dashboard/get_{$endpoint}_endpoint_url", $url, $value );

		return apply_filters( 'storeengine/dashboard/get_endpoint_url', $url, $endpoint, $value );
	}

	public static function get_current_dashboard_endpoint_url( string $endpoint = null, $value = '' ): string {
		return self::get_account_endpoint_url( $endpoint ?? self::get_current_dashboard_endpoint() ?? '', $value );
	}

	/**
	 * Get the link to the edit account details page.
	 *
	 * @return string
	 */
	public static function customer_edit_account_url(): string {
		$edit_account_url = self::get_endpoint_url( 'edit-account', '', self::get_dashboard_url() );

		return apply_filters( 'storeengine/customer/edit_account_url', $edit_account_url );
	}

	/**
	 * add-filter to lostpassword_url
	 *
	 * @param $default_url
	 * @param $redirect
	 *
	 * @return mixed|string
	 */
	public static function get_lost_password_url( $default_url = '', $redirect = '' ) {
		// Avoid loading too early.
		if ( ! did_action( 'init' ) ) {
			return $default_url;
		}

		// Don't change the admin form.
		if ( did_action( 'login_form_login' ) ) {
			return $default_url;
		}

		// Don't redirect to the StoreEngine endpoint on global network admin lost passwords.
		if ( is_multisite() && isset( $_GET['redirect_to'] ) && false !== strpos( wp_unslash( $_GET['redirect_to'] ), network_admin_url() ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return $default_url;
		}

		$permalink = self::get_page_permalink( 'password_reset_page' );

		if ( ! $permalink ) {
			return $default_url;
		}

		if ( ! empty( $redirect ) ) {
			return add_query_arg( [ 'redirect_to' => rawurlencode( $redirect ) ], $permalink );
		}

		return $permalink;
	}

	public static function get_cart_url() {
		return apply_filters( 'storeengine/get_cart_url', self::maybe_force_ssl_protocol( self::get_page_permalink( 'cart_page' ) ) );
	}

	public static function get_checkout_url() {
		return apply_filters( 'storeengine/checkout/get_checkout_url', self::maybe_force_ssl_protocol( self::get_page_permalink( 'checkout_page' ) ) );
	}

	public static function get_thankyou_page_url() {
		return apply_filters( 'storeengine/checkout/thankyou_page_url', self::maybe_force_ssl_protocol(self::get_page_permalink( 'thankyou_page' )) );
	}

	public static function get_terms_page_url() {
		return apply_filters( 'storeengine/terms_page_url', self::maybe_force_ssl_protocol( self::get_page_permalink( 'terms_page' ) ) );
	}

	public static function get_privacy_page_url() {
		return apply_filters( 'storeengine/privacy_page_url', self::maybe_force_ssl_protocol(self::get_page_permalink( 'privacy_page' )) );
	}

	public static function get_shop_url() {
		return apply_filters( 'storeengine/get_shop_url', self::maybe_force_ssl_protocol( self::get_page_permalink( 'shop_page' ) ) );
	}

	public static function maybe_force_ssl_protocol( $url ) {
		if ( $url ) {
			// Force SSL if needed.
			if ( is_ssl() || self::get_settings( 'force_ssl_checkout' ) ) {
				$url = str_replace( 'http:', 'https:', $url );
			}
		}

		return $url;
	}

	public static function get_settings( $key, $default = null ) {
		global $storeengine_settings;

		$value = $storeengine_settings->{$key} ?? $default;
		$value = apply_filters( "storeengine/get_{$key}_settings", $value, $key );

		return apply_filters( 'storeengine/get_settings', $value, $key );
	}

	/**
	 * Resolve a per-user notification preference for the email-sending code.
	 *
	 * Stored as user_meta `_storeengine_notif_{key}`. Default is opt-in
	 * (returns true when the meta key has never been set), so existing
	 * customers keep receiving emails after this feature ships.
	 *
	 * Order-status emails are transactional (receipts, shipping updates) and
	 * always return true — the Notifications UI greys that toggle out, but
	 * defence-in-depth here in case anyone POSTs the form directly.
	 *
	 * @param int    $user_id WP user id.
	 * @param string $key     Notification key, e.g. `marketing`, `vendor_new_order`.
	 */
	public static function should_send_notification( int $user_id, string $key ): bool {
		if ( 'order_status' === $key ) {
			return true;
		}
		if ( ! $user_id ) {
			return true;
		}
		$meta = get_user_meta( $user_id, '_storeengine_notif_' . $key, true );
		// Default-on: empty string means the user has never toggled it.
		return '' === $meta || '1' === $meta;
	}

	public static function get_shop_address(): string {
		global $storeengine_settings;

		return Countries::init()->get_formatted_address( [
			'address_1' => $storeengine_settings->store_address_1,
			'address_2' => $storeengine_settings->store_address_2,
			'city'      => $storeengine_settings->store_city,
			'state'     => $storeengine_settings->store_state,
			'postcode'  => $storeengine_settings->store_postcode,
			'country'   => $storeengine_settings->store_country,
		] );
	}

	public static function get_addon_active_status( $addon_name, $is_pro = false ): bool {
		global $storeengine_addons;
		if ( $is_pro && ! self::is_active_storeengine_pro() ) {
			return false;
		}
		if ( isset( $storeengine_addons->{$addon_name} ) ) {
			return (bool) $storeengine_addons->{$addon_name};
		}

		return false;
	}

	public static function dif_from_human( $date ) {
		$now = time();
		if ( ! is_numeric( $date ) ) {
			$date = strtotime( $date );
		}
		$diff = $now - $date;
		if ( $diff < 60 ) {
			/* translators: %s is the number of seconds */
			return sprintf( __( '%s seconds ago', 'storeengine' ), $diff );
		}
		if ( $diff < 3600 ) {
			/* translators: %s is the number of minutes */
			return sprintf( __( '%s minutes ago', 'storeengine' ), round( $diff / 60 ) );
		}
		if ( $diff < 86400 ) {
			/* translators: %s is the number of hours */
			return sprintf( __( '%s hours ago', 'storeengine' ), round( $diff / 3600 ) );
		}
		if ( $diff < 604800 ) {
			/* translators: %s is the number of days */
			return sprintf( __( '%s days ago', 'storeengine' ), round( $diff / 86400 ) );
		}
		if ( $diff < 2419200 ) {
			/* translators: %s is the number of weeks */
			return sprintf( __( '%s weeks ago', 'storeengine' ), round( $diff / 604800 ) );
		}
		if ( $diff < 29030400 ) {
			/* translators: %s is the number of months */
			return sprintf( __( '%s months ago', 'storeengine' ), round( $diff / 2419200 ) );
		}

		/* translators: %s is the number of years */
		return sprintf( __( '%s years ago', 'storeengine' ), round( $diff / 29030400 ) );
	}

	public static function get_tax_rate( string $postcode ): ?float {
		$tax_rates = [
			'1000' => 5.5,
			'2000' => 6.3,
			'3000' => 7.1,
			'1206' => 7,
			'1207' => 7,
			'7300' => 7,
		];

		return $tax_rates[ $postcode ] ?? null;
	}

	/**
	 * Group definitions for the frontend dashboard sidebar.
	 *
	 * Each group renders a non-clickable header before its first visible
	 * member. Items set `'group' => '<key>'` to opt in; ungrouped items
	 * sort by their own priority and render without a header.
	 *
	 * Group priority controls inter-group ordering; item priority controls
	 * order within a group.
	 *
	 * @return array<string,array{label:string,priority:int}>
	 */
	public static function get_frontend_dashboard_menu_groups(): array {
		return apply_filters( 'storeengine/frontend_dashboard_menu_groups', [
			'orders'   => [ 'label' => __( 'My orders', 'storeengine' ), 'priority' => 30 ],
			'earnings' => [ 'label' => __( 'Earnings', 'storeengine' ),  'priority' => 60 ],
			'account'  => [ 'label' => __( 'Account', 'storeengine' ),   'priority' => 100 ],
		] );
	}

	/**
	 * Sort menu items by (group, item priority) and inject a separator entry
	 * before the first visible member of each labelled group. Headers are
	 * skipped for groups that have no visible items, and for items already
	 * acting as a manual separator (e.g. the vendor section break).
	 *
	 * This is invoked once by the sidebar renderer — other consumers
	 * (rewrite rules, request handler, REST exposure) keep working with
	 * the raw item list.
	 *
	 * @param array $items
	 * @return array
	 */
	public static function apply_frontend_dashboard_menu_groups( array $items ): array {
		$groups = self::get_frontend_dashboard_menu_groups();

		$tagged = [];
		foreach ( $items as $key => $item ) {
			$group         = $item['group'] ?? '';
			$item_priority = (int) ( $item['priority'] ?? 0 );
			// Ungrouped items use their own priority as the inter-group sort key
			// so they slot wherever their priority places them (Dashboard at
			// the top, Log out at the bottom, vendor separators inline).
			$effective = isset( $groups[ $group ] )
				? (int) $groups[ $group ]['priority']
				: $item_priority;

			$tagged[ $key ] = [
				'item'      => $item,
				'group'     => $group,
				'effective' => $effective,
				'priority'  => $item_priority,
			];
		}

		uasort( $tagged, static function ( $a, $b ) {
			if ( $a['effective'] !== $b['effective'] ) {
				return $a['effective'] <=> $b['effective'];
			}
			return $a['priority'] <=> $b['priority'];
		} );

		$output             = [];
		$last_emitted_group = null;
		foreach ( $tagged as $key => $row ) {
			$group = $row['group'];
			$item  = $row['item'];

			$is_visible = ! empty( $item['public'] ) && empty( $item['hide_from_nav'] );

			// Inject the header only when:
			//   - the item is visible,
			//   - the item isn't itself a manual separator,
			//   - the item belongs to a registered group with a non-empty label,
			//   - and we haven't already emitted that group's header.
			//
			// $last_emitted_group tracks the last header we emitted — NOT the
			// last item's group. Third-party items that omit `group` (or use
			// an unknown key) flow through unchanged: they appear in the
			// sidebar at their own priority, get no header, and don't reset
			// the tracker — so a labelled group split by such an item still
			// renders its header exactly once.
			if (
				$is_visible
				&& empty( $item['is_separator'] )
				&& isset( $groups[ $group ]['label'] )
				&& '' !== $groups[ $group ]['label']
				&& $group !== $last_emitted_group
			) {
				$output[ '__group_' . $group ] = [
					'label'        => $groups[ $group ]['label'],
					'public'       => true,
					'priority'     => $row['effective'] - 1,
					'is_separator' => true,
				];
				$last_emitted_group = $group;
			}

			$output[ $key ] = $item;
		}

		return $output;
	}

	/**
	 * @return array<array{
	 *     label: string,
	 *    icon: string,
	 *    public: bool,
	 *    priority: int|float,
	 *    group?: string,
	 *    children:array<{label: string, icon: string, public: bool, priority: int|float}>,
	 *  }>
	 */
	public static function get_frontend_dashboard_menu_items(): array {
		$items = [
			'index'                      => [
				'label'    => __( 'Dashboard', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--layout',
				'public'   => true,
				'priority' => - 1,
			],
			'orders'                     => [
				'label'    => __( 'Orders', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--box',
				'public'   => true,
				'priority' => 30,
				'group'    => 'orders',
			],
			'downloads'                  => [
				'label'    => __( 'Downloads', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--brand-style',
				'public'   => true,
				'priority' => 70,
				'group'    => 'orders',
			],
			'reviews'                    => [
				'label'    => __( 'Reviews', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--star-fill',
				'public'   => true,
				'priority' => 75,
				'group'    => 'orders',
			],
			'payment-methods'            => [
				'label'    => __( 'Payment methods', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--payment',
				'public'   => true,
				'priority' => 90,
				'group'    => 'account',
			],
			'add-payment-method'         => [
				'label'    => __( 'Add Payment method', 'storeengine' ),
				'public'   => false,
				'priority' => 91,
			],
			'delete-payment-method'      => [
				'label'    => __( 'Delete Payment method', 'storeengine' ),
				'public'   => false,
				'priority' => 92,
			],
			'set-default-payment-method' => [
				'label'    => __( 'Set Default Payment method', 'storeengine' ),
				'public'   => false,
				'priority' => 93,
			],
			'edit-address'               => [
				'label'    => __( 'Addresses', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--edit',
				'public'   => true,
				'priority' => 110,
				'group'    => 'account',
				'children' => [
					'billing'  => [
						'label'    => __( 'Edit Billing Address', 'storeengine' ),
						'public'   => false,
						'priority' => 10,
					],
					'shipping' => [
						'label'    => __( 'Edit Shipping Address', 'storeengine' ),
						'public'   => false,
						'priority' => 20,
					],
				],
			],
			'edit-account'               => [
				'label'    => __( 'Account', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--build',
				'public'   => true,
				'priority' => 130,
				'group'    => 'account',
				'children' => [
					// `account` is the default sub-tab when no sub_page is
					// requested — same content as the legacy edit-account page
					// (email, name, password). Notifications + Privacy are new.
					'account'       => [
						'label'    => __( 'Account', 'storeengine' ),
						'public'   => true,
						'priority' => 10,
					],
					'notifications' => [
						'label'    => __( 'Notifications', 'storeengine' ),
						'public'   => true,
						'priority' => 20,
					],
					'privacy'       => [
						'label'    => __( 'Privacy', 'storeengine' ),
						'public'   => true,
						'priority' => 30,
					],
				],
			],
			'customer-logout'            => [
				'label'    => __( 'Log out', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--logout',
				'public'   => true,
				'priority' => 999,
			],
			'forgot-password'            => [
				// public=true so the FrontendRequestHandler doesn't gate the
				// page on login (logged-out users need to reach it), and so
				// PermalinkRewrite generates the /dashboard/forgot-password/
				// rewrite rule automatically.
				//
				// hide_from_nav keeps it out of the sidebar — it's a
				// contextual entry point reached from the login form's "Lost
				// your password?" link and from the reset email, not a
				// destination customers navigate to.
				//
				// guest_accessible tells the [storeengine_dashboard] shortcode
				// to route to this endpoint's content for logged-out visitors
				// instead of falling back to the login form, which is what it
				// does for every other endpoint.
				'label'            => __( 'Reset password', 'storeengine' ),
				'public'           => true,
				'hide_from_nav'    => true,
				'guest_accessible' => true,
				'priority'         => 1000,
			],
			'register'                   => [
				// Same flag combination as forgot-password — public so the
				// rewrite rule generates, hidden from nav (entry point is the
				// login form's "Register" link), guest_accessible so the
				// dashboard shortcode renders the form for logged-out visitors
				// instead of redirecting them through the login flow.
				'label'            => __( 'Register', 'storeengine' ),
				'public'           => true,
				'hide_from_nav'    => true,
				'guest_accessible' => true,
				'priority'         => 1001,
			],
		];

		$support_payment_methods = false;
		foreach ( self::get_payment_gateways()->get_available_payment_gateways() as $gateway ) {
			if ( $gateway->supports( 'add_payment_method' ) || $gateway->supports( 'tokenization' ) ) {
				$support_payment_methods = true;
				break;
			}
		}

		if ( ! $support_payment_methods ) {
			unset( $items['payment-methods'] );
		}

		return apply_filters( 'storeengine/frontend_dashboard_menu_items', $items );
	}

	public static function get_frontend_dashboard_page_title( $path, $sub_path = '' ) {
		$menu = self::get_frontend_dashboard_menu_items();

		if ( empty( $menu[ $path ] ) ) {
			return '';
		}

		if ( $sub_path ) {
			if ( ! empty( $menu[ $path ]['children'][ $sub_path ] ) ) {
				return $menu[ $path ]['children'][ $sub_path ]['label'];
			}
		}

		return $menu[ $path ]['label'];
	}

	public static function round( $val, int $precision = 0, int $mode = PHP_ROUND_HALF_UP ): float {
		if ( ! is_numeric( $val ) ) {
			$val = floatval( $val );
		}

		return round( $val, $precision, $mode );
	}

	public static function meta_parser( $meta ) {
		return array_map( function ( $i ) {
			return $i[0];
		}, $meta );
	}

	public static function get_cart_hash(): ?string {
		$cart_hash = self::get_cart_hash_from_cookie();
		if ( $cart_hash ) {
			return $cart_hash;
		}

		return Cart::get_cart_hash_by_user_id( get_current_user_id() );
	}

	public static function get_cart_hash_from_cookie(): string {
		return isset( $_COOKIE['storeengine_cart_hash'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['storeengine_cart_hash'] ) ) : '';
	}

	/**
	 * Set cart has in cookie.
	 *
	 * @param string $cart_hash
	 *
	 * @return void
	 * @deprecated
	 */
	public static function set_cart_hash_in_cookie( string $cart_hash ): void {
		setcookie( 'storeengine_cart_hash', $cart_hash, [
			'expires'  => time() + YEAR_IN_SECONDS,
			'path'     => defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
			'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Strict',
		] );
	}

	/**
	 * Unset Cart hash cookie.
	 *
	 * @return void
	 * @deprecated
	 */
	public static function unset_cart_hash_in_cookie(): void {
		if ( headers_sent() ) {
			return;
		}

		// @TODO delete cache on hash changes.
		// wp_cache_delete 'order:draft:' . Helper::get_cart_hash_from_cookie(), 'storeengine_orders' ;
		header( 'Set-Cookie: storeengine_cart_hash=; Path=/; HttpOnly; Max-Age=-1', false );
		setcookie( 'storeengine_cart_hash', '', [
			'expires'  => - 1,
			'path'     => defined( 'COOKIEPATH' ) ? COOKIEPATH : '/',
			'domain'   => defined( 'COOKIE_DOMAIN' ) ? COOKIE_DOMAIN : '',
			'secure'   => is_ssl(),
			'httponly' => true,
			'samesite' => 'Strict',
		] );
	}

	public static function sanitize_referer_url( string $referer_url ): string {
		$parse_url = wp_parse_url( $referer_url );

		if ( isset( $parse_url['query'] ) ) {
			// Parse query parameters
			parse_str( $parse_url['query'], $query_params );
			if ( ! empty( $query_params['redirect_to'] ) ) {
				$referer_url = $query_params['redirect_to'];
			}
			if ( ! empty( $query_params['redirect_url'] ) ) {
				$referer_url = $query_params['redirect_url'];
			}
		}

		// Sanitize the input URL
		$referer_url = esc_url_raw( $referer_url );
		if ( filter_var( $referer_url, FILTER_VALIDATE_URL ) !== false && wp_http_validate_url( $referer_url ) && strpos( $referer_url, home_url() ) === 0 ) {
			return esc_url( $referer_url );
		} elseif ( ! empty( $parse_url['path'] ) ) {
			return esc_url( home_url( sanitize_text_field( $parse_url['path'] ) ) );
		}

		return esc_url( home_url( '/' ) );
	}

	public static function asort_by_locale( &$data, $locale = '' ) {
		// Use Collator if PHP Internationalization Functions (php-intl) is available.
		if ( class_exists( 'Collator' ) ) {
			try {
				$locale   = $locale ? $locale : get_locale();
				$collator = new \Collator( $locale );
				$collator->asort( $data, \Collator::SORT_STRING );

				return $data;
			} catch ( \Throwable $e ) {
				Helper::log_error(
					sprintf(
						'An unexpected error occurred while trying to use PHP Intl Collator class, it may be caused by an incorrect installation of PHP Intl and ICU, and could be fixed by reinstalling PHP Intl, see more details about PHP Intl installation: %1$s. Error message: %2$s',
						'https://www.php.net/manual/en/intl.installation.php',
						$e->getMessage()
					)
				);
			}
		}

		// Keep a reference to original data before removing accent marks
		// as strcmp works better without accent marks and add the value back
		// to the sorted array from this reference.
		$raw_data = $data;

		array_walk( $data, function ( &$value ) {
			$value = remove_accents( html_entity_decode( $value ) );
		} );

		uasort( $data, 'strcmp' );

		foreach ( $data as $key => $val ) {
			$data[ $key ] = $raw_data[ $key ];
		}

		return $data;
	}

	public static function get_all_roles(): array {
		global $wp_roles;

		if ( ! class_exists( '\WP_Roles' ) ) {
			return [];
		}

		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new \WP_Roles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		}

		$roles_array = [];

		foreach ( $wp_roles->roles as $role_id => $role ) {
			$roles_array[] = [
				'role_id'   => $role_id,
				'role_name' => $role['name'],
			];
		}

		return $roles_array;
	}

	public static function get_sample_permalink_args( $id, $new_title = null, $new_slug = null ) {
		$post = get_post( $id );
		if ( ! $post ) {
			return '';
		}

		list( $permalink, $post_name ) = get_sample_permalink( $post->ID, $new_title, $new_slug );
		$view_link                     = false;

		if ( current_user_can( 'read_post', $post->ID ) ) {
			if ( 'draft' === $post->post_status || empty( $post->post_name ) ) {
				$view_link = get_preview_post_link( $post );
			} elseif ( 'publish' === $post->post_status || 'storeengine_product' === $post->post_type ) {
				$view_link = get_permalink( $post );
			} else {
				$view_link = str_replace( [ '%pagename%', '%postname%' ], $post->post_name, $permalink );
			}
		}

		return [
			'view_link'         => $view_link ? esc_url( $view_link ) : null,
			'editable_postname' => $post_name,
			'display_link'      => rtrim( str_replace( '%pagename%', $post_name, $permalink ) ),
			'post_name'         => $post_name,
		];
	}

	/**
	 * Get Page by title.
	 *
	 * @param string $page_title
	 * @param string $post_type
	 *
	 * @return WP_Post|null
	 */
	public static function get_page_by_title( string $page_title, string $post_type = 'page' ): ?WP_Post {
		global $wpdb;

		$page = wp_cache_get( 'storeengine:get_page_by_title:' . sanitize_title( $page_title ), $post_type );

		if ( false === $page ) {
			$page = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->prepare(
					"SELECT ID FROM $wpdb->posts WHERE post_title = %s AND post_type = %s;",
					$page_title,
					$post_type
				)
			);

			wp_cache_set( 'storeengine:get_page_by_title:' . sanitize_title( $page_title ), $page, $post_type );
		}

		if ( $page ) {
			$page = get_post( $page, OBJECT );

			if ( ! $page ) {
				wp_cache_delete( 'storeengine:get_page_by_title:' . sanitize_title( $page_title ), $post_type );
			}

			return $page;
		}

		return null;
	}

	/**
	 * @return string
	 *
	 * @deprecated 1.5.6
	 * @see Geolocation::get_user_ip()
	 */
	public static function get_user_ip(): string {
		return Geolocation::get_user_ip();
	}

	/**
	 * Get user agent string.
	 *
	 * @return string
	 */
	public static function get_user_agent(): string {
		return isset( $_SERVER['HTTP_USER_AGENT'] ) ? Formatting::clean( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized inside clean method.
	}

	public static function is_bot(): bool {
		$ua = self::get_user_agent();

		// Common bots by user agent.
		return apply_filters(
			'storeengine/is_bot',
			stripos( $ua, 'bot' ) !== false || stripos( $ua, 'spider' ) !== false || stripos( $ua, 'crawl' ) !== false,
			$ua
		);
	}

	public static function get_email_template_name( string $template_name, ?string $template_sub_name = null ): string {
		if ( $template_sub_name ) {
			return str_replace( '_', '-', $template_name ) . '-' . $template_sub_name . '.php';
		}

		return str_replace( '_', '-', $template_name ) . '.php';
	}

	/**
	 * Schedule rewrite rule flushing on next reload.
	 *
	 * @return void
	 * @since 0.0.4
	 */
	public static function flush_rewire_rules() {
		update_option( 'storeengine_required_rewrite_flush', 'yes' );
	}

	/**
	 * Checks whether the content passed contains a specific short code.
	 *
	 * @param string $tag Shortcode tag to check.
	 *
	 * @return bool
	 */
	public static function post_content_has_shortcode( string $tag = '' ): bool {
		global $post;

		return is_singular() && is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, $tag );
	}

	public static function is_storeengine(): bool {
		return apply_filters( 'storeengine/is_storeengine', self::is_shop() || self::is_product_taxonomy() || self::is_product() );
	}

	public static function is_shop(): bool {
		return ( is_post_type_archive( self::PRODUCT_POST_TYPE ) || is_page( self::get_settings( 'shop_page' ) ) );
	}

	public static function is_product(): bool {
		return is_singular( [ self::PRODUCT_POST_TYPE ] );
	}

	public static function is_product_taxonomy(): bool {
		return is_tax( get_object_taxonomies( self::PRODUCT_POST_TYPE ) );
	}

	public static function is_product_category( $term = '' ): bool {
		return is_tax( self::PRODUCT_CATEGORY_TAXONOMY, $term );
	}

	public static function is_product_tag( $term = '' ): bool {
		return is_tax( self::PRODUCT_TAG_TAXONOMY, $term );
	}

	public static function is_cart(): bool {
		$page_id = self::get_settings( 'cart_page' );

		return ( $page_id && is_page( $page_id ) ) || defined( 'STOREENGINE_CART' ) || self::post_content_has_shortcode( 'storeengine_cart' );
	}

	public static function is_checkout(): bool {
		$page_id = self::get_settings( 'checkout_page' );

		return ( $page_id && is_page( $page_id ) ) || self::post_content_has_shortcode( 'storeengine_checkout' ) || apply_filters( 'storeengine_is_checkout', false ) || defined( 'STOREENGINE_CART' ) || defined( 'STOREENGINE_CHECKOUT' );
	}

	public static function is_thank_you(): bool {
		$page_id = self::get_settings( 'thankyou_page' );

		return ( $page_id && is_page( $page_id ) ) || apply_filters( 'storeengine_is_thankyou', false );
	}

	public static function is_dashboard(): bool {
		$page_id = self::get_settings( 'dashboard_page' );

		return ( $page_id && is_page( $page_id ) ) || self::post_content_has_shortcode( 'storeengine_dashboard' ) || apply_filters( 'storeengine_is_dashboard_page', false );
	}

	/**
	 * Check if current page is dashboard index or endpoint page.
	 *
	 * @return bool|int Returns zero (0) if called outside of dashboard/endpoint page. Returns (bool) true on
	 * 					dashboard index or (bool) false on endpoint page.
	 */
	public static function is_dashboard_index() {
		if ( null === self::$dashboard_index ) {
			self::$dashboard_index = self::is_dashboard() && null === self::get_current_dashboard_endpoint();
		}

		return self::$dashboard_index;
	}

	public static function is_account_page(): bool {
		return self::is_dashboard();
	}

	public static function get_all_product_category_lists() {
		$categories = get_terms(
			array(
				'taxonomy'   => 'storeengine_product_category',
				'hide_empty' => true,
			)
		);

		return self::prepare_category_results( $categories );
	}

	public static function prepare_category_results( $terms, $parent_id = 0 ) {
		$category = array();
		foreach ( $terms as $term ) {
			if ( $term->parent === $parent_id ) {
				$term->children = self::prepare_category_results( $terms, $term->term_id );
				$category[]     = $term;
			}
		}

		return $category;
	}

	public static function is_endpoint( $endpoint = null ): bool {
		global $wp_query;

		if ( empty( $wp_query->query['storeengine_dashboard_page'] ) ) {
			return false;
		}

		if ( $endpoint ) {
			return $wp_query->query['storeengine_dashboard_page'] === $endpoint;
		}

		return true;
	}

	public static function is_add_payment_method_page(): bool {
		return self::is_dashboard() && self::is_endpoint( 'add-payment-method' );
	}

	public static function is_payment_method_list_page(): bool {
		return self::is_dashboard() && self::is_endpoint( 'payment-methods' );
	}

	public static function is_edit_address_page(): bool {
		return self::is_dashboard() && ( self::is_endpoint( 'edit-address' ) && ! empty( get_query_var( 'storeengine_dashboard_sub_page' ) ) );
	}

	/**
	 * Rest api permission check.
	 *
	 * @param string $capability
	 * @param string|null $response
	 *
	 * @return WP_Error|bool
	 */
	public static function check_rest_user_cap( string $capability, ?string $response = null ) {
		$permission = true;
		if ( ! is_user_logged_in() || ! current_user_can( $capability ) ) {
			$permission = new WP_Error(
				'storeengine_rest_forbidden_context',
				$response ? esc_html( $response ) : esc_html__( 'Sorry, insufficient permission.', 'storeengine' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return apply_filters( 'storeengine/rest_user_capability', $permission, $capability );
	}

	public static function prepare_product_search_query_args( $data ) {
		$defaults = array(
			'search'         => '',
			'category'       => [],
			'tags'           => [],
			'paged'          => 1,
			'posts_per_page' => 12,
		);
		$data     = wp_parse_args( $data, $defaults );

		// base
		$args = array(
			//'post_type'      => apply_filters( 'storeengine/get_product_archive_post_types', array( 'storeengine_product' ) ),
			'post_type'      => 'storeengine_product', // Archive query compatibility. if WP_Query post-type is array then it won't marked as archive query (required for ajax filter on archive page).
			'post_status'    => 'publish',
			'posts_per_page' => $data['posts_per_page'],
			'paged'          => $data['paged'],
		);

		// taxonomy
		$tax_query = array();
		if ( count( $data['category'] ) > 0 ) {
			$tax_query[] = array(
				'taxonomy' => 'storeengine_product_category',
				'field'    => 'slug',
				'terms'    => $data['category'],
			);
		}
		if ( count( $data['tags'] ) > 0 ) {
			$tax_query[] = array(
				'taxonomy' => 'storeengine_product_tag',
				'field'    => 'slug',
				'terms'    => $data['tags'],
			);
		}
		if ( count( $tax_query ) > 0 ) {
			$tax_query['relation'] = 'AND';
			$args['tax_query']     = $tax_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
		}

		// search
		if ( ! empty( $data['search'] ) ) {
			$args['s'] = $data['search'];
		}

		// order by
		if ( isset( $data['orderby'] ) ) {
			switch ( $data['orderby'] ) {
				case 'name':
				case 'title':
					$args['orderby'] = 'post_title';
					$args['order']   = 'asc';
					break;
				case 'date':
					$args['orderby'] = 'publish_date';
					$args['order']   = 'desc';
					break;
				case 'modified':
					$args['orderby'] = 'modified';
					$args['order']   = 'desc';
					break;
				case 'menu_order':
					$args['orderby'] = 'menu_order';
					$args['order']   = 'desc';
					break;
				default:
					$args['orderby'] = 'ID';
					$args['order']   = 'desc';
			}//end switch
		}//end if
		return apply_filters( 'storeengine/get_product_archive_search_query_args', $args, $data );
	}

	public static function get_responsive_column( $columns ): string {
		if ( is_array( $columns ) ) {
			$device  = [
				'desktop' => 'lg',
				'tablet'  => 'md',
				'mobile'  => 'sm',
			];
			$classes = '';
			foreach ( $columns as $mode => $column ) {
				if ( $column ) {
					$classes .= ' storeengine-col-' . $device[ $mode ] . '-' . ceil( 12 / $column );
				}
			}

			return ltrim( $classes );
		}

		return '';
	}

	public static function get_permalink_structure() {
		$saved_permalinks = (array) get_option( 'storeengine_permalinks', array() );
		$permalinks       = wp_parse_args(
			array_filter( $saved_permalinks ),
			array(
				'product_base'           => _x( 'product', 'slug', 'storeengine' ),
				'category_base'          => _x( 'product-category', 'slug', 'storeengine' ),
				'tag_base'               => _x( 'product-tag', 'slug', 'storeengine' ),
				'use_verbose_page_rules' => false,
			)
		);

		if ( $saved_permalinks !== $permalinks ) {
			update_option( 'storeengine_permalinks', $permalinks );
		}

		$permalinks['product_rewrite_slug']  = untrailingslashit( $permalinks['product_base'] );
		$permalinks['category_rewrite_slug'] = untrailingslashit( $permalinks['category_base'] );
		$permalinks['tag_rewrite_slug']      = untrailingslashit( $permalinks['tag_base'] );

		return $permalinks;
	}

	/**
	 * Switch plugin to site language.
	 *
	 * @return void
	 */
	public static function switch_to_site_locale() {
		self::switch_to_locale( get_locale() );
	}

	/**
	 * Switch plugin to site language.
	 *
	 * @return void
	 */
	public static function switch_to_locale( string $locale = 'en_US' ) {
		global $wp_locale_switcher;

		if ( function_exists( 'switch_to_locale' ) && isset( $wp_locale_switcher ) ) {
			switch_to_locale( $locale );

			// Filter on plugin_locale so load_plugin_textdomain loads the correct locale.
			add_filter( 'plugin_locale', 'get_locale' );
		}
	}

	/**
	 * Switch plugin language to original.
	 *
	 * @return void
	 */
	public static function restore_locale() {
		global $wp_locale_switcher;

		if ( function_exists( 'restore_previous_locale' ) && isset( $wp_locale_switcher ) ) {
			restore_previous_locale();

			// Remove filter.
			remove_filter( 'plugin_locale', 'get_locale' );
		}
	}

	/**
	 * Simple check for validating a URL, it must start with http:// or https://.
	 * and pass FILTER_VALIDATE_URL validation.
	 *
	 * @param string $url to check.
	 *
	 * @return bool
	 */
	public static function is_valid_url( string $url ): bool {

		// Must start with http:// or https://.
		/** @noinspection HttpUrlsUsage */
		if ( 0 !== strpos( $url, 'http://' ) && 0 !== strpos( $url, 'https://' ) ) {
			return false;
		}

		// Must pass validation.
		if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Alias for is_valid_url()
	 *
	 * @param string $url
	 *
	 * @return bool
	 * @see is_valid_url()
	 */
	public static function is_url( string $url ): bool {
		return self::is_valid_url( $url );
	}

	public static function is_valid_site_url( string $url ): bool {
		return str_starts_with( $url, get_option( 'siteurl' ) );
	}

	public static function get_reveiw_survey_form_radios( $slug ) {
		$html = '';
		for ( $counter = 1; $counter <= 5; $counter ++ ) {
			$html .= sprintf(
				"<td><input type='radio' name='%s-rating' value='%s' class='storeengine-radio' /></td>",
				$slug,
				$counter
			);
		}

		return $html;
	}

	public static function get_date_format() {
		$date_format = get_option( 'date_format' );
		if ( empty( $date_format ) ) {
			// Return default date format if the option is empty.
			$date_format = 'F j, Y';
		}

		return apply_filters( 'storeengine/date_format', $date_format );
	}

	public static function single_star_rating_generator( $current_rating = 0.00 ) {
		$output = '<span class="storeengine-group-star">';
		if ( 5 < $current_rating && 0 > $current_rating ) {
			$output .= '<i class="storeengine-icon storeengine-icon--star-fill"></i>';
		} elseif ( 0 === $current_rating ) {
			$output .= '<i class="storeengine-icon storeengine-icon--star-fill"></i>';
		} else {
			$output .= '<i class="storeengine-icon storeengine-icon--star-fill"></i>';
		}
		$output .= '</span>';

		return $output;
	}

	public static function star_rating_generator( $current_rating = 0.00 ) {
		$output = '<span class="storeengine-group-star">';

		for ( $i = 1; $i <= 5; $i ++ ) {
			$intRating = (int) $current_rating;

			if ( $intRating >= $i ) {
				$output .= '<i class="storeengine-icon storeengine-icon--star-fill" data-rating-value="' . $i . '"></i>';
			} else {
				if ( ( $current_rating - $i ) === - 0.5 ) {
					$output .= '<i class="storeengine-icon storeengine-icon--star-half" data-rating-value="' . $i . '"></i>';
				} else {
					$output .= '<i class="storeengine-icon storeengine-icon--star-line" data-rating-value="' . $i . '"></i>';
				}
			}
		}

		$output .= '</span>';

		return $output;
	}

	/**
	 * @param $product_id
	 * @param $user_id
	 *
	 * @return string|null
	 *
	 * Mirrors the standard "has the customer bought this product" check.
	 */
	public static function is_purchase_the_product( $product_id, $user_id = 0 ): ?string {
		global $wpdb;

		if ( ! $user_id ) {
			$user_id = get_current_user_id();
		}

		// @TODO cache user's purchase story for 30 days.
		// See the standard "has the customer bought this product" check for details.

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_var(
			$wpdb->prepare(
				"SELECT o.id
            FROM {$wpdb->prefix}storeengine_orders o
            JOIN {$wpdb->prefix}storeengine_order_product_lookup op ON o.id = op.order_id
            WHERE o.customer_id = %d
            AND op.product_id = %d
            AND o.status = 'completed'
            LIMIT 1;",
				$user_id,
				$product_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public static function is_purchase_the_membership( $product_id, $price_id, $customer_id = 0, $order_status = Constants::ORDER_STATUS_COMPLETED ) {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		global $wpdb;

		if ( ! $customer_id ) {
			$customer_id = get_current_user_id();
		}

		$user_meta = get_user_meta( $customer_id, '_storeengine_memberships', true );

		if ( is_array( $user_meta ) ) {
			$result = false;
			foreach ( $user_meta as $u_meta ) {
				if ( $price_id === $u_meta['price_id'] && Constants::ORDER_STATUS_COMPLETED === $u_meta['order_status'] ) {
					$result = true;
					break;
				}
			}

			return $result;
		}

		$user_meta = [];
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- query result cached in user meta
		$count = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_order_product_lookup op
			    JOIN {$wpdb->prefix}storeengine_orders o ON op.order_id = o.id
			    WHERE op.product_id = %d
			    AND op.price_id = %d
			    AND o.customer_id = %d
			    AND o.status = %s",
			$product_id,
			$price_id,
			$customer_id,
			$order_status
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- query result cached in user meta

		if ( $count ) {
			$user_meta[] = compact( 'customer_id', 'price_id', 'order_status' );
		}
		update_user_meta( $customer_id, '_storeengine_memberships', $user_meta );

		return $count;
	}

	/**
	 * Used to sort shipping zone methods with uasort.
	 *
	 * @param ShippingMethod|\stdClass $a First shipping zone method to compare.
	 * @param ShippingMethod|\stdClass $b Second shipping zone method to compare.
	 *
	 * @return int
	 */
	public static function shipping_zone_method_order_uasort_comparison( $a, $b ): int {
		return self::uasort_comparison( $a->method_order, $b->method_order );
	}

	/**
	 * User to sort checkout fields based on priority with uasort.
	 *
	 * @param array $a First field to compare.
	 * @param array $b Second field to compare.
	 *
	 * @return int
	 */
	public static function checkout_fields_uasort_comparison( array $a, array $b ): int {
		/*
		 * We are not guaranteed to get a priority
		 * setting. So don't compare if they don't
		 * exist.
		 */
		if ( ! isset( $a['priority'], $b['priority'] ) ) {
			return 0;
		}

		return self::uasort_comparison( $a['priority'], $b['priority'] );
	}

	/**
	 * User to sort two values with uasort.
	 *
	 * @param int $a First value to compare.
	 * @param int $b Second value to compare.
	 *
	 * @return int
	 */
	public static function uasort_comparison( int $a, int $b ): int {
		if ( $a === $b ) {
			return 0;
		}

		return ( $a < $b ) ? - 1 : 1;
	}

	/**
	 * Merge two arrays.
	 *
	 * @param array $a1 First array to merge.
	 * @param array $a2 Second array to merge.
	 *
	 * @return array
	 */
	public static function array_overlay( array $a1, array $a2 ): array {
		foreach ( $a1 as $k => $v ) {
			if ( ! array_key_exists( $k, $a2 ) ) {
				continue;
			}
			if ( is_array( $v ) && is_array( $a2[ $k ] ) ) {
				$a1[ $k ] = self::array_overlay( $v, $a2[ $k ] );
			} else {
				$a1[ $k ] = $a2[ $k ];
			}
		}

		return $a1;
	}

	/**
	 * Set a cookie - wrapper for setcookie using WP constants.
	 *
	 * @param string $name Name of the cookie being set.
	 * @param string|int|float $value Value of the cookie.
	 * @param integer $expire Expiry of the cookie.
	 * @param bool $secure Whether the cookie should be served only over https.
	 * @param bool $httponly Whether the cookie is only accessible over HTTP, not scripting languages like JavaScript.
	 */
	public static function setcookie( string $name, $value, int $expire = 0, bool $secure = false, bool $httponly = false ): void {
		/**
		 * Controls whether the cookie should be set.
		 *
		 * @param bool $set_cookie_enabled If the cookie should be set.
		 * @param string $name Cookie name.
		 * @param string $value Cookie value.
		 * @param integer $expire When the cookie should expire.
		 * @param bool $secure If the cookie should only be served over HTTPS.
		 */
		if ( ! apply_filters( 'storeengine/set_cookie_enabled', true, $name, $value, $expire, $secure ) ) {
			return;
		}

		if ( ! headers_sent() ) {
			/**
			 * Controls the options to be specified when setting the cookie.
			 *
			 * @see   https://www.php.net/manual/en/function.setcookie.php
			 *
			 * @param array $cookie_options Cookie options.
			 * @param string $name Cookie name.
			 * @param string $value Cookie value.
			 */
			$options = apply_filters(
				'storeengine/set_cookie_options',
				[
					'expires'  => $expire,
					'secure'   => $secure,
					'path'     => COOKIEPATH ? COOKIEPATH : '/',
					'domain'   => COOKIE_DOMAIN,
					/**
					 * Controls whether the cookie should only be accessible via the HTTP protocol, or if it should also be
					 * accessible to Javascript.
					 *
					 * @see   https://www.php.net/manual/en/function.setcookie.php
					 *
					 * @param bool $httponly If the cookie should only be accessible via the HTTP protocol.
					 * @param string $name Cookie name.
					 * @param string $value Cookie value.
					 * @param int $expire When the cookie should expire.
					 * @param bool $secure If the cookie should only be served over HTTPS.
					 */
					'httponly' => apply_filters( 'storeengine/cookie_httponly', $httponly, $name, $value, $expire, $secure ),
				],
				$name,
				$value
			);

			setcookie( $name, $value, $options );
		} elseif ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			headers_sent( $file, $line );
			trigger_error( esc_html( "{$name} cookie cannot be set - headers already sent by {$file} on line {$line}" ), E_USER_NOTICE ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_trigger_error, WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * What type of request is this?
	 *
	 * @param string $type admin, ajax, cron or frontend.
	 *
	 * @return bool
	 */
	public static function is_request( string $type ): bool {
		switch ( $type ) {
			case 'ref-admin':
				return self::is_admin_request();
			case 'ref-frontend':
				return ! self::is_admin_request();
			case 'admin':
				return is_admin();
			case 'ajax':
				// self::is_request( 'admin' ) is always true here.
				// should be paired with ref-admin check to diff between admin/frontend ajax.
				return defined( 'DOING_AJAX' );
			case 'cron':
				return defined( 'DOING_CRON' );
			case 'frontend':
				return ( ! is_admin() || defined( 'DOING_AJAX' ) ) && ! defined( 'DOING_CRON' ) && ! self::is_rest_api_request();
			case 'rest':
			case 'restapi':
				return self::is_rest_api_request();
			default:
				return false;
		}
	}

	public static function is_admin_request(): bool {
		if( defined( 'STOREENGINE_DOING_ADMIN_REFERER_REQUEST' ) ) {
			return STOREENGINE_DOING_ADMIN_REFERER_REQUEST;
		}
		if ( ! function_exists( 'wp_validate_redirect' ) ) {
			require_once ABSPATH . WPINC . '/pluggable.php';
		}

		$is_ref_admin = str_starts_with( strtolower( wp_get_referer() ), strtolower( admin_url() ) );

		define( 'STOREENGINE_DOING_ADMIN_REFERER_REQUEST', $is_ref_admin );

		return $is_ref_admin;
	}

	/**
	 * Returns true if the request is a non-legacy REST API request.
	 *
	 * Legacy REST requests should still run some extra code for backwards compatibility.
	 *
	 * @todo: replace this function once core WP function is available: https://core.trac.wordpress.org/ticket/42061.
	 *
	 * @return bool
	 */
	public static function is_rest_api_request(): bool {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}

		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$rest_prefix         = trailingslashit( rest_get_url_prefix() );
		$is_rest_api_request = ( false !== strpos( $_SERVER['REQUEST_URI'], $rest_prefix ) ); // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		/**
		 * Whether this is a REST API request.
		 */
		return apply_filters( 'storeengine/is_rest_api_request', $is_rest_api_request );
	}

	/**
	 * Wrapper for set_time_limit to see if it is enabled.
	 *
	 * @param int $limit Time limit.
	 */
	public static function set_time_limit( int $limit = 0 ) {
		if ( function_exists( 'set_time_limit' ) && false === strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) { // phpcs:ignore PHPCompatibility.IniDirectives.RemovedIniDirectives.safe_modeDeprecatedRemoved
			@set_time_limit( $limit ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged, WordPress.PHP.NoSilencedErrors.Discouraged -- server may choose to disable this function.
		}
	}

	public static function get_product_term_ids( $product_id, $taxonomy ) {
		$terms = get_the_terms( $product_id, $taxonomy );

		return ( empty( $terms ) || is_wp_error( $terms ) ) ? array() : wp_list_pluck( $terms, 'term_id' );
	}

	/**
	 * Get all product cats for a product by ID, including hierarchy
	 *
	 * @param int $product_id Product ID.
	 *
	 * @return array
	 */
	public static function get_product_cat_ids( int $product_id ): array {
		$product_cats = self::get_product_term_ids( $product_id, self::PRODUCT_CATEGORY_TAXONOMY );

		foreach ( $product_cats as $product_cat ) {
			$product_cats = array_merge( $product_cats, get_ancestors( $product_cat, self::PRODUCT_CATEGORY_TAXONOMY, 'taxonomy' ) );
		}

		return $product_cats;
	}

	public static function get_coupon_types(): array {
		return (array) apply_filters( 'storeengine/product_coupon_types', [ 'percentage', 'fixedAmount' ] );
	}

	/**
	 * Return a list of potential postcodes for wildcard searching.
	 *
	 * @param string $postcode Postcode.
	 * @param string $country Country to format postcode for matching.
	 *
	 * @return string[]
	 */
	public static function get_wildcard_postcodes( $postcode, $country = '' ) {
		$formatted_postcode = Formatting::format_postcode( $postcode, $country );
		$length             = function_exists( 'mb_strlen' ) ? mb_strlen( $formatted_postcode ) : strlen( $formatted_postcode );
		$postcodes          = [
			$postcode,
			$formatted_postcode,
			$formatted_postcode . '*',
		];

		for ( $i = 0; $i < $length; $i ++ ) {
			$postcodes[] = ( function_exists( 'mb_substr' ) ? mb_substr( $formatted_postcode, 0, ( $i + 1 ) * - 1 ) : substr( $formatted_postcode, 0, ( $i + 1 ) * - 1 ) ) . '*';
		}

		return $postcodes;
	}

	/**
	 * Used by shipping zones and taxes to compare a given $postcode to stored
	 * postcodes to find matches for numerical ranges, and wildcards.
	 *
	 * @param string $postcode Postcode you want to match against stored postcodes.
	 * @param array $objects Array of postcode objects from Database.
	 * @param string $object_id_key DB column name for the ID.
	 * @param string $object_compare_key DB column name for the value.
	 * @param string $country Country from which this postcode belongs. Allows for formatting.
	 *
	 * @return array Array of matching object ID and matching values.
	 */
	public static function postcode_location_matcher( $postcode, $objects, $object_id_key, $object_compare_key, $country = '' ) {
		$postcode           = Formatting::normalize_postcode( $postcode );
		$wildcard_postcodes = array_map( [
			Formatting::class,
			'clean',
		], self::get_wildcard_postcodes( $postcode, $country ) );
		$matches            = [];

		foreach ( $objects as $object ) {
			$object_id       = $object->$object_id_key;
			$compare_against = $object->$object_compare_key;

			// Handle postcodes containing ranges.
			if ( strstr( $compare_against, '...' ) ) {
				$range = array_map( 'trim', explode( '...', $compare_against ) );

				if ( 2 !== count( $range ) ) {
					continue;
				}

				list( $min, $max ) = $range;

				// If the postcode is non-numeric, make it numeric.
				if ( ! is_numeric( $min ) || ! is_numeric( $max ) ) {
					$compare = Formatting::make_numeric_postcode( $postcode );
					$min     = str_pad( Formatting::make_numeric_postcode( $min ), strlen( $compare ), '0' );
					$max     = str_pad( Formatting::make_numeric_postcode( $max ), strlen( $compare ), '0' );
				} else {
					$compare = $postcode;
				}

				if ( $compare >= $min && $compare <= $max ) {
					$matches[ $object_id ]   = $matches[ $object_id ] ?? [];
					$matches[ $object_id ][] = $compare_against;
				}
			} elseif ( in_array( $compare_against, $wildcard_postcodes, true ) ) {
				// Wildcard and standard comparison.
				$matches[ $object_id ]   = $matches[ $object_id ] ?? [];
				$matches[ $object_id ][] = $compare_against;
			}
		}

		return $matches;
	}

	/**
	 * Based on wp_list_pluck, this calls a method instead of returning a property.
	 *
	 * @param array $list List of objects or arrays.
	 * @param int|string $callback_or_field Callback method from the object to place instead of the entire object.
	 * @param int|string $index_key Optional. Field from the object to use as keys for the new array.
	 *                                      Default null.
	 *
	 * @return array Array of values.
	 */
	public static function list_pluck( array $list, $callback_or_field, $index_key = null ): array {
		// Use wp_list_pluck if this isn't a callback.
		$first_el = current( $list );
		if ( ! is_object( $first_el ) || ! is_callable( [ $first_el, $callback_or_field ] ) ) {
			return wp_list_pluck( $list, $callback_or_field, $index_key );
		}
		if ( ! $index_key ) {
			/*
			 * This is simple. Could at some point wrap array_column()
			 * if we knew we had an array of arrays.
			 */
			foreach ( $list as $key => $value ) {
				$list[ $key ] = $value->{$callback_or_field}();
			}

			return $list;
		}

		/*
		 * When index_key is not set for a particular item, push the value
		 * to the end of the stack. This is how array_column() behaves.
		 */
		$newlist = [];
		foreach ( $list as $value ) {
			// Get index. @since 3.2.0 this supports a callback.
			if ( is_callable( array( $value, $index_key ) ) ) {
				$newlist[ $value->{$index_key}() ] = $value->{$callback_or_field}();
			} elseif ( isset( $value->$index_key ) ) {
				$newlist[ $value->$index_key ] = $value->{$callback_or_field}();
			} else {
				$newlist[] = $value->{$callback_or_field}();
			}
		}

		return $newlist;
	}

	/**
	 * Get an item of post data if set, otherwise return a default value.
	 *
	 * @param string $key Meta key.
	 * @param mixed $default Default value.
	 *
	 * @return mixed Value sanitized by Formatting::clean.
	 */
	public static function get_post_data_by_key( string $key, $default = '' ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing
		return Formatting::clean( wp_unslash( self::get_var( $_POST[ $key ], $default ) ) );
	}

	/**
	 * Get data if set, otherwise return a default value or null. Prevents notices when data is not set.
	 *
	 * @param mixed $var Variable.
	 * @param mixed $default Default value.
	 *
	 * @return mixed
	 */
	public static function get_var( &$var, $default = null ) {
		return isset( $var ) ? $var : $default;
	}

	public static function is_storeengine_page( int $post_id = 0 ): bool {
		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		return in_array( (int) $post_id, self::get_storeengine_page_ids(), true );
	}

	public static function get_storeengine_page_ids(): array {
		$settings_keys = [
			'checkout_page',
			'shop_page',
			'store_shop',
			'cart_page',
			'thankyou_page',
			'dashboard_page',
			'membership_pricing_page',
			'affiliate_registration_page',
		];

		$page_ids = array_map( fn( $key ) => (int) self::get_settings( $key ), $settings_keys );

		return array_filter( $page_ids );
	}

	/**
	 * Is registration required to checkout?
	 *
	 * @return boolean
	 */
	public static function is_registration_required(): bool {
		/**
		 * Controls if registration is required in order for checkout to be completed.
		 *
		 * @param bool $checkout_registration_required If customers must be registered to checkout.
		 */
		return apply_filters( 'storeengine/checkout/registration_required', ! self::get_settings( 'enable_guest_checkout', true ) );
	}

	/**
	 * Define a constant if it is not already defined.
	 *
	 * @param string $name Constant name.
	 * @param mixed $value Value.
	 */
	public static function maybe_define_constant( string $name, $value ) {
		if ( ! defined( $name ) ) {
			define( $name, $value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.VariableConstantNameFound
		}
	}

	public static function get_assets_url( string $path = '' ): string {
		return STOREENGINE_ASSETS_URI . ltrim( $path, '/\\' );
	}

	public static function get_plugin_url( string $path = '' ): string {
		return STOREENGINE_PLUGIN_ROOT_URI . ltrim( $path, '/\\' );
	}

	public static function get_addons_url( string $addon, string $path = '' ): string {
		return self::get_plugin_url( 'addons/' . ltrim( rtrim( $addon, '/\\' ), '/\\' ) . '/' . ltrim( $path, '/\\' ) );
	}

	public static function get_upload_dir(): string {
		$upload = wp_upload_dir();

		return $upload['basedir'] . '/storeengine_uploads';
	}

	/**
	 * @param array $arr
	 * @param callable $predicate
	 *
	 * @return bool
	 * @deprecated 1.8.0
	 * @see ArrayUtil::every()
	 */
	public static function array_every( array $arr, callable $predicate ): bool {
		return ArrayUtil::every( $arr, $predicate );
	}

	/**
	 * @param array $arr
	 * @param callable $predicate
	 *
	 * @return bool
	 * @deprecated 1.8.0
	 * @see ArrayUtil::any()
	 */
	public static function array_any( array $arr, callable $predicate ): bool {
		return ArrayUtil::any( $arr, $predicate );
	}

	public static function masked_key_preview( string $key, string $name = null, $args = [] ) {
		$args = wp_parse_args( $args, [
			'start'  => 8,
			'end'    => 2,
			'mask'   => '•',
			'size'   => 5,
			'unmask' => true,
			'copy'   => true,
		] );

		if ( empty( $name ) ) {
			$name = __( 'Key', 'storeengine' );
		}

		$end     = absint( $args['end'] );
		$masked  = substr( $key, 0, absint( $args['start'] ) );
		$masked .= str_repeat( $args['mask'], absint( $args['size'] ) );
		if ( $end ) {
			$masked .= substr( $key, - 1 * $end );
		}
		?>
		<div class="masked-key-preview storeengine-flex storeengine-flex-align-center">
			<?php if ( $args['unmask'] ) { ?>
				<button
					class="toggle-key-mask storeengine-btn storeengine-btn--md storeengine-btn--preset-transparent"
					style="--icon-size:1.1em;padding:10px" type="button"
					data-key-name="<?php echo esc_attr( $name ); ?>"
					aria-label="<?php printf(
					// translators: %s Masked key name.
						esc_attr__( 'Show %s', 'storeengine' ),
						esc_attr( $name ),
					); ?>">
					<span class="storeengine-icon storeengine-icon--eye-alt" aria-hidden="true"></span>
				</button>
			<?php } ?>
			<span class="preview-masked"
				  style="font-size:0.84em;font-weight:600;border: 1px solid transparent;outline:none;padding:5px;border-radius:4px;"><?php echo esc_html( $masked ); ?></span>
			<?php if ( $args['unmask'] ) { ?>
				<input class="preview-unmasked" type="text" value="<?php echo esc_attr( $key ); ?>"
					   onclick="select(this)" readonly aria-label="<?php echo esc_attr( $name ); ?>"
					   style="font-size:0.84em;font-weight:600;border: 1px solid var(--storeengine-border-color);outline:none;padding:5px;border-radius:4px;display:none;"/>
			<?php } ?>
			<?php if ( $args['copy'] ) { ?>
				<button
					class="copy-to-clipboard storeengine-btn storeengine-btn--md storeengine-btn--preset-transparent"
					style="--icon-size:1.1em;padding:10px" type="button"
					data-content="<?php echo esc_attr( $key ); ?>"
					data-content-name="<?php echo esc_attr( $name ); ?>"
					aria-label="<?php printf(
					// translators: %s Masked key name.
						esc_attr__( 'Copy %s', 'storeengine' ),
						esc_attr( $name ),
					); ?>">
					<span class="storeengine-icon storeengine-icon--duplicate" aria-hidden="true"></span>
				</button>
			<?php } ?>
		</div>
		<?php
	}

	/**
	 * Implodes an array into a human-readable string with a localized conjunction before the last item.
	 *
	 * Examples:
	 * - Helper::implode_with( ['a'] ); // Output: "a"
	 * - Helper::implode_with( ['a', 'b'] ); // Output: "a or b"
	 * - Helper::implode_with( ['a', 'b', 'c'] ); // Output: "a, b or c"
	 * - Helper::implode_with( ['a', 'b', 'c'], 'and' ); // Output: "a, b and c"
	 * - Helper::implode_with( ['a', 'b', 'c'], '', '-' ); // Output: "a-b or c"
	 * - Helper::implode_with( ['a', 'b', 'c'], 'and', ' - ' ); // Output: "a - b and c"
	 *
	 * @param array $items The list of strings to join.
	 * @param string $conjunction Optional. The conjunction to use before the last item (e.g. 'or', 'and').
	 *                            If empty, defaults to localized 'or'.
	 * @param string $glue Optional. Glue (separator) for joining the list of words.
	 *                     Default to localized space after comma `, `.
	 *
	 * @return string The imploded string.
	 */
	public static function implode_with( array $items, string $conjunction = '', string $glue = '' ): string {
		$count = count( $items );

		if ( '' === $conjunction ) {
			$conjunction = _x( 'or', 'Conjunction before last word of an array.', 'storeengine' );
		}

		if ( '' === $glue ) {
			$glue = _x( ', ', 'Glue/separator for joining word of an array (except last word).', 'storeengine' );
		}

		if ( $count === 0 ) {
			return '';
		}

		if ( $count === 1 ) {
			return $items[0];
		}

		if ( $count === 2 ) {
			return $items[0] . " $conjunction " . $items[1];
		}

		$last = array_pop( $items );

		return implode( $glue, $items ) . ", $conjunction " . $last;
	}

	public static function rename_array_keys( array $data, array $mapping ): array {
		$remapped = [];

		foreach ( $data as $key => $value ) {
			$newKey              = $mapping[ $key ] ?? $key; // fallback to original if not mapped
			$remapped[ $newKey ] = $value;
		}

		return $remapped;
	}

	public static function get_filename_without_extension( string $filename ) {
		return pathinfo( $filename, PATHINFO_FILENAME );
	}

	/**
	 * Log critical errors to the StoreEngine Logger.
	 *
	 * @param string|\Throwable $exception Exception object or string message.
	 * @param bool              $trace     Whether to include the stack trace.
	 */
	public static function log_error( $exception, bool $trace = true ) {
		$message = is_string( $exception ) ? $exception : $exception->getMessage();

		$title    = 'Error';
		$log_data = [ 'message' => $message ];
		$status   = Logger::WARNING;

		$context = [];

		if ( $exception instanceof StoreEngineException ) {
			if ( $trace ) {
				$context = $exception->to_array( StoreEngineException::WITH_PREVIOUS & StoreEngineException::WITH_WP_TRACE );
			} else {
				$context = $exception->to_array( StoreEngineException::WITH_PREVIOUS );
			}
		} else {
			// If it is a Throwable/Exception object, add the code, file, and line number to the log.
			if ( $exception instanceof \Throwable ) {
				$title = 'Critical Error';
				$status = Logger::CRITICAL;
				$log_data['code'] = $exception->getCode();
				$log_data['file'] = $exception->getFile();
				$log_data['line'] = $exception->getLine();
			}

			if ( $trace ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary -- Capturing a backtrace to persist into the StoreEngine Logger context, not debug output.
				$context['backtrace'] = wp_debug_backtrace_summary( self::class, 0, false );
			}
		}



		if ( $context ) {
			$log_data['context'] = $context;
		}

		// Save to the StoreEngine Logger (legacy error_log implementation has been removed).
		Logger::log( $title, $log_data, $status, 'system' );
	}

	/**
	 * Sanitise + normalise an email body for output inside a StoreEngine email
	 * shell (`templates/email/*.php`).
	 *
	 * Every email template echoes its body through this instead of a bare
	 * `wp_kses_post()`. It handles the block-email builder that replaced the old
	 * Quill editor: the builder (Easy Mail Builder, `renderMode="fragment"`)
	 * still emits its OWN centred 600px "card" plus Outlook/IE conditional
	 * ("ghost") tables around the content. Dropped verbatim into our shell that
	 * produced two visible problems:
	 *
	 *   1. `wp_kses_post()` entity-encodes the `>`/`<` inside `<!--[if mso | IE]>`
	 *      … `<![endif]-->`, so mail clients that don't strip the now-malformed
	 *      comment render it as literal "<!--[if mso | IE]>" text.
	 *   2. The builder's card nested inside our own `.container/.content/.wrapper`
	 *      card — a second box offset inside the first ("body overlapping").
	 *
	 * StoreEngine already wraps every body in its own centred, CSS-inlined shell,
	 * so the builder's outer wrapper is redundant here. We strip the ghost tables
	 * and unwrap the outer card so the builder's inner blocks flow directly into
	 * our content area. Plain HTML bodies (the Quill-era defaults, plain-text
	 * mode, hand-written templates) never carry the ghost marker and pass through
	 * untouched.
	 *
	 * @param string|null $content Raw email body HTML.
	 * @return string Sanitised, shell-ready HTML.
	 */
	public static function render_email_content( $content ): string {
		return wp_kses_post( self::normalize_email_content( (string) $content ) );
	}

	/**
	 * Strip the Easy Mail Builder's redundant outer wrapper (Outlook ghost
	 * tables + the centred 600px card) so its inner blocks land cleanly inside
	 * StoreEngine's email shell. A no-op for any body that isn't builder output.
	 *
	 * @param string $content
	 * @return string
	 */
	public static function normalize_email_content( string $content ): string {
		// Only the block builder emits Outlook conditional ("ghost") wrappers;
		// plain HTML bodies never do, so leave them entirely untouched.
		if ( false === strpos( $content, '[if' ) ) {
			return $content;
		}

		// 1. Remove the Outlook/IE conditional ghost tables. Left in place they
		//    both nest a redundant card and — once wp_kses_post mangles them —
		//    leak as literal "<!--[if mso | IE]>" text.
		//
		//    The markers may arrive RAW (`]>` … `<![endif]`) straight from the
		//    builder, OR already entity-encoded (`]&gt;` … `&lt;![endif]`) because
		//    the save path runs the body through wp_kses_post before storing, and
		//    wp_kses_post encodes the `>`/`<` inside conditional comments. Match
		//    both shapes so old raw saves and current encoded saves both strip.
		$stripped = preg_replace( '/<!--\[if[^\]]*\](?:>|&gt;).*?(?:<|&lt;)!\[endif\]-->/is', '', $content );
		if ( null === $stripped ) {
			// PCRE failure (e.g. backtrack limit) — fall back to the raw content
			// rather than returning null.
			return $content;
		}
		$content = trim( $stripped );

		// 2. Unwrap the builder's outer 600px "card" table. Only the builder
		//    emits a top-level <table> as the very first node; plain bodies open
		//    with <p>/<h*>. DOMDocument keeps the (possibly nested) inner block
		//    tables intact while lifting out just the card's single cell.
		if ( class_exists( 'DOMDocument' ) && 0 === stripos( $content, '<table' ) ) {
			$dom = new \DOMDocument();
			$libxml_previous = libxml_use_internal_errors( true );
			// The XML prolog forces UTF-8; the wrapper div gives us a stable
			// query root. NOIMPLIED/NODEFDTD stop DOMDocument injecting its own
			// <html><body> chrome around our fragment.
			$dom->loadHTML(
				'<?xml encoding="UTF-8"><div id="storeengine-email-root">' . $content . '</div>',
				LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
			);
			libxml_clear_errors();
			libxml_use_internal_errors( $libxml_previous );

			$xpath = new \DOMXPath( $dom );
			// DOMDocument auto-inserts <tbody>; match both shapes.
			$cells = $xpath->query( "//div[@id='storeengine-email-root']/table[1]/tbody/tr/td | //div[@id='storeengine-email-root']/table[1]/tr/td" );
			if ( $cells && $cells->length ) {
				$inner = '';
				foreach ( $cells->item( 0 )->childNodes as $child ) {
					$inner .= $dom->saveHTML( $child );
				}
				$content = trim( $inner );
			}
		}

		return $content;
	}
}
