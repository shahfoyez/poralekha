<?php
/**
 * A class of utilities for dealing with payment.
 */

namespace StoreEngine\Utils;

use StoreEngine\Addons\Subscription\Classes\SubscriptionCollection;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Classes\PaymentTokens\PaymentToken;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class PaymentUtil {
	protected static array $cc_types = [];

	public static function credit_card_type_labels(): array {
		if ( null === self::$cc_types ) {
			self::$cc_types = apply_filters( 'storeengine/credit_card_type_labels', [
				'mastercard'       => _x( 'MasterCard', 'Name of credit card', 'storeengine' ),
				'visa'             => _x( 'Visa', 'Name of credit card', 'storeengine' ),
				'discover'         => _x( 'Discover', 'Name of credit card', 'storeengine' ),
				'american express' => _x( 'American Express', 'Name of credit card', 'storeengine' ),
				'cartes bancaires' => _x( 'Cartes Bancaires', 'Name of credit card', 'storeengine' ),
				'diners'           => _x( 'Diners', 'Name of credit card', 'storeengine' ),
				'jcb'              => _x( 'JCB', 'Name of credit card', 'storeengine' ),
			] );
		}

		return self::$cc_types;
	}

	public static function get_card_brand_abbreviations(): array {
		$brand_abbreviations = [
			'american_express' => _x( 'AMEX', 'Card brand abbreviation', 'storeengine' ),
			'cartes_bancaires' => _x( 'CB', 'Card brand abbreviation', 'storeengine' ),
			'diners_club'      => _x( 'DC', 'Card brand abbreviation', 'storeengine' ),
			'discover'         => _x( 'DS', 'Card brand abbreviation', 'storeengine' ),
			'eftpos_australia' => _x( 'EFTPOS', 'Card brand abbreviation', 'storeengine' ),
			'interac'          => _x( 'IDP', 'Card brand abbreviation', 'storeengine' ),
			'jcb'              => _x( 'JCB', 'Card brand abbreviation', 'storeengine' ),
			'mastercard'       => _x( 'MC', 'Card brand abbreviation', 'storeengine' ),
			'union_pay'        => _x( 'CUP', 'Card brand abbreviation', 'storeengine' ),
			'visa'             => _x( 'VISA', 'Card brand abbreviation', 'storeengine' ),
			'link'             => _x( 'LINK', 'Card brand abbreviation', 'storeengine' ),
			'other'            => _x( 'OTHER', 'Card brand abbreviation', 'storeengine' ),
			'unknown'          => _x( 'UNKNOWN', 'Card brand abbreviation', 'storeengine' ),
		];

		$brand_abbreviations['amex']           = &$brand_abbreviations['american_express'];
		$brand_abbreviations['diners']         = &$brand_abbreviations['diners_club'];
		$brand_abbreviations['eftpos_au']      = &$brand_abbreviations['eftpos_australia'];
		$brand_abbreviations['china_unionpay'] = &$brand_abbreviations['union_pay'];
		$brand_abbreviations['unionpay']       = &$brand_abbreviations['union_pay'];

		return apply_filters( 'storeengine/payment_method/card_brand_abbreviations', $brand_abbreviations );
	}

	public static function get_card_brand_icons(): array {
		$card_brand_icon = [
			'american_express' => Helper::get_assets_url( 'images/cards/amex.svg' ),
			'cartes_bancaires' => Helper::get_assets_url( 'images/cards/cartes_bancaires.svg' ),
			'diners_club'      => Helper::get_assets_url( 'images/cards/diners.svg' ),
			'discover'         => Helper::get_assets_url( 'images/cards/discover.svg' ),
			'eftpos_australia' => Helper::get_assets_url( 'images/cards/eftpos_au.svg' ),
			'interac'          => Helper::get_assets_url( 'images/cards/interac.svg' ),
			'jcb'              => Helper::get_assets_url( 'images/cards/jcb.svg' ),
			'mastercard'       => Helper::get_assets_url( 'images/cards/mastercard.svg' ),
			'union_pay'        => Helper::get_assets_url( 'images/cards/union_pay.svg' ),
			'visa'             => Helper::get_assets_url( 'images/cards/visa.svg' ),
			'link'             => Helper::get_assets_url( 'images/cards/link.svg' ),
			'other'            => Helper::get_assets_url( 'images/cards/credit-card.svg'),
		];

		$card_brand_icon['amex']           = &$card_brand_icon['american_express'];
		$card_brand_icon['diners']         = &$card_brand_icon['diners_club'];
		$card_brand_icon['eftpos_au']      = &$card_brand_icon['eftpos_australia'];
		$card_brand_icon['china_unionpay'] = &$card_brand_icon['union_pay'];
		$card_brand_icon['unionpay']       = &$card_brand_icon['union_pay'];
		$card_brand_icon['unknown']       = &$card_brand_icon['other'];

		return apply_filters( 'storeengine/payment_method/card_brand_icons', $card_brand_icon );
	}

	/**
	 * Get a nice name for credit card providers.
	 *
	 * @param string $type Provider Slug/Type.
	 *
	 * @return string
	 */
	public static function get_credit_card_type_label( string $type ): string {
		self::credit_card_type_labels();
		// Normalize.
		$type = strtolower( $type );
		$type = str_replace( '-', ' ', $type );
		$type = str_replace( '_', ' ', $type );


		/**
		 * Fallback to title case, uppercasing the first letter of each word.
		 */
		return apply_filters( 'storeengine/get_credit_card_type_label', ( array_key_exists( $type, self::$cc_types ) ? self::$cc_types[ $type ] : ucwords( $type ) ) );
	}

	/**
	 * Get My Account > Payment methods columns.
	 *
	 * @return array
	 * @since 2.6.0
	 */
	public static function get_account_payment_methods_columns(): array {
		return apply_filters(
			'storeengine/account_payment_methods_columns',
			[
				'id'      => __( 'ID', 'storeengine' ),
				'method'  => __( 'Method', 'storeengine' ),
				'gateway' => __( 'Gateway', 'storeengine' ),
				'expires' => __( 'Expires', 'storeengine' ),
				'actions' => __( 'Actions', 'storeengine' ),
			]
		);
	}

	/**
	 * Get My Account > Payment methods types
	 *
	 * @return array
	 * @since 2.6.0
	 */
	public static function get_account_payment_methods_types(): array {
		return apply_filters(
			'storeengine/payment_methods_types',
			[
				'cc'     => __( 'Credit card', 'storeengine' ),
				'echeck' => __( 'eCheck', 'storeengine' ),
			]
		);
	}

	/**
	 * Get customer saved payment methods list.
	 *
	 * @param int $customer_id Customer ID.
	 *
	 * @return array
	 */
	public static function get_customer_saved_methods_list( int $customer_id ): array {
		return apply_filters( 'storeengine/saved_payment_methods_list', [], $customer_id );
	}

	/**
	 * Callback for storeengine/payment_methods_list_item filter to add token id
	 * to the generated list.
	 *
	 * @param array $list_item The current list item for the saved payment method.
	 * @param PaymentToken $token The token for the current list item.
	 *
	 * @return array The list item with the token id added.
	 */
	public static function include_token_id_with_payment_methods( array $list_item, PaymentToken $token ): array {
		$list_item['tokenId'] = $token->get_id();
		// Check if brand in token data.
		$brand = ! empty( $list_item['method']['brand'] ) ? strtolower( $list_item['method']['brand'] ) : '';

		if ( ! empty( $brand ) && esc_html__( 'Credit card', 'storeengine' ) !== $brand ) {
			$list_item['method']['brand'] = self::get_credit_card_type_label( $brand );
		}

		return $list_item;
	}

	/**
	 * Get enabled payment gateways.
	 *
	 * @return array
	 */
	public static function get_enabled_payment_gateways(): array {
		return array_filter(
			Helper::get_payment_gateways()->payment_gateways(),
			fn( $payment_gateway ) => $payment_gateway->is_enabled()
		);
	}

	/**
	 * Returns enabled saved payment methods for a customer and the default method if there are multiple.
	 *
	 * @return array
	 */
	public static function get_saved_payment_methods(): array {
		if ( ! is_user_logged_in() ) {
			return [];
		}

		add_filter( 'storeengine/payment_methods_list_item', [
			self::class,
			'include_token_id_with_payment_methods'
		], 10, 2 );

		$enabled_payment_gateways = self::get_enabled_payment_gateways();
		$_saved_payment_methods   = self::get_customer_saved_methods_list( get_current_user_id() );
		$payment_methods          = [
			'enabled' => [],
			'default' => null,
		];

		// Filter out payment methods that are not enabled.
		foreach ( $_saved_payment_methods as $payment_method_group => $saved_payment_methods ) {
			$payment_methods['enabled'][ $payment_method_group ] = array_values(
				array_filter(
					$saved_payment_methods,
					function ( $saved_payment_method ) use ( $enabled_payment_gateways, &$payment_methods ) {
						if ( true === $saved_payment_method['is_default'] && null === $payment_methods['default'] ) {
							$payment_methods['default'] = $saved_payment_method;
						}

						return in_array( $saved_payment_method['method']['gateway'], array_keys( $enabled_payment_gateways ), true );
					}
				)
			);
		}

		remove_filter( 'storeengine/payment_methods_list_item', [
			self::class,
			'include_token_id_with_payment_methods'
		], 10, 2 );

		return $payment_methods;
	}

	/**
	 * Returns the default payment method for a customer.
	 *
	 * @return string
	 */
	public static function get_default_payment_method(): string {
		$saved_payment_methods = self::get_saved_payment_methods();
		// A saved payment method exists, set as default.
		if ( $saved_payment_methods && ! empty( $saved_payment_methods['default'] ) ) {
			return $saved_payment_methods['default']['method']['gateway'] ?? '';
		}

		$order = Helper::get_recent_draft_order( 0, null, false );
		// If payment method is already stored in session, use it.
		if ( $order && $order->get_payment_method() ) {
			return $order->get_payment_method();
		}

		// If no saved payment method exists, use the first enabled payment method.
		$enabled_payment_gateways = self::get_enabled_payment_gateways();
		$first_key                = array_key_first( $enabled_payment_gateways );
		$first_payment_method     = $enabled_payment_gateways[ $first_key ];

		return $first_payment_method->id ?? '';
	}

	public static function is_valid_order_pay_page(): bool {
		if ( ! Formatting::string_to_bool( get_query_var( 'order_pay' ) ) || ! get_query_var( 'order_id' ) ) {
			return false;
		}

		$order = Helper::get_order( absint( get_query_var( 'order_id' ) ) );

		// Invalid order id / not found — page can't be valid.
		if ( is_wp_error( $order ) || ! method_exists( $order, 'get_order_key' ) ) {
			return false;
		}

		// Order must actually be awaiting payment.
		if ( ! method_exists( $order, 'needs_payment' ) || ! $order->needs_payment() ) {
			return false;
		}

		// Logged-out / cross-user access requires a matching order key in the URL
		// (the link emailed to guests / linked from the dashboard).
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$provided_key = isset( $_GET['key'] ) ? Formatting::clean( sanitize_text_field( wp_unslash( $_GET['key'] ) ) ) : '';
		if ( $provided_key && hash_equals( (string) $order->get_order_key(), (string) $provided_key ) ) {
			return true;
		}

		// Otherwise the current user must have explicit permission.
		return current_user_can( 'pay_for_order', $order->get_id() );
	}

	public static function has_subscription( Order $order ): bool {
		if ( ! Helper::get_addon_active_status( 'subscription' ) ) {
			return false;
		}

		if ( $order->get_meta( '_subscription_renewal' ) ) {
			return true;
		}

		return SubscriptionCollection::order_has_subscription( $order->get_id() );
	}

	/**
	 * Whether a gateway is allowed to settle a specific order, given the
	 * order's own contents rather than the (possibly absent) cart.
	 *
	 * `PaymentGateway::is_available()` only sees a subscription via the cart's
	 * `has_subscription` meta, so it never blocks non-recurring gateways
	 * (COD, BACS, Check) on requests that bypass the cart — namely the
	 * `/checkout/pay-order` and `/checkout/payment-intent` REST endpoints used
	 * by the frontend-dashboard "Pay Order" / early-renewal flows. Call this
	 * as a server-side guard wherever a gateway is charged directly against an
	 * existing order id, so a stale or forged `payment_method` can't settle a
	 * subscription renewal through a gateway that can't actually process one.
	 *
	 * @param \StoreEngine\Payment\Gateways\PaymentGateway $gateway
	 * @param Order                                        $order
	 *
	 * @return bool
	 */
	public static function gateway_can_pay_order( \StoreEngine\Payment\Gateways\PaymentGateway $gateway, Order $order ): bool {
		return ! ( self::has_subscription( $order ) && ! $gateway->supports( 'subscriptions' ) );
	}

	public static function has_subscription_trial( Order $order ): bool {
		if ( ! self::has_subscription( $order ) ) {
			return false;
		}

		if ( $order->get_meta( '_subscription_renewal' ) ) {
			return false; // Renewal order not count for trial.
		}

		foreach ( $order->get_items() as $item ) {
			/** @var OrderItemProduct $order_item */
			if ( 'subscription' === $item->get_price_type() ) {
				return $item->is_trial();
			}
		}

		return false;
	}

	public static function is_changing_payment_method_for_subscription(): bool {
		if ( ! Helper::get_addon_active_status( 'subscription' ) ) {
			return false;
		}

		return ! empty( $_GET['change_payment_method'] ) && 'subscription' === Helper::get_order_type( absint( wp_unslash( $_GET['change_payment_method'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification
	}

	public static function maybe_display_my_payment_method( string $payment_method_to_display, Order $order ): string {
		if ( has_filter( 'storeengine/subscription/my_payment_method' ) ) {
			$payment_method_to_display = apply_filters_deprecated( 'storeengine/subscription/my_payment_method', [
				$payment_method_to_display,
				$order
			], '1.8.0', 'storeengine/payment_method/display_my_payment_method' );
		}

		return (string) apply_filters( 'storeengine/payment_method/display_my_payment_method', $payment_method_to_display, $order );
	}
}
