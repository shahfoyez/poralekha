<?php
/**
 * @var ?AbstractProduct $product
 * @var array $variation_attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Addons\InstantCheckout\Hooks as InstantCheckoutHooks;
use StoreEngine\Classes\AbstractProduct;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( ! isset( $product ) ) {
	global $product;
}

$cart_item             = storeengine_cart()->get_cart_item_by_product( $product->get_id() );
$product_count_in_cart = $cart_item ? $cart_item->quantity : 0;
$prices                = $product->get_prices();
$num_price             = count( $prices );
$is_in_stock           = method_exists( $product, 'is_in_stock' ) ? $product->is_in_stock() : true;

if ( empty( $prices ) ) {
	return;
}

// Variable product whose variants are all unconfigured (no price AND no
// pricing_id on every row, or no variants at all). Rendering the form
// would just produce an empty picker + a disabled Add-to-Cart with no
// explanation, so swap in a clear "unavailable" notice and skip the
// form entirely — matches the conventional storefront "out of stock
// and unavailable" UX for misconfigured variables.
if ( 'variable' === $product->get_type() && empty( $product->get_available_variants() ) ) {
	?>
	<p class="storeengine-product-unavailable" role="status">
		<?php esc_html_e( 'This product is not available for purchase right now — its variations have not been set up correctly. Please contact the store for help.', 'storeengine' ); ?>
	</p>
	<?php
	return;
}
?>
<?php if ( 'variable' === $product->get_type() ) { ?>
	<div id="storeengine-price-placeholder"></div>
<?php } ?>

<?php Template::get_template( 'single-product/stock.php', [ 'product' => $product ] ); ?>

<?php do_action( 'storeengine/product/before_add_to_cart_form', $product ); ?>

<form class="storeengine-ajax-add-to-cart-form storeengine-ajax-add-to-cart-form--single" action="#" method="post">
	<?php wp_nonce_field( 'storeengine_add_to_cart', 'storeengine_nonce' ); ?>
	<input type="hidden" name="product_id" value="<?php echo esc_attr( $product->get_id() ); ?>">
	<input type="hidden" id="storeengine_product_variation_id" name="variation_id" value="0"/>
	<?php
	if ( 1 === $num_price ) {
		Template::get_template( 'single-product/price.php', [
			'price'   => current( $prices ),
			'checked' => true,
			// Variable products show the variation-aware price in
			// #storeengine-price-placeholder; hide this base-tier price so the
			// page doesn't display two prices at once.
			'hidden'  => ( 'variable' === $product->get_type() ),
		] );
	}

	if ( 'variable' === $product->get_type() ) {
		Template::get_template( 'single-product/variations.php', [
			'product_type'       => $product->get_type(),
			'available_variants' => $product->get_available_variants(),
		] );
	}

	if ( $num_price > 1 ) {
		// Display style is admin-configurable: radio list (with type filter) or a
		// compact dropdown (reuses the archive/loop dropdown, which keeps the same
		// name="price_id" radios so add-to-cart / buy-now stay compatible).
		if ( 'dropdown' === Helper::get_settings( 'product_single_price_display', 'radio' ) ) {
			Template::get_template( 'single-product/prices-dropdown.php', [
				'prices'  => $prices,
				'product' => $product,
			] );
		} else {
			Template::get_template( 'single-product/prices.php', [ 'prices' => $prices ] );
		}
	}
	?>

	<?php do_action( 'storeengine/product/before_add_to_cart', $product ); ?>

	<div class="storeengine-single-product-quantity-wrap">
		<?php
		// Out-of-stock simple products: hide the quantity stepper (nothing to
		// add). Variable products keep it — stock resolves per variation in JS.
		( 'variable' === $product->get_type() || $is_in_stock ) && storeengine_quantity_input(
			apply_filters(
				'storeengine/product/quantity_form_args',
				[ 'quantity' => 1 ]
			)
		);
		?>
		<?php if (
			Helper::get_settings( 'enable_direct_checkout' )
			|| Helper::get_settings( 'hide_add_to_cart' )
			|| ( class_exists( InstantCheckoutHooks::class ) && InstantCheckoutHooks::should_show_modal_button( 'single' ) )
		) : ?>
			<?php Template::get_template( 'global/buy-now-button.php', [ 'product' => $product ] ); ?>
		<?php else : ?>
			<?php if ( $product_count_in_cart > 0 ) { ?>
				<button class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--add-to-cart" data-cart_count="<?php echo esc_attr( $product_count_in_cart ); ?>" title="<?php esc_attr_e( 'Update cart', 'storeengine' ); ?>" type="submit" data-action="add_to_cart">
					<?php
					/* translators: %d is the number of products in the cart */
					printf( esc_html( _n( '%d item in cart', '%d items in cart', $product_count_in_cart, 'storeengine' ) ), esc_html( $product_count_in_cart ) );
					?>
				</button>
			<?php } else { ?>
				<button class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--add-to-cart<?php echo ( 'variable' !== $product->get_type() && ! $is_in_stock ) ? ' storeengine-btn--out-of-stock' : ''; ?>" type="submit" data-action="add_to_cart" <?php disabled( ! $is_in_stock ); ?>><?php echo ( 'variable' !== $product->get_type() && ! $is_in_stock ) ? esc_html__( 'Out of stock', 'storeengine' ) : esc_html__( 'Add to Cart', 'storeengine' ); ?></button>
			<?php } ?>
			<?php
			// Offer a Buy Now (direct checkout) button alongside Add to Cart for
			// every in-stock product — one-time and subscription alike (variable
			// products resolve stock per variation in JS). Uses the default
			// primary style (no gray secondary preset).
			if ( 'variable' === $product->get_type() || $is_in_stock ) {
				Template::get_template( 'global/buy-now-button.php', [
					'product' => $product,
				] );
			}
			?>
		<?php endif; ?>
	</div>
	<?php if (
		! Helper::get_settings( 'hide_add_to_cart' )
		&& (
			Helper::get_settings( 'enable_direct_checkout' )
			|| ( class_exists( InstantCheckoutHooks::class ) && InstantCheckoutHooks::should_show_modal_button( 'single' ) )
		)
	) { ?>
		<div class="storeengine-ajax-add-to-cart-form__entry-footer">
			<?php if ( $product_count_in_cart > 0 ) { ?>
				<button class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--add-to-cart"
						data-cart_count="<?php echo esc_attr( $product_count_in_cart ); ?>"
						title="<?php esc_attr_e( 'Update cart', 'storeengine' ); ?>" type="submit"
						data-action="add_to_cart">
					<?php
					/* translators: %d is the number of products in the cart */
					printf( esc_html( _n( '%d item in cart', '%d items in cart', $product_count_in_cart, 'storeengine' ) ), esc_html( $product_count_in_cart ) );
					?>
				</button>
			<?php } else { ?>
				<button class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--add-to-cart" type="submit"
						data-action="add_to_cart" <?php disabled( 'variable' !== $product->get_type() && ! $is_in_stock ); ?>><?php echo ( 'variable' !== $product->get_type() && ! $is_in_stock ) ? esc_html__( 'Out of stock', 'storeengine' ) : esc_html__( 'Add to Cart', 'storeengine' ); ?></button>
			<?php } ?>
		</div>
	<?php } ?>

	<?php do_action( 'storeengine/product/after_add_to_cart', $product ); ?>
</form>

<?php do_action( 'storeengine/product/after_add_to_cart_form', $product ); ?>
