<?php
/**
 * Cart REST API
 *
 * Public, headless-friendly REST controller for the cart. Mirrors the
 * existing admin-ajax cart actions (`add_to_cart`, `update_cart_item_quantity`,
 * `remove_cart_item`, `clear_cart`, `direct_checkout`) 1:1 in functionality,
 * but speaks JSON over REST and uses the same dual-auth model as the
 * checkout REST surface:
 *
 *   - Cookie auth + `X-WP-Nonce` for same-site / WP-rendered storefronts.
 *   - Publishable-key + Origin allow-list (handled by the Instant Checkout
 *     addon's `pk_auth` middleware via the
 *     `storeengine/checkout/publishable_key_auth` filter) for cross-origin /
 *     fully headless storefronts.
 *
 * Routes:
 *   GET    /wp-json/storeengine/v1/cart                           — full snapshot
 *   DELETE /wp-json/storeengine/v1/cart                           — clear the cart
 *   POST   /wp-json/storeengine/v1/cart/items                     — add an item
 *   POST   /wp-json/storeengine/v1/cart/items/direct-checkout     — clear + add (Buy Now)
 *   PATCH  /wp-json/storeengine/v1/cart/items/<key>               — update quantity
 *   DELETE /wp-json/storeengine/v1/cart/items/<key>               — remove an item
 *   DELETE /wp-json/storeengine/v1/cart/items                     — clear the cart (alias)
 *   GET    /wp-json/storeengine/v1/cart/refresh-fragments         — re-rendered cart shortcode HTML
 *   GET    /wp-json/storeengine/v1/cart/products/<id>/variations  — variation lookup
 */

namespace StoreEngine\API;

