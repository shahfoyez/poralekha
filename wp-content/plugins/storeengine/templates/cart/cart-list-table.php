<?php
/**
 * @var string $empty_message
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Classes\Cart;
use StoreEngine\Utils\Template;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

do_action( 'storeengine/templates/before_cart_list_table' );

if ( ! storeengine_cart()->is_cart_empty() ) {
	?>
	<table class="storeengine-cart-table">
		<tbody class="storeengine-cart-table__body">
		<?php foreach ( storeengine_cart()->get_cart_items() as $key => $cart_item ) {

			$product_name      = apply_filters( 'storeengine/cart/item_name', $cart_item->name, $cart_item );
			$product_permalink = apply_filters( 'storeengine/cart/item_permalink', get_the_permalink( $cart_item->product_id ), $cart_item );
			$product_thumbnail = apply_filters( 'storeengine/cart/item_image_post_id', $cart_item->product_id, $cart_item );
			$product_price     = apply_filters(
				'storeengine/cart/item_price',
				storeengine_cart()->get_product_price(
					$cart_item->get_price(),
					$cart_item->price_id,
					$cart_item->product_id
				),
				$cart_item,
				$key
			);

			$line_subtotal = apply_filters(
				'storeengine/cart/item_subtotal',
				storeengine_cart()->get_product_subtotal(
					$cart_item->price,
					$cart_item->price_id,
					$cart_item->product_id,
					$cart_item->quantity
				),
				$cart_item,
				$key
			);
			?>
			<tr class="storeengine-cart-table__row cart-item-<?php echo esc_attr( $key ); ?>"
				data-cart_item_id="<?php echo esc_attr( $key ); ?>">

				<td class="storeengine-cart-table__body-td"
					data-title="<?php esc_attr_e( 'Product', 'storeengine' ); ?>">

					<div class="storeengine-cart-product">

						<div class="storeengine-cart-product__thumbnail">
							<a href="<?php echo esc_url( $product_permalink ); ?>">
								<?php
								storeengine_product_image(
									'thumbnail',
									$product_thumbnail,
									[
										'alt'   => $product_name,
										'class' => 'storeengine-thumbnail',
									]
								);
								?>
							</a>
						</div>

						<div class="storeengine-cart-product__content">

							<h6 class="storeengine-cart-product-title">
								<a href="<?php echo esc_url( $product_permalink ); ?>">
									<?php echo esc_html( $product_name ); ?>
								</a>
								<?php
								/**
								 * Fires inside a cart line's title, after the product
								 * name. For short inline labels that belong beside the
								 * name — e.g. a BOGO "Free" chip.
								 *
								 * @param object $cart_item The cart item.
								 * @param string $key       The cart item key.
								 */
								do_action( 'storeengine/cart/after_cart_item_title', $cart_item, $key );
								?>
							</h6>

							<?php storeengine_product_bundle_info( $cart_item->bundles ); ?>

							<?php
							Template::get_template(
								'cart/cart-item-data.php',
								[
									'item_data' => storeengine_get_cart_item_data( $cart_item ),
								]
							);
							?>

							<div class="storeengine-cart-product-price">
								<?php echo esc_html( $cart_item->price_name ); ?>
							</div>
							<?php
							/**
							 * Fires after a cart line item's content. Used to render
							 * coupon-driven labels such as a BOGO "FREE" badge.
							 *
							 * @param object $cart_item The cart item.
							 * @param string $key       The cart item key.
							 */
							do_action( 'storeengine/cart/after_cart_item_content', $cart_item, $key );
							?>

							<div class="storeengine-cart-product-price-wrap">
								<?php echo $product_price; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							</div>

						</div>

					</div>

				</td>

				<td class="storeengine-cart-table__body-td storeengine-cart-table__body-td--price"
					data-title="<?php esc_attr_e( 'Price', 'storeengine' ); ?>">

					<div class="storeengine-cart-product-quantity-wrap">

						<!-- Delete -->
						<a class="storeengine-remove-cart-item"
						   href="<?php echo esc_url( Cart::get_remove_item_url( $key ) ); ?>"
						   data-item_key="<?php echo esc_attr( $key ); ?>"
						   aria-label="<?php esc_attr_e( 'Remove this item', 'storeengine' ); ?>">
							<i class="storeengine-icon storeengine-icon--trash" aria-hidden="true"></i>
						</a>

						<!-- Quantity -->
						<?php
						storeengine_quantity_input(
							apply_filters(
								'storeengine/cart/quantity_form_args',
								[
									'name'     => 'quantity[' . $key . ']',
									'quantity' => $cart_item->quantity,
								],
								$cart_item
							)
						);
						?>

						<!-- Price -->
						<div class="storeengine-cart-item-subtotal">
							<?php echo $line_subtotal; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
						</div>

					</div>

				</td>

			</tr>
		<?php } ?>
		</tbody>
	</table>
	<?php
} else {
	if ( ! empty( $empty_message ) && is_string( $empty_message ) ) {
		echo wp_kses_post( wpautop( $empty_message ) );
	}
}

do_action( 'storeengine/templates/after_cart_list_table' );
