<?php
namespace StoreEngine\Admin\Views;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound

?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
	<head>
		<meta charset="utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<title><?php echo esc_html__( 'StoreEngine Setup Wizard', 'storeengine' ); ?></title>
		<base target="_parent">
		<?php do_action( 'admin_print_styles' ); ?>
	</head>
	<body>
		<div id="storeengine_setup_screen_wrap" class="storeengine-setup-screen-wrap">
			<?php
				$preloader = apply_filters( 'storeengine/preloader', Helper::get_preloader_html() );
				echo wp_kses_post( $preloader );
			?>
		</div>
		<?php do_action( 'admin_print_footer_scripts' ); ?>
	</body>
</html>
