<?php

namespace StoreEngine\Addons\Razorpay;

use StoreEngine\Classes\Order;
use StoreEngine\Payment_Gateways;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Formatting;
use WP_Error;

final class RazorpayService {

	/**
	 * @var ?GatewayRazorpay
	 */
	protected ?GatewayRazorpay $gateway;

	protected string $authorization_header;

	protected static ?RazorpayService $instance = null;

	/**
	 * List of currencies supported by Razorpay.
	 *
	 * @link https://razorpay.com/docs/payments/international-payments/#supported-currencies
	 *
	 * @var array|string[]
	 */
	protected static array $supported_currencies = [
		'AED',
		'ALL',
		'AMD',
		'ARS',
		'AUD',
		'AWG',
		'BBD',
		'BDT',
		'BMD',
		'BND',
		'BOB',
		'BSD',
		'BWP',
		'BZD',
		'CAD',
		'CHF',
		'CNY',
		'COP',
		'CRC',
		'CUP',
		'CZK',
		'DKK',
		'DOP',
		'DZD',
		'EGP',
		'ETB',
		'EUR',
		'FJD',
		'GBP',
		'GHS',
		'GIP',
		'GMD',
		'GTQ',
		'GYD',
		'HKD',
		'HNL',
		'HRK',
		'HTG',
		'HUF',
		'IDR',
		'ILS',
		'INR',
		'JMD',
		'KES',
		'KGS',
		'KHR',
		'KYD',
		'KZT',
		'LAK',
		'LKR',
		'LRD',
		'LSL',
		'MAD',
		'MDL',
		'MKD',
		'MMK',
		'MNT',
		'MOP',
		'MUR',
		'MVR',
		'MWK',
		'MXN',
		'MYR',
		'NAD',
		'NGN',
		'NIO',
		'NOK',
		'NPR',
		'NZD',
		'PEN',
		'PGK',
		'PHP',
		'PKR',
		'QAR',
		'RUB',
		'SAR',
		'SCR',
		'SEK',
		'SGD',
		'SLL',
		'SOS',
		'SSP',
		'SVC',
		'SZL',
		'THB',
		'TTD',
		'TZS',
		'USD',
		'UYU',
		'UZS',
		'YER',
		'ZAR',
		'TRY',
	];

	protected static array $currency_minimum_charges = [
		'INR' => 1.0,
	];

	public function __construct( $gateway = null ) {
		if ( $gateway instanceof GatewayRazorpay ) {
			$this->gateway = $gateway;
		} else {
			$this->gateway = Payment_Gateways::get_instance()->get_gateway( 'razorpay' );
		}

		$this->init_settings();
	}

	public static function init( $gateway = null ): RazorpayService {
		if ( null === self::$instance ) {
			self::$instance = new self( $gateway );
		}

		return self::$instance;
	}

	public function init_settings() {
		if ( ! $this->gateway ) {
			return;
		}

		if ( ! $this->gateway->is_enabled || 'razorpay' !== $this->gateway->id ) {
			return;
		}

		$this->authorization_header = 'Basic ' . base64_encode( $this->gateway->get_option( 'key_id' ) . ':' . $this->gateway->get_option( 'key_secret' ) );
	}

