<?php
/**
 * @var Price $price
 * @var bool $checked
 * @var bool $hidden
 */

use StoreEngine\Classes\Price;
use StoreEngine\Classes\Product\SimpleProduct;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

if ( ! isset( $product ) ) {
	/** @var SimpleProduct $product */
	global $product;
}

?>
<div class="storeengine-product__simple-price">
	<input type="hidden" name="price_id" value="<?php echo esc_attr( $price->get_id() ); ?>" />
	<?php $price->print_price_html(); ?>
</div>
<?php
// Render the price meta (e.g. installment down-payment + count) as a sibling of
// the price — outside .storeengine-product__simple-price — so it can be styled
// as its own block instead of flowing inline with the price.
$storeengine_price_meta_html = $price->get_formatted_price_meta_html();
if ( '' !== trim( (string) $storeengine_price_meta_html ) ) {
	// Loops (shop, category/tag, vendor store) show a short summary styled as a
	// compact pill. The single product page renders the richer meta with the
	// collapsible "Payment Schedule" dropdown — keep that a plain block (this
	// also covers a related-products loop on a single product page) so the pill
	// styling doesn't wrap/break the dropdown.
	$storeengine_details_class = 'storeengine-loop-product-price-details';
	if ( ! \StoreEngine\Utils\Helper::is_product() ) {
		$storeengine_details_class .= ' storeengine-loop-product-price-details--pill';
	}
	echo '<div class="' . esc_attr( $storeengine_details_class ) . '">' . wp_kses_post( $storeengine_price_meta_html ) . '</div>';
}
?>
