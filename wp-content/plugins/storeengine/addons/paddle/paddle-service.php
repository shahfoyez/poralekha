<?php

namespace StoreEngine\Addons\Paddle;

use StoreEngine\Classes\Order;
use StoreEngine\Payment_Gateways;
use StoreEngine\Utils\Formatting;
use WP_Error;

final class PaddleService {

	protected bool $is_live = false;

	protected string $client_token = '';
	protected string $api_key = '';

	protected string $currency = 'USD';

	/**
	 * List of currencies supported by Paddle.
	 *
	 * @link https://developer.paddle.com/concepts/sell/supported-currencies#supported-payments
	 *
	 * @var array|string[]
	 */
	protected static array $supported_currencies = [
		'USD',
		'EUR',
		'GBP',
		'ARS',
		'AUD',
		'BRL',
		'CAD',
		'CHF',
		'CNY',
		'COP',
		'CZK',
		'DKK',
		'HKD',
		'HUF',
		'ILS',
		'INR',
		'JPY',
		'KRW',
		'MXN',
		'NOK',
		'NZD',
		'PLN',
		'RUB',
		'SEK',
		'SGD',
		'THB',
		'TRY',
		'TWD',
		'UAH',
		'VND',
		'ZAR',
	];

	/**
	 * List of currencies supported by Paddle that has no decimals
	 *
	 * @link https://developer.paddle.com/concepts/sell/supported-currencies#supported-payments
	 *
	 * @var array|string[]
	 */
	protected static array $zero_decimal_currencies = [
		'JPY',
		'KRW',
		'VND',
	];

	protected static array $currency_minimum_charges = [
		'USD' => 0.70,
		'EUR' => 0.65,
		'GBP' => 0.55,
		'ARS' => 780.00,
		'AUD' => 1.10,
		'BRL' => 4.00,
		'CAD' => 1.00,
		'CHF' => 0.60,
		'CNY' => 5.10,
		'COP' => 2980.00,
		'CZK' => 15.50,
		'DKK' => 4.70,
		'HKD' => 5.50,
		'HUF' => 252.00,
		'ILS' => 2.50,
		'INR' => 60.00,
		'JPY' => 100,
		'KRW' => 980,
		'MXN' => 13.70,
		'NOK' => 7.25,
		'NZD' => 1.20,
		'PLN' => 2.65,
		'RUB' => 59.00,
		'SEK' => 6.80,
		'SGD' => 0.90,
		'THB' => 23.00,
		'TRY' => 27.00,
		'TWD' => 25.00,
		'UAH' => 29.00,
		'VND' => 20276,
		'ZAR' => 12.75,
	];

	/**
	 * @var ?GatewayPaddle
	 */
	protected ?GatewayPaddle $gateway;

	protected static ?PaddleService $instance = null;

	public static function init( $gateway = null ): PaddleService {
		if ( null === self::$instance ) {
			self::$instance = new self( $gateway );
		}

		return self::$instance;
	}

	public function __construct( $gateway = null ) {
		if ( $gateway instanceof GatewayPaddle ) {
			$this->gateway = $gateway;
		} else {
			$this->gateway = Payment_Gateways::get_instance()->get_gateway( 'paddle' );
		}

		$this->init_settings();
	}

	public function init_settings() {
		if ( ! $this->gateway ) {
			return;
		}

		if ( ! $this->gateway->is_enabled || 'paddle' !== $this->gateway->id ) {
			return;
		}

		$this->is_live      = $this->gateway->get_option( 'is_production', true );
		$key_type           = $this->is_live ? '' : 'test_';
		$this->client_token = $this->gateway->get_option( $key_type . 'client_token' );
		$this->api_key      = $this->gateway->get_option( $key_type . 'api_key' );
		$this->currency     = Formatting::get_currency();
	}

	/**
	 * List of currencies supported by Paddle
	 *
	 * @return string[]
	 */
	public static function get_supported_currencies(): array {
		return self::$supported_currencies;
	}

