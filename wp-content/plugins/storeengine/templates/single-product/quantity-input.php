<?php
/**
 * Add to cart quantity input.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

_deprecated_file( __FILE__, '1.6.9', 'global/quantity-input.php', 'Use storeengine_quantity_input() instead to render quantity input.' );

if ( ! isset( $quantity ) ) {
	$quantity = 1;
}

storeengine_quantity_input( ['quantity' => $quantity] );
storeengine_quantity_input( apply_filters( 'storeengine/product/quantity_form_args', ['quantity' => $quantity] ) );
