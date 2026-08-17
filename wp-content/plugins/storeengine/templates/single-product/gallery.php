<?php
/**
 * Single-product gallery — custom vanilla-JS gallery (no jQuery/Flickity).
 *
 * The markup is server-rendered (works with JS disabled and for SEO); the
 * bundle enqueued in `storeengine_enqueue_product_gallery_assets()` enhances it
 * into the carousel / stacked / grid layout and wires the lightbox + zoom.
 *
 * @var int $product_id
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( ! isset( $product_id ) ) {
	$product_id = get_the_ID();
}

// Enqueue here too so the gallery works whether it's rendered via the
// [storeengine_single_product_gallery] shortcode or directly from header.php.
storeengine_enqueue_product_gallery_assets( $product_id );
?>
<div class="storeengine-gallery_wrapper">
	<?php storeengine_render_product_gallery( $product_id ); ?>
</div>
