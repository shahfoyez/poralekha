<?php
/**
 * Stock badge for single product page.
 *
 * @var \StoreEngine\Classes\AbstractProduct $product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! isset( $product ) || ! is_object( $product ) ) {
	return;
}

if ( ! method_exists( $product, 'is_in_stock' ) ) {
	return;
}

$storeengine_is_in_stock = $product->is_in_stock();
$status                   = method_exists( $product, 'get_stock_status' ) ? $product->get_stock_status() : 'instock';
$storeengine_is_low       = method_exists( $product, 'is_low_stock' ) ? $product->is_low_stock() : false;
$storeengine_qty          = method_exists( $product, 'get_stock_quantity' ) ? $product->get_stock_quantity() : null;

// Digital products have no physical stock — never show the "In stock" badge.
// (Out-of-stock / backorder / low-stock warnings still show if stock is managed.)
$storeengine_is_digital = method_exists( $product, 'get_shipping_type' ) && 'digital' === $product->get_shipping_type();
if ( $storeengine_is_digital && $storeengine_is_in_stock && ! $storeengine_is_low && 'onbackorder' !== $status ) {
	return;
}

$storeengine_class = 'storeengine-stock-badge storeengine-stock-badge--';
if ( ! $storeengine_is_in_stock ) {
	$storeengine_class .= 'out';
} elseif ( $storeengine_is_low ) {
	$storeengine_class .= 'low';
} else {
	$storeengine_class .= 'in';
}

$storeengine_label = '';
if ( ! $storeengine_is_in_stock ) {
	$storeengine_label = __( 'Out of stock', 'storeengine' );
} elseif ( 'onbackorder' === $status ) {
	$storeengine_label = __( 'Available on backorder', 'storeengine' );
} elseif ( $storeengine_is_low && $storeengine_qty ) {
	$storeengine_label = sprintf(
		/* translators: %d: remaining stock count */
		_n( 'Only %d left in stock', 'Only %d left in stock', (int) $storeengine_qty, 'storeengine' ),
		(int) $storeengine_qty
	);
} else {
	$storeengine_label = __( 'In stock', 'storeengine' );
}
?>
<p class="<?php echo esc_attr( $storeengine_class ); ?>" role="status">
	<?php echo esc_html( $storeengine_label ); ?>
</p>

<?php if ( ! $storeengine_is_in_stock ) : ?>
	<?php
	/**
	 * Fires after the out-of-stock badge — pro back-in-stock addon hooks here
	 * to render a "Notify me" form.
	 *
	 * @param \StoreEngine\Classes\AbstractProduct $product
	 */
	do_action( 'storeengine/product/after_out_of_stock_badge', $product );
	?>
<?php endif; ?>
