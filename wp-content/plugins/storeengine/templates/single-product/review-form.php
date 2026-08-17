<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use StoreEngine\Utils\Helper;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

if ( ! storeengine_can_review_product( get_the_ID() ) ) {
	return;
}

$storeengine_review_media_max      = storeengine_review_media_max();
$storeengine_review_media_max_size = storeengine_review_media_max_size( get_the_ID() );

?>

<div class="storeengine-review-form storeengine-review-form--open-form storeengine-mb-4">
	<div class="storeengine-review-form__add-review">
		<div class="storeengine-review-form__intro">
			<span class="storeengine-review-form__title"><?php esc_html_e( 'Share your experience', 'storeengine' ); ?></span>
			<span class="storeengine-review-form__subtitle"><?php esc_html_e( 'Tell other shoppers what you think about this product.', 'storeengine' ); ?></span>
		</div>
	</div>
	<div class="storeengine-review-form_survey-form-wrap">
		<?php
		$comment_form = [
			/* translators: %s is product title */
			'title_reply'         => '',
			/* translators: %s is product title */
			'title_reply_to'      => esc_html__( 'Leave a Reply to %s', 'storeengine' ),
			'title_reply_before'  => '<span id="reply-title" class="storeengine-review-reply-title">',
			'title_reply_after'   => '</span>',
			'comment_notes_after' => '',
			'label_submit'        => esc_html__( 'Submit', 'storeengine' ),
			'class_submit'        => 'storeengine-btn storeengine-btn--bg-purple',
			'logged_in_as'        => '',
		];

		$name_email_required = true;
		$fields              = [
			'author' => [
				'label'    => __( 'Name', 'storeengine' ),
				'type'     => 'text',
				'value'    => '',
				'required' => $name_email_required,
			],
			'email'  => [
				'label'    => __( 'Email', 'storeengine' ),
				'type'     => 'email',
				'value'    => '',
				'required' => $name_email_required,
			],
		];

		$comment_form['fields'] = array();

		foreach ( $fields as $key => $field ) {
			$field_html  = '<p class="storeengine-review-form-' . esc_attr( $key ) . '">';
			$field_html .= '<label for="' . esc_attr( $key ) . '">' . esc_html( $field['label'] );

			if ( $field['required'] ) {
				$field_html .= '&nbsp;<span class="required">*</span>';
			}

			$field_html .= '</label><input id="' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" type="' . esc_attr( $field['type'] ) . '" value="' . esc_attr( $field['value'] ) . '" size="30" ' . ( $field['required'] ? 'required' : '' ) . ' /></p>';

			$comment_form['fields'][ $key ] = $field_html;
		}

		$comment_form['must_log_in'] = sprintf(
			'<p class="must-log-in">%s</p>',
			sprintf(
				/* translators: %s opening and closing link tags respectively */
				esc_html__( 'You must be %1$slogged in%2$s to post a review.', 'storeengine' ),
				'<a href="' . esc_url( storeengine_login_url( get_permalink() ) ) . '">', '</a>'
			)
		);

		$comment_form['comment_field'] =
		'<div class="storeengine-review-form-rating"><select name="storeengine_rating" id="storeengine_rating" required>
				<option value="">' . esc_html__( 'Rate&hellip;', 'storeengine' ) . '</option>
				<option value="5">' . esc_html__( 'Perfect', 'storeengine' ) . '</option>
				<option value="4">' . esc_html__( 'Good', 'storeengine' ) . '</option>
				<option value="3">' . esc_html__( 'Average', 'storeengine' ) . '</option>
				<option value="2">' . esc_html__( 'Not that bad', 'storeengine' ) . '</option>
				<option value="1">' . esc_html__( 'Very poor', 'storeengine' ) . '</option>
		</select></div>';

		$comment_form['comment_field'] .= '<p class="storeengine-review-form-review"><textarea id="storeengine_comment" class="storeengine-input" name="comment" cols="45" rows="8" placeholder="' . esc_attr__( 'Enter your feedback', 'storeengine' ) . '" required></textarea></p>';

		// Media (images/videos) uploader. Files are uploaded via AJAX before
		// submit; their attachment IDs are stored in the hidden field below and
		// saved as comment meta once the review is posted.
		$comment_form['comment_field'] .= '<div class="storeengine-review-form-media" data-product-id="' . esc_attr( get_the_ID() ) . '" data-max="' . esc_attr( $storeengine_review_media_max ) . '" data-max-size="' . esc_attr( $storeengine_review_media_max_size ) . '">' .
			'<label class="storeengine-review-form-media__label">' .
			'<span class="storeengine-review-form-media__icon" aria-hidden="true"></span>' .
			esc_html__( 'Add photos or videos', 'storeengine' ) .
			'<input type="file" class="storeengine-review-form-media__input" accept="image/*,video/*" multiple />' .
			'</label>' .
			'<div class="storeengine-review-form-media__previews" aria-live="polite"></div>' .
			'<input type="hidden" name="storeengine_review_media" class="storeengine-review-form-media__ids" value="" />' .
			'</div>';

		comment_form( apply_filters( 'storeengine/templates/product_review_comment_form_args', $comment_form ) );
		?>
	</div>
</div>
