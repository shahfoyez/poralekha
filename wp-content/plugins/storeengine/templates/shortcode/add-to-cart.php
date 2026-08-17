<?php
/**
 * @var \StoreEngine\Classes\AbstractProduct $product
 * @var bool $direct_checkout
 * @var string $label
 * @var bool $show_quantity
 * @var int $quantity
 * @var int $price_id
 * @var int $variation_id
 * @var bool $disabled
 * @var array $prices
 * @var string $price_display
 * @var string $icon
 * @var string $icon_position
 * @var bool $button_display
 * @var bool $dummy
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Template;

$cart_item             = storeengine_cart()->get_cart_item_by_product( $product->get_id() );
$product_count_in_cart = $cart_item ? $cart_item->quantity : 0;
?>
<form class="storeengine-ajax-add-to-cart-form storeengine-add-to-cart-shortcode" action="#" method="post">
	<?php wp_nonce_field( 'storeengine_add_to_cart', 'storeengine_nonce' ); ?>
	<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>">
	<input type="hidden" name="variation_id" value="<?php echo esc_attr( $variation_id || $cart_item ? $cart_item->variation_id : 0 ); ?>"/>

	<?php if ( ! empty( $prices ) ) : ?>
		<div class="storeengine-single__amount">
			<?php
			if ( 'price_range' === $price_display ) {
				if ( 1 === count( $prices ) ) {
					echo wp_kses_post( $prices[0]->get_price_html() );
				} else {
					usort( $prices, fn( $a, $b ) => $b->get_price() - $a->get_price() );
					$max = reset( $prices );
					$min = end( $prices );
					echo wp_kses_post( Formatting::format_price_range( $max->get_price(), $min->get_price() ) );
				}
			} elseif ( in_array( $price_display, [ 'dropdown', 'radio' ], true ) ) {
				if ( $dummy && 'dropdown' === $price_display ) {
					?>
					<div class="storeengine-dropdown__toggle">
						<span class="storeengine-loop-product-price-summery" style="width: 100%;">
							<span class="storeengine-loop-product-price-label">
								<?php echo esc_html( $prices[0]->get_name() ); ?>
							</span>
							<span class="storeengine-loop-product-price-value">
								<?php echo wp_kses_post( $prices[0]->get_price_html() ); ?>
								<span class="storeengine-icon--arrow-square-down" aria-hidden="true"></span>
							</span>
						</span>
					</div>
					<?php
				} else {
					Template::get_template(
						( 'dropdown' === $price_display ? 'loop' : 'single-product' ) . '/prices.php',
						[
							'prices'  => $prices,
							'product' => $product,
						]
					);
				}
			} ?>
		</div>
	<?php endif; ?>
	<input type="hidden" name="price_id" value="<?php echo esc_attr( $price_id ); ?>">

	<?php if ( $button_display ): ?>
		<div class="storeengine-single-product-quantity-wrap">
			<?php if ( $show_quantity ) :
				storeengine_quantity_input(
					apply_filters(
						'storeengine/product/quantity_form_args',
						[ 'quantity' => $quantity ]
					)
				);
			else : ?>
				<input type="hidden" name="quantity" value="<?php echo esc_html( $quantity ); ?>">
			<?php endif; ?>

			<?php
			Template::get_template( 'global/buy-now-button.php', [
				'product'       => $product,
				'action'        => $direct_checkout ? 'buy_now' : 'add_to_cart',
				'label'         => $label,
				'icon'          => $icon,
				'icon_position' => $icon_position,
				'disabled'      => $disabled,
				'count'         => $product_count_in_cart,
			] );
			?>
		</div>
	<?php endif; ?>
</form>
