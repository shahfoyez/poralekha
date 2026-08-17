<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\InstantCheckout\Hooks as InstantCheckoutHooks;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

global $product;

$cart_item             = storeengine_cart()->get_cart_item_by_product( get_the_ID() );
$product_count_in_cart = $cart_item ? $cart_item->quantity : 0;
$prices                = $product ? $product->get_prices() : [];
$num_prices            = count( $prices );
$is_in_stock           = method_exists( $product, 'is_in_stock' ) ? $product->is_in_stock() : true;

if ( empty( $prices ) || ! $num_prices ) {
	return;
}
?>

<?php if ( 'variable' === $product->get_type() ) : ?>
	<?php if ( function_exists( 'storeengine_product_has_card_variants' ) && storeengine_product_has_card_variants( $product ) ) : ?>
		<?php
		// Interactive card: the archive script resolves the variant from the
		// swatch selection and fills the hidden price_id / variation_id, so the
		// existing delegated add-to-cart handler adds the exact variant.
		$se_card_model = storeengine_get_card_variant_model( $product );
		?>
		<form class="storeengine-ajax-add-to-cart-form storeengine-ajax-add-to-cart-form--loop storeengine-card-variant-form" action="#" method="post">
			<div class="storeengine-ajax__amount">
				<div class="storeengine-product__simple-price" data-storeengine-card-price>
					<?php echo wp_kses_post( $se_card_model['price_html'] ); ?>
				</div>
			</div>
			<div class="storeengine-add-to-cart-buttons">
				<?php wp_nonce_field( 'storeengine_add_to_cart', 'storeengine_nonce' ); ?>
				<input type="hidden" name="product_id" value="<?php the_ID(); ?>">
				<input type="hidden" name="price_id" value="" data-storeengine-card-price-id>
				<input type="hidden" name="variation_id" value="0" data-storeengine-card-variation-id>
				<button class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--add-to-cart" type="submit" data-action="add_to_cart" disabled>
					<?php esc_html_e( 'Select options', 'storeengine' ); ?>
				</button>
			</div>
		</form>
	<?php else : ?>
		<a href="<?php echo esc_url( get_the_permalink( $product->get_id() ) ); ?>" class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--view-options"><?php esc_html_e( 'View Options', 'storeengine' ); ?></a>
	<?php endif; ?>
<?php else : ?>
	<form class="storeengine-ajax-add-to-cart-form storeengine-ajax-add-to-cart-form--loop" action="#" method="post">
		<?php
		if ( 1 < $num_prices && 'price-range' === Helper::get_settings( 'product_archive_multi_price_display', 'dropdown' ) ) {
			usort( $prices, fn ( $a, $b ) => $b->get_price() - $a->get_price() );
			$max = reset( $prices );
			$min = end( $prices );
			?>
			<div class="storeengine-ajax__amount">
				<div class="storeengine-product__simple-price">
					<?php echo wp_kses_post( \StoreEngine\Utils\Formatting::format_price_range( $max->get_price(), $min->get_price() ) ); ?>
				</div>
			</div>
			<div class="storeengine-add-to-cart-buttons">
				<a href="<?php echo esc_url( get_the_permalink( $product->get_id() ) ); ?>" class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--view-options"><?php esc_html_e( 'View Options', 'storeengine' ); ?></a>
			</div>
			<?php
		} else { ?>
			<div class="storeengine-ajax__amount">
				<?php
				if ( 1 === $num_prices ) {
					Template::get_template( 'loop/price.php', [
						'price' => current( $prices ),
					] );
				} else {
					Template::get_template( 'loop/prices.php', [
						'prices' => $prices,
					] );
				}
				?>
			</div>
			<div class="storeengine-add-to-cart-buttons">
				<?php wp_nonce_field( 'storeengine_add_to_cart', 'storeengine_nonce' ); ?>
				<input type="hidden" name="product_id" value="<?php the_ID(); ?>">
				<?php
				$single_price = ( 1 === $num_prices ) ? current( $prices ) : null;
				// Subscriptions add to cart like any other product now, so no
				// special-cased Buy Now button alongside Add to Cart here.
				// Installment products still force Buy Now (direct checkout).
				$force_direct = Helper::get_settings( 'enable_direct_checkout' )
					|| Helper::get_settings( 'hide_add_to_cart' )
					|| ( $single_price && $single_price->is_type( 'installment' ) )
					|| ( class_exists( InstantCheckoutHooks::class ) && InstantCheckoutHooks::should_show_modal_button( 'archive' ) );
				?>
				<?php if ( ! $is_in_stock ) : ?>
						<button class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--add-to-cart storeengine-btn--out-of-stock" type="submit" data-action="add_to_cart" disabled><?php esc_html_e( 'Out of stock', 'storeengine' ); ?></button>
					<?php elseif ( $force_direct ) : ?>
					<?php Template::get_template( 'global/buy-now-button.php', [ 'product' => $product ] ); ?>
				<?php else : ?>
					<button class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--add-to-cart" type="submit"
							data-action="add_to_cart">
						<?php
						if ( $product_count_in_cart > 0 ) {
							// translators: %d. Item quantity for a product in the cart.
							printf( esc_html__( '%d in cart', 'storeengine' ), esc_html( $product_count_in_cart ) );
						} else {
							esc_html_e( 'Add to Cart', 'storeengine' );
						}
						?>
					</button>
				<?php endif; ?>
			</div>
		<?php } ?>
	</form>
<?php endif; ?>

