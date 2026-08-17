<?php
/**
 * Output a single payment method
 *
 * @package     StoreEngine\Templates
 * @version     1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @var StoreEngine\Payment\Gateways\PaymentGateway $gateway */

?>
<div class="storeengine-checkout-available-payment-item storeengine-checkout-available-payment-item--<?php echo esc_attr( $gateway->id ); ?>">
	<div class="storeengine-accordion-drawer">
		<input id="payment_method_<?php echo esc_attr( $gateway->id ); ?>" class="storeengine-accordion-drawer__trigger storeengine-radio" type="radio" name="payment_method" value="<?php echo esc_attr( $gateway->id ); ?>"<?php checked( isset($selected) && is_string($selected) ? $selected === $gateway->id : $gateway->is_current() ); ?>>
		<label class="storeengine-accordion-drawer__title" for="payment_method_<?php echo esc_attr( $gateway->id ); ?>">
			<?php echo $gateway->get_icon(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.XSS.EscapeOutput.OutputNotEscaped -- Escaped inside the getter. ?>
			<span class="storeengine-ajax-checkout-form__title"><?php echo esc_html( $gateway->get_title() ); ?></span>
		</label>
		<div class="storeengine-payment-box storeengine-accordion-drawer__content-wrapper">
			<div class="storeengine-accordion-drawer__content"><?php $gateway->payment_fields(); ?></div>
		</div>
	</div>
</div>
<?php

// End of file payment-method.php
