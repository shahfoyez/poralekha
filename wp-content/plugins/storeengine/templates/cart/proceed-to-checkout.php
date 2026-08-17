<?php

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$classes      = 'storeengine-btn storeengine-btn--preset-blue storeengine-btn--proceed-to-checkout';
$checkout_url = Helper::get_checkout_url();
if ( Helper::cart()->is_cart_empty() ) {
	$classes      .= ' storeengine-btn--disabled';
	$checkout_url = '#';
}
?>
<a href="<?php echo esc_url( $checkout_url ); ?>" class="<?php echo esc_attr( $classes ); ?>">
	<?php esc_html_e( 'Proceed to checkout', 'storeengine' ); ?>
</a>
