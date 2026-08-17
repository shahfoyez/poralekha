<?php
/**
 * Hooks.
 */

namespace StoreEngine\Addons\CatalogMode;

use StoreEngine\Frontend\FloatingCart;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {
	use Singleton;

	protected function __construct() {
		add_filter( 'storeengine/backend_scripts_data', [ $this, 'add_script_data' ] );
		add_filter( 'init', [ $this, 'dispatch_catalog_mode' ] );
	}

	/**
	 * Dispatch catalog mode if enabled.
	 * Using anonymous function so actions can't be removed.
	 *
	 * @return void
	 */
	public function dispatch_catalog_mode() {
		if ( ! Settings::init()->isEnabled() || self::maybe_exclude_user() ) {
			return;
		}

		if ( Settings::init()->get_settings( 'disable_cart_checkout' ) ) {
			// Block REST cart + checkout endpoints with a 403 so the storefront's
			// add-to-cart / buy-now / place-order paths fail loudly when catalog
			// mode is on. Filter applies to the storeengine namespace only.
			add_filter( 'rest_pre_dispatch', [ $this, 'block_cart_rest_routes' ], 10, 3 );
		}

		// Registered here on `init` (not inside the `template_redirect` block below)
		// so the swap also applies to admin-ajax requests, e.g. the shop page's
		// AJAX pagination/filter handler (`archive_product_filter`), which renders
		// the product loop without ever firing `template_redirect`. The default
		// callbacks being removed here are registered at file-load time in
		// includes/frontend/hooks.php, well before `init`, so they already exist.
		if ( self::should_replace_add_to_cart() ) {
			remove_action( 'storeengine/templates/product_loop_footer_content', 'storeengine_product_loop_add_to_cart' );
			remove_action( 'storeengine/templates/single-product/header_right_content', 'storeengine_single_product_add_to_cart' );
			add_action(
				'storeengine/templates/product_loop_footer_content',
				[ $this, 'product_loop_add_to_cart_replacement' ]
			);

			add_action(
				'storeengine/templates/single-product/header_right_content',
				[ $this, 'single_product_add_to_cart_replacement' ]
			);
		}

		add_action( 'template_redirect', function () {
			if ( Settings::init()->get_settings( 'disable_cart_checkout' ) ) {
				if ( Helper::is_checkout() || Helper::is_cart() ) {
					wp_safe_redirect( get_post_type_archive_link( Helper::PRODUCT_POST_TYPE ) );
					exit;
				}

				remove_action( 'wp_footer', [ FloatingCart::init(), 'display_cart_button' ] );
				remove_action( 'storeengine/templates/single_product_content', 'storeengine_single_view_cart' );
			}
		}, 999 );
	}

	public function product_loop_add_to_cart_replacement() {
		Template::get_template( 'catalog-mode/loop-add-to-cart.php', $this->get_replacement_args() );
	}

	public function single_product_add_to_cart_replacement() {
		Template::get_template( 'catalog-mode/single-product-add-to-cart.php', $this->get_replacement_args() );
	}

	protected function get_replacement_args(): array {
		$button_args = null;

		if ( Settings::init()->get_settings( 'customize_add_to_cart' ) && Settings::init()->get_settings( 'add_to_cart_link' ) ) {
			$button_text   = Helper::is_shop() ? 'add_to_cart_shop_page_text' : 'add_to_cart_product_page_text';
			$button_target = Settings::init()->get_settings( 'add_to_cart_link_target' );
			$button_target = in_array( $button_target, [ '_self', '_blank' ] ) ? $button_target : '_self';
			$button_args   = [
				'button_text'   => Settings::init()->get_settings( $button_text ),
				'button_link'   => Settings::init()->get_settings( 'add_to_cart_link' ),
				'button_rel'    => 'noopener noreferrer',
				'button_target' => $button_target,
			];
		}

		return apply_filters(
			'storeengine/catalog-mode/add-to-cart-replacement-args',
			[
				'hide_pricing'            => Settings::init()->get_settings( 'disable_price' ),
				'hide_add_to_cart'        => self::maybe_hide_add_to_cart(),
				'show_pricing'            => ! Settings::init()->get_settings( 'disable_price' ),
				'pricing_placeholder'     => Settings::init()->get_settings( 'price_placeholder' ),
				'add_to_cart_placeholder' => Settings::init()->get_settings( 'add_to_cart_placeholder' ),
				'add_to_cart_button'      => $button_args,
			]
		);
	}

	public static function maybe_exclude_user(): bool {
		$excludes = Settings::init()->get_settings( 'exclude_role' );

		if ( $excludes && is_user_logged_in() ) {
			$user = wp_get_current_user();
			foreach ( $excludes as $exclude ) {
				if ( in_array( $exclude, $user->roles, true ) ) {
					return true;
				}
			}
		}

		return false;
	}

	public static function should_replace_add_to_cart(): bool {
		return ! ( ! self::maybe_hide_add_to_cart() && ! Settings::init()->get_settings( 'disable_price' ) );
	}

	public static function maybe_hide_add_to_cart(): bool {
		return ! Settings::init()->get_settings( 'hide_add_to_cart_in' ) || 'all' === Settings::init()->get_settings( 'hide_add_to_cart_in' ) || 'shop' === Settings::init()->get_settings( 'hide_add_to_cart_in' ) && Helper::is_shop() || 'product' === Settings::init()->get_settings( 'hide_add_to_cart_in' ) && Helper::is_product();
	}

	/**
	 * Reject REST hits to the storefront cart + checkout routes when
	 * `disable_cart_checkout` is on. Replaces the legacy admin-ajax block.
	 */
	public function block_cart_rest_routes( $result, $server, $request ) {
		if ( null !== $result ) {
			return $result;
		}
		$route = method_exists( $request, 'get_route' ) ? (string) $request->get_route() : '';
		if ( '' === $route ) {
			return $result;
		}

		$blocked_prefixes = apply_filters(
			'storeengine/catalog-mode/disabled_rest_routes',
			[
				'/storeengine/v1/cart',
				'/storeengine/v1/checkout',
				'/storeengine/v1/instant-checkout',
				'/storeengine/v1/embed-keys',
			]
		);

		foreach ( $blocked_prefixes as $prefix ) {
			if ( 0 === strpos( $route, $prefix ) ) {
				return new \WP_Error(
					'storeengine_catalog_mode_disabled',
					__( 'Products are view-only. Contact us for purchasing options.', 'storeengine' ),
					[ 'status' => 403 ]
				);
			}
		}

		return $result;
	}

	public function add_script_data( array $data ): array {
		// All registered roles, not get_editable_roles(): catalog-mode exclusion
		// must target every role on the site, including ones the `editable_roles`
		// filter hides from the user-management UI.
		$roles             = wp_roles()->roles;
		$data['userRoles'] = [
			[
				'label' => esc_html__( 'No exclusion', 'storeengine' ),
				'value' => '',
			],
		];

		foreach ( $roles as $role => ['name' => $name] ) {
			$data['userRoles'][] = [
				'label' => esc_html( $name ),
				'value' => esc_attr( $role ),
			];
		}

		return $data;
	}
}

// End of file hooks.php.
