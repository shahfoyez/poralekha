<?php
/**
 * @var string $placeholder
 * @var string $button_label
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<form id="storeengine-ajax-apply-coupon-form" action="#" class="storeengine-ajax-apply-coupon-form" method="post">
	<input type="text" name="coupon_code" placeholder="<?php echo esc_attr( $placeholder ); ?>"
		   aria-label="<?php echo esc_attr( $placeholder ); ?>"/>
	<input class="storeengine-ajax-apply-coupon-form__submit" type="submit" name="apply_coupon" value="<?php echo esc_attr( $button_label ); ?>"
		   aria-label="<?php echo esc_attr( $button_label ); ?>"/>
</form>
