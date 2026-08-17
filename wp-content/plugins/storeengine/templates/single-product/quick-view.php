<?php
/**
 * Quick View — a compact single-product page rendered into the archive modal.
 * Reuses the real single-product gallery + summary (header_right_content), so
 * behaviour stays identical to the full product page.
 *
 * @var \StoreEngine\Classes\AbstractProduct $product
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Template;

$product_id = $product->get_id();
$excerpt    = get_the_excerpt( $product_id );
?>
<div class="storeengine-quick-view">
	<div class="storeengine-quick-view__media">
		<?php Template::get_template( 'single-product/gallery.php', [ 'product_id' => $product_id ] ); ?>
	</div>
	<div class="storeengine-quick-view__summary">
		<h2 class="storeengine-quick-view__title">
			<a href="<?php echo esc_url( get_permalink( $product_id ) ); ?>"><?php echo esc_html( $product->get_name() ); ?></a>
		</h2>

		<?php
		// Star-rating summary (only when the product has approved reviews).
		$se_rating       = (object) \StoreEngine\Models\Product::get_product_rating( $product_id );
		$se_rating_avg   = (float) ( $se_rating->rating_avg ?? 0 );
		$se_rating_count = (int) ( $se_rating->rating_count ?? 0 );
		if ( $se_rating_count > 0 ) :
			?>
			<div class="storeengine-quick-view__rating">
				<span class="storeengine-quick-view__stars"><?php echo wp_kses_post( \StoreEngine\Utils\Helper::star_rating_generator( $se_rating_avg ) ); ?></span>
				<span class="storeengine-quick-view__rating-count">
					<?php
					echo esc_html(
						sprintf(
							/* translators: %1$s: average rating, %2$d: review count. */
							_n( '%1$s (%2$d review)', '%1$s (%2$d reviews)', $se_rating_count, 'storeengine' ),
							number_format_i18n( $se_rating_avg, 1 ),
							$se_rating_count
						)
					);
					?>
				</span>
			</div>
		<?php endif; ?>

		<div class="storeengine-entry_taxonomy storeengine-quick-view__tax">
			<?php Template::get_template( 'single-product/categories.php' ); ?>
		</div>

		<?php
		// Low-stock urgency ("Only N left") when the product manages stock and
		// is at/under its low-stock threshold.
		if ( $product->is_low_stock() ) :
			$se_stock_qty = (int) $product->get_stock_quantity();
			?>
			<p class="storeengine-quick-view__stock-urgency">
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: remaining stock quantity. */
						_n( 'Only %d left in stock', 'Only %d left in stock', $se_stock_qty, 'storeengine' ),
						$se_stock_qty
					)
				);
				?>
			</p>
		<?php endif; ?>

		<div class="storeengine-quick-view__cart">
			<?php do_action( 'storeengine/templates/single-product/header_right_content' ); ?>
		</div>

		<?php if ( $excerpt ) : ?>
			<div class="storeengine-quick-view__excerpt"><?php echo wp_kses_post( wpautop( $excerpt ) ); ?></div>
		<?php endif; ?>

		<a class="storeengine-quick-view__full-link" href="<?php echo esc_url( get_permalink( $product_id ) ); ?>">
			<?php esc_html_e( 'View full details', 'storeengine' ); ?>
		</a>
	</div>
</div>
