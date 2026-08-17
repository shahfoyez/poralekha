<?php

namespace StoreEngine\Ajax;

use StoreEngine\Classes\AbstractAjaxHandler;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Frontend AJAX for product reviews.
 *
 * Handles image/video uploads attached to a review before the review form is
 * submitted. Uploads land in the media library and their attachment IDs are
 * carried into the comment via a hidden field, then stored as comment meta by
 * {@see \StoreEngine\Frontend\Comments::add_comment_rating()}.
 */
class Reviews extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'upload_review_media' => [
				'callback' => [ $this, 'upload_review_media' ],
				'fields'   => [
					'product_id' => 'integer',
				],
			],
			'update_my_review'    => [
				'callback' => [ $this, 'update_my_review' ],
				'fields'   => [
					'comment_id' => 'integer',
					'rating'     => 'integer',
					'content'    => 'textarea',
					'media'      => 'string',
				],
			],
			'add_my_review'       => [
				'callback' => [ $this, 'add_my_review' ],
				'fields'   => [
					'product_id' => 'integer',
					'rating'     => 'integer',
					'content'    => 'textarea',
					'media'      => 'string',
				],
			],
		];
	}

	/**
	 * Customer creates a review for a product they may review (e.g. from the
	 * dashboard "My Reviews" screen).
	 *
	 * @param array $payload Sanitized request payload.
	 */
	public function add_my_review( array $payload ) {
		$product_id = absint( $payload['product_id'] ?? 0 );

		if ( ! $product_id || 'storeengine_product' !== get_post_type( $product_id ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid product.', 'storeengine' ) ], 400 );
		}

		if ( ! storeengine_can_review_product( $product_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to review this product.', 'storeengine' ) ], 403 );
		}

		if ( storeengine_get_user_review( $product_id ) ) {
			wp_send_json_error( [ 'message' => __( 'You have already reviewed this product.', 'storeengine' ) ], 409 );
		}

		$rating  = (int) ( $payload['rating'] ?? 0 );
		$content = trim( (string) ( $payload['content'] ?? '' ) );

		if ( $rating < 1 || $rating > 5 ) {
			wp_send_json_error( [ 'message' => __( 'Please choose a rating.', 'storeengine' ) ], 400 );
		}
		if ( '' === $content ) {
			wp_send_json_error( [ 'message' => __( 'Please write your review.', 'storeengine' ) ], 400 );
		}

		$user = wp_get_current_user();

		// Apply the store's review-approval setting.
		$approved = storeengine_review_auto_approve() ? 1 : 0;

		$comment_id = wp_insert_comment( wp_slash( [
			'comment_post_ID'      => $product_id,
			'comment_author'       => $user->display_name,
			'comment_author_email' => $user->user_email,
			'comment_content'      => wp_kses_post( $content ),
			'comment_type'         => 'storeengine_product',
			'comment_approved'     => $approved,
			'user_id'              => $user->ID,
		] ) );

		if ( ! $comment_id ) {
			wp_send_json_error( [ 'message' => __( 'Could not save your review.', 'storeengine' ) ], 500 );
		}

		update_comment_meta( $comment_id, 'storeengine_rating', $rating );
		$this->save_my_review_media( $comment_id, $product_id, (string) ( $payload['media'] ?? '' ) );

		do_action( 'storeengine/frontend/after_product_rating', $comment_id, $product_id, $rating );

		wp_send_json_success( [ 'message' => __( 'Review submitted.', 'storeengine' ) ] );
	}

	/**
	 * Persist a comma-separated list of review-media attachment ids (validated
	 * + capped) as comment meta. Shared by add/update.
	 *
	 * @param int    $comment_id Comment id.
	 * @param int    $product_id Product id.
	 * @param string $media_csv  Comma-separated attachment ids.
	 */
	protected function save_my_review_media( int $comment_id, int $product_id, string $media_csv ) {
		$ids     = array_filter( array_map( 'absint', explode( ',', $media_csv ) ) );
		$user_id = get_current_user_id();
		$valid   = [];
		foreach ( array_unique( $ids ) as $attachment_id ) {
			// Must be review media the current user uploaded for this product.
			if ( (int) get_post_meta( $attachment_id, '_storeengine_review_media', true ) === $product_id
				&& (int) get_post_field( 'post_author', $attachment_id ) === $user_id ) {
				$valid[] = $attachment_id;
			}
		}
		$max = function_exists( 'storeengine_review_media_max' ) ? storeengine_review_media_max() : 0;
		if ( $max > 0 && count( $valid ) > $max ) {
			$valid = array_slice( $valid, 0, $max );
		}
		if ( $valid ) {
			update_comment_meta( $comment_id, 'storeengine_review_media', $valid );
		} else {
			delete_comment_meta( $comment_id, 'storeengine_review_media' );
		}
	}

	/**
	 * Customer edits their own review (rating, text, media).
	 *
	 * @param array $payload Sanitized request payload.
	 */
	public function update_my_review( array $payload ) {
		$comment_id = absint( $payload['comment_id'] ?? 0 );
		$comment    = $comment_id ? get_comment( $comment_id ) : null;

		if ( ! $comment || 'storeengine_product' !== $comment->comment_type ) {
			wp_send_json_error( [ 'message' => __( 'Review not found.', 'storeengine' ) ], 404 );
		}

		// Only the review's own author may edit it.
		if ( (int) $comment->user_id !== get_current_user_id() ) {
			wp_send_json_error( [ 'message' => __( 'You can only edit your own review.', 'storeengine' ) ], 403 );
		}

		$rating  = (int) ( $payload['rating'] ?? 0 );
		$content = trim( (string) ( $payload['content'] ?? '' ) );

		if ( $rating < 1 || $rating > 5 ) {
			wp_send_json_error( [ 'message' => __( 'Please choose a rating.', 'storeengine' ) ], 400 );
		}
		if ( '' === $content ) {
			wp_send_json_error( [ 'message' => __( 'Please write your review.', 'storeengine' ) ], 400 );
		}

		wp_update_comment( [
			'comment_ID'      => $comment_id,
			'comment_content' => wp_kses_post( $content ),
		] );
		update_comment_meta( $comment_id, 'storeengine_rating', $rating );

		// Media: comma-separated attachment ids the buyer uploaded for this product.
		$this->save_my_review_media( $comment_id, (int) $comment->comment_post_ID, (string) ( $payload['media'] ?? '' ) );

		/**
		 * Fires after a customer edits their own review. Return true from any
		 * callback on the filter below to re-moderate (set to pending).
		 */
		if ( apply_filters( 'storeengine/remoderate_edited_review', false, $comment_id ) ) {
			wp_set_comment_status( $comment_id, 'hold' );
		}

		wp_send_json_success( [ 'message' => __( 'Review updated.', 'storeengine' ) ] );
	}

	/**
	 * Allowed media types for a review upload: common images + videos only.
	 *
	 * @return array<string,string> extension(s) => mime type.
	 */
	protected function allowed_mimes(): array {
		return apply_filters( 'storeengine/review_media_allowed_mimes', [
			'jpg|jpeg|jpe' => 'image/jpeg',
			'gif'          => 'image/gif',
			'png'          => 'image/png',
			'webp'         => 'image/webp',
			'mp4|m4v'      => 'video/mp4',
			'mov|qt'       => 'video/quicktime',
			'webm'         => 'video/webm',
			'ogv'          => 'video/ogg',
		] );
	}

	public function upload_review_media( array $payload ) {
		$product_id = absint( $payload['product_id'] ?? 0 );

		// Eligible to leave a review, or already has one (editing from the
		// dashboard) — either may attach media.
		$allowed = $product_id && (
			storeengine_can_review_product( $product_id ) ||
			storeengine_get_user_review( $product_id )
		);

		if ( ! $allowed ) {
			wp_send_json_error( [ 'message' => __( 'You are not allowed to add media to this review.', 'storeengine' ) ], 403 );
		}

		$max_size    = storeengine_review_media_max_size( $product_id );
		$server_max  = size_format( (int) wp_max_upload_size() );
		$upload_err  = isset( $_FILES['file']['error'] ) ? (int) $_FILES['file']['error'] : UPLOAD_ERR_NO_FILE; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified upstream in handle_request().

		// PHP rejected the file before we saw it — usually because it exceeds the
		// server's upload_max_filesize / post_max_size. Say so explicitly.
		if ( UPLOAD_ERR_INI_SIZE === $upload_err || UPLOAD_ERR_FORM_SIZE === $upload_err ) {
			wp_send_json_error( [
				/* translators: %s: server upload-size limit (e.g. 2 MB). */
				'message' => sprintf( __( 'This file is larger than the server upload limit (%s). Ask the site administrator to increase the PHP upload_max_filesize / post_max_size, then try again.', 'storeengine' ), $server_max ),
			], 400 );
		}

		if ( UPLOAD_ERR_PARTIAL === $upload_err ) {
			wp_send_json_error( [ 'message' => __( 'The upload was interrupted. Please try again.', 'storeengine' ) ], 400 );
		}

		if ( empty( $_FILES['file'] ) || ! isset( $_FILES['file']['name'] ) || UPLOAD_ERR_NO_FILE === $upload_err ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_send_json_error( [ 'message' => __( 'No file uploaded.', 'storeengine' ) ], 400 );
		}

		if ( isset( $_FILES['file']['size'] ) && (int) $_FILES['file']['size'] > $max_size ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			wp_send_json_error( [
				/* translators: %s: human readable file-size limit. */
				'message' => sprintf( __( 'File is too large. Maximum upload size is %s.', 'storeengine' ), size_format( $max_size ) ),
			], 400 );
		}

		$filename  = sanitize_file_name( wp_unslash( $_FILES['file']['name'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$file_type = wp_check_filetype( $filename, $this->allowed_mimes() );

		if ( empty( $file_type['type'] ) ) {
			wp_send_json_error( [ 'message' => __( 'Only images and videos can be attached to a review.', 'storeengine' ) ], 400 );
		}

		if ( ! function_exists( 'media_handle_upload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		// Restrict the accepted mimes for this single upload call.
		$mimes  = $this->allowed_mimes();
		$filter = static function () use ( $mimes ) {
			return $mimes;
		};
		add_filter( 'upload_mimes', $filter );

		// Reviewers are usually customers/subscribers without `upload_files`.
		// Grant it just for this verified request so media_handle_upload works.
		$grant_upload = static function ( $allcaps ) {
			$allcaps['upload_files'] = true;
			return $allcaps;
		};
		add_filter( 'user_has_cap', $grant_upload );

		// finally: always drop the temporary grant + mime override, even if
		// media_handle_upload throws.
		try {
			$attachment_id = media_handle_upload( 'file', 0, [], [ 'test_form' => false, 'mimes' => $mimes ] );
		} finally {
			remove_filter( 'user_has_cap', $grant_upload );
			remove_filter( 'upload_mimes', $filter );
		}

		if ( is_wp_error( $attachment_id ) ) {
			wp_send_json_error( [ 'message' => $attachment_id->get_error_message() ], 400 );
		}

		// Tag the attachment so it can be recognised as review media.
		update_post_meta( $attachment_id, '_storeengine_review_media', $product_id );

		$mime = get_post_mime_type( $attachment_id );

		wp_send_json_success( [
			'id'    => $attachment_id,
			'url'   => wp_get_attachment_url( $attachment_id ),
			'thumb' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) ?: wp_get_attachment_url( $attachment_id ),
			'type'  => ( 0 === strpos( (string) $mime, 'video/' ) ) ? 'video' : 'image',
			'mime'  => $mime,
		] );
	}
}
