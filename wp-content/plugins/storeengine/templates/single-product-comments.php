<?php
/**
 * If the current post is protected by a password and
 * the visitor has not yet entered the password,
 * return early without loading the comments.
 */

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// If neither reviews nor comments are enabled, return early
if ( post_password_required() ) {
	return;
}

/**
 * Load the comments template only when product comments are enabled. FSE themes
 * reach this through the [storeengine_single_product_comments] shortcode in the
 * block template, so we no longer bail on is_fse_theme() here.
 */
if ( ! Helper::get_settings( 'enable_product_comments', false ) ) {
	return;
}

Helper::get_template( 'single-product/comments.php' );
