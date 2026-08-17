<?php

namespace StoreEngine\Addons\MultiCurrency\Classes;

defined( 'ABSPATH' ) || exit;

/**
 * Multi-Currency Hooks
 *
 * All integration with StoreEngine core happens here via filters.
 * Core never calls this addon directly — it only fires hooks.
 *
 * ─── WHY WE READ THE COOKIE DIRECTLY ────────────────────────────────────────
 *
 * place_order() defines STOREENGINE_DOING_CHECKOUT=true on its FIRST LINE,
 * before any hook fires. Every hook inside place_order() therefore sees
 * is_checkout_context()=true, which makes ActiveCurrency::get() (via the
 * storeengine/currency filter) return the base currency.
 *
 * Additionally, the hooks we previously targeted:
 *   storeengine/checkout/before_process_order  → does NOT exist in place_order()
 *   storeengine/checkout/before_pay_order      → does NOT exist in place_order()
 *   storeengine/checkout/create_order          → does NOT exist in place_order()
 *   storeengine/order/db/create               → never fires because the draft
 *                                               order already has an ID, so
 *                                               save() calls update() not create()
 *
 * Hooks that DO fire inside place_order():
 *   storeengine/frontend/checkout/before_place_order  (line 423) ← fires early but
 *                                                                    DOING_CHECKOUT already true
 *   storeengine/checkout/order_processed              (line 497) ← after $order->save()
 *   storeengine/checkout/after_place_order            (line 517) ← after payment
 *   storeengine/checkout/payment_successful           (filter)   ← in prepare_checkout_response
 *
 * SOLUTION: Read $_COOKIE['se_currency'] directly — it is always present in the
 * AJAX request because the browser sends all cookies with every XHR. This bypasses
 * the filter lock entirely. Then use storeengine/checkout/order_processed (fires
 * after save()) to force-write the correct currency to the DB via wpdb->update().
 *
 * ─── ANALYTICS ───────────────────────────────────────────────────────────────
 *
 * GA4 + Facebook Pixel both fire from this JS event after checkout:
 *   doActionAsync("storeengine.checkout.purchase_completed", result)
 *
 * GA4 reads:  result.order.currency  and  result.order.total
 * FB reads:   StoreEngineGlobal.currency_options.currency  and  result.order.total
 *
 * The result object comes from prepare_checkout_response() → $order->get_data().
 * We patch it via storeengine/checkout/payment_successful using the checkout-time
 * rate stored as order meta (_mc_display_rate).
 *
 * @package StoreEngine\Addons\MultiCurrency
 */

