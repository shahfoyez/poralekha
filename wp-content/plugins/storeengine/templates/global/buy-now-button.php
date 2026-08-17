<?php
/**
 * Buy Now / Add to Cart purchase button.
 *
 * Single source of truth for the purchase button across single product,
 * loop, shortcode and catalog-mode templates. When `$action` is `buy_now`
 * and the Instant Checkout addon is active, the button is decorated with
 * `data-instant-checkout="modal"` + product/price IDs so the launcher
 * hijacks the click and opens the Quick Checkout modal instead of
 * running the legacy /cart/items/direct-checkout REST flow.
 *
 * @var \StoreEngine\Classes\AbstractProduct $product       Required.
 * @var string                              $action        'buy_now' (default) | 'add_to_cart'.
 * @var string                              $label         Visible / data-label text.
 * @var string                              $extra_class   Extra classes appended to the button.
 * @var string                              $icon          Optional icon HTML (wp_kses_post-allowed).
 * @var string                              $icon_position 'left' | 'right'.
 * @var bool                                $disabled      Render the disabled attribute.
 * @var int                                 $count         If > 0, replaces label with "%d in cart".
 */

use StoreEngine\Addons\InstantCheckout\Hooks as InstantCheckoutHooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( empty( $product ) || ! method_exists( $product, 'get_id' ) ) {
	return;
}

$action                    = $action ?? 'buy_now';
$storeengine_is_buy_now    = 'buy_now' === $action;
$storeengine_label         = $label ?? ( $storeengine_is_buy_now ? __( 'Buy Now', 'storeengine' ) : __( 'Add to Cart', 'storeengine' ) );
$storeengine_extra_class   = $extra_class ?? '';
$storeengine_icon          = $icon ?? '';
$storeengine_icon_position = $icon_position ?? 'left';
$storeengine_disabled      = ! empty( $disabled );
$storeengine_count         = (int) ( $count ?? 0 );

// Out-of-stock: disable the button and relabel it "Out of stock" (standard
// storefront behaviour). Variable products gate per selected variation in JS, so
// only force the parent state for non-variable products here.
$storeengine_is_variable = method_exists( $product, 'get_type' ) && 'variable' === $product->get_type();
$storeengine_in_stock    = ! method_exists( $product, 'is_in_stock' ) || $product->is_in_stock();
if ( ! $storeengine_is_variable && ! $storeengine_in_stock ) {
	$storeengine_disabled = true;
	$storeengine_count    = 0;
	$storeengine_label    = __( 'Out of stock', 'storeengine' );
}

$storeengine_action_class = $storeengine_is_buy_now ? 'storeengine-btn--direct-checkout' : 'storeengine-btn--add-to-cart';
$storeengine_icon_class   = '' !== $storeengine_icon ? 'storeengine-btn--has-icon' : '';
$storeengine_attrs        = $storeengine_is_buy_now && class_exists( InstantCheckoutHooks::class )
	? InstantCheckoutHooks::direct_checkout_attrs( $product )
	: '';
?>
<button
	class="storeengine-btn storeengine-btn--preset-blue <?php echo esc_attr( trim( $storeengine_action_class . ' ' . $storeengine_icon_class . ' ' . $storeengine_extra_class ) ); ?>"
	type="submit"
	data-label="<?php echo esc_attr( $storeengine_label ); ?>"
	data-action="<?php echo esc_attr( $action ); ?>"<?php echo $storeengine_attrs; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- helper returns pre-escaped attr string ?>
	<?php disabled( $storeengine_disabled ); ?>
><?php
	if ( '' !== $storeengine_icon && 'left' === $storeengine_icon_position ) {
		echo wp_kses_post( $storeengine_icon );
	}

	if ( $storeengine_count > 0 ) {
		// translators: %d. Item quantity for a product in the cart.
		echo esc_html( sprintf( _n( '%d in cart', '%d in cart', $storeengine_count, 'storeengine' ), $storeengine_count ) );
	} else {
		echo esc_html( $storeengine_label );
	}

	if ( '' !== $storeengine_icon && 'right' === $storeengine_icon_position ) {
		echo wp_kses_post( $storeengine_icon );
	}
?></button>
