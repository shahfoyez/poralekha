<?php
/**
 * Checkout Service.
 *
 * Single source of truth for the order-placement pipeline. Used by the REST
 * surface (traditional /checkout/ page + embedded React checkout) and by
 * the subscription addon when materialising a subscription from a cart.
 *
 * Pure: never calls wp_send_json_*. Returns array on success or WP_Error on
 * failure so the caller can shape the response (REST JSON, CLI output, etc.)
 * however it wants.
 */

namespace StoreEngine\Classes;

use StoreEngine;
use StoreEngine\Classes\Cache\OrderCache;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order\OrderItemCoupon;
use StoreEngine\Classes\Order\OrderItemFee;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Classes\Order\OrderItemShipping;
use StoreEngine\Classes\Order\OrderItemTax;
use StoreEngine\Shipping\ShippingRate;
use StoreEngine\Utils\CheckoutFields;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Geolocation;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\TaxUtil;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CheckoutService {

	/**
	 * Place an order with the given checkout payload.
	 *
	 * @param array      $payload        Checkout fields (mirrors the existing AJAX schema).
	 * @param Order|null $existing_order Optional pre-resolved draft order; if null we look up
	 *                                   the recent draft for the current user.
	 *
	 * @return array|WP_Error On success: { order_id, status, redirect, order, ...gateway-specific }.
	 *                        On failure: WP_Error with a `status` data key (HTTP code) plus optional fields.
	 */
	public static function place_order( array $payload, ?Order $existing_order = null ) {
		try {
			if ( ! defined( 'STOREENGINE_DOING_CHECKOUT' ) ) {
				define( 'STOREENGINE_DOING_CHECKOUT', true );
			}

			$validation = self::validate( $payload );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}
			// validate() returns the payload after all validate_data filters have run
			// (e.g. placeholder-email injection for phone contacts). Propagate those
			// modifications so apply_addresses() and create_customer() see the
			// corrected values rather than the raw pre-filter input.
			$payload = $validation;

			$cart = Helper::cart();
			if ( $cart->needs_shipping() ) {
				if ( empty( $payload['shipping_method'] ) && empty( $cart->get_meta( 'chosen_shipping_methods' ) ) ) {
					return new WP_Error(
						'storeengine_checkout_no_shipping',
						__( 'Sorry, this order requires a shipping option.', 'storeengine' ),
						[ 'status' => 422 ]
					);
				}
			}

			$order = $existing_order;
			if ( ! $order ) {
				$order = Helper::get_recent_draft_order( get_current_user_id(), null, false );
			}
			if ( ! $order ) {
				return new WP_Error(
					'storeengine_checkout_no_order',
					__( 'Order not found.', 'storeengine' ),
					[ 'status' => 404 ]
				);
			}

			do_action( 'storeengine/frontend/checkout/before_place_order', $order );

			// Prepare order data.
			$order->clear_items();
			$order->set_currency( Formatting::get_currency() );
			$order->set_order_placed_date_gmt();
			$order->set_order_placed_date();

			self::apply_addresses( $order, $payload );
			self::subscribe_to_email( $payload );

			$gateway = null;
			if ( $cart->needs_payment() ) {
				$gateway = Helper::get_payment_gateway( $payload['payment_method'] ?? '' );
				if ( ! $gateway ) {
					return new WP_Error(
						'storeengine_checkout_invalid_gateway',
						__( 'Invalid payment gateway.', 'storeengine' ),
						[ 'status' => 422 ]
					);
				}
				$order->set_payment_method( $gateway );
			} else {
				$order->set_payment_method( '' );
			}

			$order->set_customer_id( apply_filters( 'storeengine/frontend/checkout/customer_id', get_current_user_id() ) );
			$order->set_prices_include_tax( TaxUtil::prices_include_tax() );
			$order->set_ip_address( Geolocation::get_user_ip() );
			$order->set_user_agent( Helper::get_user_agent() );

			if ( ! empty( $payload['order_note'] ) ) {
				$order->set_customer_note( (string) $payload['order_note'] );
			}

			$customer_obj = StoreEngine::init()->customer;
			if ( $customer_obj ) {
				$order->update_meta_data( 'is_vat_exempt', $customer_obj->get_is_vat_exempt() );
			}

			// Order items — snapshot the cart onto the order.
			self::add_product( $order, $cart );
			self::add_fee( $order, $cart );
			self::add_subscription_setup_fees( $order, $cart );
			self::add_shipping( $order, $cart );
			self::apply_coupon( $order, $cart );
			self::add_tax( $order, $cart );

			$order->set_cart_hash( $cart->get_cart_hash() );

			$customer = self::create_or_update_customer( $order );
			if ( is_wp_error( $customer ) ) {
				return $customer;
			}

			$order_context = new OrderContext( $order->get_status() );
			$order_context->proceed_to_next_status( 'order_placed', $order );

			// Save before payment so the gateway can read order id / meta.
			$order->save();
			$order->read( true );

			// Reserve stock for the next 60 minutes so concurrent checkouts can't oversell.
			$reservation_items = [];
			foreach ( $order->get_items( 'line_item' ) as $line_item ) {
				$reservation_items[] = [
					'product_id'   => method_exists( $line_item, 'get_product_id' ) ? (int) $line_item->get_product_id() : 0,
					'variation_id' => method_exists( $line_item, 'get_variation_id' ) ? (int) $line_item->get_variation_id() : 0,
					'quantity'     => method_exists( $line_item, 'get_quantity' ) ? (int) $line_item->get_quantity() : 0,
				];
			}
			\StoreEngine\Classes\StockManager::reserve_stock_for_order( $order->get_id(), $reservation_items, 60 );

			// Capture coupons before the cart is cleared.
			$coupons = $cart->get_coupons();

			do_action( 'storeengine/checkout/order_processed', $order, $payload );

			$result = [];
			if ( $cart->needs_payment() && $gateway ) {
				$payment = $gateway->process_payment( $order );
				if ( is_wp_error( $payment ) ) {
					return $payment;
				}
				if ( is_array( $payment ) ) {
					$result = $payment;
				}
			} else {
				// Mark paid before transitioning — OrderContext::proceed_to_next_status()
				// promotes Processing → Completed only when paid_status is already 'paid'
				// and the order is flagged as digital auto-complete. Setting paid after
				// the transition would skip that branch and leave a digital free order
				// stuck in Processing. If the transition throws, revert the in-memory
				// paid_status so a later save() can't persist a paid-but-not-advanced state.
				$order->set_paid_status( 'paid' );
				try {
					$ctx2 = new OrderContext( $order->get_status() );
					$ctx2->proceed_to_next_status( 'processing', $order );
				} catch ( \Throwable $e ) {
					$order->set_paid_status( 'unpaid' );
					throw $e;
				}
				$order->save();
				$result = [
					'result'   => 'success',
					'redirect' => $order->get_checkout_order_received_url(),
				];
			}

			do_action( 'storeengine/checkout/after_place_order', $order, $payload );

			self::update_coupon_usage( $coupons, $order );

			$result['order_id'] = $order->get_id();
			$result['status']   = $order->get_status();

			if ( isset( $result['result'] ) && 'success' === $result['result'] ) {
				$result = self::prepare_checkout_response( $order, $result );
			}

			$cart->clear_cart();

			return $result;
		} catch ( StoreEngineException $e ) {
			$customer_email = $payload['billing_email'] ?? ( $payload['user_email'] ?? 'Unknown Email' );

			Logger::log(
				sprintf( 'Checkout Failed (%s)', $customer_email ),
				[
					'customer_email' => $customer_email,
					'message'        => $e->getMessage(),
					'code'           => $e->get_wp_error_code(),
					'trace'          => $e->getTraceAsString(),
					'payload'        => $payload,
				],
				Logger::ERROR,
				'checkout'
			);

			return new WP_Error(
				$e->get_wp_error_code() ?: 'storeengine_checkout_failed',
				$e->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	/**
	 * Recalculate cart with new field values (address change, shipping, etc.).
	 *
	 * @return array Response payload (mirrors the legacy `update_checkout` AJAX shape minus
	 *               the wp_send_json wrapper).
	 */
	public static function update_checkout( array $data ): array {
		$data        = apply_filters( 'storeengine/frontend/checkout/before_update_draft_order', $data );
		$draft_order = Helper::get_recent_draft_order();
		$old_payment_method = $draft_order ? $draft_order->get_payment_method( 'edit' ) : '';

		$old_shipping_method = Helper::cart()->get_meta( 'chosen_shipping_methods' );
		$old_shipping_method = $old_shipping_method ? reset( $old_shipping_method ) : false;

		// Snapshot the PREVIOUS address from the draft order before
		// apply_addresses() overwrites it. We can't read "old" from the cart
		// customer here: the REST layer (Checkout::sync_cart_from_fields) has
		// already written the new address onto it, so a customer-vs-payload diff
		// would always be equal and the `refresh` flag below would never flip —
		// leaving the totals/shipping fragment stale until a full page reload.
		$old_address_snapshot = [];
		if ( $draft_order ) {
			$old_address_getters = [
				'billing_city'         => 'get_billing_city',
				'billing_state'        => 'get_billing_state',
				'billing_postcode'     => 'get_billing_postcode',
				'billing_country'      => 'get_billing_country',
				'shipping_city'        => 'get_shipping_city',
				'shipping_state'       => 'get_shipping_state',
				'shipping_postal_code' => 'get_shipping_postcode',
				'shipping_country'     => 'get_shipping_country',
			];
			foreach ( $old_address_getters as $key => $getter ) {
				$old_address_snapshot[ $key ] = method_exists( $draft_order, $getter ) ? (string) $draft_order->$getter( 'edit' ) : '';
			}
		}

		self::apply_addresses( $draft_order, $data );
		$draft_order->set_cart_hash( Helper::cart()->get_cart_hash() );
		$draft_order->save();

		do_action( 'storeengine/frontend/checkout/update_checkout', $draft_order );
		$data['order'] = $draft_order->get_id();

		$response = [
			'order'                   => $data,
			'massage'                 => esc_html__( 'Order updated successfully.', 'storeengine' ),
			'hash'                    => $draft_order->get_hash(),
			'refresh_payment_methods' => false,
			'needs_shipping'          => StoreEngine::init()->get_cart()->needs_shipping(),
			'same_as_shipping'        => $data['same_as_shipping'] ?? false,
		];

		if ( TaxUtil::is_tax_enabled() || \StoreEngine\Utils\ShippingUtils::is_shipping_enabled() ) {
			$keys        = [
				'billing_city', 'billing_state', 'billing_postcode', 'billing_country',
				'shipping_city', 'shipping_state', 'shipping_postal_code', 'shipping_country',
			];
			$new_address = [];
			$old_address = [];
			foreach ( $keys as $key ) {
				$new_val = (string) ( $data[ $key ] ?? '' );
				$old_val = $old_address_snapshot[ $key ] ?? '';
				if ( str_starts_with( $key, 'billing_' ) ) {
					$new_address['billing'][] = $new_val;
					$old_address['billing'][] = $old_val;
				} else {
					$new_address['shipping'][] = $new_val;
					$old_address['shipping'][] = $old_val;
				}
			}

			if ( ! StoreEngine::init()->get_cart()->needs_shipping() ) {
				unset( $new_address['shipping'], $old_address['shipping'] );
			}

			$response['refresh'] = md5( wp_json_encode( $new_address ) ) !== md5( wp_json_encode( $old_address ) );

			if ( $old_shipping_method && ! empty( $data['shipping_method'] ) && $data['shipping_method'] !== $old_shipping_method ) {
				$response['refresh'] = true;
			}
		}

		$new_payment_method = $draft_order->get_payment_method( 'edit' );
		if ( $old_payment_method !== $new_payment_method ) {
			$response['refresh_payment_methods'] = true;
		} elseif ( 'cod' === $new_payment_method && ! empty( $response['refresh'] ) ) {
			$response['refresh_payment_methods'] = true;
		}

		return $response;
	}

	/**
	 * Validate the place-order payload. Returns the (possibly-augmented) data on success,
	 * WP_Error with a `fields` data key on failure.
	 */
	public static function validate( array $data ) {
		/**
		 * Allow addons to pre-process / gate the payload before validation runs
		 * (e.g. inject a synthesized email in phone-only mode, or reject an
		 * unverified contact). Returning a WP_Error short-circuits the checkout.
		 *
		 * @param array $data Checkout payload.
		 */
		$data = apply_filters( 'storeengine/checkout/validate_data', $data );
		if ( is_wp_error( $data ) ) {
			return $data;

		}
		$pre_validation = apply_filters( 'storeengine/checkout/validate', null, $data );
		if ( is_wp_error( $pre_validation ) ) {
			return $pre_validation;
		}

		$cart            = Helper::cart();
		$needs_shipping  = $cart ? $cart->needs_shipping() : false;
		$required_fields = CheckoutFields::required_payload_keys( $needs_shipping, (string) ( $data['shipping_country'] ?? '' ) );

		// `billing_email` shouldn't be required separately if `email` (user_email)
		// is the canonical contact field — make sure we accept either when both
		// are listed as required.
		if ( in_array( 'user_email', $required_fields, true ) && empty( $data['user_email'] ) && ! empty( $data['billing_email'] ) ) {
			$data['user_email'] = $data['billing_email'];
		}

		$missing_fields = [];

		if ( $cart && $cart->needs_payment() ) {
			$required_fields[] = 'payment_method';
		}

		foreach ( $required_fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				$missing_fields[] = $field;
			}
		}

		if ( ! empty( $missing_fields ) ) {
			// Name the actual missing fields (e.g. "Please fill in the required
			// field(s): City, Postcode") instead of a generic prompt, so the
			// shopper knows exactly what to fix. Both checkout surfaces render
			// this message verbatim, so this single change covers both.
			$labels_by_key = [];
			foreach ( CheckoutFields::all() as $row ) {
				$labels_by_key[ $row['payload_key'] ] = $row['label'];
			}
			$labels_by_key['payment_method'] = __( 'Payment method', 'storeengine' );

			$missing_labels = array_values( array_unique( array_map(
				static function ( $key ) use ( $labels_by_key ) {
					return $labels_by_key[ $key ] ?? $key;
				},
				$missing_fields
			) ) );

			return new WP_Error(
				'storeengine_checkout_missing_fields',
				sprintf(
					/* translators: %s: comma-separated list of the missing required field labels. */
					__( 'Please fill in the required field(s): %s', 'storeengine' ),
					implode( ', ', $missing_labels )
				),
				[ 'status' => 422, 'fields' => $missing_fields ]
			);
		}

		// Email format check — prevents `set_order_email` from throwing a
		// fatal further down the place-order pipeline. Addons can drop the
		// requirement (e.g. phone-only mode) via `storeengine/checkout/require_email`.
		$require_email = (bool) apply_filters( 'storeengine/checkout/require_email', true, $data );
		if ( $require_email && ! empty( $data['user_email'] ) && ! is_email( $data['user_email'] ) ) {
			return new WP_Error(
				'storeengine_checkout_invalid_email',
				__( 'Please enter a valid email address.', 'storeengine' ),
				[ 'status' => 422, 'fields' => [ 'user_email' ] ]
			);
		}

		// Country gate mirroring the conventional checkout validation,
		// rejecting a billing/shipping country that's outside the allowed list.
		// The storefront's country `<select>` already filters to the allow-list
		// at render time (frontend/functions.php:868-880); this is the
		// server-side safety net for direct REST submissions / scripted clients.
		//
		// Trusted-operator contexts (POS, admin-created orders, subscription
		// renewals) don't route through this validate() call and are
		// structurally bypassed — matches the "validate_checkout only runs
		// on storefront flow" pattern.
		$allowed_sell = array_keys( \StoreEngine\Classes\Countries::init()->get_allowed_countries() );
		if ( ! empty( $allowed_sell ) && ! empty( $data['billing_country'] ) && ! in_array( $data['billing_country'], $allowed_sell, true ) ) {
			return new WP_Error(
				'storeengine_checkout_country_restricted',
				__( 'We do not currently sell to your billing country. Please contact us if you believe this is an error.', 'storeengine' ),
				[ 'status' => 422, 'fields' => [ 'billing_country' ] ]
			);
		}

		if ( $needs_shipping && ! empty( $data['shipping_country'] ) ) {
			$allowed_ship = array_keys( \StoreEngine\Classes\Countries::init()->get_shipping_countries() );
			if ( ! empty( $allowed_ship ) && ! in_array( $data['shipping_country'], $allowed_ship, true ) ) {
				return new WP_Error(
					'storeengine_checkout_ship_country_restricted',
					__( 'We do not currently ship to the selected shipping country.', 'storeengine' ),
					[ 'status' => 422, 'fields' => [ 'shipping_country' ] ]
				);
			}
		}

		$coupons = $cart->get_coupons();
		if ( empty( $coupons ) || is_user_logged_in() || ! $cart->has_items() ) {
			return $data;
		}

		foreach ( $coupons as $coupon ) {
			$is_valid = $coupon->validate_coupon( false );
			if ( is_wp_error( $is_valid ) ) {
				return new WP_Error(
					$is_valid->get_error_code() ?: 'storeengine_checkout_coupon_invalid',
					$is_valid->get_error_message(),
					[ 'status' => 422 ]
				);
			}

			if ( $coupon->get_usage_limit_per_user() > 0 ) {
				$user = get_user_by( 'email', $data['user_email'] ?? '' );
				if ( ! $user ) {
					continue;
				}
				if ( $coupon->get_usage_by_user_id( $user->ID ) >= $coupon->get_usage_limit_per_user() ) {
					return new WP_Error(
						'storeengine_checkout_coupon_limit',
						__( 'Sorry, Coupon has reached its limit', 'storeengine' ),
						[ 'status' => 422 ]
					);
				}
			}
		}

		return $data;
	}

	/**
	 * Hydrate billing + shipping props on an order from the checkout payload.
	 * Mirrors the legacy `Ajax\Checkout::set_checkout_data` exactly.
	 */
	public static function apply_addresses( Order $order, array $data ): void {
		OrderCache::delete_draft_order();

		// Form-encoded radio values arrive here as the literal strings "true" /
		// "false". A naive `(bool) "false"` returns true (non-empty string),
		// which silently overwrites a customer's "Use a different billing
		// address" choice on every checkout-field debounce. Route through the
		// shared tolerant coercer instead — same one CheckoutFields uses for
		// its enabled/required flags.
		$same_as_shipping = isset( $data['same_as_shipping'] )
			? CheckoutFields::to_bool( $data['same_as_shipping'] )
			: false;

		$need_shipping = StoreEngine::init()->get_cart()->needs_shipping();

		// Persist the customer's explicit choice on the cart so the next
		// render of billing-address.php can honour it (otherwise the template
		// falls back to comparing the two addresses and re-flips the radio
		// whenever they happen to match).
		if ( $need_shipping ) {
			Helper::cart()->set_meta( 'same_as_shipping', $same_as_shipping ? 'yes' : 'no' );
		}

		if ( $need_shipping && $same_as_shipping ) {
			$data['billing_first_name'] = $data['shipping_first_name'] ?? '';
			$data['billing_last_name']  = $data['shipping_last_name'] ?? '';
			$data['billing_address_1']  = $data['shipping_address_1'] ?? '';
			$data['billing_address_2']  = $data['shipping_address_2'] ?? '';
			$data['billing_city']       = $data['shipping_city'] ?? '';
			$data['billing_state']      = $data['shipping_state'] ?? '';
			$data['billing_postcode']   = $data['shipping_postal_code'] ?? '';
			$data['billing_country']    = $data['shipping_country'] ?? '';
			$data['billing_email']      = $data['user_email'] ?? '';
			$data['billing_phone']      = $data['shipping_phone'] ?? '';
		}

		// Only persist a syntactically valid email — `update_checkout` runs on
		// every debounced field change while the shopper is typing, so a
		// partial value like "hello" reaches here long before the address is
		// complete. set_order_email() throws on bad input, which used to
		// surface as a 500 fatal in the REST endpoint. Final-submit validation
		// happens in self::validate() before this method runs.
		$candidate_email = $data['user_email'] ?? '';
		if ( $candidate_email && is_email( $candidate_email ) ) {
			$order->set_order_email( $candidate_email );
		}
		$order->set_currency( Formatting::get_currency() );

		$billing_address = [
			'billing_first_name' => $data['billing_first_name'] ?? '',
			'billing_last_name'  => $data['billing_last_name'] ?? '',
			'billing_address_1'  => $data['billing_address_1'] ?? '',
			'billing_address_2'  => $data['billing_address_2'] ?? '',
			'billing_country'    => $data['billing_country'] ?? '',
			'billing_state'      => $data['billing_state'] ?? '',
			'billing_city'       => $data['billing_city'] ?? '',
			'billing_postcode'   => $data['billing_postcode'] ?? '',
			'billing_email'      => $data['billing_email'] ?? '',
			'billing_phone'      => $data['billing_phone'] ?? '',
		];
		$order->set_props( $billing_address );

		if ( ! empty( $data['shipping_method'] ) ) {
			Helper::cart()->set_meta( 'chosen_shipping_methods', [ $data['shipping_method'] ] );
		}
		// If no method was supplied we deliberately leave the existing
		// `chosen_shipping_methods` meta in place. A later step (after
		// $order->save()) will default it to the first available method per
		// package — clearing it here would discard that auto-selection on
		// every checkout-field debounce and re-introduce the "Sorry, this
		// order requires a shipping option" error on submit.

		if ( ! $need_shipping ) {
			$shipping_address = [
				'shipping_first_name' => $data['billing_first_name'] ?? '',
				'shipping_last_name'  => $data['billing_last_name'] ?? '',
				'shipping_address_1'  => $data['billing_address_1'] ?? '',
				'shipping_address_2'  => $data['billing_address_2'] ?? '',
				'shipping_country'    => $data['billing_country'] ?? '',
				'shipping_state'      => $data['billing_state'] ?? '',
				'shipping_city'       => $data['billing_city'] ?? '',
				'shipping_postcode'   => $data['billing_postcode'] ?? '',
				'shipping_email'      => $data['billing_email'] ?? '',
				'shipping_phone'      => $data['billing_phone'] ?? '',
			];
		} else {
			$shipping_address = [
				'shipping_first_name' => $data['shipping_first_name'] ?? '',
				'shipping_last_name'  => $data['shipping_last_name'] ?? '',
				'shipping_address_1'  => $data['shipping_address_1'] ?? '',
				'shipping_address_2'  => $data['shipping_address_2'] ?? '',
				'shipping_country'    => $data['shipping_country'] ?? '',
				'shipping_state'      => $data['shipping_state'] ?? '',
				'shipping_city'       => $data['shipping_city'] ?? '',
				'shipping_postcode'   => $data['shipping_postal_code'] ?? '',
				'shipping_email'      => $data['shipping_email'] ?? '',
				'shipping_phone'      => $data['shipping_phone'] ?? '',
			];
		}
		$order->set_props( $shipping_address );

		if ( isset( $data['payment_method'] ) ) {
			$order->set_payment_method( Helper::get_payment_gateway( $data['payment_method'] ) );
		}

		$order->set_shipping_total( Helper::cart()->get_shipping_total() );
		$order->set_shipping_tax( Helper::cart()->get_shipping_tax() );
		$order->set_discount_total( Helper::cart()->get_discount_total() );
		$order->set_discount_tax( Helper::cart()->get_discount_tax() );
		$order->set_cart_tax( Helper::cart()->get_cart_contents_tax() + Helper::cart()->get_fee_tax() );
		$order->set_total( Helper::cart()->get_total( 'edit' ) );

		$order->save();

		// Default chosen_shipping_methods to the first available method per
		// package when shipping is required but the shopper hasn't picked one.
		// Matches the template-side fallback in cart-shipping.php and ensures
		// place_order's "needs a shipping option" guard passes even if the
		// user submits without ever touching the radio.
		if ( $need_shipping && empty( $data['shipping_method'] ) ) {
			$existing = (array) Helper::cart()->get_meta( 'chosen_shipping_methods' );
			$packages = \StoreEngine\Shipping\Shipping::init()->get_packages();
			$resolved = [];
			foreach ( $packages as $i => $package ) {
				if ( ! empty( $existing[ $i ] ) ) {
					$resolved[ $i ] = $existing[ $i ];
					continue;
				}
				$rates = $package['rates'] ?? [];
				if ( ! empty( $rates ) && is_array( $rates ) ) {
					$first          = reset( $rates );
					$resolved[ $i ] = $first ? $first->get_id() : '';
				}
			}
			if ( $resolved ) {
				Helper::cart()->set_meta( 'chosen_shipping_methods', $resolved );
			}
		}
	}

	/**
	 * Subscribe the customer to email if the payload requests it.
	 */
	public static function subscribe_to_email( array $data ): void {
		if ( isset( $data['subscribe_to_email'] ) && StoreEngine::init()->customer ) {
			$customer = StoreEngine::init()->customer;
			$customer->set_subscribe_to_email( true );
			$customer->save();
		}
	}

	/**
	 * Create a customer record for a guest order or update an existing one with
	 * the new addresses. Returns Customer or WP_Error.
	 */
	public static function create_or_update_customer( Order $order ) {
		$customer_id = $order->get_customer_id();
		if ( ! $customer_id ) {
			$customer_id = self::create_customer(
				$order->get_billing_first_name(),
				$order->get_billing_last_name(),
				$order->get_order_email()
			);

			if ( is_wp_error( $customer_id ) ) {
				return $customer_id;
			}
			$order->set_customer_id( $customer_id );
			$order->save();
		}

		$customer = Helper::get_customer( $customer_id );

		// Save Billing details.
		$customer->set_billing_first_name( $order->get_billing_first_name() );
		$customer->set_billing_last_name( $order->get_billing_last_name() );
		$customer->set_billing_address_1( $order->get_billing_address_1() );
		$customer->set_billing_address_2( $order->get_billing_address_2() );
		$customer->set_billing_city( $order->get_billing_city() );
		$customer->set_billing_state( $order->get_billing_state() );
		$customer->set_billing_postcode( $order->get_billing_postcode() );
		$customer->set_billing_country( $order->get_billing_country() );
		$customer->set_billing_phone( $order->get_billing_phone() );
		$customer->set_billing_email( $order->get_billing_email() );

		if ( $order->needs_shipping_address() ) {
			$customer->set_shipping_first_name( $order->get_shipping_first_name() );
			$customer->set_shipping_last_name( $order->get_shipping_last_name() );
			$customer->set_shipping_address_1( $order->get_shipping_address_1() );
			$customer->set_shipping_address_2( $order->get_shipping_address_2() );
			$customer->set_shipping_city( $order->get_shipping_city() );
			$customer->set_shipping_state( $order->get_shipping_state() );
			$customer->set_shipping_postcode( $order->get_shipping_postcode() );
			$customer->set_shipping_country( $order->get_shipping_country() );
			$customer->set_shipping_phone( $order->get_shipping_phone() );
			$customer->set_shipping_email( $order->get_shipping_email() );
		}

		$customer->save();

		return $customer;
	}

	protected static function create_customer( string $first_name, string $last_name, string $email ) {
		$email_exists = email_exists( $email );
		if ( $email_exists ) {
			// An account already uses this email. A guest checkout must NOT silently
			// bind to (and then overwrite) another user's customer record — otherwise
			// anyone who knows a victim's email could attach an order to their account
			// and clobber their stored billing/shipping data. Require the shopper to
			// log in (mirrors the conventional storefront behaviour). A logged-in request that already matches
			// its own account falls through to the normal update path.
			if ( get_current_user_id() === (int) $email_exists ) {
				return $email_exists;
			}

			return new WP_Error(
				'storeengine_email_belongs_to_existing_account',
				__( 'An account is already registered with this email address. Please log in to continue.', 'storeengine' ),
				[
					// Surface an actionable login URL (returns to checkout, email
					// pre-filled) so the UI can render a real "Log in to continue"
					// button next to the message instead of a dead-end error.
					'status'    => 409,
					'login_url' => storeengine_checkout_login_url( $email ),
				]
			);
		}

		$base_username = strstr( $email, '@', true );
		$username      = $base_username;
		$counter       = 1;

		while ( username_exists( $username ) ) {
			$username = $base_username . $counter;
			$counter ++;
		}

		$userdata = apply_filters( 'storeengine/checkout/create_customer_data', [
			'user_login'   => $username,
			'user_email'   => $email,
			'role'         => 'storeengine_customer',
			'display_name' => $first_name . ' ' . $last_name,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
		] );

		$userdata['user_pass'] = wp_generate_password();

		$user_id = wp_insert_user( $userdata );

		if ( ! is_wp_error( $user_id ) ) {
			wp_signon( [
				'user_login'    => $username,
				'user_password' => $userdata['user_pass'],
				'remember'      => true,
			], is_ssl() );
			do_action( 'storeengine/checkout/customer_created', $user_id, $userdata );
		}

		return $user_id;
	}

	/**
	 * @param Coupon[] $coupons
	 */
	public static function update_coupon_usage( array $coupons, Order $order ): void {
		foreach ( $coupons as $coupon ) {
			add_post_meta( $coupon->get_id(), '_storeengine_coupon_used_by', $order->get_customer_id() );
			$usage_count = (int) get_post_meta( $coupon->get_id(), '_storeengine_coupon_usage_count', true );
			update_post_meta( $coupon->get_id(), '_storeengine_coupon_usage_count', $usage_count + 1 );
		}
	}

	/**
	 * Shape the success response with order data, dates, line items, and a redirect URL.
	 */
	public static function prepare_checkout_response( Order $order, array $result ): array {
		$data         = $order->get_data();
		$data['meta'] = $data['meta_data'] ?? [];
		unset( $data['meta_data'] );

		foreach ( [ 'line_items', 'tax_lines', 'shipping_lines', 'fee_lines', 'coupon_lines' ] as $type ) {
			if ( ! empty( $data[ $type ] ) ) {
				$data[ $type ] = array_values( array_map( static function ( $item ) {
					return array_merge( $item->get_data(), [ 'meta' => $item->get_meta_data() ] );
				}, $data[ $type ] ) );
			}
		}

		foreach ( [ 'date_created_gmt', 'date_updated_gmt', 'date_paid_gmt', 'date_completed_gmt', 'order_placed_date_gmt' ] as $date_prop ) {
			if ( ! empty( $data[ $date_prop ] ) && is_a( $data[ $date_prop ], StoreengineDatetime::class ) ) {
				$data[ $date_prop ] = $data[ $date_prop ]->format( 'Y-m-d H:i:s' );
			}
		}

		$result['order'] = $data;

		if ( empty( $result['redirect'] ) ) {
			$result['redirect'] = $order->get_checkout_order_received_url();
		}

		return apply_filters( 'storeengine/checkout/payment_successful', $result, $order->get_id() );
	}

	/**
	 * Snapshot cart line items onto the order as OrderItemProduct rows.
	 *
	 * @throws StoreEngineException When a referenced price/variation can't be resolved.
	 */
	public static function add_product( Order &$order, Cart $cart ) {
		$coupon_discount_per_item = (array) $cart->get_coupon_discount_per_item();

		foreach ( $cart->get_cart_items() as $item_key => $values ) {
			$item  = new OrderItemProduct();
			$price = new Price( $values->price_id );

			if ( ! $price->get_id() ) {
				throw new StoreEngineException( esc_html__( 'Price not found!', 'storeengine' ), 'not_found_price' );
			}

			if ( ! $values->name ) {
				$values->name = $price->get_product_title();
			}

			$line_subtotal = (float) ( $values->line_subtotal ?? 0 );
			$line_total    = (float) ( $values->line_total ?? 0 );

			// The cart bakes coupon discounts into line_total for most items, but
			// NOT for subscription line items — their line_total stays at the
			// pre-discount base. That leaves line_total == line_subtotal, so
			// Order::calculate_totals() (which derives the discount from
			// subtotal - total across line items) recomputes a zero discount and
			// drops the coupon from the order totals and emails. When this item
			// has a coupon discount that the cart failed to apply to line_total,
			// re-derive line_total from the pre-discount subtotal minus this
			// item's discount share so every product type carries the discount
			// consistently. Items whose line_total is already discounted
			// (line_total < line_subtotal) are left untouched.
			$item_discount = isset( $coupon_discount_per_item[ $item_key ] ) ? (float) array_sum( (array) $coupon_discount_per_item[ $item_key ] ) : 0;
			if ( $item_discount > 0 && $line_total >= $line_subtotal ) {
				$line_total = max( 0, $line_subtotal - $item_discount );
			}

			$item->set_props( [
				'name'                  => $values->name,
				'product_id'            => $values->product_id ?? $price->get_product_id(),
				'variation_id'          => $values->variation_id ?? 0,
				'variation'             => $values->variation ?? [],
				'product_type'          => $price->get_product_type() ?? '',
				'shipping_type'         => $price->get_shipping_type() ?? '',
				'digital_auto_complete' => $price->get_digital_auto_complete(),
				'price_type'            => $values->price_type ?? $price->get_price_type(),
				'price_id'              => $price->get_id(),
				'price_name'            => $price->get_name(),
				'price'                 => $price->get_price(),
				'quantity'              => absint( $values->quantity ),
				'tax_class'             => $values->tax_class ?? '',
				'subtotal'              => $line_subtotal,
				'total'                 => $line_total,
				'subtotal_tax'          => $values->line_subtotal_tax ?? 0,
				'total_tax'             => $values->line_tax ?? 0,
				'taxes'                 => $values->line_tax_data ?? [],
			] );

			$item->add_meta_data( '_price_settings', $price->get_settings(), true );

			foreach ( $price->get_settings() as $field => $value ) {
				if ( method_exists( $item, "set_{$field}" ) ) {
					$item->{"set_{$field}"}( $value );
				}
			}

			$item->set_backorder_meta();

			$variation_id = $values->variation_id ?? 0;

			if ( 0 < $variation_id ) {
				$variation = Helper::get_product_variation( $variation_id );
				if ( ! $variation ) {
					throw new StoreEngineException( esc_html__( 'Invalid variation selected!', 'storeengine' ), 'invalid-product-variation' );
				}

				$item->add_meta_data( '_variation_price', (float) $variation->get_price(), true );
				$item->set_price( $price->get_price() + (float) $variation->get_price() );

				foreach ( $variation->get_attributes() as $attribute ) {
					$item->add_meta_data( $attribute->taxonomy, $attribute->slug, true );
				}
			}

			if ( 'bundled' === $item->get_product_type() && $price->get_product()->get_bundles() ) {
				$item->add_meta_data( '_bundles', $price->get_product()->get_bundles(), true );
			}

			/**
			 * @param OrderItemProduct $item
			 * @param \StoreEngine\Classes\CartItem $values
			 * @param Order $order
			 */
			do_action( 'storeengine/checkout/create_order_line_item', $item, $values, $order );

			$order->add_item( $item );
		}

		$order->maybe_set_digital_auto_complete();
	}

	public static function add_fee( Order &$order, Cart $cart ) {
		foreach ( $cart->get_fees() as $fee ) {
			$item = new OrderItemFee();
			$fee  = (object) $fee;

			$item->set_props( [
				'name'      => $fee->name,
				'tax_class' => $fee->taxable ? $fee->tax_class : '',
				'amount'    => $fee->amount,
				'total'     => $fee->total,
				'total_tax' => $fee->tax,
				'taxes'     => [ 'total' => $fee->tax_data ],
			] );

			/**
			 * Fires after creating fee on Order.
			 *
			 * @param OrderItemFee $item ItemFee object.
			 * @param object $fee Fee data.
			 * @param Order $order Order instance.
			 */
			do_action( 'storeengine/checkout/create_order_fee_item', $item, $fee, $order );

			$order->add_item( $item );
		}
	}

	/**
	 * Materialize subscription signup/setup fees as one-time order fee items.
	 *
	 * Non-subscription setup fees are added to the cart as fees (see Cart::
	 * add_product_to_cart) and flow into the order via add_fee(). Subscription
	 * setup fees deliberately are NOT cart fees — they must not recur, so the
	 * subscription addon folds them into the cart's *aggregate* total via a
	 * price-calculation filter instead. That aggregate is display-only: it never
	 * becomes an order item, so Order::calculate_totals() (which sums line items
	 * + fee items) drops the fee and the recorded order total under-runs what
	 * the customer is charged — e.g. base 400 + fee 100 records as 400 while
	 * gateways collect 500.
	 *
	 * Adding the fee here as a one-time OrderItemFee puts it into the order total
	 * (so order-total gateways like Razorpay/PayPal charge it) and the order
	 * record reconciles with itemizing gateways (Paddle/Stripe). It stays off the
	 * recurring subscription, which is built from the recurring cart (fees
	 * removed), and is skipped on renewals.
	 */
	public static function add_subscription_setup_fees( Order &$order, Cart $cart ) {
		if ( $order->get_meta( '_subscription_renewal' ) ) {
			return;
		}

		foreach ( $cart->get_cart_items() as $cart_item ) {
			if ( 'subscription' !== $cart_item->price_type ) {
				continue;
			}

			$fee_price = (float) ( $cart_item->setup_fee_price ?? 0 );
			if ( empty( $cart_item->setup_fee ) || $fee_price <= 0 ) {
				continue;
			}

			$item = new OrderItemFee();
			$item->set_props( [
				'name'      => ! empty( $cart_item->setup_fee_name ) ? $cart_item->setup_fee_name : __( 'Setup Fee', 'storeengine' ),
				'amount'    => $fee_price,
				'total'     => $fee_price,
				'total_tax' => 0,
			] );

			/**
			 * Fires after creating a subscription setup-fee item on the order.
			 *
			 * @param OrderItemFee $item      Fee item.
			 * @param object       $cart_item Cart item carrying the setup fee.
			 * @param Order        $order     Order instance.
			 */
			do_action( 'storeengine/checkout/create_order_setup_fee_item', $item, $cart_item, $order );

			$order->add_item( $item );
		}
	}

	public static function add_tax( Order &$order, Cart $cart ) {
		foreach ( array_keys( $cart->get_cart_contents_taxes() + $cart->get_shipping_taxes() + $cart->get_fee_taxes() ) as $tax_rate_id ) {
			if ( $tax_rate_id && apply_filters( 'storeengine/frontend/cart/remove_taxes_zero_rate_id', 'zero-rated' ) === $tax_rate_id ) {
				return;
			}

			$item = new OrderItemTax();
			$item->set_props( [
				'rate_id'            => $tax_rate_id,
				'order_id'           => $order->get_id(),
				'tax_total'          => $cart->get_tax_amount( $tax_rate_id ),
				'shipping_tax_total' => $cart->get_shipping_tax_amount( $tax_rate_id ),
				'rate_code'          => Tax::get_rate_code( $tax_rate_id ),
				'label'              => Tax::get_rate_label( $tax_rate_id ),
				'compound'           => Tax::is_compound( $tax_rate_id ),
				'rate_percent'       => Tax::get_rate_percent_value( $tax_rate_id ),
			] );

			/**
			 * Fires after adding tax on Order.
			 *
			 * @param OrderItemTax $item ItemTax object.
			 * @param int $tax_rate_id Tax rate id.
			 * @param Order $order Order instance.
			 */
			do_action( 'storeengine/checkout/create_order_tax_item', $item, $tax_rate_id, $order );

			$order->add_item( $item );
		}
	}

	public static function add_shipping( Order &$order, Cart $cart ) {
		if ( ! $cart->needs_shipping() ) {
			return;
		}

		$shipping_rates = $cart->get_shipping_methods();
		if ( empty( $shipping_rates ) ) {
			return;
		}

		/** @var ShippingRate $shipping_rate */
		$shipping_rate = $shipping_rates[0];

		$shipping_item = new OrderItemShipping();

		$shipping_item->set_props( [
			'method_title' => $shipping_rate->get_label(),
			'method_id'    => $shipping_rate->get_method_id(),
			'instance_id'  => $shipping_rate->get_instance_id(),
			'total'        => Formatting::format_decimal( $shipping_rate->get_cost() ),
			'taxes'        => [ 'total' => $shipping_rate->get_taxes() ],
			'tax_status'   => $shipping_rate->get_tax_status(),
		] );

		foreach ( $shipping_rate->get_meta_data() as $key => $value ) {
			$shipping_item->add_meta_data( $key, $value, true );
		}

		/**
		 * Fires after adding shipping on Order.
		 *
		 * @param OrderItemShipping $shipping_item Shipping item.
		 * @param ShippingRate $shipping_rate Shipping rate from cart.
		 * @param Order $order Order instance.
		 */
		do_action( 'storeengine/checkout/create_order_shipping_item', $shipping_item, $shipping_rate, $order );

		$order->add_item( $shipping_item );
	}

	public static function apply_coupon( Order &$order, Cart $cart ) {
		foreach ( $cart->get_coupons() as $coupon ) {
			$item = new OrderItemCoupon();

			$item->set_props( [
				'code'         => $coupon->get_code(),
				'discount'     => $cart->get_coupon_discount_amount( $coupon->get_code(), $cart->display_prices_including_tax() ),
				'discount_tax' => $cart->get_coupon_discount_tax_amount( $coupon->get_code() ),
			] );

			do_action( 'storeengine/checkout/create_order_coupon_item', $item, $coupon, $order );

			$order->add_item( $item );
		}
	}
}
