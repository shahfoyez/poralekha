<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit(); // Exit if accessed directly
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedFunctionFound

?>
<div id="default-comments" class="storeengine-container storeengine-products-comment">

	<?php
	// You can start editing here -- including this comment!
	if ( have_comments() ) :
		?>
		<div class="title">
			<h3 class="comments-title">
				<span class="storeengine-default-comments-title">
					<?php
					$storeengine_comments_number = get_comments_number();
					printf(
					/* translators: %s: Number of comments. */
						esc_html( _n( '%s Comment About This Product', '%s Comments About This Product', $storeengine_comments_number, 'storeengine' ) ),
						esc_html( number_format_i18n( $storeengine_comments_number ) )
					);
					?>
				</span>
			</h3><!-- .comments-title -->
		</div>

		<?php the_comments_navigation(); ?>

		<ol class="comment-list storeengine-comments-list">
			<?php wp_list_comments( [
				'callback' => 'storeengine_comments',
				'style'    => 'ol',
			] ); ?>
		</ol><!-- .comment-list -->

		<?php
		the_comments_navigation();

		// If comments are closed and there are comments, let's leave a little note, shall we?
		if ( ! comments_open() ) :
			?>
			<p class="no-comments"><?php esc_html_e( 'Comments are closed.', 'storeengine' ); ?></p>
		<?php
		endif;
	endif; // Check for have_comments().

	comment_form( [
		'comment_notes_before' => '',
		'label_submit'         => __( 'Submit', 'storeengine' ),
		'class_form'           => 'storeengine-comment-form',
		'must_log_in'          => sprintf(
			'<p class="must-log-in">%s</p>',
			sprintf(
			/* translators: %s opening and closing link tags respectively */
				esc_html__( 'You must be %1$slogged in%2$s to post a comment.', 'storeengine' ),
				'<a href="' . esc_url( storeengine_login_url( get_permalink() ) ) . '">', '</a>'
			)
		),
	] );
	?>

</div><!-- #comments -->