	/**
	 * List of currencies supported by Paddle that has no decimals
	 *
	 * @return string[]
	 */
	public static function no_decimal_currencies(): array {
		return self::$zero_decimal_currencies;
	}

	/**
	 * @param string $api_key
	 * @param bool $is_live
	 *
	 * @return bool
	 */
	public static function validate_api_key( string $api_key, bool $is_live = false ): bool {
		$base_url = $is_live ? 'https://api.paddle.com/' : 'https://sandbox-api.paddle.com/';

		$response = wp_remote_get( $base_url . 'event-types', [
			'headers' => [
				'content-type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			],
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		return 200 === $code;
	}

	/**
	 * Get Paddle amount to pay.
	 * Amount is in cents, for some countries it needs to be multiplied by 1000.
	 *
	 * @param float|int $total Amount due.
	 * @param string $currency Accepted currency.
	 *
	 * @return float|int
	 */
	public static function get_paddle_amount( $total, string $currency = '' ) {
		if ( ! $currency ) {
			$currency = Formatting::get_currency();
		}

		if ( in_array( $currency, self::no_decimal_currencies(), true ) ) {
			return absint( round( $total ) );
		}

		return absint( Formatting::format_decimal( ( (float) $total * 100 ), Formatting::get_price_decimals() ) ); // In cents.
	}

	/**
	 * Build a Paddle `billing_cycle` for subscription prices.
	 *
	 * Without this, Paddle treats the line as a one-time charge and never creates a
	 * subscription/subscriber. Returns null for non-subscription prices so they stay
	 * one-time.
	 *
	 * @param string     $price_type    Price type (only 'subscription' recurs).
	 * @param string     $duration_type day|week|month|year (legacy plurals accepted).
	 * @param int|string $duration      Billing frequency (every N intervals).
	 *
	 * @return array{interval:string,frequency:int}|null
	 */
	private static function build_billing_cycle( string $price_type, $duration_type, $duration ): ?array {
		if ( 'subscription' !== $price_type ) {
			return null;
		}

		$map      = [ 'days' => 'day', 'weeks' => 'week', 'months' => 'month', 'years' => 'year' ];
		$interval = $map[ (string) $duration_type ] ?? (string) $duration_type;
		if ( ! in_array( $interval, [ 'day', 'week', 'month', 'year' ], true ) ) {
			$interval = 'month';
		}

		return [
			'interval'  => $interval,
			'frequency' => max( 1, (int) $duration ),
		];
	}

	/**
	 * Build a one-time Paddle line item for a subscription setup/signup fee.
	 *
	 * Has NO billing_cycle, so Paddle charges it only on the first transaction
	 * (alongside the recurring item) and never on renewals.
	 *
	 * The setup fee is NOT folded into the recurring line's per-unit price — the
	 * cart keeps `line_subtotal` as the clean recurring amount and surfaces the
	 * fee only in the aggregate cart total. So this line ADDS the fee once; it
	 * is not compensating for anything baked into the recurring line.
	 * Subscriptions are always quantity 1, so the fee is charged once.
	 *
	 * @return array
	 */
	private static function build_setup_fee_item( string $fee_name, float $fee_price, string $product_name, string $tax_mode, $tax_category, string $currency ): array {
		$fee_name = $fee_name ?: __( 'Setup Fee', 'storeengine' );

		return [
			'price'    => [
				'name'        => $fee_name,
				'description' => __( 'One-time setup fee.', 'storeengine' ),
				'tax_mode'    => $tax_mode,
				'unit_price'  => [
					// Convert using THIS line's currency — a zero-decimal
					// currency (JPY/KRW/VND) must not be multiplied by 100.
					'amount'        => (string) self::get_paddle_amount( $fee_price, $currency ),
					'currency_code' => $currency,
				],
				'product'     => [
					'name'         => trim( $product_name . ' — ' . $fee_name ),
					'tax_category' => $tax_category,
				],
				// No billing_cycle → charged once, not recurring.
			],
			'quantity' => 1,
		];
	}

	public function get_api_url( string $endpoint ): string {
		$base_url = $this->is_live ? 'https://api.paddle.com/' : 'https://sandbox-api.paddle.com/';

		return $base_url . $endpoint;
	}

	public function request( string $endpoint, string $method = 'GET', array $data = [] ) {
		return wp_remote_request( $this->get_api_url( $endpoint ), [
			'method'  => $method,
			'headers' => [
				'content-type'  => 'application/json',
				'Authorization' => 'Bearer ' . $this->api_key,
			],
			'body'    => ! empty( $data ) ? wp_json_encode( $data ) : [],
			'timeout' => 15,
		] );
	}

	/**
	 * Checks Stripe minimum order value authorized per currency
	 */
	public static function get_minimum_amount( $currency = '' ) {
		if ( ! $currency ) {
			$currency = Formatting::get_currency();
		}

		return self::$currency_minimum_charges[ $currency ] ?? .50;
	}

	/**
	 * Build the Paddle `items` payload from the active cart. Used by the
	 * GatewayAdapterInterface::create_intent flow where the draft order is a
	 * fresh skeleton without line items yet.
	 *
	 * @param \StoreEngine\Classes\Cart $cart
	 * @param Order                     $order  Currency / context.
	 * @return array Items array in Paddle's expected shape.
	 */
	public function prepare_items_from_cart( $cart, Order $order ): array {
		if ( ! $cart ) {
			return [];
		}

		$items          = [];
		$paddle_gateway = Payment_Gateways::init()->get_gateway( 'paddle' );
		if ( ! $paddle_gateway ) {
			return [];
		}

		$default_tax_category = $paddle_gateway->get_option( 'default_tax_category' );
		$tax_mode             = $paddle_gateway->get_option( 'tax_mode', 'internal' );

		foreach ( $cart->get_cart_items() as $item ) {
			$product_tax  = get_post_meta( $item->product_id, '_storeengine_paddle_tax_category', true );
			$product_name = $item->name;

			// Setup/signup fee → a SEPARATE one-time Paddle line (added after the main
			// item below) so it's charged once, not folded into a recurring price.
			$setup_fee      = ! empty( $item->setup_fee ) ? (float) ( $item->setup_fee_price ?? 0 ) : 0;
			$setup_fee_name = $setup_fee > 0 ? ( ! empty( $item->setup_fee_name ) ? $item->setup_fee_name : __( 'Setup Fee', 'storeengine' ) ) : '';

			// Mirror prepare_items_from_order(): trust the line's
			// SUBTOTAL (pre-coupon, post-installment). The
			// installment-plan addon's `raw_item_price` filter is
			// applied during cart-totals calculation, so
			// `line_subtotal = installment_fraction × qty`. Coupons
			// only affect `line_total` — keeping subtotal here means
			// the Paddle `discount_id` applies the coupon exactly
			// once on Paddle's side, avoiding the double-apply that
			// happened when we sent `line_total / qty`.
			//
			// Fall back to the raw `price` + setup-fee combination for
			// unstamped early-build states or carts without totals.
			// The setup fee is NOT folded into `line_subtotal`/`line_total`/
			// `price` — those carry the clean recurring amount. The fee only
			// appears in the cart's aggregate subtotal and is billed as its own
			// one-time Paddle line below, so the recurring unit price is used
			// as-is (no fee to strip out). Subscriptions are always quantity 1.
			$qty           = max( 1, (int) ( $item->quantity ?? 1 ) );
			$line_subtotal = isset( $item->line_subtotal ) ? (float) $item->line_subtotal : 0;
			if ( $line_subtotal > 0 ) {
				$unit_price = round( $line_subtotal / $qty, 4 );
			} else {
				// Never silently fall back to the full catalog price: for an
				// installment deposit the authoritative amount-due is the line
				// total (already reduced to the down payment), not `price`.
				// Charging `price` here is what over-charged installment buyers.
				$line_total = isset( $item->line_total ) ? (float) $item->line_total : 0;
				if ( $line_total > 0 ) {
					$unit_price = round( $line_total / $qty, 4 );
				} else {
					$unit_price = (float) ( $item->price ?? 0 );
				}
			}

			$price = [
				// Paddle rejects an empty price name; fall back to the product name.
				'name'        => ( ! empty( $item->price_name ) ? $item->price_name : $product_name ),
				'description' => sprintf(
					/* translators: 1: product ID, 2: price ID */
					__( 'Product ID: %1$s, Price Id: %2$s', 'storeengine' ),
					$item->product_id,
					$item->price_id
				),
				'tax_mode'    => $tax_mode,
				'unit_price'  => [
					'amount'        => (string) self::get_paddle_amount( $unit_price ),
					'currency_code' => $order->get_currency(),
				],
				'custom_data' => [
					'merchant_product_id'   => $item->product_id,
					'merchant_price_id'     => $item->price_id,
					'merchant_variation_id' => $item->variation_id ?? 0,
				],
				'product'     => [
					'name'         => $product_name,
					'tax_category' => ! empty( $product_tax ) ? $product_tax : $default_tax_category,
				],
			];

			// Subscription prices must carry a billing_cycle so Paddle creates a
			// recurring subscription (and a subscriber) instead of a one-time charge.
			$billing_cycle = self::build_billing_cycle( $item->price_type ?? '', $item->payment_duration_type ?? '', $item->payment_duration ?? 0 );
			if ( $billing_cycle ) {
				$price['billing_cycle'] = $billing_cycle;
			}

			$items[] = [
				'price'    => $price,
				'quantity' => (int) $item->quantity,
			];

			if ( $setup_fee > 0 ) {
				$items[] = self::build_setup_fee_item(
					$setup_fee_name,
					$setup_fee,
					$item->name,
					$tax_mode,
					! empty( $product_tax ) ? $product_tax : $default_tax_category,
					$order->get_currency()
				);
			}
		}

		return $items;
	}

	/**
	 * Build the Paddle `items` payload from an existing Order's line items.
	 * Used for re-quoting flows where the order is already hydrated.
	 *
	 * @param Order $order
	 * @return array
	 */
	public function prepare_items_from_order( Order $order ): array {
		$items          = [];
		$paddle_gateway = Payment_Gateways::init()->get_gateway( 'paddle' );
		if ( ! $paddle_gateway ) {
			return [];
		}

		$default_tax_category = $paddle_gateway->get_option( 'default_tax_category' );
		$tax_mode             = $paddle_gateway->get_option( 'tax_mode', 'internal' );

		foreach ( $order->get_items() as $item ) {
			/** @var \StoreEngine\Classes\Order\OrderItemProduct $item */
			$product_tax  = $item->get_meta( '_paddle_tax_category' );
			$product_tax  = empty( $product_tax ) ? get_post_meta( $item->get_product_id(), '_storeengine_paddle_tax_category', true ) : $product_tax;
			$product_name = $item->get_name();

			// Setup/signup fee → a SEPARATE one-time Paddle line (added after the main
			// item). Skipped on renewals — a renewal must not re-charge the signup fee.
			$setup_fee      = ( $item->get_setup_fee() && ! $order->get_meta( '_subscription_renewal' ) ) ? (float) $item->get_setup_fee_price() : 0;
			$setup_fee_name = $setup_fee > 0 ? ( $item->get_setup_fee_name() ?: __( 'Setup Fee', 'storeengine' ) ) : '';

			// Use the line's SUBTOTAL (pre-coupon, post-installment) — NOT
			// `total` and NOT `get_charge_unit_price()` — because
			// GatewayPaddle::create_intent() also sends a `discount_id`
			// derived from the order's coupons. Paddle then applies that
			// discount once on its side. Sending `total/qty` here would
			// pre-subtract the same coupon and Paddle would charge with
			// the discount applied twice.
			//
			// Installment sub-orders are still correct: the installment-
			// plan addon adjusts the cart `raw_item_price` filter, so
			// `subtotal = installment_fraction × qty` (pre-coupon). Paddle
			// then deducts any coupon on top via discount_id. Final
			// total matches the cart's view either way.
			// The setup fee is NOT folded into `subtotal`/`total`/`get_price()`
			// — those carry the clean recurring amount. The fee is billed as its
			// own one-time Paddle line below, so the recurring unit price is used
			// as-is. Subscriptions are always quantity 1.
			$qty           = max( 1, (int) $item->get_quantity() );
			$line_subtotal = (float) $item->get_subtotal();
			if ( $line_subtotal > 0 ) {
				$unit_price = round( $line_subtotal / $qty, 4 );
			} else {
				// Never silently fall back to the full catalog price: for an
				// installment deposit/sub-order the authoritative amount-due is
				// the line total (already the down payment), not get_price().
				$line_total = (float) $item->get_total();
				$unit_price = $line_total > 0 ? round( $line_total / $qty, 4 ) : (float) $item->get_price();
			}

			$price = [
				// Paddle rejects an empty price name; fall back to the product name.
				'name'        => ( $item->get_price_name() ?: $product_name ),
				'description' => sprintf(
					/* translators: 1: product ID, 2: price ID */
					__( 'Product ID: %1$s, Price Id: %2$s', 'storeengine' ),
					$item->get_product_id(),
					$item->get_price_id()
				),
				'tax_mode'    => $tax_mode,
				'unit_price'  => [
					'amount'        => (string) self::get_paddle_amount( $unit_price ),
					'currency_code' => $order->get_currency(),
				],
				'custom_data' => [
					'merchant_product_id'   => $item->get_product_id(),
					'merchant_price_id'     => $item->get_price_id(),
					'merchant_variation_id' => $item->get_variation_id(),
				],
				'product'     => [
					'name'         => $product_name,
					'tax_category' => ! empty( $product_tax ) ? $product_tax : $default_tax_category,
				],
			];

			// Recurring subscription items need a billing_cycle (renewal orders excluded —
			// a renewal is a one-off charge against the existing Paddle subscription).
			if ( ! $order->get_meta( '_subscription_renewal' ) ) {
				$billing_cycle = self::build_billing_cycle( $item->get_price_type(), $item->get_payment_duration_type(), $item->get_payment_duration() );
				if ( $billing_cycle ) {
					$price['billing_cycle'] = $billing_cycle;
				}
			}

			$items[] = [
				'price'    => $price,
				'quantity' => $item->get_quantity(),
			];

			if ( $setup_fee > 0 ) {
				$items[] = self::build_setup_fee_item(
					$setup_fee_name,
					$setup_fee,
					$item->get_name(),
					$tax_mode,
					! empty( $product_tax ) ? $product_tax : $default_tax_category,
					$order->get_currency()
				);
			}
		}

		return $items;
	}

	public function create_transaction( int $order_id, array $items, $discount_id = null ) {
		$response = $this->request( 'transactions', 'POST', [
			'items'       => $items,
			'discount_id' => $discount_id,
			'custom_data' => [
				'merchant_order_id' => $order_id,
			],
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		if ( ! empty( $body ) && ! is_wp_error( $body ) ) {
			$body = json_decode( $body );
		}

		if ( 201 !== $status_code || isset( $body->error ) ) {
			return new WP_Error(
				'paddle_transaction_create_error',
				sprintf(
				// translators: %s. Paddle error message.
					__( '%s.<br/> Please contact the site administrator.', 'storeengine' ),
					$body->error->detail ?? __( 'Paddle request failed', 'storeengine' )
				),
				// Array data (not the raw stdClass) — StoreEngineException reads $data['status'].
				[ 'status' => $status_code ?: 400 ]
			);
		}

		return $body;
	}

	public function update_transaction( string $transaction_id, array $data ) {
		$response = $this->request( 'transactions/' . $transaction_id, 'PATCH', $data );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		if ( ! empty( $body ) && ! is_wp_error( $body ) ) {
			$body = json_decode( $body );
		}
		if ( 200 !== $status_code ) {
			return new WP_Error( 'paddle_transaction_update_error', ucfirst( $body->error->detail ?? __( 'Paddle request failed', 'storeengine' ) ) . '.<br/> Please contact the site administrator.', [ 'status' => $status_code ?: 400 ] );
		}

		return $body;
	}

	/**
	 * Create a Paddle refund adjustment against a transaction.
	 *
	 * @param string $transaction_id Paddle transaction id (txn_…).
	 * @param array  $items          [{ item_id, type:'full' } | { item_id, type:'partial', amount }].
	 * @param string $reason         Refund reason (shown in Paddle).
	 *
	 * @return object|WP_Error The created adjustment, or WP_Error on failure.
	 */
	public function create_refund_adjustment( string $transaction_id, array $items, string $reason = '' ) {
		$response = $this->request( 'adjustments', 'POST', [
			'action'         => 'refund',
			'transaction_id' => $transaction_id,
			'reason'         => $reason ?: __( 'Refund', 'storeengine' ),
			'items'          => array_values( $items ),
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ) );

		if ( 201 !== $status_code || isset( $body->error ) ) {
			$detail = $body->error->detail ?? __( 'Paddle refund request failed.', 'storeengine' );

			// WP_Error data must be an ARRAY (StoreEngineException reads $data['status']);
			// passing the raw Paddle stdClass response here triggers a fatal downstream.
			return new WP_Error( 'paddle_refund_error', rtrim( (string) $detail, '.' ) . '.', [ 'status' => $status_code ?: 400 ] );
		}

		return $body->data ?? $body;
	}

	public function get_transaction( string $transaction_id ) {
		$response = $this->request( 'transactions/' . $transaction_id );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return new WP_Error( 'paddle_invalid_transaction', 'Invalid paddle transaction Id.' );
		}

		$body = wp_remote_retrieve_body( $response );
		$body = json_decode( $body );

		if ( ! $body || ! isset( $body->data->id ) ) {
			return new WP_Error( 'paddle_get_transaction_error', 'Failed to get paddle transaction.' );
		}

		return $body->data;
	}

	/**
	 * Fetch a Paddle customer's email address by customer id.
	 *
	 * Used by the webhook order-resolution fallback when a transaction has no
	 * (resolvable) merchant_order_id — e.g. transactions created directly in
	 * Paddle (payment links, Retain). Returns '' when unavailable.
	 */
	public function get_customer_email( string $customer_id ): string {
		if ( ! $customer_id ) {
			return '';
		}

		$response = $this->request( 'customers/' . $customer_id );
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return '';
		}

		$body = json_decode( wp_remote_retrieve_body( $response ) );

		return isset( $body->data->email ) ? sanitize_email( $body->data->email ) : '';
	}

	public function create_discount( int $order_id, array $coupons ) {
		if ( empty( $coupons ) ) {
			return new WP_Error( 'paddle_empty_coupons', 'Empty coupons on checkout.' );
		}

		$amount = 0;
		foreach ( $coupons as $coupon_amount ) {
			$amount += $coupon_amount;
		}

		$response = $this->request( 'discounts', 'POST', [
			'amount'        => (string) $this->get_paddle_amount( $amount ),
			'description'   => "Discount for order #$order_id",
			'type'          => 'flat',
			'mode'          => 'custom',
			'usage_limit'   => 1,
			'currency_code' => $this->currency,
			'custom_data'   => [
				'merchant_order_id' => $order_id,
				'coupons'           => $coupons,
			],
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		if ( ! empty( $body ) ) {
			$body = json_decode( $body );
		}

		if ( 201 !== $status_code ) {
			return new WP_Error( 'paddle_discount_create_error', ucfirst( $body->error->detail ) . '.<br/> Please contact the site administrator.' );
		}

		return $body;
	}
}
