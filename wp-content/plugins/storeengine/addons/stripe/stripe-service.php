<?php

namespace StoreEngine\Addons\Stripe;

use Exception;
use StoreEngine\Addons\Stripe\StripeCustomer as StoreEngineStripeCustomerEntity;
use StoreEngine\Classes\Customer;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Order;
use StoreEngine\Payment_Gateways;
use StoreEngine\Stripe\Account;
use StoreEngine\Stripe\BankAccount;
use StoreEngine\Stripe\Card;
use StoreEngine\Stripe\Customer as StripeCustomer;
use StoreEngine\Stripe\Exception\ApiErrorException;
use StoreEngine\Stripe\Exception\AuthenticationException;
use StoreEngine\Stripe\PaymentIntent;
use StoreEngine\Stripe\PaymentMethod;
use StoreEngine\Stripe\Price as StripePrice;
use StoreEngine\Stripe\Product as StripeProduct;
use StoreEngine\Stripe\Refund;
use StoreEngine\Stripe\SetupIntent;
use StoreEngine\Stripe\Source;
use StoreEngine\Stripe\StripeClient;
use StoreEngine\Stripe\Subscription as StripeSubscription;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use Throwable;
use WP_Error;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class StripeService {

	/**
	 * Option storing on-demand Stripe Product ids, keyed by mode (live|test)
	 * then by md5(name), so native-subscription line items can reference a real
	 * `product` id instead of the unsupported inline `product_data`.
	 */
	const PRODUCT_CACHE_OPTION = 'storeengine_stripe_products';

	protected bool $is_live = false;

	protected string $publishable_key = '';

	protected string $secret_key = '';

	protected string $redirect_url = '';

	protected string $currency = 'USD';

	/**
	 * List of currencies supported by Stripe.
	 *
	 * @link https://docs.stripe.com/currencies
	 *
	 * @var array|string[]
	 */
	protected static array $supported_currencies = [
		'USD',
		'AED',
		'AFN',
		'ALL',
		'AMD',
		'ANG',
		'AOA',
		'ARS',
		'AUD',
		'AWG',
		'AZN',
		'BAM',
		'BBD',
		'BDT',
		'BGN',
		'BIF',
		'BMD',
		'BND',
		'BOB',
		'BRL',
		'BSD',
		'BWP',
		'BYN',
		'BZD',
		'CAD',
		'CDF',
		'CHF',
		'CLP',
		'CNY',
		'COP',
		'CRC',
		'CVE',
		'CZK',
		'DJF',
		'DKK',
		'DOP',
		'DZD',
		'EGP',
		'ETB',
		'EUR',
		'FJD',
		'FKP',
		'GBP',
		'GEL',
		'GIP',
		'GMD',
		'GNF',
		'GTQ',
		'GYD',
		'HKD',
		'HNL',
		'HTG',
		'HUF',
		'IDR',
		'ILS',
		'INR',
		'ISK',
		'JMD',
		'JPY',
		'KES',
		'KGS',
		'KHR',
		'KMF',
		'KRW',
		'KYD',
		'KZT',
		'LAK',
		'LBP',
		'LKR',
		'LRD',
		'LSL',
		'MAD',
		'MDL',
		'MGA',
		'MKD',
		'MMK',
		'MNT',
		'MOP',
		'MUR',
		'MVR',
		'MWK',
		'MXN',
		'MYR',
		'MZN',
		'NAD',
		'NGN',
		'NIO',
		'NOK',
		'NPR',
		'NZD',
		'PAB',
		'PEN',
		'PGK',
		'PHP',
		'PKR',
		'PLN',
		'PYG',
		'QAR',
		'RON',
		'RSD',
		'RUB',
		'RWF',
		'SAR',
		'SBD',
		'SCR',
		'SEK',
		'SGD',
		'SHP',
		'SLE',
		'SOS',
		'SRD',
		'STD',
		'SZL',
		'THB',
		'TJS',
		'TOP',
		'TRY',
		'TTD',
		'TWD',
		'TZS',
		'UAH',
		'UGX',
		'UYU',
		'UZS',
		'VND',
		'VUV',
		'WST',
		'XAF',
		'XCD',
		'XOF',
		'XPF',
		'YER',
		'ZAR',
		'ZMW',
	];

	/**
	 * List of currencies supported by Stripe that has no decimals
	 * https://docs.stripe.com/currencies#zero-decimal from https://docs.stripe.com/currencies#presentment-currencies
	 * ugx is an exception and not in this list for being a special cases in Stripe https://docs.stripe.com/currencies#special-cases
	 *
	 * @var array|string[]
	 */
	protected static array $zero_decimal_currencies = [
		'BIF', // Burundian Franc
		'CLP', // Chilean Peso
		'DJF', // Djiboutian Franc
		'GNF', // Guinean Franc
		'JPY', // Japanese Yen
		'KMF', // Comorian Franc
		'KRW', // South Korean Won
		'MGA', // Malagasy Ariary
		'PYG', // Paraguayan Guaraní
		'RWF', // Rwandan Franc
		//'UGX', // Ugandan Shilling
		'VND', // Vietnamese Đồng
		'VUV', // Vanuatu Vatu
		'XAF', // Central African Cfa Franc
		'XOF', // West African Cfa Franc
		'XPF', // Cfp Franc
	];

	/**
	 * List of currencies supported by Stripe that has three decimals
	 * https://docs.stripe.com/currencies?presentment-currency=AE#three-decimal
	 *
	 * @var array|string[]
	 */
	protected static array $three_decimal_currencies = [
		'BHD', // Bahraini Dinar
		'JOD', // Jordanian Dinar
		'KWD', // Kuwaiti Dinar
		'OMR', // Omani Rial
		'TND', // Tunisian Dinar
	];

	protected static array $currency_minimum_charges = [
		'USD' => 0.50,
		'AED' => 2.00,
		'AUD' => 0.50,
		'BGN' => 1.00,
		'BRL' => 0.50,
		'CAD' => 0.50,
		'CHF' => 0.50,
		'CZK' => 15.00,
		'DKK' => 2.50,
		'EUR' => 0.50,
		'GBP' => 0.30,
		'HKD' => 4.00,
		'HUF' => 175.00,
		'INR' => 0.50,
		'JPY' => 50,
		'MXN' => 10,
		'MYR' => 2,
		'NOK' => 3.00,
		'NZD' => 0.50,
		'PLN' => 2.00,
		'RON' => 2.00,
		'SEK' => 3.00,
		'SGD' => 0.50,
		'THB' => 10,
	];

	/**
	 * @var ?GatewayStripe
	 */
	protected ?GatewayStripe $gateway;

	private ?StripeClient $stripe_client = null;

	protected static ?StripeService $instance = null;

	public static function init( $gateway = null ): StripeService {
		if ( null === self::$instance ) {
			self::$instance = new self( $gateway );
		}

		return self::$instance;
	}

	public function get_client(): ?StripeClient {
		return $this->stripe_client;
	}

	public function __construct( $gateway = null ) {
		if ( $gateway instanceof GatewayStripe ) {
			$this->gateway = $gateway;
		} else {
			$this->gateway = Payment_Gateways::get_instance()->get_gateway( 'stripe' );
		}

		$this->init_settings();
	}

	public function init_settings() {
		global $wp;

		if ( ! $this->gateway ) {
			return;
		}

		if ( ! $this->gateway->is_enabled || 'stripe' !== $this->gateway->id ) {
			return;
		}

		// WP-Org doesn't allow certificate files in the repo.
		// Using ca bundle from WP-core.
		\StoreEngine\Stripe\Stripe::setCABundlePath( ABSPATH . WPINC . '/certificates/ca-bundle.crt' );

		$this->is_live         = $this->gateway->get_option( 'is_production', true );
		$key_type              = $this->is_live ? '' : 'test_';
		$this->publishable_key = $this->gateway->get_option( $key_type . 'publishable_key' );
		$this->secret_key      = $this->gateway->get_option( $key_type . 'secret_key' );
		$this->currency        = Formatting::get_currency();
		$this->redirect_url    = home_url( $wp->request );

		if ( ! $this->secret_key ) {
			return;
		}

		$this->stripe_client = new StripeClient( $this->secret_key );
	}

	public function get_customer( Customer $customer, bool $create = true ) {
		if ( ! $customer->get_id() ) {
			return new WP_Error( 'customer_not_found', esc_html__( 'Customer not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		// Create a Stripe customer if not stored
		$customer_id = get_user_meta( $customer->get_id(), 'stripe_customer_id', true );

		if ( ! $customer_id && $create ) {
			$stripe_customer = $this->stripe_client->customers->create( [
				'email'          => $customer->get_billing_email() ?: $customer->get_email(),
				'name'           => $customer->get_billing_full_name(),
				'phone'          => $customer->get_billing_phone(),
				'invoice_prefix' => 'SE', // @TODO: Allow store to change that via gateway settings.
				'metadata'       => [
					'email' => $customer->get_email(),
					'se_id' => $customer->get_id(),
				],
			] );

			// Save stripe customer id.
			$customer_id = $stripe_customer->id;
			update_user_meta( $customer->get_id(), 'stripe_customer_id', $customer_id );
		}

		return $customer_id;
	}

	public function create_checkout_payment_intent( Order $order, ?float $amount = null, ?string $stripe_customer_id = null, bool $save_method = false ): PaymentIntent {
		try {
			if ( ! $order->get_id() ) {
				throw new StoreEngineException( esc_html__( 'Order not found.', 'storeengine' ), 'order_not_found', null, 404 );
			}

			$checkout_customer_name  = $order->get_billing_first_name( 'edit' ) ?? 'Guest User – ' . $order->get_order_key( 'edit' );
			$checkout_customer_email = $order->get_billing_email() ?? 'guest.' . $order->get_order_key( 'edit' ) . '@' . wp_parse_url( get_site_url(), PHP_URL_HOST );

			if ( ! $amount ) {
				$amount = Helper::cart() ? Helper::cart()->get_total( 'create_payment' ) : 0;
			}

			// Authoritative fallback: derive the amount from the order when there
			// is no live checkout cart (renewal / order-pay). See the matching
			// note in self::create_payment_intent().
			if ( ! $amount && $order->get_id() ) {
				$amount = (float) $order->get_total( 'edit' );
			}

			// Never send a $0 / sub-minimum amount to Stripe — fail with an
			// actionable message instead of the opaque minimum-charge error.
			self::assert_minimum_charge_amount( $order, (float) $amount );

			if ( $stripe_customer_id ) {
				$customer = $this->stripe_client->customers->retrieve( $stripe_customer_id );
				$order->add_meta_data( '_stripe_customer_id', $stripe_customer_id, true );
			} else {
				$customer = $this->create_customer( $checkout_customer_name, $checkout_customer_email );
				$order->add_meta_data( '_stripe_customer_id', $customer->id, true );
			}

			$args = [
				'currency'             => $order->get_currency( 'edit' ),
				'amount'               => self::get_stripe_amount( $amount, $order->get_currency() ),
				'payment_method_types' => [ 'card' ],
//				'automatic_payment_methods' => [
//					'enabled'         => true,
//					'allow_redirects' => 'never', // for now only non-redirect based payment.
//				],
				'description'          => 'Payment for ' . get_bloginfo( 'name' ) . ' Order #' . $order->get_id(),
				'customer'             => $customer->id,
				'metadata'             => [
					'customer_name'          => $checkout_customer_name,
					'customer_email'         => $checkout_customer_email,
					'storeengine_order_id'   => $order->get_id(),
					'storeengine_order_hash' => $order->get_cart_hash(),
					'site_url'               => home_url(),
					'user_id'                => get_current_user_id(),
				],
			];

			if ( $save_method ) {
				$args['setup_future_usage'] = 'off_session';
			}

			return $this->stripe_client->paymentIntents->create( $args );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-create-payment-intent' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped inside called function.
		}
	}

	/**
	 * Creates and confirm a setup intent with the given payment method ID.
	 *
	 * @param string $return_url
	 *
	 * @return SetupIntent
	 * @throws StoreEngineException
	 */
	public function create_setup_intent( int $user_id, string $return_url = '' ): SetupIntent {
		try {
			// Determine the customer managing the payment methods, create one if we don't have one already.
			$customer = new StoreEngineStripeCustomerEntity( $user_id );

			// Manually create the payment information array to create & confirm the setup intent.

			return $this->stripe_client->setupIntents->create( [
				'customer'             => $customer->update_or_create_customer(), // Stripe Customer ID.
				'payment_method_types' => [ 'card' ],
				'usage'                => 'off_session',
				'metadata'             => [
					'site_url' => home_url(),
					'user_id'  => get_current_user_id(),
				],
//					'return_url'     => $return_url, // necessary when user chooses payment option that needs redirect (e.g. Bank).
//					'use_stripe_sdk' => true, // required when using server side flow, we're using client side (js) flow.
//					'confirm'        => true, // required when using server side flow, we're using client side (js) flow.
			] );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-create-setup-intent' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		} catch ( Throwable $e ) {
			throw StoreEngineException::convert_exception( $e, 'failed-to-create-setup-intent' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped inside called function.
		}
	}

	/**
	 * @param Order $order
	 * @param ?string $stripe_customer_id
	 * @param ?float $amount
	 * @param ?string $payment_method_id
	 *
	 * @return PaymentIntent
	 * @throws StoreEngineException
	 *
	 * @see self::create_checkout_payment_intent()
	 */
	public function create_payment_intent(
		Order $order,
		?float $amount = null,
		?string $stripe_customer_id = null,
		?string $payment_method_id = null,
		array $extra = []
	): PaymentIntent {
		try {
			if ( ! $order->get_id() ) {
				throw new StoreEngineException( esc_html__( 'Order not found.', 'storeengine' ), 'order_not_found', null, 404 );
			}

			$checkout_customer_name  = $order->get_billing_first_name( 'edit' ) ?? 'Guest User – ' . $order->get_order_key( 'edit' );
			$checkout_customer_email = $order->get_billing_email() ?? 'guest.' . $order->get_order_key( 'edit' ) . '@' . wp_parse_url( get_site_url(), PHP_URL_HOST );

			if ( ! $amount ) {
				$amount = Helper::cart() ? Helper::cart()->get_total( 'create_payment' ) : 0;
			}

			// Authoritative fallback for off-session charges. Renewal / order-pay
			// charges run without a live checkout cart, so the amount must come
			// from the order itself. This previously only fired when the
			// `_subscription_renewal` meta was present, which left other renewal
			// variants (installment, resubscribe, early/manual renewal) sending a
			// $0 amount to Stripe and tripping the "minimum charge amount" error.
			if ( ! $amount && $order->get_id() ) {
				$amount = (float) $order->get_total( 'edit' );
			}

			// Never send a $0 / sub-minimum amount to Stripe — fail with an
			// actionable message instead of the opaque minimum-charge error.
			self::assert_minimum_charge_amount( $order, (float) $amount );

			if ( $stripe_customer_id ) {
				$existing_stripe_customer = true;
				$customer                 = $this->stripe_client->customers->retrieve( $stripe_customer_id );
				$order->add_meta_data( '_stripe_customer_id', $stripe_customer_id, true );
			} else {
				$customer                 = $this->create_customer( $checkout_customer_name, $checkout_customer_email );
				$existing_stripe_customer = false;
				$order->add_meta_data( '_stripe_customer_id', $customer->id, true );
			}

			$args = [
				//'capture_method'            => 'automatic', // seems redundant // Important for automatic capture.
				'currency'                  => $order->get_currency( 'edit' ),
				'amount'                    => self::get_stripe_amount( $amount, $order->get_currency() ),
//				'payment_method_types'      => [ 'card' ],
				'automatic_payment_methods' => [
					'enabled'         => true,
					'allow_redirects' => 'never', // for now only non-redirect based payment.
				],
				'description'               => 'Payment for ' . get_bloginfo( 'name' ) . ' Order #' . $order->get_id(),
				'customer'                  => $customer->id,
				'metadata'                  => [
					'site_url'               => home_url(),
					'customer_name'          => $checkout_customer_name,
					'customer_email'         => $checkout_customer_email,
					'storeengine_order_id'   => $order->get_id(),
					'storeengine_order_hash' => $order->get_order_key(),
				],
			];

			if ( $this->gateway->has_subscription( $order ) && ! $payment_method_id ) {
				$args['setup_future_usage'] = 'off_session';
			}

			if ( get_current_user_id() ) {
				$args['metadata']['user_id'] = get_current_user_id();
			}

			if ( $payment_method_id ) {
				$payment_method = $this->stripe_client->paymentMethods->retrieve( $payment_method_id );
				if ( ! $existing_stripe_customer || ! $payment_method->customer ) {
					try {
						$this->stripe_client->paymentMethods->attach( $payment_method_id, [ 'customer' => $stripe_customer_id ] );
					} catch ( ApiErrorException $e ) {
						Helper::log_error( $e );
					}
				}

				$args['off_session']    = true;
				$args['payment_method'] = $payment_method_id;
				$args['confirm']        = true;
				$args['expand']         = [ 'latest_charge.balance_transaction' ];
			}

			if ( ! empty( $extra ) ) {
				$args = array_merge( $args, $extra );
			}

			return $this->stripe_client->paymentIntents->create( $args );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-create-payment-intent' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped inside called function.
		}
	}

//	public function create_payment_intent_and_charge_for_subscription( $subscription ): PaymentIntent {
//		try {
//			$this->stripe_client->paymentMethods->attach( $subscription['meta']['stripe_payment_method_id'], [ 'customer' => $subscription['meta']['stripe_customer_id'] ] );
//			$previousPaymentIntents = $this->stripe_client->paymentIntents->retrieve( $subscription['meta']['stripe_payment_intent_id'] );
//
//			return $this->stripe_client->paymentIntents->create( [
//				// As it's cents, it would not be 1000
//				'amount'                    => self::get_stripe_amount( $subscription['total_amount'], $previousPaymentIntents->currency ),
//				'currency'                  => $previousPaymentIntents->currency,
//				'automatic_payment_methods' => [
//					'enabled'         => true,
//					'allow_redirects' => 'never',
//				],
//				'description'               => 'Renewal Payment for ' . get_bloginfo( 'name' ) . ' subscription-' . $subscription['id'],
//				'customer'                  => $subscription['meta']['stripe_customer_id'],
//				'setup_future_usage'        => 'on_session',
//				// 'off_session' for one-time payments
//				'payment_method'            => $subscription['meta']['stripe_payment_method_id'],
//				'confirm'                   => true,
//			] );
//		} catch ( ApiErrorException $e ) {
//			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-get-payment-intent' );
//		}
//	}

	/**
	 * Map a StoreEngine billing duration type to a Stripe recurring interval.
	 *
	 * @param string $duration_type day|week|month|year (singular or plural).
	 *
	 * @return string Stripe interval (day|week|month|year).
	 */
	public static function map_billing_interval( string $duration_type ): string {
		$map = [
			'day'    => 'day',
			'days'   => 'day',
			'week'   => 'week',
			'weeks'  => 'week',
			'month'  => 'month',
			'months' => 'month',
			'year'   => 'year',
			'years'  => 'year',
		];

		return $map[ strtolower( trim( $duration_type ) ) ] ?? 'month';
	}

	/**
	 * Stripe caps a recurring interval at one year. Cap the interval_count so we
	 * fail loudly in our own code rather than getting a Stripe API error.
	 *
	 * @param string $interval Stripe interval (day|week|month|year).
	 *
	 * @return int
	 */
	public static function max_interval_count( string $interval ): int {
		switch ( $interval ) {
			case 'day':
				return 365;
			case 'week':
				return 52;
			case 'month':
				return 12;
			case 'year':
			default:
				return 1;
		}
	}

	/**
	 * @param string $name
	 *
	 * @return StripeProduct
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function create_product( string $name ): StripeProduct {
		try {
			return $this->stripe_client->products->create( [
				'name' => $name,
				'type' => 'service',
			] );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-create-product' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped inside called function.
		}
	}

	/**
	 * @param $product_id
	 * @param $price
	 * @param $interval
	 * @param $interval_count
	 *
	 * @return StripePrice
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function create_price( $product_id, $price, $interval, $interval_count ): StripePrice {
		try {
			return $this->stripe_client->prices->create( [
				'product'     => $product_id,
				'unit_amount' => self::get_stripe_amount( $price, $this->currency ),
				'currency'    => $this->currency,
				'recurring'   => [
					'interval'       => $interval,
					'interval_count' => $interval_count,
				],
			] );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-create-price' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped inside called function.
		}
	}

	/**
	 * @param $name
	 * @param $email
	 *
	 * @return StripeCustomer
	 * @throws StoreEngineException
	 */
	public function create_customer( $name, $email ): StripeCustomer {
		try {
			return $this->stripe_client->customers->create( [
				'name'  => $name,
				'email' => $email,
			] );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-create-customer' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped inside called function.
		}
	}

	/**
	 * Resolve a Stripe Product id for a subscription/invoice line name.
	 *
	 * Stripe's Subscriptions `items[].price_data` and `add_invoice_items[].price_data`
	 * accept only an existing `product` id — inline `product_data` is a
	 * Checkout-Sessions-only feature, so passing it here fails with
	 * "Received unknown parameter: items[0][price_data][product_data]. Did you
	 * mean product?". We create a lightweight Product on demand and cache its id
	 * (per name, per live/test mode) so repeat subscriptions reuse it instead of
	 * piling up duplicate products in the Stripe dashboard.
	 *
	 * @param string $name Human-readable line name.
	 *
	 * @return string Stripe Product id (prod_…).
	 * @throws StoreEngineException
	 */
	protected function get_or_create_product( string $name ): string {
		$name = '' !== trim( $name ) ? trim( $name ) : __( 'Subscription', 'storeengine' );
		$mode = $this->is_live ? 'live' : 'test';
		$key  = md5( $name );

		$cache = get_option( self::PRODUCT_CACHE_OPTION, [] );
		if ( ! is_array( $cache ) ) {
			$cache = [];
		}

		if ( ! empty( $cache[ $mode ][ $key ] ) ) {
			return (string) $cache[ $mode ][ $key ];
		}

		try {
			$product = $this->stripe_client->products->create( [
				'name'     => $name,
				'metadata' => [ 'created_by' => 'StoreEngine' ],
			] );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-create-product' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped inside called function.
		}

		$cache[ $mode ][ $key ] = $product->id;
		update_option( self::PRODUCT_CACHE_OPTION, $cache, false );

		return $product->id;
	}

	/**
	 * Create a native Stripe (Billing) subscription using inline price_data, so
	 * there are no Stripe Product/Price objects to pre-create or cache.
	 *
	 * @param string $customer_id            Stripe customer id (cus_…).
	 * @param array  $items                  Recurring items. Each:
	 *                                        ['name', 'amount'(float), 'interval'(day|week|month|year),
	 *                                         'interval_count'(int), 'quantity'(int)].
	 * @param string $default_payment_method Stripe payment method id (pm_…).
	 * @param array  $args                   Optional:
	 *                                        ['currency', 'trial_period_days'(int),
	 *                                         'add_invoice_items'(array of ['name','amount','quantity']),
	 *                                         'metadata'(array), 'payment_behavior', 'off_session'(bool)].
	 *
	 * @return StripeSubscription
	 * @throws StoreEngineException
	 */
	public function create_subscription( string $customer_id, array $items, string $default_payment_method, array $args = [] ): StripeSubscription {
		$currency = strtolower( $args['currency'] ?? $this->currency );

		$line_items = [];
		foreach ( $items as $item ) {
			$interval       = self::map_billing_interval( $item['interval'] ?? 'month' );
			$interval_count = min( max( 1, (int) ( $item['interval_count'] ?? 1 ) ), self::max_interval_count( $interval ) );

			$line_items[] = [
				'price_data' => [
					'currency'    => $currency,
					'unit_amount' => self::get_stripe_amount( $item['amount'] ?? 0, $currency ),
					'recurring'   => [
						'interval'       => $interval,
						'interval_count' => $interval_count,
					],
					'product'     => $this->get_or_create_product( (string) ( $item['name'] ?? '' ) ),
				],
				'quantity'   => max( 1, (int) ( $item['quantity'] ?? 1 ) ),
			];
		}

		$params = [
			'customer'               => $customer_id,
			'items'                  => $line_items,
			'default_payment_method' => $default_payment_method,
			'payment_behavior'       => $args['payment_behavior'] ?? 'error_if_incomplete',
			'off_session'            => $args['off_session'] ?? true,
			'expand'                 => [ 'latest_invoice.payment_intent' ],
			'metadata'               => array_merge( [ 'created_by' => 'StoreEngine' ], $args['metadata'] ?? [] ),
		];

		if ( ! empty( $args['trial_period_days'] ) ) {
			$params['trial_period_days'] = (int) $args['trial_period_days'];
		}

		// One-time charges billed on the subscription's FIRST invoice (setup fee,
		// or one-time products in a mixed cart).
		if ( ! empty( $args['add_invoice_items'] ) && is_array( $args['add_invoice_items'] ) ) {
			$invoice_items = [];
			foreach ( $args['add_invoice_items'] as $invoice_item ) {
				$invoice_items[] = [
					'price_data' => [
						'currency'    => $currency,
						'unit_amount' => self::get_stripe_amount( $invoice_item['amount'] ?? 0, $currency ),
						'product'     => $this->get_or_create_product( (string) ( $invoice_item['name'] ?? __( 'One-time item', 'storeengine' ) ) ),
					],
					'quantity'   => max( 1, (int) ( $invoice_item['quantity'] ?? 1 ) ),
				];
			}
			$params['add_invoice_items'] = $invoice_items;
		}

		try {
			return $this->stripe_client->subscriptions->create(
				apply_filters( 'storeengine/stripe/create_subscription_args', $params, $items, $args )
			);
		} catch ( ApiErrorException $e ) {
			// A cached product id can go stale if the Stripe product was deleted
			// (common after wiping test-mode data). Purge the cache and rebuild
			// the line items once so we don't fail with a "No such product" error.
			if ( 'resource_missing' === $e->getStripeCode() ) {
				delete_option( self::PRODUCT_CACHE_OPTION );
			}
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-create-subscription' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped inside called function.
		}
	}

	/**
	 * Update a Stripe subscription (e.g. cancel_at_period_end, pause/resume,
	 * default_payment_method change).
	 *
	 * @param string $subscription_id
	 * @param array  $params
	 *
	 * @return StripeSubscription
	 * @throws StoreEngineException
	 */
	public function update_subscription( string $subscription_id, array $params ): StripeSubscription {
		try {
			return $this->stripe_client->subscriptions->update( $subscription_id, $params );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-update-subscription' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	public function get_payment_intent( $stripe_payment_intent, $expand = false ): PaymentIntent {
		try {
			if ( $expand === true ) {
				return $this->stripe_client->paymentIntents->retrieve(
					$stripe_payment_intent,
					[ 'expand' => [ 'latest_charge.balance_transaction' ] ]
				);
			}

			return $this->stripe_client->paymentIntents->retrieve( $stripe_payment_intent );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-get-payment-intent' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped inside called function.
		}
	}


	public function cancel_subscription( $subscription_id ) {
		try {
			return $this->stripe_client->subscriptions->cancel( $subscription_id );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-cancel-subscription' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- escaped inside called function.
		}
	}

	/**
	 * @param string $customer_id
	 *
	 * @return \StoreEngine\Stripe\Collection
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function list_subscriptions( string $customer_id ): \StoreEngine\Stripe\Collection {
		try {
			return $this->stripe_client->subscriptions->all( [ 'customer' => $customer_id ] );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-retrieve-subscriptions' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * @param $subscription_id
	 *
	 * @return StripeSubscription
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function retrieve_subscription( $subscription_id ): StripeSubscription {
		try {
			return $this->stripe_client->subscriptions->retrieve( $subscription_id );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-retrieve-subscription' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * @param $subscription_id
	 *
	 * @return StripeSubscription
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function resume_subscription( $subscription_id ): StripeSubscription {
		try {
			return $this->stripe_client->subscriptions->update( $subscription_id, [
				'cancel_at_period_end' => false,
			] );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-update-subscription' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * @param string $customer_id
	 * @param string|int $product_id
	 *
	 * @return false|mixed
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function search_subscription( string $customer_id, $product_id ) {
		$subscriptions = $this->list_subscriptions( $customer_id );
		foreach ( $subscriptions as $subscription ) {
			if ( (int) $subscription->items->data[0]->price->product === (int) $product_id ) {
				return $subscription;
			}
		}

		return false;
	}


	public function create_webhook( array $events, string $url ): \StoreEngine\Stripe\WebhookEndpoint {
		try {
			return $this->stripe_client->webhookEndpoints->create( [
				'url'            => $url,
				'enabled_events' => $events,
			] );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-create-webhook' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	public function get_webhook( string $webhook_id ): \StoreEngine\Stripe\WebhookEndpoint {
		try {
			return $this->stripe_client->webhookEndpoints->retrieve( $webhook_id );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-get-webhook' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * @param string $secret_key
	 *
	 * @return string|WP_Error
	 */
	public static function validate_keys( string $secret_key ) {
		try {
			// WP-Org doesn't allow certificate files in the repo.
			// Using ca bundle from WP-core.
			\StoreEngine\Stripe\Stripe::setCABundlePath( ABSPATH . WPINC . '/certificates/ca-bundle.crt' );
			$stripe  = new StripeClient( $secret_key );
			$account = $stripe->accounts->retrieve();

			return $account->id;
		} catch ( ApiErrorException $e ) {
			Helper::log_error( $e );
			return new WP_Error( 'invalid_secret_key', esc_html( $e->getMessage() ) );
		}
	}

	public static function validate_publishable_key( string $publishable_key ): bool {
		try {
			// WP-Org doesn't allow certificate files in the repo.
			// Using ca bundle from WP-core.
			\StoreEngine\Stripe\Stripe::setCABundlePath( ABSPATH . WPINC . '/certificates/ca-bundle.crt' );

			\StoreEngine\Stripe\Stripe::setApiKey( $publishable_key );
			PaymentMethod::all( [ 'limit' => 1 ] );

			return true;
		} catch ( AuthenticationException $e ) {
			return false;
		} catch ( Exception $e ) {
			return true;
		}
	}

	/**
	 * @param $subscription_id
	 *
	 * @return array
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function get_subscription_current_period_info( $subscription_id ): array {
		$subscription = $this->retrieve_subscription( $subscription_id );
		$period_start = $subscription->current_period_start;
		$period_end   = $subscription->current_period_end;

		return [
			'start' => $period_start,
			'end'   => $period_end,
		];
	}

	/**
	 * @param $amount
	 * @param $currency
	 * @param $source
	 * @param $description
	 *
	 * @return \StoreEngine\Stripe\Charge
	 * @throws StoreEngineException
	 * @deprecated
	 */
	public function create_charge( $amount, $currency, $source, $description ): \StoreEngine\Stripe\Charge {
		try {
			// @TODO use self::get_stripe_amount
			return $this->stripe_client->charges->create( [
				'amount'      => $amount,
				'currency'    => $currency,
				'source'      => $source,
				'description' => $description,
			] );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-create-charge' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * @param string $intent_id
	 * @param array|null $params
	 *
	 * @return PaymentIntent
	 * @throws StoreEngineException
	 */
	public function capture_payment( string $intent_id, ?array $params = null ): PaymentIntent {
		try {
			return $this->stripe_client->paymentIntents->capture( $intent_id, $params );
		} catch ( ApiErrorException $e ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw StoreEngineException::from_stripe_api_error( $e, 'stripe-capture-payment-intent-failed', [
				'intent_id' => $intent_id,
				'params'    => $params
			] );
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * @param string $intent_id
	 * @param array|null $params
	 *
	 * @return PaymentIntent
	 * @throws StoreEngineException
	 */
	public function update_payment_intent( string $intent_id, ?array $params = null ): PaymentIntent {
		try {
			return $this->stripe_client->paymentIntents->update( $intent_id, $params );
		} catch ( ApiErrorException $e ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw StoreEngineException::from_stripe_api_error( $e, 'stripe-update-payment-intent-failed', [
				'intent_id' => $intent_id,
				'params'    => $params
			] );
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * @return true|WP_Error
	 * @deprecated
	 */
	public function is_stripe_configured() {
		if ( empty( $this->secret_key ) ) {
			return new WP_Error( 'empty_secret_key', esc_html__( 'Stripe secret key is empty', 'storeengine' ) );
		}
		$result = $this->validate_keys( $this->secret_key );
		if ( is_wp_error( $result ) ) {
			return new WP_Error( 'invalid_secret_key', esc_html( $result->get_error_message() ) );
		}

		return true;
	}

	/**
	 * Refund charged amount.
	 * Stripe only support `duplicate`, `fraudulent`, or `requested_by_customer` as
	 * refund reason. This is different from the refund reason set by the admin.
	 *
	 * @param string $charge_id
	 * @param float|string $amount
	 * @param Order $order
	 *
	 * @return Refund
	 * @throws StoreEngineException
	 */
	public function refund( string $charge_id, $amount, Order $order ): Refund {
		try {
			// "Reason must be one of duplicate, fraudulent, or requested_by_customer"
			return $this->stripe_client->refunds->create( [
				'charge' => $charge_id,
				'amount' => self::get_stripe_amount( $amount, $order->get_currency() ),
				'expand' => [ 'balance_transaction' ],
			] );
		} catch ( ApiErrorException $e ) {
			// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
			throw StoreEngineException::from_stripe_api_error( $e, 'stripe-payment-refund-failed', [
				'charge_id' => $charge_id,
				'params'    => $amount,
				'order'     => $order->get_id(),
			] );
			// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	public function get_balance_history( $transaction_id ) {
		try {
			return $this->stripe_client->balanceTransactions->retrieve( $transaction_id );
		} catch ( ApiErrorException $e ) {
			Helper::log_error( $e );
			return new WP_Error( 'stripe_api_error', esc_html( $e->getMessage() ) );
		}
	}

	/**
	 * @param string $setup_intent_id
	 *
	 * @return SetupIntent
	 * @throws StoreEngineException
	 */
	public function get_setup_intent( string $setup_intent_id ): SetupIntent {
		try {
			return $this->stripe_client->setupIntents->retrieve( $setup_intent_id );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-retrieve-setup-intent' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	public function get_setup_intents( array $params = null ): \StoreEngine\Stripe\Collection {
		try {
			return $this->stripe_client->setupIntents->all( $params );
		} catch ( ApiErrorException $e ) {
			throw StoreEngineException::from_stripe_api_error( $e, 'failed-to-get-setup-intents' ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	public function getClient(): ?StripeClient {
		return $this->stripe_client;
	}

	/**
	 * Returns a payment method object from Stripe given an ID. Accepts both 'src_xxx' and 'pm_xxx'
	 *  style IDs for backwards compatibility.
	 *
	 * @param string $payment_method_id The ID of the payment method to retrieve.
	 *
	 * @return PaymentMethod|Source|WP_Error
	 * @throws StoreEngineException
	 */
	public function get_payment_method( string $payment_method_id, bool $wp_error = true ) {
		if ( ! $payment_method_id ) {
			return new WP_Error( 'empty_payment_method_or_source_id', esc_html__( 'Payment method or source ID is empty.', 'storeengine' ) );
		}

		try {
			if ( 0 === strpos( $payment_method_id, 'src_' ) ) {
				// Sources have a separate API.
				return $this->stripe_client->sources->retrieve( $payment_method_id );
			}

			// If it's not a source it's a PaymentMethod.
			return $this->stripe_client->paymentMethods->retrieve( $payment_method_id );
		} catch ( ApiErrorException $e ) {
			$type = 0 === strpos( $payment_method_id, 'src_' ) ? 'source' : 'payment_method';

			$exception = StoreEngineException::from_stripe_api_error( $e, 'error-retrieving-stripe-' . $type ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped

			if ( ! $wp_error ) {
				throw $exception;
			}

			Helper::log_error( $e );

			return $exception->toWpError();
		}
	}

	/**
	 * @param PaymentMethod|Source $source
	 * @param array $params
	 *
	 * @return PaymentMethod|Source
	 * @throws StoreEngineException
	 */
	public function update_payment_method( $source, array $params = [] ) {
		try {
			if ( 0 === strpos( $source->id, 'src_' ) ) {
				// Sources have a separate API.
				return $this->stripe_client->sources->update( $source->id, $params );
			}

			// If it's not a source it's a PaymentMethod.
			return $this->stripe_client->paymentMethods->update( $source->id, $params );
		} catch ( ApiErrorException $e ) {
			$type = 0 === strpos( $source->id, 'src_' ) ? 'source' : 'payment_method';

			throw StoreEngineException::from_stripe_api_error( $e, 'error-retrieving-stripe-' . $type ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * Returns true if the provided payment method is a card, false otherwise.
	 *
	 * @param PaymentMethod|Source $payment_method The provided payment method object. Can be a Source or a Payment Method.
	 *
	 * @return bool  True if payment method is a card, false otherwise.
	 */
	public static function is_card_payment_method( $payment_method ): bool {
		if ( ! isset( $payment_method->object ) || ! isset( $payment_method->type ) ) {
			return false;
		}

		if ( 'payment_method' !== $payment_method->object && 'source' !== $payment_method->object ) {
			return false;
		}

		return 'card' === $payment_method->type;
	}

	/**
	 * Evaluates whether a given Stripe Source (or Stripe Payment Method) is reusable.
	 * Payment Methods are always reusable; Sources are only reusable when the appropriate
	 * usage metadata is provided.
	 *
	 * @param PaymentMethod|Source $payment_method The source or payment method to be evaluated.
	 *
	 * @return bool  Returns true if the source is reusable; false otherwise.
	 */
	public static function is_reusable_payment_method( $payment_method ): bool {
		return self::is_payment_method_object( $payment_method ) || ( isset( $payment_method->usage ) && 'reusable' === $payment_method->usage );
	}

	/**
	 * Evaluates whether the object passed to this function is a Stripe Payment Method.
	 *
	 * @param PaymentMethod|Source $payment_method The object that should be evaluated.
	 *
	 * @return bool             Returns true if the object is a Payment Method; false otherwise.
	 */
	public static function is_payment_method_object( $payment_method ): bool {
		return isset( $payment_method->object ) && 'payment_method' === $payment_method->object;
	}

	/**
	 * Attaches a payment method to the given customer.
	 *
	 * @param string $customer_id The ID of the customer the payment method should be attached to.
	 * @param string $payment_method_id The payment method that should be attached to the customer.
	 *
	 * @return Account|BankAccount|Card|Source
	 * @throws StoreEngineException
	 */
	public function attach_payment_method_to_customer( string $customer_id, string $payment_method_id ) {
		try {
			// Sources and Payment Methods need different API calls.
			if ( 0 === strpos( $payment_method_id, 'src_' ) ) {
				return $this->stripe_client->customers->updateSource( $customer_id, $payment_method_id );
			}

			return $this->stripe_client->paymentmethods->customers->attach( $payment_method_id, [ 'customer' => $customer_id ] );
		} catch ( ApiErrorException $e ) {
			$type = 0 === strpos( $payment_method_id, 'src_' ) ? 'source' : 'payment_method';
			throw StoreEngineException::from_stripe_api_error( $e, 'error-attach-customer-method-' . $type ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * Detaches a payment method from the given customer.
	 *
	 * @param string $customer_id The ID of the customer that contains the payment method that should be detached.
	 * @param string $payment_method_id The ID of the payment method that should be detached.
	 *
	 * @return array|Account|BankAccount|Card|PaymentMethod|Source The response from the API request
	 *
	 * @throws StoreEngineException
	 */
	public static function detach_payment_method_from_customer( string $customer_id, string $payment_method_id ) {
		if ( ! self::should_detach_payment_method_from_customer() ) {
			return [];
		}

		$payment_method_id = sanitize_text_field( $payment_method_id );

		try {
			// Sources and Payment Methods need different API calls.
			if ( 0 === strpos( $payment_method_id, 'src_' ) ) {
				return self::init()->getClient()->customers->deleteSource( $customer_id, $payment_method_id );
			}

			return self::init()->getClient()->paymentMethods->detach( $payment_method_id );
		} catch ( ApiErrorException $e ) {
			$type = 0 === strpos( $payment_method_id, 'src_' ) ? 'source' : 'payment_method';
			throw StoreEngineException::from_stripe_api_error( $e, 'error-detach-customer-method-' . $type ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}
	}

	/**
	 * Checks if a payment method should be detached from a customer.
	 *
	 * If the site is a staging/local/development site in live mode, we should not detach the payment method
	 * from the customer to avoid detaching it from the production site.
	 *
	 * @return bool True if the payment should be detached, false otherwise.
	 */
	public static function should_detach_payment_method_from_customer(): bool {
		// If we are in test mode, we can always detach the payment method.
		if ( ! self::init()->is_live ) {
			return true;
		}

		// Return true for the delete user request from the admin dashboard when the site is a production site
		// and return false when the site is a staging/local/development site.
		// This is to avoid detaching the payment method from the live production site.
		// Requests coming from the customer account page i.e delete payment method, are not affected by this and returns true.
		if ( is_admin() ) {
			if ( 'production' === wp_get_environment_type() ) {
				return true;
			} else {
				return false;
			}
		}

		return true;
	}

	/**
	 * List of currencies supported by Stripe
	 *
	 * @return string[]
	 */
	public static function get_supported_currencies(): array {
		return self::$supported_currencies;
	}

	/**
	 * List of currencies supported by Stripe that has no decimals
	 *
	 * @return string[]
	 */
	public static function no_decimal_currencies(): array {
		return self::$zero_decimal_currencies;
	}

	/**
	 * List of currencies supported by Stripe that has three decimals
	 *
	 * @return array $currencies
	 */
	private static function three_decimal_currencies(): array {
		return self::$three_decimal_currencies;
	}

	/**
	 * Get Stripe amount to pay.
	 * Amount is be in cents, for some country it needs to be multiplied by 1000.
	 *
	 * @param float|int $total Amount due.
	 * @param string $currency Accepted currency.
	 *
	 * @return float|int
	 */
	public static function get_stripe_amount( $total, string $currency = '' ) {
		if ( ! $currency ) {
			$currency = Formatting::get_currency();
		}

		if ( in_array( $currency, self::no_decimal_currencies(), true ) ) {
			return absint( round( $total ) );
		}

		if ( in_array( $currency, self::three_decimal_currencies(), true ) ) {
			$price_decimals = Formatting::get_price_decimals();
			$amount         = absint( Formatting::format_decimal( ( (float) $total * 1000 ), $price_decimals ) ); // For three decimal currencies.

			return $amount - ( $amount % 10 ); // Round the last digit down. See https://docs.stripe.com/currencies?presentment-currency=AE#three-decimal
		}

		return absint( Formatting::format_decimal( ( (float) $total * 100 ), Formatting::get_price_decimals() ) ); // In cents.
	}

	/**
	 * Inverse of {@see get_stripe_amount()} — convert a Stripe minor-unit amount
	 * (e.g. `$charge->amount`) back to a decimal amount in the store currency.
	 *
	 * @param int|float $minor    Amount in the currency's smallest unit.
	 * @param string    $currency Accepted currency.
	 *
	 * @return float
	 */
	public static function get_stripe_amount_reverse( $minor, string $currency = '' ): float {
		if ( ! $currency ) {
			$currency = Formatting::get_currency();
		}

		if ( in_array( $currency, self::no_decimal_currencies(), true ) ) {
			return (float) $minor;
		}

		if ( in_array( $currency, self::three_decimal_currencies(), true ) ) {
			return (float) $minor / 1000;
		}

		return (float) $minor / 100;
	}

	public static function get_currency_minimum_charges(): array {
		return self::$currency_minimum_charges;
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
	 * Guards against sending a sub-minimum (or zero) amount to Stripe.
	 *
	 * A PaymentIntent below the currency minimum otherwise fails with Stripe's
	 * opaque "The amount must be greater than or equal to the minimum charge
	 * amount ... use a Setup Intent instead" error. Throwing here instead yields
	 * an actionable message and leaves the order in a retryable state.
	 *
	 * Both sides are normalised to the smallest currency unit so the comparison
	 * is unit-safe across decimal / no-decimal / three-decimal currencies.
	 *
	 * @param Order $order
	 * @param float $amount Amount in major units (e.g. dollars).
	 *
	 * @return void
	 * @throws StoreEngineException When the amount is below the Stripe minimum.
	 */
	public static function assert_minimum_charge_amount( Order $order, float $amount ): void {
		$currency       = $order->get_currency();
		$charge_amount  = self::get_stripe_amount( $amount, $currency );
		$minimum_amount = self::get_stripe_amount( self::get_minimum_amount( $currency ), $currency );

		if ( $charge_amount >= $minimum_amount ) {
			return;
		}

		$exception = new StoreEngineException(
			sprintf(
			/* translators: 1: attempted charge amount, 2: minimum allowed amount. */
				esc_html__( 'The payment amount (%1$s) is below the minimum charge amount (%2$s) allowed by Stripe for this currency, so the payment could not be processed.', 'storeengine' ),
				wp_strip_all_tags( Formatting::price( $amount, [ 'currency' => $currency ] ) ),
				wp_strip_all_tags( Formatting::price( self::get_minimum_amount( $currency ), [ 'currency' => $currency ] ) )
			),
			'amount-below-stripe-minimum'
		);

		Helper::log_error( $exception );

		throw $exception;
	}

	/**
	 * Stripe uses smallest denomination in currencies such as cents.
	 * We need to format the returned currency from Stripe into human readable form.
	 * The amount is not used in any calculations so returning string is sufficient.
	 *
	 * @param object $balance_transaction
	 * @param string $type Type of number to format
	 *
	 * @return string
	 */
	public static function format_balance_fee( $balance_transaction, $type = 'fee' ) {
		if ( ! is_object( $balance_transaction ) ) {
			return '';
		}

		if ( in_array( strtoupper( $balance_transaction->currency ), self::no_decimal_currencies(), true ) ) {
			if ( 'fee' === $type ) {
				return $balance_transaction->fee;
			}

			return $balance_transaction->net;
		}

		if ( 'fee' === $type ) {
			return number_format( $balance_transaction->fee / 100, 2, '.', '' );
		}

		return number_format( $balance_transaction->net / 100, 2, '.', '' );
	}
}
