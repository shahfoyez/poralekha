<?php
/**
 * @var $item_data array key-value item data.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( ! empty( $item_data ) ) {
	?>
	<ul class="storeengine-cart-item-data">
		<?php
		foreach ( $item_data as $data ) {
			$classNames = 'storeengine-cart-item-data-' . strtolower( str_replace( ' ', '-', wp_strip_all_tags( $data['label'] ) ) );
			if ( ! empty( $data['class'] ) ) {
				$classNames .= ' ' . $data['class'];
			}
			printf(
				'<li class="%1$s"><b>%2$s</b>: <span>%3$s</span></li>',
				sanitize_html_class( $classNames ),
				wp_kses_post( $data['label'] ),
				wp_kses_post( $data['value'] )
			);
			?>
		<?php } ?>
	</ul>
	<?php
}
