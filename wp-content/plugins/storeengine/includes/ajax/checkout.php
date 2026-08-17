<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine;
use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\AbstractRequestHandler;
use StoreEngine\Classes\Cache\OrderCache;
use StoreEngine\Classes\CheckoutService;
use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Coupon;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderContext;
use StoreEngine\models\Order as OrderModel;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Geolocation;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\TaxUtil;

class Checkout extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'get_states'             => [
				'callback'             => [ $this, 'get_states' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'cc' => 'string',
				],
			],
			'refund'                 => [
				'callback'             => [ $this, 'refund' ],
				'allow_visitor_action' => false,
				'capability'           => 'manage_options',
				'fields'               => [
					'order_id'               => 'int',
					'refund_type'            => 'string',
					'refund_amount'          => 'float',
					'refund_reason'          => 'string',
					'api_refund'             => 'boolean',
					'refunded_amount'        => 'float',
					'line_item_qtys'         => 'string',
					'line_item_totals'       => 'string',
					'line_item_tax_totals'   => 'string',
					'restock_refunded_items' => 'string',
				],
			],
			'get_order'              => [
				'callback' => [ $this, 'get_order' ],
				'fields'   => [ 'order_id' => 'integer' ]
			]
		];
	}

	protected function get_states( $payload ) {
		if ( empty( $payload['cc'] ) ) {
			wp_send_json_error( esc_html__( 'Country code is required.', 'storeengine' ) );
		}

		$cc     = strtoupper( $payload['cc'] );
		$states = Countries::init()->get_states( $cc );

		wp_send_json_success( [
			'cc'     => $cc,
			'states' => $states ? $states : false,
		] );
	}

	/**
	 * Set checkout data.
	 *
	 * @param Order $order
	 * @param array $data
	 *
	 * @return void
	 * @throws StoreEngineException
	 */
	protected function set_checkout_data( Order $order, array $data ): void {
		OrderCache::delete_draft_order();
		$same_as_shipping = $data['same_as_shipping'] ?? false;

		$need_shipping = StoreEngine::init()->get_cart()->needs_shipping();
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

		// Set order email.
		$order->set_order_email( $data['user_email'] ?? '' );

		// Set current currency code, in case store currency changed.
		$order->set_currency( Formatting::get_currency() );


		// Billing data.
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
		} elseif ( $need_shipping ) {
			Helper::cart()->set_meta( 'chosen_shipping_methods', [] );
		}

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

		// set totals.
		$order->set_shipping_total( Helper::cart()->get_shipping_total() );
		$order->set_shipping_tax( Helper::cart()->get_shipping_tax() );
		$order->set_discount_total( Helper::cart()->get_discount_total() );
		$order->set_discount_tax( Helper::cart()->get_discount_tax() );
		$order->set_cart_tax( Helper::cart()->get_cart_contents_tax() + Helper::cart()->get_fee_tax() );
		$order->set_total( Helper::cart()->get_total( 'edit' ) );

		$order->save();
	}

	/**
	 * @return array
	 * @deprecated
	 */
	protected function prepare_purchase_items_data(): array {
		$cart = Helper::cart();
		do_action( 'storeengine/cart/check_items' );

		$purchase_items = [];
		foreach ( Helper::cart()->get_cart_items() as $cart_item ) {
			$purchase_items[] = [
				'product_id'          => $cart_item['product_id'],
				'variation_id'        => 0,
				'price_id'            => $cart_item['price_id'],
				'price'               => $cart_item['price'],
				'product_qty'         => $cart_item['quantity'],
				'coupon_amount'       => $cart->get_total_discount(),
				'tax_amount'          => $cart->get_taxes_total( true, false ),
				'shipping_amount'     => 0,
				'shipping_tax_amount' => 0,
			];
		}

		return [ $cart, $purchase_items ];
	}

	/**
	 * Prepare order data.
	 *
	 * @param array $data Data.
	 * @param array $purchase_items Purchase Items.
	 *
	 * @return array
	 * @deprecated 1.5.8
	 */
	protected function prepare_order_data( array $data, array $purchase_items ): array {
		return [
			'status'                 => 'draft',
			'currency'               => Helper::get_settings( 'store_currency', 'USD' ),
			'type'                   => Helper::cart()->has_subscription_product() ? 'subscription' : 'onetime',
			'tax_amount'             => Helper::cart()->get_taxes_total( true, false ),
			'total_amount'           => Helper::cart()->get_total( 'draft_order' ),
			'customer_id'            => get_current_user_id(),
			'billing_email'          => $data['billing_email'],
			'payment_method'         => $data['payment_method'] ?? '',
			'payment_method_title'   => $data['payment_method'] ?? '',
			'customer_note'          => $data['order_note'] ?? '',
			'transaction_id'         => null,
			'purchase_items'         => $purchase_items,
			'order_billing_address'  => [
				'first_name' => $data['billing_first_name'] ?? '',
				'last_name'  => $data['billing_last_name'] ?? '',
				'company'    => $data['billing_company'] ?? '',
				'address_1'  => $data['billing_address_1'] ?? '',
				'address_2'  => $data['billing_address_2'] ?? '',
				'city'       => $data['billing_city'] ?? '',
				'state'      => $data['billing_state'] ?? '',
				'postcode'   => $data['billing_postcode'] ?? '',
				'country'    => $data['billing_country'] ?? '',
				'email'      => $data['billing_email'] ?? '',
				'phone'      => $data['billing_phone'] ?? '',
			],
			'order_shipping_address' => [
				'first_name' => $data['billing_first_name'] ?? '',
				'last_name'  => $data['billing_last_name'] ?? '',
				'company'    => $data['billing_company'] ?? '',
				'address_1'  => $data['billing_address_1'] ?? '',
				'address_2'  => $data['billing_address_2'] ?? '',
				'city'       => $data['billing_city'] ?? '',
				'state'      => $data['billing_state'] ?? '',
				'postcode'   => $data['billing_postcode'] ?? '',
				'country'    => $data['billing_country'] ?? '',
				'email'      => $data['billing_email'] ?? '',
				'phone'      => $data['billing_phone'] ?? '',
			],
		];
	}

	public function get_order( array $payload ) {
		if ( empty( $payload['order_id'] ) ) {
			wp_send_json_error( esc_html__( 'Order ID is required.', 'storeengine' ) );
		}

		$order = Helper::get_order( $payload['order_id'] );

		if ( ! current_user_can( 'manage_orders' ) && get_current_user_id() !== $order->get_customer_id() ) {
			wp_send_json_error( esc_html__( "You doesn't have enough permission to view this order.", 'storeengine' ) );
		}

		$result = CheckoutService::prepare_checkout_response( $order, [] );

		wp_send_json_success( $result['order'] );
	}

	protected function create_or_update_customer( Order $order ) {
		$customer_id = $order->get_customer_id();
		if ( ! $customer_id ) {
			$customer_id = $this->create_customer( $order->get_billing_first_name(), $order->get_billing_last_name(), $order->get_order_email() );

			if ( is_wp_error( $customer_id ) ) {
				return $customer_id;
			} else {
				$order->set_customer_id( $customer_id );
				$order->save();
			}
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
			// Save Shipping details.
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

	protected function create_customer( $first_name, $last_name, $email ) {
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

			return new \WP_Error(
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

		// Don't pass password through any filter.
		$userdata['user_pass'] = wp_generate_password();

		$user_id = wp_insert_user( $userdata );

		if ( ! is_wp_error( $user_id ) ) {
			wp_signon( array(
				'user_login'    => $username,
				'user_password' => $userdata['user_pass'],
				'remember'      => true,
			), is_ssl() );

			do_action( 'storeengine/checkout/customer_created', $user_id, $userdata );
		}

		return $user_id;
	}

	/**
	 * @param Coupon[] $coupons
	 *
	 * @return void
	 */
	protected function update_coupon_usage( array $coupons, Order $order ) {
		foreach ( $coupons as $coupon ) {
			add_post_meta( $coupon->get_id(), '_storeengine_coupon_used_by', $order->get_customer_id() );
			$usage_count = (int) get_post_meta( $coupon->get_id(), '_storeengine_coupon_usage_count', true );
			update_post_meta( $coupon->get_id(), '_storeengine_coupon_usage_count', $usage_count + 1 );
		}
	}

	private function validate_checkout_data( array $data ) {
		$required_fields = [
			'billing_first_name',
			'billing_last_name',
			'billing_address_1',
			'billing_city',
			'billing_postcode',
			'billing_country',
			'billing_email',
			'billing_phone',
		];
		$missing_fields  = [];

		$cart = Helper::cart();
		if ( $cart->needs_payment() ) {
			$required_fields[] = 'payment_method';
		}

		// loop through all the fields and check if they are empty if empty return error for that field
		foreach ( $required_fields as $field ) {
			if ( empty( $data[ $field ] ) ) {
				$missing_fields[] = $field;
			}
		}

		if ( ! empty( $missing_fields ) ) {
			// Name the actual missing fields so the shopper knows exactly what to
			// fix, matching CheckoutService::validate().
			$labels_by_key = [];
			foreach ( \StoreEngine\Utils\CheckoutFields::all() as $row ) {
				$labels_by_key[ $row['payload_key'] ] = $row['label'];
			}
			$labels_by_key['payment_method'] = __( 'Payment method', 'storeengine' );

			$missing_labels = array_values( array_unique( array_map(
				static function ( $key ) use ( $labels_by_key ) {
					return $labels_by_key[ $key ] ?? $key;
				},
				$missing_fields
			) ) );

			wp_send_json_error( [
				'message' => sprintf(
					/* translators: %s: comma-separated list of the missing required field labels. */
					esc_html__( 'Please fill in the required field(s): %s', 'storeengine' ),
					implode( ', ', $missing_labels )
				),
				'fields'  => $missing_fields,
			] );
		}


		$coupons = $cart->get_coupons();

		if ( empty( $coupons ) || is_user_logged_in() || ! $cart->has_items() ) {
			return;
		}

		foreach ( $coupons as $coupon ) {
			$is_valid = $coupon->validate_coupon( false );

			if ( is_wp_error( $is_valid ) ) {
				wp_send_json_error( $is_valid->get_error_message() );

				continue;
			}

			if ( $coupon->get_usage_limit_per_user() > 0 ) {
				$user = get_user_by( 'email', $data['user_email'] );
				if ( ! $user ) {
					continue;
				}

				if ( $coupon->get_usage_by_user_id( $user->ID ) >= $coupon->get_usage_limit_per_user() ) {
					wp_send_json_error( esc_html__( 'Sorry, Coupon has reached its limit', 'storeengine' ) );
				}
			}
		}
	}

	/**
	 * @param $data
	 *
	 * @return void
	 * @deprecated 1.5.8
	 */
	public function change_subscription( $data ) {
		if ( empty( $data['order_id'] ) ) {
			wp_send_json_error( __( 'Order ID is required.', 'storeengine' ) );
		}

		if ( empty( $data['upgrade_price_id'] ) ) {
			wp_send_json_error( __( 'Price ID is required.', 'storeengine' ) );
		}

		$order_id         = $data['order_id'];
		$upgrade_price_id = $data['upgrade_price_id'];

		$order         = new OrderModel();
		$order_data    = $order->get_by_primary_key( $order_id );
		$cancel_result = $order->cancel_subscription( $order_data );
		if ( is_wp_error( $cancel_result ) ) {
			wp_send_json_error( $cancel_result->get_error_message() );
		}

		Helper::cart()->clear_cart();
		Helper::cart()->add_product_to_cart( $upgrade_price_id );

		$purchase_items = $this->prepare_purchase_items_data();

		$order_data = $this->prepare_order_data( $order_data, $purchase_items );
		$order->save( $order_data );

		do_action( 'storeengine/frontend/subscription/after_change_subscription', $data, $order_id );

		wp_send_json_success( 'Subscription status updated successfully.' );
	}

	public function refund( $data ) {
		$order = Helper::get_order( absint( $data['order_id'] ?? 0 ) );

		if ( is_wp_error( $order ) ) {
			wp_send_json_error( $order->get_error_message() );
		}

		if ( ! $order->get_id() ) {
			wp_send_json_error( esc_html__( 'Order not found', 'storeengine' ), 404 );
		}

		if ( empty( $data['refund_type'] ) ) {
			wp_send_json_error( esc_html__( 'Select if it is a full or partial refund.', 'storeengine' ) );
		}

		if ( 'full' === $data['refund_type'] ) {
			$refund_amount = Formatting::format_decimal( $order->get_total( 'refund' ) - $order->get_total_refunded( 'refund' ), Formatting::get_price_decimals() );
		} else {
			$refund_amount = Formatting::format_decimal( sanitize_text_field( wp_unslash( $data['refund_amount'] ?? 0 ) ), Formatting::get_price_decimals() );
		}
		// Required.
		$refund_reason   = isset( $data['refund_reason'] ) ? sanitize_text_field( wp_unslash( $data['refund_reason'] ) ) : '';
		$api_refund      = isset( $data['api_refund'] ) && Formatting::string_to_bool( $data['api_refund'] );
		$refunded_amount = Formatting::format_decimal( sanitize_text_field( wp_unslash( $data['refunded_amount'] ?? 0 ) ), Formatting::get_price_decimals() );

		// Optional.
		$line_item_qtys         = isset( $data['line_item_qtys'] ) ? json_decode( sanitize_text_field( wp_unslash( $data['line_item_qtys'] ) ), true ) : [];
		$line_item_totals       = isset( $data['line_item_totals'] ) ? json_decode( sanitize_text_field( wp_unslash( $data['line_item_totals'] ) ), true ) : [];
		$line_item_tax_totals   = isset( $data['line_item_tax_totals'] ) ? json_decode( sanitize_text_field( wp_unslash( $data['line_item_tax_totals'] ) ), true ) : [];
		$restock_refunded_items = isset( $data['restock_refunded_items'] ) && Formatting::string_to_bool( $data['restock_refunded_items'] );

		try {
			$max_refund = Formatting::format_decimal( $order->get_total() - $order->get_total_refunded(), Formatting::get_price_decimals() );
			if ( ( ! $refund_amount && ( Formatting::format_decimal( 0, Formatting::get_price_decimals() ) !== $refund_amount ) ) || $max_refund < $refund_amount || 0 > $refund_amount ) {
				throw new StoreEngineException( esc_html__( 'Invalid refund amount', 'storeengine' ), 'invalid_refund_amount' );
			}

			if ( Formatting::format_decimal( $order->get_total_refunded(), Formatting::get_price_decimals() ) !== $refunded_amount ) {
				throw new StoreEngineException( esc_html__( 'Error processing refund. Please try again.', 'storeengine' ), 'invalid_refunded_amount' );
			}

			// Prepare line items which we are refunding.
			$line_items = [];
			$item_ids   = array_unique( array_merge( array_keys( $line_item_qtys ), array_keys( $line_item_totals ) ) );

			foreach ( $item_ids as $item_id ) {
				$line_items[ $item_id ] = [
					'qty'          => 0,
					'refund_total' => 0,
					'refund_tax'   => [],
				];
			}
			foreach ( $line_item_qtys as $item_id => $qty ) {
				$line_items[ $item_id ]['qty'] = max( $qty, 0 );
			}
			foreach ( $line_item_totals as $item_id => $total ) {
				$line_items[ $item_id ]['refund_total'] = Formatting::format_decimal( $total );
			}
			foreach ( $line_item_tax_totals as $item_id => $tax_totals ) {
				$line_items[ $item_id ]['refund_tax'] = array_filter( Formatting::format_decimal_array( $tax_totals ) );
			}

			// Optional opt-in (used by the order editor refund modal) to deactivate
			// licenses generated by this order. Travels in create_refund() $args to
			// the storeengine/order/refund_created action (handled by license-management).
			$deactivate_licenses = isset( $data['deactivate_licenses'] ) && Formatting::string_to_bool( $data['deactivate_licenses'] );

			// Create the refund object.
			// Mirrors the standard line-item refund handler.
			$refund = Helper::create_refund( [
				'amount'              => $refund_amount,
				'reason'              => $refund_reason,
				'order_id'            => $order->get_id(),
				'line_items'         => $line_items,
				'refund_payment'      => $api_refund,
				'restock_items'      => $restock_refunded_items,
				'deactivate_licenses' => $deactivate_licenses,
			] );


			if ( is_wp_error( $refund ) ) {
				throw StoreEngineException::from_wp_error( $refund );
			}

			// create_refund() flips order status/paid_status in the DB but does not
			// invalidate the cached order object; drop it so the next order fetch
			// (the editor refetches after refund) reflects the new status/totals.
			wp_cache_delete( 'orders_' . $order->get_id(), 'storeengine_orders' );

			$status = '';
			if ( did_action( 'storeengine/order/partially_refunded' ) ) {
				$status = 'partially_refunded';
			}

			if ( did_action( 'storeengine/order/fully_refunded' ) ) {
				$status = 'fully_refunded';
			}

			// Reload order data from db.
			$order->read( true );

			wp_send_json_success( array_merge(
				Helper::get_payment_data( $order ),
				[
					'refunds_total'   => $order->get_total_refunded( true ),
					'refunded_amount' => $order->get_total_refunded( true ),
					'refund_status'   => $status,
				]
			) );

		} catch ( StoreEngineException $e ) {
			$order_id = absint( $data['order_id'] ?? 0 );
			$order_obj = Helper::get_order( $order_id );
			$customer_email = ( ! is_wp_error( $order_obj ) && $order_obj ) ? $order_obj->get_billing_email() : 'Unknown Email';

			\StoreEngine\Classes\Logger::log(
				sprintf( 'Refund Failed - Order #%d', $order_id ),
				[
					'customer_email' => $customer_email,
					'message'        => $e->getMessage(),
					'code'           => $e->get_wp_error_code(),
					'request_data'   => $data
				],
				\StoreEngine\Classes\Logger::ERROR,
				'refund'
			);

			wp_send_json_error( $e->get_wp_error() );
		}
	}

	protected function subscribe_to_email( array $data ) {
		if ( isset( $data['subscribe_to_email'] ) ) {
			$customer = Helper::get_customer();
			$customer->set_subscribe_to_email( true );
			$customer->save();
		}
	}

}
