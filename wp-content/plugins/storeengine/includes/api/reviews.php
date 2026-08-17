<?php
/**
 * Product reviews REST controller (admin moderation + manual creation).
 *
 * Reviews are WordPress comments of type `storeengine_product` with a
 * `storeengine_rating` meta and optional `storeengine_review_media` (attachment
 * IDs). This controller powers the admin Products → Reviews screen.
 *
 * @package StoreEngine\API
 */

namespace StoreEngine\API;

use StoreEngine\Utils\Helper;
use WP_Comment_Query;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Reviews extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = STOREENGINE_PLUGIN_SLUG . '/v1';
		$this->rest_base = 'reviews';
	}

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_collection_params(),
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_editable_args(),
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_editable_args(),
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'force' => [
						'type'    => 'boolean',
						'default' => false,
					],
				],
			],
		] );
	}

	public function permissions_check( $request ) {
		return Helper::check_rest_user_cap( 'manage_options' );
	}

	public function get_collection_params(): array {
		return [
			'page'     => [ 'type' => 'integer', 'default' => 1 ],
			'per_page' => [ 'type' => 'integer', 'default' => 20 ],
			'search'   => [ 'type' => 'string' ],
			'status'   => [ 'type' => 'string', 'default' => 'all', 'enum' => [ 'all', 'approve', 'hold', 'spam', 'trash' ] ],
			'product'  => [ 'type' => 'integer', 'default' => 0 ],
			'rating'   => [ 'type' => 'integer', 'default' => 0 ],
			'orderby'  => [ 'type' => 'string', 'default' => 'comment_date_gmt' ],
			'order'    => [ 'type' => 'string', 'default' => 'DESC', 'enum' => [ 'ASC', 'DESC' ] ],
		];
	}

	protected function get_editable_args(): array {
		return [
			'product_id' => [ 'type' => 'integer' ],
			'author'     => [ 'type' => 'string' ],
			'email'      => [ 'type' => 'string' ],
			'content'    => [ 'type' => 'string' ],
			'rating'     => [ 'type' => 'integer', 'minimum' => 1, 'maximum' => 5 ],
			'status'     => [ 'type' => 'string', 'enum' => [ 'approve', 'hold', 'spam', 'trash' ] ],
			'date'       => [ 'type' => 'string' ],
			'media'      => [ 'type' => 'array', 'items' => [ 'type' => 'integer' ] ],
		];
	}

	public function get_items( $request ) {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$status   = $request->get_param( 'status' ) ?: 'all';

		$args = [
			'type'    => 'storeengine_product',
			'number'  => $per_page,
			'offset'  => ( $page - 1 ) * $per_page,
			'orderby' => $request->get_param( 'orderby' ) ?: 'comment_date_gmt',
			'order'   => $request->get_param( 'order' ) ?: 'DESC',
			'status'  => $this->map_status( $status ),
		];

		if ( $request->get_param( 'search' ) ) {
			$args['search'] = sanitize_text_field( $request->get_param( 'search' ) );
		}

		if ( $request->get_param( 'product' ) ) {
			$args['post_id'] = absint( $request->get_param( 'product' ) );
		}

		if ( $request->get_param( 'rating' ) ) {
			$args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				[
					'key'   => 'storeengine_rating',
					'value' => absint( $request->get_param( 'rating' ) ),
				],
			];
		}

		$query    = new WP_Comment_Query();
		$comments = $query->query( $args );

		// Total for pagination (same filters, count only).
		$count_args           = $args;
		$count_args['number'] = 0;
		$count_args['offset'] = 0;
		$count_args['count']  = true;
		$total                = ( new WP_Comment_Query() )->query( $count_args );

		$items = array_map( [ $this, 'prepare_review' ], $comments );

		$response = new WP_REST_Response( $items );
		$response->header( 'X-WP-Total', (int) $total );
		$response->header( 'X-WP-TotalPages', (int) ceil( $total / $per_page ) );

		return $response;
	}

	public function get_item( $request ) {
		$comment = get_comment( absint( $request['id'] ) );

		if ( ! $comment || 'storeengine_product' !== $comment->comment_type ) {
			return new WP_Error( 'storeengine_review_not_found', __( 'Review not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $this->prepare_review( $comment ) );
	}

	public function create_item( $request ) {
		$product_id = absint( $request->get_param( 'product_id' ) );

		if ( ! $product_id || 'storeengine_product' !== get_post_type( $product_id ) ) {
			return new WP_Error( 'storeengine_invalid_product', __( 'A valid product is required.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$rating  = (int) $request->get_param( 'rating' );
		$content = (string) $request->get_param( 'content' );

		if ( $rating < 1 || $rating > 5 ) {
			return new WP_Error( 'storeengine_invalid_rating', __( 'Rating must be between 1 and 5.', 'storeengine' ), [ 'status' => 400 ] );
		}

		$author = sanitize_text_field( (string) $request->get_param( 'author' ) );
		$email  = sanitize_email( (string) $request->get_param( 'email' ) );
		$status = $request->get_param( 'status' ) ?: 'approve';
		$date   = $request->get_param( 'date' );

		if ( '' === $author ) {
			$author = __( 'Anonymous', 'storeengine' );
		}

		$commentdata = [
			'comment_post_ID'      => $product_id,
			'comment_author'       => $author,
			'comment_author_email' => $email,
			'comment_content'      => wp_kses_post( $content ),
			'comment_type'         => 'storeengine_product',
			'comment_approved'     => 'approve' === $status ? 1 : ( 'hold' === $status ? 0 : $status ),
			'comment_author_IP'    => '',
			'comment_agent'        => 'StoreEngine',
			'user_id'              => 0,
		];

		if ( $date ) {
			$timestamp = strtotime( $date );
			if ( $timestamp ) {
				$commentdata['comment_date']     = gmdate( 'Y-m-d H:i:s', $timestamp + ( (int) ( get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) ) );
				$commentdata['comment_date_gmt'] = gmdate( 'Y-m-d H:i:s', $timestamp );
			}
		}

		// wp_insert_comment doesn't run comment_post save hooks, so we set the
		// rating/media meta ourselves below.
		$comment_id = wp_insert_comment( wp_slash( $commentdata ) );

		if ( ! $comment_id ) {
			return new WP_Error( 'storeengine_review_create_failed', __( 'Could not create the review.', 'storeengine' ), [ 'status' => 500 ] );
		}

		update_comment_meta( $comment_id, 'storeengine_rating', $rating );
		$this->save_media_meta( $comment_id, $product_id, $request->get_param( 'media' ) );

		return rest_ensure_response( $this->prepare_review( get_comment( $comment_id ) ) );
	}

	public function update_item( $request ) {
		$comment = get_comment( absint( $request['id'] ) );

		if ( ! $comment || 'storeengine_product' !== $comment->comment_type ) {
			return new WP_Error( 'storeengine_review_not_found', __( 'Review not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$update = [ 'comment_ID' => $comment->comment_ID ];

		if ( null !== $request->get_param( 'content' ) ) {
			$update['comment_content'] = wp_kses_post( (string) $request->get_param( 'content' ) );
		}

		if ( null !== $request->get_param( 'author' ) ) {
			$update['comment_author'] = sanitize_text_field( (string) $request->get_param( 'author' ) );
		}

		if ( null !== $request->get_param( 'email' ) ) {
			$update['comment_author_email'] = sanitize_email( (string) $request->get_param( 'email' ) );
		}

		if ( count( $update ) > 1 ) {
			wp_update_comment( wp_slash( $update ) );
		}

		$status = $request->get_param( 'status' );
		if ( $status ) {
			wp_set_comment_status( $comment->comment_ID, $status );
		}

		$rating = $request->get_param( 'rating' );
		if ( null !== $rating && $rating >= 1 && $rating <= 5 ) {
			update_comment_meta( $comment->comment_ID, 'storeengine_rating', (int) $rating );
		}

		if ( null !== $request->get_param( 'media' ) ) {
			$this->save_media_meta( $comment->comment_ID, (int) $comment->comment_post_ID, $request->get_param( 'media' ) );
		}

		return rest_ensure_response( $this->prepare_review( get_comment( $comment->comment_ID ) ) );
	}

	public function delete_item( $request ) {
		$comment = get_comment( absint( $request['id'] ) );

		if ( ! $comment || 'storeengine_product' !== $comment->comment_type ) {
			return new WP_Error( 'storeengine_review_not_found', __( 'Review not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$force = (bool) $request->get_param( 'force' );
		$result = wp_delete_comment( $comment->comment_ID, $force );

		if ( ! $result ) {
			return new WP_Error( 'storeengine_review_delete_failed', __( 'Could not delete the review.', 'storeengine' ), [ 'status' => 500 ] );
		}

		return rest_ensure_response( [ 'deleted' => true, 'id' => (int) $comment->comment_ID ] );
	}

	protected function save_media_meta( int $comment_id, int $product_id, $media ) {
		if ( ! is_array( $media ) ) {
			return;
		}

		$ids = array_values( array_filter( array_map( 'absint', $media ) ) );

		if ( empty( $ids ) ) {
			delete_comment_meta( $comment_id, 'storeengine_review_media' );
			return;
		}

		foreach ( $ids as $attachment_id ) {
			update_post_meta( $attachment_id, '_storeengine_review_media', $product_id );
		}

		update_comment_meta( $comment_id, 'storeengine_review_media', $ids );
	}

	protected function map_status( string $status ) {
		switch ( $status ) {
			case 'approve':
				return 'approve';
			case 'hold':
				return 'hold';
			case 'spam':
				return 'spam';
			case 'trash':
				return 'trash';
			default:
				return 'all';
		}
	}

	protected function prepare_review( $comment ): array {
		$product   = get_post( $comment->comment_post_ID );
		$rating    = (int) get_comment_meta( $comment->comment_ID, 'storeengine_rating', true );
		$media_ids = get_comment_meta( $comment->comment_ID, 'storeengine_review_media', true );
		$media     = [];

		if ( is_array( $media_ids ) ) {
			foreach ( $media_ids as $attachment_id ) {
				$attachment_id = absint( $attachment_id );
				$url           = wp_get_attachment_url( $attachment_id );
				if ( ! $url ) {
					continue;
				}
				$mime    = (string) get_post_mime_type( $attachment_id );
				$media[] = [
					'id'    => $attachment_id,
					'url'   => $url,
					'thumb' => wp_get_attachment_image_url( $attachment_id, 'thumbnail' ) ?: $url,
					'type'  => ( 0 === strpos( $mime, 'video/' ) ) ? 'video' : 'image',
				];
			}
		}

		$status = 'hold';
		if ( '1' === (string) $comment->comment_approved || 1 === $comment->comment_approved || 'approve' === $comment->comment_approved ) {
			$status = 'approve';
		} elseif ( 'spam' === $comment->comment_approved ) {
			$status = 'spam';
		} elseif ( 'trash' === $comment->comment_approved ) {
			$status = 'trash';
		}

		return [
			'id'            => (int) $comment->comment_ID,
			'product_id'    => (int) $comment->comment_post_ID,
			'product_title' => $product ? $product->post_title : '',
			'author'        => $comment->comment_author,
			'email'         => $comment->comment_author_email,
			'avatar'        => get_avatar_url( $comment->comment_author_email ),
			'content'       => $comment->comment_content,
			'rating'        => $rating,
			'status'        => $status,
			'date'          => $comment->comment_date,
			'date_gmt'      => $comment->comment_date_gmt,
			'media'         => $media,
		];
	}
}