use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {

	public function __construct() {

		// ── Currency resolution ───────────────────────────────────────────
		add_filter( 'storeengine/currency', [ $this, 'filter_active_currency' ] );

		// ── Price conversion ──────────────────────────────────────────────
		add_filter( 'storeengine/product/get_price', [ $this, 'convert_display_price' ], 10, 2 );
		add_filter( 'storeengine/product/get_compare_price', [ $this, 'convert_display_price' ], 10, 2 );

		// ── Price args — correct currency symbol ──────────────────────────
		add_filter( 'storeengine/price_args', [ $this, 'set_display_currency_in_args' ] );

		// ── JS data ───────────────────────────────────────────────────────
		add_filter( 'storeengine/frontend_scripts_data', [ $this, 'inject_js_data' ] );


		// ── REST / mini-cart ──────────────────────────────────────────────
		add_action( 'rest_api_init', [ $this, 'prime_active_currency_for_rest' ] );

		// ── Frontend switcher ─────────────────────────────────────────────
		add_action( 'storeengine/templates/archive_header_filter',               [ $this, 'dispatch_currency_switcher' ], 5 );
		add_action( 'storeengine/templates/single-product/header_right_content', [ $this, 'dispatch_currency_switcher' ], 5 );

		// ── Coupons ───────────────────────────────────────────────────────────
		// 1. fixedAmount value stored in USD — convert to display currency.
		add_filter( 'storeengine/coupon_get_discount_amount',
			[ $this, 'convert_fixed_coupon_discount' ], 10, 5 );
		// 2. Minimum spend: stored in USD, compared against EUR subtotal.
		add_filter( 'storeengine/coupon_validate_minimum_amount',
			[ $this, 'validate_coupon_minimum_in_display_currency' ], 10, 3 );
		// 3. Maximum spend: same problem.
		add_filter( 'storeengine/coupon_validate_maximum_amount',
			[ $this, 'validate_coupon_maximum_in_display_currency' ], 10, 2 );

		// ── Shipping rates ────────────────────────────────────────────────────
		// Shipping rates are stored in the session in base currency.
		// These filters fire every time the rate is read (bypasses session cache)
		// converting cost and tax amounts to the active display currency.
		add_filter( 'storeengine/shipping_rate_cost',
			[ $this, 'convert_shipping_rate_cost' ], 10, 2 );
		add_filter( 'storeengine/shipping_rate_taxes',
			[ $this, 'convert_shipping_rate_taxes' ], 10, 2 );
		add_filter( 'storeengine/shipping/package_rates',
			[ $this, 'convert_shipping_package_rates' ], 10, 1 );

		// ── Cart item price precision ─────────────────────────────────────────
		// CartItem->price may be stored in the session with more decimal places
		// than get_price_decimals() allows (e.g. 3688.062 from a rate conversion).
		// add_number_precision_deep() in CartTotals multiplies by 100, giving
		// 368806.2 — a float that get_discounted_price_in_cents(): int cannot
		// accept without a PHP 8.1 deprecation. We round here to guarantee a
		// clean value before it enters the precision system.
		add_filter( 'storeengine/cart/raw_item_price',
			[ $this, 'round_cart_item_price' ], 10, 1 );
	}

	// ═══════════════════════════════════════════════════════════════════════════
	// DISPLAY CURRENCY FILTERS (frontend / non-checkout pages)
	// ═══════════════════════════════════════════════════════════════════════════

	public function filter_active_currency( string $base_currency ): string {
		if ( Helper::is_dashboard() ) {
			return $base_currency;
		}

		$active = ActiveCurrency::get();

		if ( $active === $base_currency || ! ActiveCurrency::is_allowed( $active ) ) {
			return $base_currency;
		}

		return $active;
	}

	public function convert_display_price( float $price, $context ): float {
		if ( $context !== 'view' || Helper::is_dashboard() ) {
			return $price;
		}

		$active = ActiveCurrency::get();
		$base   = strtoupper( Helper::get_settings( 'store_currency', 'USD' ) );

		if ( $active === $base ) {
			return $price;
		}

		return round( ExchangeRates::convert( $price, $active ), Formatting::get_price_decimals() );
	}

	public function set_display_currency_in_args( array $args ): array {
		if ( Helper::is_dashboard() ) {
			return $args;
		}

		$active = ActiveCurrency::get();
		$base   = strtoupper( Helper::get_settings( 'store_currency', 'USD' ) );

		if ( $active !== $base && empty( $args['currency'] ) ) {
			$args['currency'] = $active;
		}

		return $args;
	}

	public function inject_js_data( array $data ): array {
		$base   = strtoupper( Helper::get_settings( 'store_currency', 'USD' ) );
		$active = ActiveCurrency::get();
		$rates  = ExchangeRates::get_cached_rates();

		$data['multi_currency'] = [
			'base_currency'      => $base,
			'active_currency'    => $active,
			'enabled_currencies' => Settings::get( 'enabled_currencies', [] ),
			'show_switcher'      => (bool) Settings::get( 'show_switcher', true ),
			'rates'              => $rates['rates']   ?? [],
			'rates_updated'      => $rates['updated'] ?? '',
			'switch_url_param'   => ActiveCurrency::URL_PARAM,
		];

		// Facebook Pixel reads currency from StoreEngineGlobal.currency_options.currency.
		if ( $active !== $base ) {
			$data['currency_options']['currency']        = $active;
			$data['currency_options']['currency_symbol'] = Helper::get_currency_symbol( $active );
		}

		return $data;
	}



	// ═══════════════════════════════════════════════════════════════════════════
	// MINI-CART + REST
	// ═══════════════════════════════════════════════════════════════════════════

	public function prime_active_currency_for_rest(): void {
		ActiveCurrency::get();
	}



	public function dispatch_currency_switcher(): void {
		echo do_shortcode( '[storeengine_currency_switcher]' );
	}


	/**
	 * Convert a fixedAmount coupon discount to display currency.
	 *
	 * Problem: coupon_amount is stored in base currency (e.g. $10 USD).
	 * Cart items are now in EUR. Applying $10 USD to EUR prices gives
	 * a wrong discount proportion. We convert the coupon amount to EUR first.
	 *
	 * Percentage coupons work on the price ratio — no conversion needed.
	 *
	 * Fires in Discounts::apply_coupon_fixed_cart() and apply_coupon_fixed_product()
	 * for each item being discounted.
	 *
	 * @param float       $discount           Pre-calculated discount (may be wrong).
	 * @param float       $price_to_discount  Item price in display currency (in cents precision).
	 * @param object|null $cart_item
	 * @param bool        $single
	 * @param \StoreEngine\Classes\Coupon $coupon
	 * @return float
	 */
	public function convert_fixed_coupon_discount( float $discount, float $price_to_discount, $cart_item, bool $single, $coupon ): float {
		if ( Helper::is_dashboard() ) {
			return $discount;
		}

		$active = ActiveCurrency::get();
		$base   = strtoupper( Helper::get_settings( 'store_currency', 'USD' ) );

		if ( $active === $base ) {
			return $discount;
		}

		// Only fixed-amount types need conversion. Percentage works on ratio.
		if ( ! in_array( $coupon->get_discount_type(), [ 'fixedAmount', 'fixed_cart', 'fixed_product' ], true ) ) {
			return $discount;
		}

		$base_amount = (float) $coupon->get_amount();
		if ( $base_amount <= 0 ) {
			return $discount;
		}

		$display_amount = ExchangeRates::convert( $base_amount, $active );
		$display_amount = round( $display_amount, \StoreEngine\Utils\Formatting::get_price_decimals() );
		return min( $display_amount, $price_to_discount );
	}

	/**
	 * Validate minimum spend in display currency.
	 *
	 * Problem: minimum_amount stored in USD (e.g. $50). Cart subtotal is now EUR 46
	 * which equals $50 USD. Original comparison (50 > 46) wrongly rejects the coupon.
	 *
	 * We convert the stored minimum to display currency and redo the comparison.
	 *
	 * @param bool   $is_invalid  true = coupon rejected by original comparison.
	 * @param \StoreEngine\Classes\Coupon $coupon
	 * @param float  $subtotal    Cart subtotal already in display currency.
	 * @return bool
	 */
	public function validate_coupon_minimum_in_display_currency( bool $is_invalid, $coupon, float $subtotal ): bool {
		$active = ActiveCurrency::get();
		$base   = strtoupper( Helper::get_settings( 'store_currency', 'USD' ) );

		if ( $active === $base ) {
			return $is_invalid;
		}

		$minimum_base = $coupon->get_minimum_amount();
		if ( $minimum_base <= 0 ) {
			return false; // No minimum set — always valid.
		}

		return ExchangeRates::convert( $minimum_base, $active ) > $subtotal;
	}

	/**
	 * Validate maximum spend in display currency.
	 *
	 * Same problem as minimum: stored in USD, compared against EUR subtotal.
	 *
	 * @param bool   $is_invalid
	 * @param \StoreEngine\Classes\Coupon $coupon
	 * @return bool
	 */
	public function validate_coupon_maximum_in_display_currency( bool $is_invalid, $coupon ): bool {
		$active = ActiveCurrency::get();
		$base   = strtoupper( Helper::get_settings( 'store_currency', 'USD' ) );

		if ( $active === $base ) {
			return $is_invalid;
		}

		$maximum_base = $coupon->get_maximum_amount();
		if ( $maximum_base <= 0 ) {
			return false; // No maximum set — always valid.
		}

		$subtotal_display = (float) Helper::cart()->get_displayed_subtotal();

		return ExchangeRates::convert( $maximum_base, $active ) < $subtotal_display;
	}
	// ═══════════════════════════════════════════════════════════════════════════
	// SHIPPING RATE CONVERSION
	// ═══════════════════════════════════════════════════════════════════════════

	/**
	 * Convert a shipping rate's cost to the active display currency.
	 *
	 * Shipping rates are calculated and cached in the session in base currency.
	 * This filter fires every time ShippingRate::get_cost() is called, which is
	 * what CartTotals reads via magic __get — so we bypass the session cache and
	 * always return the correct converted amount.
	 *
	 * Same guard pattern as convert_display_price():
	 *   - Skip on dashboard (admin panel).
	 *   - Skip when active currency equals base currency (no conversion needed).
	 *
	 * @param float        $cost  Raw cost in base currency.
	 * @param \StoreEngine\Classes\Order\ShippingRate $rate  The rate object.
	 * @return float  Cost in active display currency.
	 */
	public function convert_shipping_rate_cost( float $cost, $rate ): float {
		if ( Helper::is_dashboard() ) {
			return $cost;
		}

		$active = ActiveCurrency::get();
		$base   = strtoupper( Helper::get_settings( 'store_currency', 'USD' ) );

		if ( $active === $base ) {
			return $cost;
		}

		return round( ExchangeRates::convert( $cost, $active ), Formatting::get_price_decimals() );
	}

	/**
	 * Convert shipping tax amounts to the active display currency.
	 *
	 * Tax amounts are stored alongside the rate in the session (base currency).
	 * CartTotals reads them via $shipping_object->taxes (magic __get → get_taxes()).
	 * We convert each tax amount here so the shipping tax line in the cart
	 * shows the correct value in the display currency.
	 *
	 * Taxes are an associative array keyed by tax rate ID:
	 *   [ '1' => 1.50, '2' => 0.75, ... ]
	 *
	 * @param array        $taxes  Tax amounts in base currency, keyed by tax rate ID.
	 * @param \StoreEngine\Classes\Order\ShippingRate $rate   The rate object.
	 * @return array  Tax amounts in active display currency.
	 */
	public function convert_shipping_rate_taxes( array $taxes, $rate ): array {
		if ( Helper::is_dashboard() ) {
			return $taxes;
		}

		$active = ActiveCurrency::get();
		$base   = strtoupper( Helper::get_settings( 'store_currency', 'USD' ) );

		if ( $active === $base || empty( $taxes ) ) {
			return $taxes;
		}

		foreach ( $taxes as $tax_rate_id => $amount ) {
			$taxes[ $tax_rate_id ] = round( ExchangeRates::convert( (float) $amount, $active ), Formatting::get_price_decimals() );
		}

		return $taxes;
	}

	public function convert_shipping_package_rates($rates): array {
		if (Helper::is_dashboard()) {
			return $rates;
		}

		$active = ActiveCurrency::get();
		$base   = strtoupper(Helper::get_settings('store_currency', 'USD'));

		if ($active === $base) {
			return $rates;
		}

		foreach ($rates as $rate_id => $rate) {
			$cost = (float) $rate->get_cost();
			$converted_cost = ExchangeRates::convert($cost, $active);

			$rate->set_cost(
				round($converted_cost, Formatting::get_price_decimals())
			);

			$taxes = $rate->get_taxes();

			if (!empty($taxes) && is_array($taxes)) {
				foreach ($taxes as $tax_id => $amount) {
					$taxes[$tax_id] = round(
						ExchangeRates::convert((float) $amount, $active),
						Formatting::get_price_decimals()
					);
				}

				$rate->set_taxes($taxes);
			}

			$rates[$rate_id] = $rate;
		}

		return $rates;
	}

	/**
	 * Round cart item price to store decimal precision.
	 *
	 * CartItem::get_price() fires storeengine/cart/raw_item_price.
	 * CartTotals reads this and multiplies by 10^decimals to convert to
	 * "cents" for integer arithmetic. If the stored price has more decimal
	 * places than get_price_decimals() (e.g. 3688.062 from a rate conversion),
	 * the result is a non-integer float (368806.2) which causes a PHP 8.1
	 * deprecation when get_discounted_price_in_cents(): int tries to return it.
	 *
	 * This filter runs unconditionally (no currency guard needed) because
	 * rounding to 2 decimal places is always safe and correct regardless
	 * of which currency is active.
	 *
	 * @param  float $price  Raw price from cart session.
	 * @return float         Price rounded to store decimal precision.
	 */
	public function round_cart_item_price( float $price ): float {
		return round( $price, Formatting::get_price_decimals() );
	}

}
