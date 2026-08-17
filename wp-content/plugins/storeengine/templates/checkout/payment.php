<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$available_gateways   = Helper::get_payment_gateways()->get_available_payment_gateways();
$order_button_classes = 'storeengine-btn storeengine-btn--preset-blue';

// On the order-pay page there is no cart to read a total from — the cart is
// empty (e.g. an early subscription renewal never touches it) — so fall back
// to the order's own total. Otherwise a site with no subscription-capable
// gateway enabled leaves this button clickable with zero payment methods
// rendered, letting the customer submit a broken payment.
$is_order_pay_page = Formatting::string_to_bool( get_query_var( 'order_pay' ) );
if ( $is_order_pay_page ) {
	$order_pay_order = Helper::get_order( absint( get_query_var( 'order_id' ) ) );
	$relevant_total = ( $order_pay_order && ! is_wp_error( $order_pay_order ) ) ? (float) $order_pay_order->get_total( 'edit' ) : 0.0;
} else {
	$relevant_total = (float) storeengine()->cart->get_total( 'edit' );
}

$order_button_disabled = empty( $available_gateways ) && 0 < $relevant_total;

if ( $order_button_disabled ) {
	$order_button_classes .= ' storeengine-btn--disabled';
}

?>
<div class="storeengine-ajax-checkout-form__payments">
	<?php if ( $needs_payment ?? false ) { ?>
		<div class="storeengine-checkout-available-payments">
			<h6 class="storeengine-ajax-checkout-form__heading"><?php esc_html_e( 'Payment Method', 'storeengine' ); ?></h6>
			<?php
			if ( ! empty( $available_gateways ) ) {
				foreach ( $available_gateways as $gateway ) {
					Template::get_template( 'checkout/payment-method.php', [ 'gateway' => $gateway ] );
				}
			} else {
				$notice = apply_filters( 'storeengine/checkout_page/no_payment_method_notice', [
					'message' => __( 'No payment methods are available right now. Please contact support and we’ll assist you in completing your payment.', 'storeengine' ),
					'title'   => __( 'Unable to Process Payment', 'storeengine' ),
				] );

				storeengine_show_notice( $notice['message'], [ 'title' => $notice['title'], 'type' => 'secondary' ] );
			}
			?>
		</div>
	<?php } ?>

	<div class="storeengine-place-order">
		<noscript>
			<?php
				storeengine_show_notice(
					__( 'It looks like JavaScript is disabled in your browser. Please enable JavaScript and reload this page to continue, or contact support for assistance.', 'storeengine' ),
					[
						'title' => __( 'JavaScript is required to complete checkout.', 'storeengine' ),
						'type'  => 'secondary',
					]
				);
			?>
		</noscript>

		<?php do_action( 'storeengine/checkout_page/before_submit' ); ?>

		<div class="storeengine-payment-elements storeengine-checkout__order-btn" id="storeengine-payment-elements">
			<button type="submit" class="<?php echo esc_attr( $order_button_classes ); ?>" name="storeengine_checkout_place_order" id="storeengine_place_order"<?php disabled( $order_button_disabled ); ?>><?php echo esc_html( $place_order_label ?? __('Place Order', 'storeengine') ); ?></button>
		</div>

		<?php do_action( 'storeengine/checkout_page/after_submit' ); ?>
	</div>
</div>
