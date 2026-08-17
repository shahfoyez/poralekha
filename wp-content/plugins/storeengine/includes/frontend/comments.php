<?php

namespace StoreEngine\Frontend;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Comments {
	public static function init() {
		$self = new self();
		add_filter( 'preprocess_comment', [ $self, 'validate_review_submission' ] );
		add_action( 'comment_post', array( $self, 'add_comment_rating' ), 1 );
		add_filter( 'comments_template_query_args', [ __CLASS__, 'comments_template_query_args' ] );
		add_filter( 'pre_comment_approved', [ $self, 'set_review_approval' ], 20, 2 );
	}

	/**
	 * Enforce review eligibility on the native comment endpoint.
	 *
	 * The on-product review form submits through wp-comments-post.php, so the
	 * template's own gate is not enough — a crafted POST could bypass it. Reviews
	 * are identified by the presence of the storeengine_rating field; plain
	 * product comments are left untouched.
	 *
	 * @param array $commentdata Comment data.
	 *
	 * @return array
	 */
	public function validate_review_submission( $commentdata ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- core handles the comment submission nonce/flood.
		$post_id = (int) ( $commentdata['comment_post_ID'] ?? 0 );

		if ( ! $post_id
			|| ! isset( $_POST['storeengine_rating'] )
			|| 'storeengine_product' !== get_post_type( $post_id )
			|| ! function_exists( 'storeengine_can_review_product' ) ) {
			// phpcs:enable WordPress.Security.NonceVerification.Missing
			return $commentdata;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		if ( ! storeengine_can_review_product( $post_id ) ) {
			wp_die(
				esc_html__( 'You are not allowed to review this product.', 'storeengine' ),
				esc_html__( 'Review not allowed', 'storeengine' ),
				[ 'response' => 403, 'back_link' => true ]
			);
		}

		if ( storeengine_get_user_review( $post_id ) ) {
			wp_die(
				esc_html__( 'You have already reviewed this product.', 'storeengine' ),
				esc_html__( 'Already reviewed', 'storeengine' ),
				[ 'response' => 409, 'back_link' => true ]
			);
		}

		return $commentdata;
	}

	/**
	 * Apply the store's review-approval setting to a product review submitted
	 * through the on-product form (identified by the storeengine_rating field).
	 *
	 * @param int|string $approved    Current approval status.
	 * @param array       $commentdata Comment data.
	 *
	 * @return int|string
	 */
	public function set_review_approval( $approved, $commentdata ) {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- core already flood/nonce checks comment submission.
		if ( 'spam' === $approved || 'trash' === $approved ) {
			return $approved;
		}

		$post_id = (int) ( $commentdata['comment_post_ID'] ?? 0 );

		if ( $post_id
			&& isset( $_POST['storeengine_rating'] )
			&& 'storeengine_product' === get_post_type( $post_id )
			&& function_exists( 'storeengine_review_auto_approve' ) ) {
			return storeengine_review_auto_approve() ? 1 : 0;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing

		return $approved;
	}

	/**
	 * Rating field for comments.
	 *
	 * @param int $comment_id Comment ID.
	 */
	public function add_comment_rating( $comment_id ) {
		if ( isset( $_POST['comment_post_ID'] ) && 'storeengine_product' === get_post_type( absint( $_POST['comment_post_ID'] ) ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$comment_post_ID    = absint( sanitize_text_field( wp_unslash( $_POST['comment_post_ID'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$storeengine_rating = isset( $_POST['storeengine_rating'] ) ? (int) sanitize_text_field( wp_unslash( $_POST['storeengine_rating'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing


			if ( ! $storeengine_rating ) { // phpcs:ignore input var ok, CSRF ok.
				return;
			}

			wp_update_comment( [ 'comment_ID' => $comment_id, 'comment_type' => 'storeengine_product' ] );

			add_comment_meta( $comment_id, 'storeengine_rating', $storeengine_rating, true );

			// Media (images/videos) uploaded ahead of submit are carried in a
			// hidden field as a comma-separated list of attachment IDs. Keep only
			// IDs that are review-media attachments the buyer just uploaded.
			$this->save_review_media( $comment_id, $comment_post_ID );

			/**
			 * Fires after adding product rating.
			 *
			 * @param int $comment_id Comment id.
			 * @param int $comment_post_ID Post id.
			 * @param int $storeengine_rating Rating.
			 */
			do_action( 'storeengine/frontend/after_product_rating', $comment_id, $comment_post_ID, $storeengine_rating );
		}
	}

	/**
	 * Persist review media (attachment IDs) submitted with a review.
	 *
	 * @param int $comment_id      Comment id.
	 * @param int $comment_post_ID Product id the comment belongs to.
	 */
	protected function save_review_media( int $comment_id, int $comment_post_ID ) {
		if ( empty( $_POST['storeengine_review_media'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- core comment submission already nonce/flood checked.
			return;
		}

		$raw = sanitize_text_field( wp_unslash( $_POST['storeengine_review_media'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$ids = array_filter( array_map( 'absint', explode( ',', $raw ) ) );

		if ( empty( $ids ) ) {
			return;
		}

		$user_id = get_current_user_id();
		$valid   = [];
		foreach ( array_unique( $ids ) as $attachment_id ) {
			// Only accept review-media attachments the current user uploaded for
			// this product (tagged by the upload_review_media AJAX handler).
			if ( (int) get_post_meta( $attachment_id, '_storeengine_review_media', true ) === $comment_post_ID
				&& (int) get_post_field( 'post_author', $attachment_id ) === $user_id ) {
				$valid[] = $attachment_id;
				wp_update_post( [ 'ID' => $attachment_id, 'post_parent' => $comment_post_ID ] );
			}
		}

		// Enforce the configured per-review media cap (0 = unlimited).
		$max = function_exists( 'storeengine_review_media_max' ) ? storeengine_review_media_max() : 0;
		if ( $max > 0 && count( $valid ) > $max ) {
			$valid = array_slice( $valid, 0, $max );
		}

		if ( ! empty( $valid ) ) {
			add_comment_meta( $comment_id, 'storeengine_review_media', $valid, true );
		}
	}

	public static function comments_template_query_args( array $args ): array {
		if ( Helper::is_product() ) {
			$args['type'] = 'comment';
		}

		return $args;
	}
}
