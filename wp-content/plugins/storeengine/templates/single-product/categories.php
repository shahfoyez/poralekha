<?php

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

global $product;

$product_id = $product->get_id();
$categories = get_the_terms( $product_id, Helper::PRODUCT_CATEGORY_TAXONOMY );
if ( $categories && ! is_wp_error( $categories ) ) {
	?>
	<div class="storeengine-single-product-categories">
			<span
				class="storeengine-single-product-categories__label"><?php echo esc_html__( 'Categories:', 'storeengine' ); ?></span>
		<span class="storeengine-single-product-categories__items">
				<?php
				$category_links = array();
				foreach ( $categories as $category ) {
					$category_links[] = '<a href="' . esc_url( get_term_link( $category ) ) . '">' . esc_html( $category->name ) . '</a>';
				}
				echo wp_kses( implode( ',', $category_links ), [
					'a' => [
						'href'   => true,
						'target' => true,
						'title'  => true,
					],
				] );
				?>
			</span>
	</div>
	<?php
}
?>
