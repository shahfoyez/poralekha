<?php
/**
 * Instant Checkout — addon-style routes that the React app needs alongside
 * the core /checkout/{state,update,place,states} surface.
 *
 *   POST /storeengine/v1/instant-checkout/sync-items
 *   POST /storeengine/v1/instant-checkout/stripe-intent
 *
 * Plus a session-resolver filter that core's checkout controller delegates to:
 *
 *   storeengine/checkout/resolve_session  (returns session array|null|WP_Error)
 *
 * Phase 3 will generalise stripe-intent into /payment-intent/{gateway_id}.
 */

namespace StoreEngine\Addons\InstantCheckout\Api;

use StoreEngine;
use StoreEngine\API\AbstractRestApiController;
use StoreEngine\API\Checkout as CoreCheckout;
use StoreEngine\Utils\CheckoutFields;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CheckoutBridge extends AbstractRestApiController {

	protected $rest_base = 'instant-checkout';

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );

		add_filter( 'storeengine/checkout/resolve_session', [ $self, 'resolve_session' ], 10, 3 );
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base . '/sync-items', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'sync_items' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'session_id' => [ 'type' => 'string', 'required' => true ],
					'selection'  => [ 'type' => 'array', 'required' => true ],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/stripe-intent', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_stripe_intent' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'session_id' => [ 'type' => 'string', 'required' => true ],
				],
			],
		] );
	}

	public function resolve_session( $existing, string $session_id, WP_REST_Request $request ) {
		if ( null !== $existing ) {
			return $existing;
		}
		$session = Session::consume_session( $session_id );
		if ( ! $session ) {
			return new WP_Error( 'storeengine_instant_checkout_session_expired', __( 'Checkout session expired.', 'storeengine' ), [ 'status' => 410 ] );
		}

		return $session;
	}

	public function permission_callback( WP_REST_Request $request ) {
		$auth = apply_filters( 'storeengine/instant_checkout/session/authorize', null, $request );
		if ( is_wp_error( $auth ) ) {
			return $auth;
		}
		return true;
	}

	public function sync_items( WP_REST_Request $request ) {
		$rl = apply_filters( 'storeengine/instant_checkout/session/rate_limit', true, $request );
		if ( is_wp_error( $rl ) ) {
			return $rl;
		}

		$session_id = sanitize_text_field( (string) $request->get_param( 'session_id' ) );
		$session    = Session::consume_session( $session_id );
		if ( ! $session ) {
			return new WP_Error( 'storeengine_instant_checkout_session_expired', __( 'Checkout session expired.', 'storeengine' ), [ 'status' => 410 ] );
		}

		$declared   = isset( $session['items'] ) ? (array) $session['items'] : [];
		$by_product = [];
		foreach ( $declared as $row ) {
			$by_product[ (int) $row['product_id'] ] = $row;
		}

		$selection = [];
		foreach ( (array) $request->get_param( 'selection' ) as $row ) {
			$row = (array) $row;
			$pid = (int) ( $row['product_id'] ?? 0 );
			if ( ! $pid || ! isset( $by_product[ $pid ] ) ) {
				continue;
			}
			$declared_row = $by_product[ $pid ];
			$selection[]  = [
				'product_id' => $pid,
				'price_id'   => (int) ( $row['price_id'] ?? $declared_row['price_id'] ?? 0 ),
				'quantity'   => max( 1, (int) ( $row['quantity'] ?? 1 ) ),
			];
		}

		Session::update_session_selection( $session_id, $selection );
		$session['selection'] = $selection;

		StoreEngine::init()->load_cart();
		$cart = Helper::cart();
		if ( $cart ) {
			CoreCheckout::hydrate_cart_from_session( $cart, $session );
			$cart->calculate_totals();
		}

		return rest_ensure_response( $this->build_snapshot( $session ) );
	}

	public function create_stripe_intent( WP_REST_Request $request ) {
		$rl = apply_filters( 'storeengine/instant_checkout/session/rate_limit', true, $request );
		if ( is_wp_error( $rl ) ) {
			return $rl;
		}

		$session_id = sanitize_text_field( (string) $request->get_param( 'session_id' ) );
		$session    = Session::consume_session( $session_id );
		if ( ! $session ) {
			return new WP_Error( 'storeengine_instant_checkout_session_expired', __( 'Checkout session expired.', 'storeengine' ), [ 'status' => 410 ] );
		}

		if ( ! class_exists( '\\StoreEngine\\Addons\\Stripe\\StripeService' ) ) {
			return new WP_Error( 'storeengine_instant_checkout_stripe_inactive', __( 'Stripe addon is not active.', 'storeengine' ), [ 'status' => 422 ] );
		}

		StoreEngine::init()->load_cart();
		$cart = Helper::cart();
		if ( $cart && $cart->is_cart_empty() ) {
			CoreCheckout::hydrate_cart_from_session( $cart, $session );
		}
		$cart->calculate_totals();

		$order = Helper::get_recent_draft_order( get_current_user_id(), null, true );
		if ( ! $order ) {
			return new WP_Error( 'storeengine_instant_checkout_no_order', __( 'Could not create order.', 'storeengine' ), [ 'status' => 500 ] );
		}

		$order->set_currency( Formatting::get_currency() );
		$order->set_total( (float) $cart->get_total( 'edit' ) );
		$order->save();

		$stripe_customer_id = null;
		if ( is_user_logged_in() && class_exists( '\\StoreEngine\\Addons\\Stripe\\StripeCustomer' ) ) {
			try {
				$customer = new \StoreEngine\Addons\Stripe\StripeCustomer( get_current_user_id() );
				if ( ! $customer->get_id() ) {
					$stripe_customer_id = $customer->create_customer( [ 'order' => $order ] );
				} else {
					$stripe_customer_id = $customer->update_customer( [ 'order' => $order ] );
				}
			} catch ( \Throwable $e ) {
				// Non-fatal; continue as guest charge.
			}
		}

		try {
			$intent = \StoreEngine\Addons\Stripe\StripeService::init()->create_checkout_payment_intent(
				$order,
				(float) $order->get_total(),
				$stripe_customer_id,
				false
			);
			$order->add_meta_data( '_stripe_intent_id', $intent->id, true );
			$order->add_meta_data( '_stripe_currency', $intent->currency, true );
			$order->save();

			return rest_ensure_response( [
				'payment_intent_id' => $intent->id,
				'client_secret'     => $intent->client_secret,
				'order_id'          => $order->get_id(),
			] );
		} catch ( \Throwable $e ) {
			error_log( '[storeengine instant checkout] stripe intent failed: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return new WP_Error( 'storeengine_instant_checkout_stripe_intent_failed', $e->getMessage(), [ 'status' => 500 ] );
		}
	}

	protected function build_snapshot( ?array $session ): array {
		$cart     = Helper::cart();
		$gateways = [];
		foreach ( Helper::get_payment_gateways()->get_available_payment_gateways() as $gateway ) {
			$g = [
				'id'          => $gateway->id,
				'title'       => method_exists( $gateway, 'get_title' ) ? $gateway->get_title() : ( $gateway->title ?? $gateway->id ),
				'description' => method_exists( $gateway, 'get_description' ) ? $gateway->get_description() : '',
			];
			if ( method_exists( $gateway, 'get_option' ) ) {
				$is_production       = (bool) $gateway->get_option( 'is_production', true );
				$g['is_production']  = $is_production;
				$pk                  = $gateway->get_option( ( $is_production ? '' : 'test_' ) . 'publishable_key' );
				if ( $pk ) {
					$g['publishable_key'] = $pk;
				}
			}
			$gateways[] = apply_filters( 'storeengine/checkout/gateway/' . $gateway->id . '/data', $g, $gateway );
		}

		$items = [];
		foreach ( $cart->get_cart_items() as $item ) {
			$product = Helper::get_product( $item->product_id );
			$items[] = [
				'product_id' => (int) $item->product_id,
				'price_id'   => (int) ( $item->price_id ?? 0 ),
				'name'       => $product ? $product->get_name() : '',
				'image'      => $product ? ( get_the_post_thumbnail_url( $product->get_id(), 'thumbnail' ) ?: null ) : null,
				'quantity'   => (int) $item->quantity,
				'price'      => (float) ( $item->price ?? 0 ),
				'subtotal'   => (float) ( $item->subtotal ?? ( $item->price * $item->quantity ) ),
			];
		}

		$declared  = [];
		$selection = [];
		if ( $session && ! empty( $session['items'] ) ) {
			foreach ( (array) $session['items'] as $row ) {
				$pid = (int) ( $row['product_id'] ?? 0 );
				if ( ! $pid ) {
					continue;
				}
				$product   = Helper::get_product( $pid );
				$pid_price = (int) ( $row['price_id'] ?? 0 );
				$price     = null;
				if ( $product ) {
					$prices = $product->get_prices();
					if ( $pid_price ) {
						foreach ( $prices as $p ) {
							if ( (int) $p->get_id() === $pid_price ) {
								$price = $p;
								break;
							}
						}
					}
					if ( ! $price && $prices ) {
						$price     = reset( $prices );
						$pid_price = (int) $price->get_id();
					}
				}
				$declared[] = [
					'product_id' => $pid,
					'price_id'   => $pid_price,
					'name'       => ! empty( $row['label'] ) ? (string) $row['label'] : ( $product ? $product->get_name() : '' ),
					'image'      => ! empty( $row['image'] ) ? (string) $row['image'] : ( $product ? ( get_the_post_thumbnail_url( $pid, 'thumbnail' ) ?: null ) : null ),
					'price'      => $price ? (float) $price->get_price() : 0.0,
					'optional'   => ! empty( $row['optional'] ),
					'default'    => ! empty( $row['default'] ),
					'quantity'   => max( 1, (int) ( $row['quantity'] ?? 1 ) ),
				];
			}
		}
		if ( $session && ! empty( $session['selection'] ) ) {
			foreach ( (array) $session['selection'] as $row ) {
				$selection[] = [
					'product_id' => (int) ( $row['product_id'] ?? 0 ),
					'price_id'   => (int) ( $row['price_id'] ?? 0 ),
					'quantity'   => (int) ( $row['quantity'] ?? 1 ),
				];
			}
		}

		return [
			'cart'               => [
				'items'          => $items,
				'needs_shipping' => $cart->needs_shipping(),
				'needs_payment'  => $cart->needs_payment(),
				'currency'       => Formatting::get_currency(),
			],
			'totals'             => [
				'subtotal' => (float) $cart->get_cart_subtotal(),
				'shipping' => (float) $cart->get_shipping_total(),
				'tax'      => (float) $cart->get_taxes_total( true, false ),
				'discount' => (float) $cart->get_discount_total(),
				'total'    => (float) $cart->get_total( 'edit' ),
			],
			'available_gateways' => $gateways,
			'declared_items'     => $declared,
			'selection'          => $selection,
			'checkout_fields'    => array_values( CheckoutFields::all() ),
		];
	}
}
