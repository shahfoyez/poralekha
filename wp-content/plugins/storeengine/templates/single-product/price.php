<?php
/**
 * @var Price $price
 * @var bool $checked
 * @var bool $hidden
 */

use StoreEngine\Classes\Price;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$id_attr = 'product-price-' . $price->get_product_id() . '-' . $price->get_id();

// When $hidden is true we still emit the price_id input (add-to-cart needs it)
// but skip the visible price. Used for variable products, whose effective price
// is shown by the variation-aware #storeengine-price-placeholder instead — so we
// don't render two prices (base tier + variation total) that confuse shoppers.
$hidden = isset( $hidden ) ? (bool) $hidden : false;

?>
<div class="storeengine-single-product-simple-price">
	<input type="hidden" name="price_id" value="<?php echo esc_attr( $price->get_id() ); ?>"/>
	<?php if ( ! $hidden ) : ?>
		<?php $price->print_price_html(); ?>
		<span class="storeengine-single-product-price-details"><?php $price->print_formatted_price_meta_html(); ?></span>
	<?php endif; ?>
</div>
