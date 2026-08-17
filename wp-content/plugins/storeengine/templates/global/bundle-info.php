<?php
/**
 * @var $cart_item CartItem Cart item.
 */

use StoreEngine\Classes\CartItem;
use StoreEngine\Classes\Price;
use StoreEngine\Utils\Formatting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( ! empty( $bundles ) && is_array( $bundles ) ) {
	$bundle_panel_id = wp_unique_id( 'storeengine-bundle-items-' );
	?>
	<div class="storeengine-bundle-info">
		<button type="button" class="bundle-info-toggle storeengine-bundle-toggle" aria-expanded="false" aria-controls="<?php echo esc_attr( $bundle_panel_id ); ?>">
			<span class="storeengine-bundle-toggle__label"><?php esc_html_e( 'Bundled Items', 'storeengine' ); ?></span>
			<span class="storeengine-bundle-toggle__icon storeengine-icon storeengine-icon--arrow-down" aria-hidden="true"></span>
		</button>
		<div id="<?php echo esc_attr( $bundle_panel_id ); ?>" class="bundle-items storeengine-bundle-panel" aria-hidden="true">
			<?php
			foreach ( $bundles as $bundle ) {
				try {
					$bundle = wp_parse_args( $bundle, [
						'product_id'   => 0,
						'price_id'     => 0,
						'product_name' => '',
						'quantity'     => 1,
						'price'        => 0,
						'price_name'   => '',
					] );
					?>
					<?php
					$bundle_quantity    = (int) $bundle['quantity'];
					$bundle_price_label = $bundle['price_name'];
					$permalink = '';
					if ( ! in_array( get_post_status( $bundle['product_id'] ), [ 'trash', 'draft', 'auto-draft' ], true ) ) {
						$permalink = get_permalink( $bundle['product_id'] );
					}
					?>
						<?php if ( $bundle['product_name'] ) { ?>
							<div class="bundle-item storeengine-bundle-item">
							<div class="item-thumb storeengine-bundle-item__thumb">
								<?php storeengine_product_image( 'small', $bundle['product_id'], [ 'alt' => $bundle['product_name'] ] ); ?>
							</div>
							<div class="item-main storeengine-bundle-item__main">
								<?php if ( ! $permalink ) { ?>
									<span class="item-name storeengine-bundle-item__name" title="<?php echo esc_attr( $bundle['product_name'] ); ?>"><?php echo esc_html( $bundle['product_name'] ); ?></span>
								<?php } else { ?>
									<a class="item-name storeengine-bundle-item__name" href="<?php echo esc_url( $permalink ); ?>" title="<?php echo esc_attr( $bundle['product_name'] ); ?>" target="_blank"><?php echo esc_html( $bundle['product_name'] ); ?></a>
								<?php } ?>
								<div class="item-quantity storeengine-bundle-item__quantity"><span>&times; <?php echo esc_html( $bundle_quantity ); ?></span></div>
							</div>
							<div class="item-price storeengine-bundle-item__price">
								<span class="screen-reader-text">
								<?php
								$price = Formatting::price( Formatting::get_price_to_display( $bundle['price'], null, null, [ 'qty' => $bundle_quantity ] ) );
								printf(
								// translators: %s. Item price.
									esc_html__( 'Original price is %s', 'storeengine' ),
									esc_html( wp_strip_all_tags( $price ) )
								);
								?>
								</span>
								<span aria-hidden="true">
									<span class="bundle-price-amount"><?php echo wp_kses_post( $price ); ?></span>
									<?php if ( $bundle_price_label ) { ?>
										<span class="price-name">(<?php echo esc_html( $bundle_price_label ); ?>)</span>
									<?php } ?>
								</span>
							</div>
							</div>
						<?php } ?>
					<?php
				} catch ( Exception $e ) {
					// NoOp.
				}
			}
			?>
		</div>
	</div>
	<?php
}
