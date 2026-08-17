<?php

use StoreEngine\Classes\Countries;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\TaxUtil;
use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$cart = storeengine_cart();
?>

<?php do_action( 'storeengine/cart/before_cart_totals' ); ?>

<table class="storeengine-cart-sub-total-table">
	<tr class="order-subtotal">
		<th scope="row storeengine-order-summary__subtotal"><?php esc_html_e( 'Subtotal', 'storeengine' ); ?></th>
		<td data-title="<?php esc_attr_e( 'Subtotal', 'storeengine' ); ?>"><?php echo $cart->get_cart_subtotal(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
	</tr>

	<?php foreach ( Helper::cart()->get_coupons() as $coupon ) { ?>
	<tr class="storeengine-cart-sub-total-table__coupon">
		<th scope="row"><small><?php Formatting::cart_totals_coupon_label( $coupon ); ?></small></th>
		<td data-title="<?php echo esc_attr( Formatting::cart_totals_coupon_label( $coupon, false ) ); ?>"><?php Formatting::cart_totals_coupon_html( $coupon ); ?></td>
	</tr>
	<?php } ?>

	<?php
	if ( Helper::cart()->needs_shipping() && Helper::cart()->show_shipping() ) {
		Formatting::cart_totals_shipping_html();
	}
	?>

	<?php foreach ( Helper::cart()->get_fees() as $fee ) { ?>
	<tr class="order-fee">
		<th scope="row"><?php Formatting::cart_totals_fee_label( $fee ); ?></th>
		<td data-title="<?php esc_attr( Formatting::cart_totals_fee_label( $fee, false ) ); ?>"><?php echo Formatting::price( $fee->amount ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></td>
	</tr>
	<?php } ?>

	<?php Template::get_template( 'cart/cart-taxes.php' ); ?>

	<?php do_action( 'storeengine/cart/cart_totals_before_order_total' ); ?>

	<tr class="order-total">
		<th scope="row"><strong><?php echo esc_html( apply_filters( 'storeengine/cart/order_total_label', __( 'Total', 'storeengine' ), $cart ) ); ?></strong></th>
		<td data-cart-total="<?php echo esc_attr(Helper::cart()->get_total('edit')); ?>" data-title="<?php esc_attr_e( 'Total', 'storeengine' ); ?>"><?php Formatting::cart_totals_order_total_html(); ?></td>
	</tr>

	<?php do_action( 'storeengine/cart/cart_totals_after_order_total' ); ?>
</table>

<?php do_action( 'storeengine/cart/after_cart_totals' ); ?>
