<?php
/**
 * @var string $placeholder
 * @var string $button_label
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Utils\Template;

?>

<div class="storeengine-apply-coupon-form-shortcode">
	<?php
	Template::get_template( 'cart/coupon-form.php', [
		'placeholder'  => $placeholder,
		'button_label' => $button_label,
	] );
	?>
</div>
