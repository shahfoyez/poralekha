<?php
/**
 * No Content message template.
 * @version 1.0.0
 * @since 1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$storeengine_image   = $image ?? STOREENGINE_ASSETS_URI . 'images/NoDataAvailable.svg';
$storeengine_title   = $title ?? __( 'No content Available!', 'storeengine' );
$storeengine_message = $message ?? esc_html__( 'No data was found to see the available list here.', 'storeengine' );
$storeengine_classes = 'storeengine-oops storeengine-oops__message ' . ( $classes ?? '' );

?>
<div class="<?php echo esc_attr( trim( $storeengine_classes ) ); ?>">
	<?php if ( $storeengine_image ) { ?>
		<div class="storeengine-oops__icon">
			<img src="<?php echo esc_url( $storeengine_image ); ?>" alt="<?php echo esc_attr( $storeengine_title ); ?>">
		</div>
		<div class="storeengine-oops__content">
			<h3 class="storeengine-oops__heading"><?php echo esc_html( $storeengine_title ); ?></h3>
			<div class="storeengine-oops__text"><?php echo wp_kses_post( wpautop( wptexturize( $storeengine_message ) ) ); ?></div>
		</div>
	<?php } else { ?>
		<div class="storeengine-oops__icon">
			<h3 class="storeengine-oops__heading"><?php echo esc_html( $storeengine_title ); ?></h3>
			<div class="storeengine-oops__text"><?php echo wp_kses_post( wpautop( wptexturize( $storeengine_message ) ) ); ?></div>
		</div>
	<?php } ?>
</div>
