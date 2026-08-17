<?php
/**
 * The template to display the review thumbnail
 *
 * This template can be overridden by copying it to yourtheme/storeengine/review-thumbnail.php.
 *
 * @package StoreEngine\Templates
 * @version 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

?>
<div class="storeengine-review-thumbnail">
	<img src="<?php echo esc_url( storeengine_placeholder_image_src( 'thumbnail' ) ); ?>" alt="<?php esc_attr_e( 'Placeholder', 'storeengine' ); ?>"/>
	<img src="<?php echo esc_url( storeengine_placeholder_image_src( 'thumbnail' ) ); ?>" alt="<?php esc_attr_e( 'Placeholder', 'storeengine' ); ?>"/>
	<img src="<?php echo esc_url( storeengine_placeholder_image_src( 'thumbnail' ) ); ?>" alt="<?php esc_attr_e( 'Placeholder', 'storeengine' ); ?>"/>
	<img src="<?php echo esc_url( storeengine_placeholder_image_src( 'thumbnail' ) ); ?>" alt="<?php esc_attr_e( 'Placeholder', 'storeengine' ); ?>"/>
	<img src="<?php echo esc_url( storeengine_placeholder_image_src( 'thumbnail' ) ); ?>" alt="<?php esc_attr_e( 'Placeholder', 'storeengine' ); ?>"/>
</div>
