<?php
/**
 * @var \StoreEngine\Classes\CartItem[] $cart_items
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$cart = storeengine_cart();
?>

<div class="storeengine-order-summary">
	<h4 class="storeengine-ajax-checkout-form__title"><?php esc_html_e( 'Order details', 'storeengine' ); ?></h4>
	<div class="storeengine-order-summary-item-wrap">
		<?php
			do_action( 'storeengine/checkout/before_order_summary' );
		foreach ( $cart_items as $key => $cart_item ) {
				$item_name = apply_filters( 'storeengine/cart/item_name', $cart_item->name, $cart_item );

				/**
				 * Trim long product names in the order summary. It is a narrow
				 * sidebar column, so an untrimmed name wraps over several lines
				 * and pushes the price and quantity out of view. The full name
				 * stays available on hover. 0 disables trimming.
				 *
				 * @param int    $length    Maximum characters.
				 * @param object $cart_item The cart item.
				 */
				$name_limit   = (int) apply_filters( 'storeengine/checkout/order_summary_item_name_length', 45, $cart_item );
				$display_name = $item_name;
			if ( $name_limit > 0 && mb_strlen( $item_name ) > $name_limit ) {
				// Cut on a word boundary where possible so the trimmed name
				// does not end mid-word.
				$cut           = mb_substr( $item_name, 0, $name_limit );
				$last_space    = mb_strrpos( $cut, ' ' );
				$display_name  = rtrim( false !== $last_space && $last_space > (int) ( $name_limit * 0.6 ) ? mb_substr( $cut, 0, $last_space ) : $cut );
				$display_name .= '…';
			}

				// Which price of the product is in the cart. Only meaningful when
				// the product actually offers more than one — otherwise the name
				// ("Standard") is noise.
				$price_name   = '';
				$item_product = Helper::get_product( $cart_item->product_id );
			if ( $item_product && method_exists( $item_product, 'get_prices' ) ) {
				$item_prices = $item_product->get_prices();
				if ( is_array( $item_prices ) && count( $item_prices ) > 1 ) {
					$price_name = (string) ( $cart_item->price_name ?? '' );
				}
			}
			?>
				<div class="storeengine-order-summary__item">
					<div class="storeengine-order-item-entry-left">
						<a href="<?php the_permalink( $cart_item->product_id ); ?>">
						<?php storeengine_product_image( 'thumbnail', apply_filters( 'storeengine/cart/item_image_post_id', $cart_item->product_id, $cart_item ), [ 'class' => 'storeengine-product__thumbnail-image' ] ); ?>
						</a>
						<?php
						/*
						 * Quantity as a count bubble on the thumbnail rather than a
						 * "Quantity (1)" line — it carried the same visual weight as
						 * the price for what is usually a single digit. The styling
						 * for this badge already existed, unused.
						 */
						?>
						<span class="storeengine-order-item__qty-badge"
							aria-label="
							<?php
							/* translators: %d: quantity of this item. */
							echo esc_attr( sprintf( __( 'Quantity: %d', 'storeengine' ), (int) $cart_item->quantity ) );
							?>
							"><?php echo esc_html( $cart_item->quantity ); ?></span>
					</div>
					<div class="storeengine-order-item-entry-right">
						<div class="storeengine-order-item">
							<h6>
								<a href="<?php echo esc_attr( apply_filters( 'storeengine/cart/item_permalink', get_the_permalink( $cart_item->product_id ), $cart_item ) ); ?>"
									title="<?php echo esc_attr( $item_name ); ?>">
								<?php echo esc_html( $display_name ); ?>
								</a>
								<?php
								/**
								 * Fires inside an order-summary line's title, after the
								 * product name. For short inline labels that belong
								 * beside the name — e.g. a BOGO "Free" chip.
								 *
								 * @param object $cart_item The cart item.
								 * @param string $key       The cart item key.
								 */
								do_action( 'storeengine/checkout/after_order_summary_item_title', $cart_item, $key );
								?>
							</h6>
						<?php storeengine_product_bundle_info( $cart_item->bundles ); ?>
						<?php Template::get_template( 'cart/cart-item-data.php', [ 'item_data' => storeengine_get_cart_item_data( $cart_item ) ] ); ?>
						<?php
						/**
						 * Fires inside a checkout order-summary line item, below the
						 * product details. Used for per-line labels that belong with
						 * the product — e.g. a BOGO "Free" chip.
						 *
						 * @param object $cart_item The cart item.
						 * @param string $key       The cart item key.
						 */
						do_action( 'storeengine/checkout/order_summary_item_meta', $cart_item, $key );
						?>
							<div class="storeengine-order-item__the-sum">
								<div class="storeengine-order-item__price">
								<?php echo isset( $cart_item->price_html ) ? wp_kses_post( $cart_item->price_html ) : storeengine_cart()->get_product_price( $cart_item->get_price(), $cart_item->price_id, $cart_item->product_id ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
								<?php
								// Which price of a multi-price product this is, shown with the
								// amount it belongs to rather than on a line of its own.
								if ( '' !== $price_name ) {
									printf( '<span class="storeengine-order-item__price-name">%s</span>', esc_html( $price_name ) );
								}
								?>
								</div>
							</div>
						</div>
					</div>
					<?php
					/**
					 * Fires after a checkout order-summary line item. Used to render
					 * coupon-driven labels such as a BOGO "FREE" badge.
					 *
					 * @param object $cart_item The cart item.
					 * @param string $key       The cart item key.
					 */
					do_action( 'storeengine/checkout/after_order_summary_item', $cart_item, $key );
					?>
				</div>
		<?php } ?>
	</div>
</div>
