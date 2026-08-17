<?php

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$prices     = $args['prices'] ?? [];
$num_price = count( $prices );

?>
<main id="site-content" class="storeengine-membership-main-container">
	<div class="storeengine-membership-container-div storeengine-width-50 storeengine-mx-auto">
		<?php if ( ! empty( $args['page_title'] ) ) { ?>
			<h2><?php echo esc_html( $args['page_title'] ); ?></h2>
		<?php } ?>
		<?php if ( ! empty( $args['message'] ) ) { ?>
			<div class="storeengine-unauthorized-container">
				<?php echo wp_kses_post( wpautop( $args['message'] ) ); ?>
			</div>
		<?php } ?>
		<?php if ( ! empty( $args['prices'] ) ) : ?>
			<form class="storeengine-ajax-add-to-cart-form storeengine-ajax-add-to-cart-form--single" action="#" method="post">
				<?php wp_nonce_field( 'storeengine_add_to_cart', 'storeengine_nonce' ); ?>
				<?php
				if ( 1 === $num_price ) {
					Template::get_template( 'single-product/price.php', [
						'price'   => current( $prices ),
						'checked' => true,
					] );
				} else {
					Template::get_template( 'single-product/prices.php', [ 'prices' => $prices ] );
				}
				?>

				<button class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--direct-checkout" type="submit" data-action="buy_now"><?php esc_html_e( 'Purchase Now', 'storeengine' ); ?></button>
			</form>
		<?php endif; ?>
	</div>
</main>
