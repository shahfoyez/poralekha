<?php
/**
 * @var \StoreEngine\Classes\Price[] $prices
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>

<div class="storeengine-single-product-prices">
	<?php foreach ( $prices as $i => $price ) :
		$id_attr   = 'product-price-' . $price->get_product_id() . '-' . $price->get_id();
		$cart_item = storeengine_cart()->get_cart_item_by_product( $price->get_product_id(), $price->get_id() );
		$checked   = $cart_item || 0 === $i;
		?>
		<label class="storeengine-single-product-price" for="<?php echo esc_attr( $id_attr ); ?>">
			<span class="storeengine-single-product-price-summery">
				<span class="storeengine-single-product-price-label">
					<input
						type="radio"
						class="storeengine-radio"
						id="<?php echo esc_attr( $id_attr ); ?>"
						name="price_id"
						value="<?php echo esc_attr( $price->get_id() ); ?>"
						data-price_type="<?php echo esc_attr( $price->get_price_type() ); ?>"
						<?php echo $cart_item && $cart_item->price_id === $price->get_id() ? 'data-cart_count="' . esc_attr( $cart_item->quantity ) . '"' : ''; ?>
						<?php checked( $checked ); ?>
					/>
					<span class="storeengine-single-product-price-name"><?php echo esc_html( $price->get_name() ); ?></span>
				</span>
				<span class="storeengine-single-product-price-value"><?php $price->print_price_summery_html(); ?></span>
			</span>
			<span class="storeengine-single-product-price-details"><?php $price->print_formatted_price_meta_html(); ?></span>
		</label>
	<?php endforeach; ?>
</div>