	/**
	 * @param string $key_id
	 * @param string $key_secret
	 *
	 * @return bool
	 */
	public static function validate_keys( string $key_id, string $key_secret ): bool {
		$response = wp_remote_get( self::get_api_url( 'v1/payments' ), [
			'headers' => [
				'content-type'  => 'application/json',
				'Authorization' => 'Basic ' . base64_encode( $key_id . ':' . $key_secret ),
			],
			'timeout' => 15,
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );

		return 200 === $code;
	}

	public static function get_api_url( string $endpoint ): string {
		return 'https://api.razorpay.com/' . $endpoint;
	}

	public function request( string $endpoint, string $method = 'GET', array $data = [] ) {
		return wp_remote_request( self::get_api_url( $endpoint ), [
			'method'  => $method,
			'headers' => [
				'content-type'  => 'application/json',
				'Authorization' => $this->authorization_header,
			],
			'body'    => ! empty( $data ) ? wp_json_encode( $data ) : [],
			'timeout' => 15,
		] );
	}

	/**
	 * @param Order $order
	 *
	 * @return string|WP_Error
	 */
	public function create_order( Order $order ) {
		$amount   = self::get_razorpay_amount( $order->get_total(), $order->get_currency() );
		$response = $this->request( 'v1/orders', 'POST', [
			'amount'   => $amount,
			'currency' => $order->get_currency(),
			'notes'    => [
				'merchant_order_id' => $order->get_id(),
			],
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( ! ( 200 <= $status_code && $status_code < 300 ) ) {
			return new WP_Error( 'razorpay_order_create_error', 'Failed to create razorpay order.' );
		}

		$body = wp_remote_retrieve_body( $response );
		$body = json_decode( $body, true );

		if ( ! isset( $body['id'] ) ) {
			return new WP_Error( 'razorpay_order_create_error', 'Failed to create razorpay order.' );
		}

		return $body['id'];
	}

	public function get_payment( string $payment_id ) {
		$response = $this->request( 'v1/payments/' . $payment_id, 'GET' );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return new WP_Error( 'razorpay_get_payment_error', 'Failed to get razorpay payment.' );
		}

		$body = wp_remote_retrieve_body( $response );
		$body = json_decode( $body, true );

		if ( ! isset( $body['id'] ) ) {
			return new WP_Error( 'razorpay_get_payment_error', 'Failed to get razorpay payment.' );
		}

		return $body;
	}

	public function capture_payment( string $payment_id, float $amount, string $currency = '' ) {
		if ( empty( $currency ) ) {
			$currency = Formatting::get_currency();
		}

		$amount   = self::get_razorpay_amount( $amount, $currency );
		$response = $this->request( 'v1/payments/' . $payment_id . '/capture', 'POST', [
			'amount'   => $amount,
			'currency' => $currency,
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( ! ( 200 <= $status_code && $status_code < 300 ) ) {
			return new WP_Error( 'razorpay_get_payment_error', 'Failed to capture razorpay payment.' );
		}

		$body = wp_remote_retrieve_body( $response );
		$body = json_decode( $body, true );

		if ( ! isset( $body['id'] ) || 'captured' !== $body['status'] ) {
			return new WP_Error( 'razorpay_get_payment_error', 'Failed to capture razorpay payment.' );
		}

		return $body;
	}

	/**
	 * Refunds a payment.
	 *
	 * @param string $payment_id
	 * @param float $amount
	 *
	 * @return array|WP_Error
	 */
	public function refund( string $payment_id, float $amount ) {
		$amount   = self::get_razorpay_amount( $amount );
		$response = $this->request( 'v1/payments/' . $payment_id . '/refund', 'POST', [
			'amount' => $amount,
			'speed'  => 'optimum',
		] );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( ! ( 200 <= $status_code && $status_code < 300 ) ) {
			return new WP_Error( 'razorpay_get_payment_error', 'Failed to refund razorpay payment.' );
		}

		$body = wp_remote_retrieve_body( $response );
		$body = json_decode( $body, true );

		if ( ! isset( $body['id'] ) ) {
			return new WP_Error( 'razorpay_get_payment_error', 'Failed to refund razorpay payment.' );
		}

		return $body;
	}

	/**
	 * Checks Razorpay minimum order value authorized per currency
	 */
	public static function get_minimum_amount( $currency = '' ) {
		if ( ! $currency ) {
			$currency = Formatting::get_currency();
		}

		return self::$currency_minimum_charges[ $currency ] ?? .50;
	}

	/**
	 * Get Razorpay amount to pay.
	 * Amount is be in cents, for some country it needs to be multiplied by 1000.
	 *
	 * @param float|int $total Amount due.
	 * @param string $currency Accepted currency.
	 *
	 * @return float|int
	 */
	public static function get_razorpay_amount( $total, string $currency = '' ) {
		return absint( Formatting::format_decimal( ( (float) $total * 100 ), Formatting::get_price_decimals() ) ); // In cents.
	}

}