use StoreEngine;
use StoreEngine\Classes\Price;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cart extends AbstractRestApiController {

	protected $rest_base = 'cart';

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_cart' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'clear_cart' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/items', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'add_item' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => $this->add_item_args(),
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'clear_cart' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/items/direct-checkout', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'direct_checkout' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => $this->add_item_args(),
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/items/(?P<key>[A-Za-z0-9]+)', [
			'args' => [
				'key' => [ 'type' => 'string', 'required' => true ],
			],
			[
				'methods'             => 'PATCH',
				'callback'            => [ $this, 'update_cart_item' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'quantity' => [ 'type' => 'integer', 'required' => true, 'minimum' => 0 ],
				],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'remove_cart_item' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/refresh-fragments', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'refresh_fragments' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/products/(?P<product_id>\d+)/variations', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_variations' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'product_id' => [ 'type' => 'integer', 'required' => true ],
				],
			],
		] );
	}

	protected function add_item_args(): array {
		return [
			'price_id'     => [ 'type' => 'integer', 'required' => true, 'minimum' => 1 ],
			'product_id'   => [ 'type' => 'integer', 'required' => false ],
			'variation_id' => [ 'type' => 'integer', 'required' => false ],
			'quantity'     => [ 'type' => 'integer', 'required' => false, 'minimum' => 1, 'default' => 1 ],
		];
	}

	/**
	 * Dual-auth permission check — mirrors API\Checkout::permission_callback().
	 *
	 *   - X-StoreEngine-Pk header: defer to the addon's
	 *     `storeengine/checkout/publishable_key_auth` filter.
	 *   - Otherwise: same-site cookie auth (WP REST nonce already validated).
	 */
	public function permission_callback( WP_REST_Request $request ) {
		$pk = $request->get_header( 'x_storeengine_pk' );
		if ( ! $pk ) {
			$pk = $request->get_param( 'pk' );
		}

		if ( $pk ) {
			$result = apply_filters( 'storeengine/checkout/publishable_key_auth', null, $request );
			if ( null === $result ) {
				return new WP_Error(
					'storeengine_cart_pk_unsupported',
					__( 'Publishable-key authentication is not active. Enable the Instant Checkout addon.', 'storeengine' ),
					[ 'status' => 401 ]
				);
			}
			return $result;
		}

		// Allow guests on cart endpoints — same as the legacy admin-ajax handler.
		return true;
	}

	/* ── Endpoints ──────────────────────────────────────────────────────── */

	public function get_cart() {
		StoreEngine::init()->load_cart();
		return rest_ensure_response( $this->snapshot() );
	}

	public function add_item( WP_REST_Request $request ) {
		StoreEngine::init()->load_cart();
		$prepared = $this->prepare_item_payload( $request );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		$result = Helper::cart()->add_product_to_cart(
			$prepared['price_id'],
			$prepared['quantity'],
			$prepared['variation_id'],
			$prepared['variation_data']
		);

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), [ 'status' => 422 ] );
		}

		// Both hook names are fired so addons that listened to either continue
		// to work after the admin-ajax → REST migration. Same payload shape.
		do_action( 'storeengine/cart/added_to_cart', $request->get_params() );
		do_action( 'storeengine/ajax/cart/added_to_cart', $request->get_params() );

		return rest_ensure_response( $this->build_add_to_cart_response( $prepared['product_id'], $prepared['price_id'], false ) );
	}

	public function direct_checkout( WP_REST_Request $request ) {
		StoreEngine::init()->load_cart();
		$prepared = $this->prepare_item_payload( $request );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}

		Helper::cart()->clear_cart();

		$result = Helper::cart()->add_product_to_cart(
			$prepared['price_id'],
			$prepared['quantity'],
			$prepared['variation_id'],
			$prepared['variation_data']
		);

		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), [ 'status' => 422 ] );
		}

		do_action( 'storeengine/cart/added_to_cart', $request->get_params() );
		do_action( 'storeengine/ajax/cart/added_to_cart', $request->get_params() );

		return rest_ensure_response( $this->build_add_to_cart_response( $prepared['product_id'], $prepared['price_id'], true ) );
	}

	public function update_cart_item( WP_REST_Request $request ) {
		StoreEngine::init()->load_cart();
		$key      = sanitize_text_field( (string) $request->get_param( 'key' ) );
		$quantity = (int) $request->get_param( 'quantity' );

		$result = Helper::cart()->update_quantity( $key, $quantity );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( $result->get_error_code(), $result->get_error_message(), [ 'status' => 422 ] );
		}

		$item = Helper::cart()->get_cart_item( $key );
		if ( ! $item ) {
			// Quantity 0 removes the item — no item left to return.
			return rest_ensure_response( [
				'item'       => null,
				'product_id' => 0,
				'price_id'   => 0,
				'message'    => __( 'Item updated in your cart.', 'storeengine' ),
				'snapshot'   => $this->snapshot(),
			] );
		}

		$product = Helper::get_product( $item->product_id );
		$title   = $product ? $product->get_name() : ( $item->name ?? '' );
		/* translators: %s: Item name in quotes. */
		$title   = $title ? sprintf( _x( '&ldquo;%s&rdquo;', 'Item name in quotes', 'storeengine' ), $title ) : __( 'Item', 'storeengine' );
		$message = sprintf(
			/* translators: %s Product title. */
			__( '%s updated in your cart.', 'storeengine' ),
			apply_filters( 'storeengine/cart/item_updated_title', $title, $item )
		);

		return rest_ensure_response( [
			'item'       => $item,
			'product_id' => (int) $item->product_id,
			'price_id'   => (int) ( $item->price_id ?? 0 ),
			'message'    => $message,
			'snapshot'   => $this->snapshot(),
		] );
	}

	public function remove_cart_item( WP_REST_Request $request ) {
		StoreEngine::init()->load_cart();
		$key = sanitize_text_field( (string) $request->get_param( 'key' ) );

		$item = Helper::cart()->get_cart_item( $key );
		if ( ! $item ) {
			return new WP_Error( 'storeengine_cart_item_not_found', __( 'Cart item not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$product = Helper::get_product( $item->product_id );
		$title   = $product ? $product->get_name() : ( $item->name ?? '' );
		/* translators: %s: Item name in quotes. */
		$title   = $title ? sprintf( _x( '&ldquo;%s&rdquo;', 'Item name in quotes', 'storeengine' ), $title ) : __( 'Item', 'storeengine' );
		$message = sprintf(
			/* translators: %s Product title. */
			__( '%s removed from your cart.', 'storeengine' ),
			apply_filters( 'storeengine/cart/item_removed_title', $title, $item )
		);

		if ( ! Helper::cart()->remove_cart_item( $key ) ) {
			return new WP_Error( 'storeengine_cart_remove_failed', __( 'Failed to remove item from cart.', 'storeengine' ), [ 'status' => 500 ] );
		}

		return rest_ensure_response( [
			'removed'    => true,
			'item_key'   => $key,
			'item'       => $item,
			'product_id' => (int) $item->product_id,
			'price_id'   => (int) ( $item->price_id ?? 0 ),
			'message'    => $message,
			'snapshot'   => $this->snapshot(),
		] );
	}

	/**
	 * Build the add-to-cart response shape consumed by the storefront cart.js.
	 * Mirrors what the legacy admin-ajax handler returned (item + product_id +
	 * price_id + optional redirect) and runs the same legacy filter so addons
	 * that hooked into `storeengine/ajax/cart/add_to_cart_response` still work.
	 */
	protected function build_add_to_cart_response( int $product_id, int $price_id, bool $redirect ): array {
		$item = Helper::cart()->get_cart_item_by_product( $product_id, $price_id );
		$data = [
			'item'       => $item,
			'product_id' => $product_id,
			'price_id'   => $price_id,
			'snapshot'   => $this->snapshot(),
		];
		if ( $redirect ) {
			$data['redirect'] = esc_url_raw( Helper::get_checkout_url() );
		}

		return apply_filters( 'storeengine/ajax/cart/add_to_cart_response', $data, $product_id, $price_id );
	}

	/**
	 * Replacement for the legacy `refresh_cart` admin-ajax action.
	 * Returns the same set of HTML fragments cart.js swaps in after each cart
	 * mutation: cart sub-total table, order summary, and the checkout
	 * payment-method block (so available gateways re-render after totals shift).
	 */
	public function refresh_fragments() {
		StoreEngine::init()->load_cart();

		/*
		 * The cart sub-total fragment is shared by the cart and checkout pages,
		 * but the shipping template (cart/cart-shipping.php) renders differently
		 * for each: a compact "chosen method" line on the cart vs a selectable
		 * radio list on checkout. This REST refresh carries no page context, so
		 * the shared STOREENGINE_CART flag used to be defined unconditionally —
		 * which made Helper::is_cart() true even for checkout refreshes and
		 * collapsed the shipping selector into a single hidden radio. Detect the
		 * originating page from the referer and flag the correct context instead.
		 */
		if ( $this->is_cart_page_request() ) {
			if ( ! defined( 'STOREENGINE_CART' ) ) {
				define( 'STOREENGINE_CART', true );
			}
		} elseif ( ! defined( 'STOREENGINE_CHECKOUT' ) ) {
			define( 'STOREENGINE_CHECKOUT', true );
		}
		set_query_var( 'order_pay', 'false' );

		$shortcodes = apply_filters( 'storeengine/cart/refresh_shortcodes', [
			'storeengine-order-summary-shortcode'        => '[storeengine_order_summary]',
			'storeengine-cart-sub-total-table-shortcode' => '[storeengine_cart_sub_total_table]',
		] );

		$fragments = [];
		foreach ( $shortcodes as $class => $shortcode ) {
			$fragments[ $class ] = do_shortcode( $shortcode );
		}

		ob_start();
		if ( function_exists( 'storeengine_checkout_payment_method' ) ) {
			storeengine_checkout_payment_method();
		}
		$fragments['storeengine-ajax-checkout-form__payments'] = ob_get_clean();

		return rest_ensure_response( $fragments );
	}

	/**
	 * Whether the current fragment-refresh request originated from the cart page.
	 *
	 * Keeps Helper::is_cart()/is_checkout() accurate during the context-less REST
	 * refresh so shared fragments (notably the shipping method selector) render in
	 * the right mode. Defaults to checkout context when the referer is missing or
	 * unresolvable, because that is the page that needs the selectable shipping UI.
	 *
	 * @return bool
	 */
	protected function is_cart_page_request(): bool {
		$referer = wp_get_referer();
		if ( ! $referer ) {
			return false;
		}

		$cart_page_id = absint( Helper::get_settings( 'cart_page' ) );
		if ( ! $cart_page_id ) {
			return false;
		}

		return absint( url_to_postid( $referer ) ) === $cart_page_id;
	}

	public function clear_cart() {
		StoreEngine::init()->load_cart();
		Helper::cart()->clear_cart();

		return rest_ensure_response( [
			'cleared'  => true,
			'snapshot' => $this->snapshot(),
		] );
	}

	public function get_variations( WP_REST_Request $request ) {
		$product_id = (int) $request->get_param( 'product_id' );
		$product    = Helper::get_product( $product_id );
		if ( ! $product ) {
			return new WP_Error( 'storeengine_product_not_found', __( 'Product not found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		if ( ! $product->is_type( 'variable' ) ) {
			return new WP_Error( 'storeengine_product_not_variable', __( 'Product is not variable.', 'storeengine' ), [ 'status' => 422 ] );
		}

		$variations = method_exists( $product, 'get_variations' ) ? $product->get_variations() : [];

		return rest_ensure_response( [
			'product_id' => $product_id,
			'variations' => $variations,
		] );
	}

	/* ── Helpers ────────────────────────────────────────────────────────── */

	/**
	 * Validate + normalise an add-to-cart payload — same logic the legacy
	 * AJAX handler uses, just returning WP_Error instead of wp_send_json_error.
	 *
	 * @return array{price_id:int,product_id:int,quantity:int,variation_id:int,variation_data:array}|WP_Error
	 */
	protected function prepare_item_payload( WP_REST_Request $request ) {
		$price_id = (int) $request->get_param( 'price_id' );
		if ( ! $price_id ) {
			return new WP_Error( 'storeengine_cart_invalid_price', __( 'Price ID is required.', 'storeengine' ), [ 'status' => 422 ] );
		}

		try {
			$price = new Price( $price_id );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'storeengine_cart_invalid_price', __( 'Invalid price ID.', 'storeengine' ), [ 'status' => 422 ] );
		}

		$product = $price->get_product();
		if ( ! $product ) {
			return new WP_Error( 'storeengine_cart_product_not_found', __( 'Product not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$quantity = max( 1, (int) ( $request->get_param( 'quantity' ) ?: 1 ) );

		$out = [
			'price_id'       => $price->get_id(),
			'product_id'     => $product->get_id(),
			'quantity'       => $quantity,
			'variation_id'   => 0,
			'variation_data' => [],
		];

		if ( $product->is_type( 'variable' ) ) {
			$variation_id = (int) $request->get_param( 'variation_id' );
			if ( ! $variation_id ) {
				return new WP_Error( 'storeengine_cart_variation_required', __( 'Variation ID is required.', 'storeengine' ), [ 'status' => 422 ] );
			}
			$variation = Helper::get_product_variation( $variation_id );
			if ( ! $variation ) {
				return new WP_Error( 'storeengine_cart_invalid_variation', __( 'Invalid variation ID.', 'storeengine' ), [ 'status' => 422 ] );
			}
			$out['variation_id'] = $variation->get_id();
			foreach ( $variation->get_attributes() as $attr ) {
				if ( isset( $attr->taxonomy, $attr->slug ) ) {
					$out['variation_data'][ $attr->taxonomy ] = $attr->slug;
				}
			}
		}

		return $out;
	}

	/**
	 * A consistent cart snapshot returned alongside every mutation. Matches the
	 * shape of the cart-relevant subset of the checkout snapshot so headless
	 * storefronts can reuse the same UI helpers.
	 */
	protected function snapshot(): array {
		$cart = Helper::cart();
		if ( ! $cart ) {
			return [
				'items'           => [],
				'totals'          => [ 'subtotal' => 0, 'shipping' => 0, 'tax' => 0, 'discount' => 0, 'total' => 0 ],
				'currency'        => Formatting::get_currency(),
				'needs_shipping'  => false,
				'needs_payment'   => false,
				'applied_coupons' => [],
				'item_count'      => 0,
			];
		}

		$cart->calculate_totals();

		$per_item_discounts = method_exists( $cart, 'get_coupon_discount_per_item' ) ? $cart->get_coupon_discount_per_item() : [];
		$reward_units       = method_exists( $cart, 'get_reward_units' ) ? $cart->get_reward_units() : [];

		$items = [];
		foreach ( $cart->get_cart_items() as $cart_item_key => $item ) {
			$product = Helper::get_product( $item->product_id );

			// Rewarded ("free") units + per-item coupon discount, so the UI can
			// mark or split free lines.
			$free_units  = 0;
			$free_coupon = '';
			foreach ( $reward_units as $code => $keys ) {
				if ( ! empty( $keys[ $cart_item_key ] ) ) {
					$free_units += (int) $keys[ $cart_item_key ];
					$free_coupon = (string) $code;
				}
			}

			$items[] = [
				'item_key'          => $cart_item_key,
				'product_id'        => (int) $item->product_id,
				'price_id'          => (int) ( $item->price_id ?? 0 ),
				'name'              => $product ? $product->get_name() : ( $item->name ?? '' ),
				'image'             => $product ? ( get_the_post_thumbnail_url( $item->product_id, 'thumbnail' ) ?: null ) : null,
				'quantity'          => (int) $item->quantity,
				'price'             => (float) ( $item->price ?? 0 ),
				'subtotal'          => (float) ( $item->subtotal ?? ( ( $item->price ?? 0 ) * ( $item->quantity ?? 0 ) ) ),
				'variation_id'      => (int) ( $item->variation_id ?? 0 ),
				'coupon_discount'   => (float) array_sum( $per_item_discounts[ $cart_item_key ] ?? [] ),
				'free_units'        => $free_units,
				'free_units_coupon' => $free_coupon,
				// Add-on line markers (e.g. an auto-added gift line).
				'item_data'         => (array) ( $item->item_data ?? [] ),
			];
		}

		$applied_coupons = [];
		if ( method_exists( $cart, 'get_coupons' ) ) {
			foreach ( $cart->get_coupons() as $coupon ) {
				if ( ! is_object( $coupon ) ) {
					continue;
				}
				$code = method_exists( $coupon, 'get_code' )
					? (string) $coupon->get_code()
					: ( property_exists( $coupon, 'code' ) ? (string) $coupon->code : '' );
				if ( '' === $code ) {
					continue;
				}
				$applied_coupons[] = [
					'code'     => $code,
					'discount' => method_exists( $cart, 'get_coupon_discount_amount' )
						? (float) $cart->get_coupon_discount_amount( $code )
						: 0.0,
				];
			}
		}

		$snapshot = [
			'items'           => $items,
			'totals'          => [
				'subtotal' => (float) $cart->get_cart_subtotal(),
				'shipping' => (float) $cart->get_shipping_total(),
				'tax'      => (float) $cart->get_taxes_total( true, false ),
				'discount' => (float) $cart->get_discount_total(),
				'total'    => (float) $cart->get_total( 'edit' ),
			],
			'currency'        => Formatting::get_currency(),
			'needs_shipping'  => $cart->needs_shipping(),
			'needs_payment'   => $cart->needs_payment(),
			'applied_coupons' => $applied_coupons,
			'item_count'      => array_sum( array_map( fn( $i ) => $i['quantity'], $items ) ),
		];

		/**
		 * Filters the cart snapshot returned to storefront clients.
		 *
		 * Lets addons enrich the snapshot, e.g. split rewarded units into a
		 * separate "FREE" line or inject coupon-goal suggestions.
		 *
		 * @param array $snapshot The cart snapshot.
		 * @param Cart  $cart     The cart object.
		 */
		return apply_filters( 'storeengine/cart/snapshot', $snapshot, $cart );
	}
}
