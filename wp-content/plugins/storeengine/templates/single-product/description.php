<?php
/**
 * @var string $content
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}
?>
<div class="storeengine-product-single__content-item storeengine-single-course__content-item--description">
	<h2><?php esc_html_e( 'Description', 'storeengine' ); ?></h2>
	<?php
	if ( isset( $content ) ) {
		echo wp_kses_post( $content );
	} else {
		the_content();
	}
	?>
</div>
