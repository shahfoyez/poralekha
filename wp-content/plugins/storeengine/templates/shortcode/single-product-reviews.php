<?php
/**
 * @var object $rating
 * @var int $product_id
 */

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="single-product-reviews-shortcode">
	<?php
	Helper::get_template( 'single-product/feedback.php', [ 'rating' => $rating ] );
	Helper::get_template( 'single-product/reviews.php', [ 'product_id' => $product_id ] );
	?>
</div>
