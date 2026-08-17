<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

global $storeengine_settings;
$shop_url = get_permalink( $storeengine_settings->shop_page );

?>

<a href="<?php echo esc_url( $shop_url ?? '/' ); ?>" class="storeengine-btn storeengine-btn--preset-blue storeengine-btn--continue-shopping"><?php esc_html_e( 'Continue Shopping', 'storeengine' ); ?></a>
