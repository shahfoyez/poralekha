<?php

namespace StoreEngine\Utils;

use StoreEngine;
use StoreEngine\Classes\Countries;
use StoreEngine\Classes\Customer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TaxUtil {

	protected static ?bool $prices_include_tax = null;

	public static function is_tax_enabled(): bool {
		/**
		 * Whether the tax engine is active.
		 *
		 * Defaults to the merchant's `enable_product_tax` setting, but addons that
		 * compute tax externally (e.g. Stripe Automatic Tax) can force it on so the
		 * computed tax actually lands in the cart/order total and is persisted as a
		 * tax line — keeping charge, order and invoice a single source of truth.
		 *
		 * @param bool $enabled Whether product tax is enabled.
		 */
		return (bool) apply_filters( 'storeengine/tax_enabled', Helper::get_settings( 'enable_product_tax' ) );
	}

	/**
	 * Get rounding mode for internal tax calculations.
	 *
	 * @return int
	 */
	public static function get_tax_rounding_mode(): int {
		$mode = self::prices_include_tax() ? PHP_ROUND_HALF_DOWN : PHP_ROUND_HALF_UP;

		return intval( apply_filters( 'storeengine/tax_rounding_mode', $mode ) );
	}

	public static function tax_based_on() {
		return Helper::get_settings( 'tax_based_on', 'shipping' );
	}

	public static function default_customer_address() {
		return Helper::get_settings( 'store_default_customer_address' );
	}

	public static function tax_round_at_subtotal() {
		return Helper::get_settings( 'tax_round_at_subtotal', false );
	}

	public static function prices_include_tax(): bool {
		if ( null === self::$prices_include_tax ) {
			self::$prices_include_tax = Helper::get_settings( 'prices_include_tax', false );
		}

		return self::is_tax_enabled() && apply_filters( 'storeengine/prices_include_tax', self::$prices_include_tax );
	}

	/**
	 * Returns 'incl' if tax should be included in cart, otherwise returns 'excl'.
	 *
	 * @param Customer|null|false $customer
	 *
	 * @return string
	 */
	public static function get_tax_price_display_mode( $customer = null ): string {
		if ( ! $customer ) {
			$customer = storeengine()->get_customer();
		}

		$tax_display_mode = Helper::get_settings( 'tax_display_cart', 'excl' );

		if ( $customer && $customer->get_is_vat_exempt() ) {
			$tax_display_mode = 'excl';
		}

		return apply_filters( 'storeengine/tax/price_display_mode', $tax_display_mode, $customer );
	}

	/**
	 * Return whether-or-not the cart is displaying prices including tax, rather than excluding tax.
	 *
	 * @param Customer|null|false $customer
	 *
	 * @return bool
	 */
	public static function display_prices_including_tax( $customer = null ): bool {
		/**
		 * Filtering if display prices including tax or not!
		 *
		 * @param bool $including_tax True / False.
		 */
		return apply_filters(
			'storeengine/tax/display_prices_including_tax',
			'incl' === self::get_tax_price_display_mode( $customer )
		);
	}

	/**
	 * Return whether-or-not the cart is displaying prices excluding tax, rather than excluding tax.
	 *
	 * @param Customer|null|false $customer
	 *
	 * @return bool
	 */
	public static function display_prices_excluding_tax( $customer = null ): bool {
		/**
		 * Filtering if display prices including tax or not!
		 *
		 * @param bool $including_tax True / False.
		 */
		return apply_filters(
			'storeengine/tax/display_prices_excluding_tax',
			'excl' === self::get_tax_price_display_mode( $customer )
		);
	}

	public static function get_tax_label_suffix(): string {
		if ( ! StoreEngine::init()->customer ) {
			return '';
		}

		$taxable_address = StoreEngine::init()->customer->get_taxable_address();
		$label_suffix  = '';
		if ( StoreEngine::init()->customer->is_customer_outside_base() && ! StoreEngine::init()->customer->has_calculated_shipping() ) {
			/* translators: %s location. */
			$label_suffix = sprintf( ' <small>' . esc_html__( '(estimated for %s)', 'storeengine' ) . '</small>', Countries::init()->estimated_for_prefix( $taxable_address[0] ) . Countries::get_instance()->get_country( $taxable_address[0] ) );
		}

		return apply_filters( 'storeengine/tax/label_suffix', $label_suffix );
	}
}

// End of file tax-util.php.
