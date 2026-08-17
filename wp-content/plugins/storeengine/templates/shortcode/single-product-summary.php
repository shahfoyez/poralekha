<?php
/**
 * @var \StoreEngine\Classes\AbstractProduct $product
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Utils\Template;

?>
<h1 class="storeengine-single__title"><?php echo esc_attr( $product->get_name() ); ?></h1>
<div class="storeengine-entry_taxonomy">
	<?php Template::get_template( 'single-product/categories.php' ); ?>
	<?php Template::get_template( 'single-product/tag.php' ); ?>
</div>
<?php do_action( 'storeengine/templates/single-product/header_right_content' ); ?>
