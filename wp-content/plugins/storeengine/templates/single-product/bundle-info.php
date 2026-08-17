<?php
/**
 * @var AbstractProduct|BundledProduct $product
 */

use StoreEngine\Classes\AbstractProduct;
use StoreEngine\Classes\Price;
use StoreEngine\Classes\Product\BundledProduct;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

global $product;

if ( $product && 'bundled' === $product->get_type() ) {
	$bundles = $product->get_bundles();
	if ( ! empty( $bundles ) ) {
		?>
		<div class="storeengine-single-product-bundle">
			<table class="formatted-bundle-info">
				<?php
				foreach ( $bundles as [ 'price_id' => $price_id ] ) {
					try {
						$bundle_price = new Price( $price_id );
						$bundle_product = $bundle_price->get_product();
						?>
						<tr>
							<th>
								<a href="<?php echo esc_url( $bundle_product->get_permalink() ); ?>"><?php echo esc_html( $bundle_product->get_name() ); ?></a>
								<span>&times; 1</span>
							</th>
							<td>
								<?php echo wp_kses_post( $bundle_price->get_price_html() ); ?>
								<span>(<?php echo esc_html( $bundle_price->get_name() ); ?>)</span>
							</td>
						</tr>
						<?php
					} catch ( Exception $e ) {
						// No-Op
					}
				}
				?>
			</table>
		</div>
		<?php
	}
}
