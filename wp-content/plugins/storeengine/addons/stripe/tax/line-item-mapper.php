<?php
/**
 * Maps a StoreEngine Cart into a Stripe Tax Calculations API line_items[] payload.
 */

namespace StoreEngine\Addons\Stripe\Tax;

use StoreEngine\Classes\Cart;
use StoreEngine\Classes\CartItem;
use StoreEngine\Addons\Stripe\StripeService;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\TaxUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LineItemMapper {

	public static function from_cart( Cart $cart ): array {
		$default_code = (string) StripeTaxSettings::get( 'default_tax_code', 'txcd_99999999' );
		$currency     = Formatting::get_currency();
		$tax_behavior = TaxUtil::prices_include_tax() ? 'inclusive' : 'exclusive';
		$items        = [];

		foreach ( $cart->get_cart_items() as $cart_item_key => $cart_item ) {
			if ( ! $cart_item instanceof CartItem ) {
				continue;
			}

			$quantity = (int) ( $cart_item->quantity ?? 1 );

			// Prefer post-discount line_total when populated; fall back to
			// pre-discount subtotal; finally synthesize price * qty.
			if ( null !== $cart_item->line_total && '' !== $cart_item->line_total ) {
				$line_amount = (float) $cart_item->line_total;
			} elseif ( null !== $cart_item->line_subtotal && '' !== $cart_item->line_subtotal ) {
				$line_amount = (float) $cart_item->line_subtotal;
			} else {
				$line_amount = (float) $cart_item->price * $quantity;
			}

			$amount = StripeService::get_stripe_amount( $line_amount, $currency );

			$tax_code = $default_code;
			if ( $cart_item->product_id ) {
				$override = (string) get_post_meta( (int) $cart_item->product_id, '_stripe_tax_code', true );
				if ( $override ) {
					$tax_code = $override;
				}
			}

			$items[] = [
				'amount'       => $amount,
				'reference'    => (string) $cart_item_key,
				'tax_code'     => $tax_code,
				'quantity'     => $quantity,
				'tax_behavior' => $tax_behavior,
			];
		}

		return apply_filters( 'storeengine/stripe_tax/line_items', $items, $cart );
	}

	public static function shipping_cost( Cart $cart ): ?array {
		$shipping_total = (float) $cart->get_shipping_total();
		if ( $shipping_total <= 0 ) {
			return null;
		}

		return [
			'amount'       => StripeService::get_stripe_amount( $shipping_total, Formatting::get_currency() ),
			'tax_code'     => (string) StripeTaxSettings::get( 'shipping_tax_code', 'txcd_92010001' ),
			'tax_behavior' => TaxUtil::prices_include_tax() ? 'inclusive' : 'exclusive',
		];
	}
}
