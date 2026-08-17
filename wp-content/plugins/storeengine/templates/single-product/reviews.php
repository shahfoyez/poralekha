<?php
/**
 * @var int $product_id
 */

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

?>

<div id="storeengine-reviews" class="storeengine-single-product__content-item storeengine-single-product__content-item--reviews">
	<?php
	/**
	 * The storeengine/templates/single_filter hook.
	 *
	 * @hooked single_filter - 10
	 */
	do_action( 'storeengine/templates/single_filter' );

	// @TODO 3x query for showing command & verifying if the user is commented.

	// @XXX this can be stored into product meta. For simplicity,
	//      we can suffix the meta-key with the user-id and store the timestamp or comment id.
	//      E.G get_post_meta( $prodId, 'user_review_' . get_current_user_id(), true );
	$user_comment = get_comments( [
		'user_id'  => get_current_user_id(),
		'post_id'  => $product_id,
		'meta_key' => 'storeengine_rating', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
		'count'    => true,
	] );

	if ( ! $user_comment ) {
		// @XXX we're hiding the form if user already commented before.
		//      but are we blocking the request?!... user can make request through console or postman.
		Helper::get_template( 'single-product/review-form.php' );
	}

	$paged             = max( absint( get_query_var( 'cpage' ) ), 1 ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	$comments_per_page = 5;
	$args              = [
		'post_id'  => $product_id,
		'status'   => 'approve',
		'number'   => $comments_per_page,
		'paged'    => $paged,
		'type'     => 'storeengine_product',
		'meta_key' => 'storeengine_rating', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
	];

	$comment_query = new \WP_Comment_Query();
	$comments      = $comment_query->query( $args ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

	if ( ! empty( $comments ) ) : ?>
		<ol class="storeengine-review-list">
			<?php
			foreach ( $comments as $comment ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
				storeengine_review_lists( $comment );
			}
			?>
		</ol>
	<?php endif; ?>
</div><!-- #comments -->
