<?php
/**
 * @var bool $hide_pricing
 * @var bool $hide_add_to_cart
 * @var string $pricing_placeholder
 * @var string $add_to_cart_placeholder
 * @var ?array $add_to_cart_button
 */

use StoreEngine\Addons\InstantCheckout\Hooks as InstantCheckoutHooks;
use StoreEngine\Classes\AbstractProduct;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

/** @var AbstractProduct $product */
global $product;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

$cart_item             = storeengine_cart()->get_cart_item_by_product( get_the_ID() );
$product_count_in_cart = $cart_item ? $cart_item->quantity : 0;
?>

<?php if ( 'variable' === $product->get_type() ) { ?>
	<div id="storeengine-price-placeholder"></div>
<?php } ?>

<?php do_action( 'storeengine/product/before_add_to_cart_form', $product ); ?>

<form class="storeengine-catalog-mode storeengine-catalog-mode--single-product storeengine-ajax-add-to-cart-form storeengine-ajax-add-to-cart-form--single" action="#" method="post">
	<?php
	if ( $hide_pricing ) {
		if ( $pricing_placeholder ) {
			?>
			<div class="storeengine-catalog-mode--pricing-placeholder storeengine-mt-6">
				<p><?php echo wp_kses_post( $pricing_placeholder ); ?></p>
			</div>
			<?php
		}
	} else {
		$prices    = $product->get_prices();
		$num_price = count( $prices );

		if ( 1 === $num_price ) {
			Template::get_template( 'single-product/price.php', [
				'price'   => current( $prices ),
				'checked' => true,
			] );
		}

		if ( 'variable' === $product->get_type() ) {
			Template::get_template( 'single-product/variations.php', [
				'product_type'       => $product->get_type(),
				'available_variants' => $product->get_available_variants(),
			] );
		}

		if ( $num_price > 1 ) {
			Template::get_template( 'single-product/prices.php', [ 'prices' => $prices ] );
		}
	}

	if ( $hide_add_to_cart ) {
		if ( $add_to_cart_placeholder ) {
			?>
			<div class="storeengine-catalog-mode--add-to-cart-placeholder storeengine-mt-6">
				<p><?php echo wp_kses_post( $add_to_cart_placeholder ); ?></p>
			</div>
			<?php
		}
		if ( $add_to_cart_button ) {
			?>
			<div class="storeengine-catalog-mode--add-to-cart-replacement storeengine-mt-6">
				<div class="storeengine-add-to-cart-buttons">
					<a href="<?php echo esc_url( $add_to_cart_button['button_link'] ); ?>" class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--add-to-cart-replacement" rel="<?php echo esc_attr( $add_to_cart_button['button_rel'] ); ?>" target="<?php echo esc_attr( $add_to_cart_button['button_target'] ); ?>"><?php echo esc_html( $add_to_cart_button['button_text'] ); ?></a>
				</div>
			</div>
			<?php
		}
	} else { ?>
		<?php wp_nonce_field( 'storeengine_add_to_cart', 'storeengine_nonce' ); ?>
		<input type="hidden" name="product_id" value="<?php the_ID(); ?>">
		<input type="hidden" id="storeengine_product_variation_id" name="variation_id" value="0"/>

		<?php do_action( 'storeengine/product/before_add_to_cart', $product ); ?>

		<div class="storeengine-single-product-quantity-wrap">
			<?php storeengine_quantity_input(
				apply_filters(
					'storeengine/product/quantity_form_args',
					[ 'quantity' => 1 ]
				)
			); ?>
			<?php if (
				Helper::get_settings( 'enable_direct_checkout' )
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
					<button class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--add-to-cart" type="submit" data-action="add_to_cart"><?php esc_html_e( 'Add to Cart', 'storeengine' ); ?></button>
				<?php } ?>
			<?php endif; ?>
		</div>
		<?php if (
			Helper::get_settings( 'enable_direct_checkout' )
			|| ( class_exists( InstantCheckoutHooks::class ) && InstantCheckoutHooks::should_show_modal_button( 'single' ) )
		) { ?>
			<div class="storeengine-ajax-add-to-cart-form__entry-footer">
				<?php if ( $product_count_in_cart > 0 ) { ?>
					<button class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--add-to-cart" data-cart_count="<?php echo esc_attr( $product_count_in_cart ); ?>" title="<?php esc_attr_e( 'Update cart', 'storeengine' ); ?>" type="submit" data-action="add_to_cart">
						<?php
						/* translators: %d is the number of products in the cart */
						printf( esc_html( _n( '%d item in cart', '%d items in cart', $product_count_in_cart, 'storeengine' ) ), esc_html( $product_count_in_cart ) );
						?>
					</button>
				<?php } else { ?>
					<button class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--add-to-cart" type="submit" data-action="add_to_cart"><?php esc_html_e( 'Add to Cart', 'storeengine' ); ?></button>
				<?php } ?>
			</div>
		<?php } ?>

		<?php do_action( 'storeengine/product/after_add_to_cart', $product ); ?>
		<?php
	}
	?>
</form>

<?php do_action( 'storeengine/product/after_add_to_cart_form', $product ); ?>
