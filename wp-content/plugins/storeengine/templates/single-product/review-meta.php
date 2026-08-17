<?php
/**
 * The template to display the reviewers meta data (name, verified owner, review date)
 *
 * This template can be overridden by copying it to yourtheme/storeengine/single-course/review-meta.php.
 *
 * @package StoreEngine\Templates
 * @version 1.0.0
 *
 * @var WP_Comment $comment
 */


if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}


if ( '0' === $comment->comment_approved ) { ?>
	<p class="storeengine-review-meta">
		<em class="storeengine-review-meta__awaiting-approval">
			<?php esc_html_e( 'Your review is awaiting approval', 'storeengine' ); ?>
		</em>
	</p>
<?php } else { ?>
	<div class="storeengine-review-meta">
		<?php do_action( 'storeengine/templates/review_display_rating', $comment ); ?>
		<span class="storeengine-flex storeengine-flex-align-center storeengine-flex-justify-center">
			<svg width="4" height="4" viewBox="0 0 4 4" fill="none" xmlns="http://www.w3.org/2000/svg">
				<path d="M1.708 3.43003C1.232 3.43003 0.825998 3.2667 0.489998 2.94003C0.163331 2.60403 -2.5034e-06 2.19803 -2.5034e-06 1.72203C-2.5034e-06 1.2367 0.163331 0.8307 0.489998 0.504033C0.825998 0.168033 1.232 3.27826e-05 1.708 3.27826e-05C2.19333 3.27826e-05 2.604 0.168033 2.94 0.504033C3.276 0.8307 3.444 1.2367 3.444 1.72203C3.444 2.19803 3.276 2.60403 2.94 2.94003C2.604 3.2667 2.19333 3.43003 1.708 3.43003Z" fill="#636363"/>
			</svg>
		</span>
		<time class="storeengine-review-meta__published-date moment-skip" datetime="<?php echo esc_attr( get_comment_date( 'c', $comment ) ); ?>">
			<?php echo esc_html( get_comment_date( StoreEngine\Utils\Helper::get_date_format(), $comment ) ); ?>
		</time>
	</div>
	<?php
}
