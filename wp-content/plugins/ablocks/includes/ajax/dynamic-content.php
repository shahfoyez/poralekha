<?php

namespace ABlocks\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\AbstractAjaxHandler;
use ABlocks\Helper;

class DynamicContent extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = array(
			'get_taxonomies_data'      => array(
				'callback' => array( $this, 'get_taxonomies_data' ),
				'capability' => 'edit_posts',
				'fields'     => [
					'post_type'      => 'string',
				],
			),
			'get_taxonomy_term_data'      => array(
				'callback' => array( $this, 'get_taxonomy_term_data' ),
				'capability' => 'edit_posts',
				'fields'     => [
					'taxonomy'      => 'string',
				],
			),
			'get_terms_for_post'      => array(
				'callback' => array( $this, 'get_terms_for_post' ),
				'capability' => 'edit_posts',
				'fields'     => [
					'post_id'      => 'integer',
					'taxonomy'      => 'string',
				],
			),
			'get_all_meta_keys_for_post_type'      => array(
				'callback' => array( $this, 'get_all_meta_keys_for_post_type' ),
				'capability' => 'edit_posts',
				'fields'     => [
					'post_type'      => 'string',
				],
			),
			'get_post_meta'      => array(
				'callback' => array( $this, 'get_post_meta' ),
				'capability' => 'edit_posts',
				'fields'     => [
					'post_id'      => 'integer',
					'meta_key'      => 'string',
				],
			),
			'search_posts'      => array(
				'callback' => array( $this, 'search_posts' ),
				'capability' => 'edit_posts',
				'fields'     => [
					'query'      => 'string',
				],
			),
			'get_author_data'      => array(
				'callback' => array( $this, 'get_author_data' ),
				'capability' => 'edit_posts',
				'fields'     => [
					'post_id'           => 'integer',
					'author_id'         => 'integer',
				],
			),
			'get_author_meta'      => array(
				'callback' => array( $this, 'get_author_meta' ),
				'capability' => 'edit_posts',
				'fields'     => [
					'author_id'      => 'integer',
					'meta_key'      => 'string',
				],
			),
			'get_post_comments_count'      => array(
				'callback' => array( $this, 'get_post_comments_count' ),
				'capability' => 'edit_posts',
				'fields'     => [
					'postID'      => 'integer',
				],
			),
			'get_featured_media_data'      => array(
				'callback' => array( $this, 'get_featured_media_data' ),
				'capability' => 'edit_posts',
				'fields'     => [
					'postID'      => 'integer',
				],
			),
			'get_all_taxonomies_terms_data'      => array(
				'callback' => array( $this, 'get_all_taxonomies_terms_data' ),
				'capability' => 'edit_posts'
			),
			'search_medias'      => array(
				'callback' => array( $this, 'search_medias' ),
				'capability' => 'edit_posts',
				'fields'     => [
					'query'      => 'string',
					'mediaID'      => 'integer',
				],
			),
			'get_all_authors_data'      => array(
				'callback' => array( $this, 'get_all_authors_data' ),
				'capability' => 'edit_posts'
			),
			'get_author_profile_picture_data'      => array(
				'callback' => array( $this, 'get_author_profile_picture_data' ),
				'capability' => 'edit_posts',
				'fields'     => [
					'postID'      => 'integer',
				],
			),
			'get_academy_course_data'      => array(
				'callback' => array( $this, 'get_academy_course_data' ),
				'capability' => 'edit_posts',
				'fields'     => [
					'post_id'      => 'integer',
					'meta_key'      => 'string',
				],
			),
		);
	}

	public function get_taxonomies_data( $payload ) {
		$post_type = $payload['post_type'];

		if ( empty( $post_type ) ) {
			wp_send_json_error( __( 'Sorry, Post type is required!', 'ablocks' ) );
		}

		$all_taxonomies = Helper::get_taxonomies_data_for_post_type( $post_type );

		wp_send_json_success( $all_taxonomies );
	}

	public function get_taxonomy_term_data( $payload ) {
		if ( empty( $payload['taxonomy'] ) ) {
			wp_send_json_error( __( 'Sorry, Taxonomy is required', 'ablocks' ) );
		}
		$terms = get_terms([
			'taxonomy' => $payload['taxonomy'],
			'hide_empty' => false, // Set to true if you only want terms attached to posts
		]);

		$results = array_map(function( $term ) {
			return [
				'label' => $term->name,
				'value' => $term->term_id,
			];
		}, $terms);

		wp_send_json_success( $results );
	}

	public function get_terms_for_post( $payload ) {
		$terms = Helper::get_terms_for_post( $payload['taxonomy'], $payload['post_id'] );
		wp_send_json_success( $terms );
	}

	public function get_all_meta_keys_for_post_type( $payload ) {
		global $wpdb;
		$post_type = $payload['post_type'];

		if ( empty( $post_type ) ) {
			wp_send_json_error( __( 'Sorry, Post type is required!', 'ablocks' ) );
		}

		$query = "
            SELECT DISTINCT pm.meta_key
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE p.post_type = %s
            AND pm.meta_key NOT LIKE '\_%'
        ";

		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared 
		$meta_keys = $wpdb->get_col( $wpdb->prepare( $query, $post_type ) );

		wp_send_json_success( $meta_keys );
	}

	public function get_post_meta( $payload ) {

		if ( empty( $payload['post_id'] ) || empty( $payload['meta_key'] ) ) {
			wp_send_json_error( __( 'Sorry, Post ID and Meta Key is required!', 'ablocks' ) );
		}

		$post_id = (int) $payload['post_id'];
		$meta_key = $payload['meta_key'];

		$result = get_post_meta( $post_id, $meta_key, true );
		wp_send_json_success( $result );
	}

	public function search_posts( $payload ) {
		global $wpdb;
		$search_query = $payload['query'];
		$included_post_types = get_post_types(
			[
				'public' => true,
				'show_in_rest' => true, // using `'show_in_rest' => true` because, unwanted post-types like `elementor_library` was being returned without this.
			],
			'names'
		);
		$included_post_types = array_values( $included_post_types );
		$excluded_post_types = [ 'attachment', 'elementor_library' ];
		$included_post_types = array_diff( $included_post_types, $excluded_post_types );
		$post_type_placeholders = implode( ',', array_fill( 0, count( $included_post_types ), '%s' ) );
		if ( empty( $search_query ) ) {
			$query = "
                SELECT ID, post_title, post_type
                FROM $wpdb->posts
                WHERE post_status = 'publish'
                AND post_type IN ($post_type_placeholders)
                ORDER BY post_date DESC
                LIMIT 5
            ";
			// phpcs:ignore  WordPress.DB.PreparedSQL.NotPrepared 
			$prepared_query = $wpdb->prepare( $query, ...$included_post_types );
		} else {
			$query = "
                SELECT ID, post_title, post_type
                FROM $wpdb->posts
                WHERE post_status = 'publish'
                AND post_title LIKE %s
                AND post_type IN ($post_type_placeholders)
                LIMIT 5
            ";
			$safe_query = '%' . $wpdb->esc_like( $search_query ) . '%';
			// phpcs:ignore  WordPress.DB.PreparedSQL.NotPrepared 
			$prepared_query = $wpdb->prepare( $query, array_merge( [ $safe_query ], $included_post_types ) );
		}//end if
		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared 
		$results = $wpdb->get_results( $prepared_query, ARRAY_A );
		wp_send_json_success( $results );
	}

	public function get_author_data( $payload ) {
		$post_id = $payload['post_id'] ?? 0;
		$author_id = $payload['author_id'] ?? 0;

		if ( ! $post_id || ! get_post( $post_id ) ) {
			wp_send_json_error( 'Invalid post ID.', 400 );
		}

		$data = Helper::get_author_data( $post_id, $author_id );

		wp_send_json_success( $data );
	}

	public function get_author_meta( $payload ) {
		$meta_key = $payload['meta_key'];
		$author_id = $payload['author_id'];
		$data = get_user_meta( $author_id, $meta_key, true );
		wp_send_json_success( $data );
	}

	public function get_post_comments_count( $payload ) {
		$postID = $payload['postID'] ?? 0;
		$result = get_comments_number( $postID );
		wp_send_json_success( $result );
	}

	public function get_featured_media_data( $payload ) {
		$postID = $payload['postID'] ?? 0;

		$result = [];

		if ( $postID ) {
			$attachment_id = get_post_meta( $postID, '_thumbnail_id', true );

			if ( $attachment_id ) {
				$attachment = get_post( $attachment_id );

				if ( $attachment ) {
					$result = [
						'featured_image_title' => $attachment->post_title,
						'featured_image_alt' => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
						'featured_image_caption' => $attachment->post_excerpt,
						'featured_image_description' => $attachment->post_content,
						'featured_image_file_url' => wp_get_attachment_url( $attachment_id ),
						'featured_image_attachment_url' => get_permalink( $attachment_id )
					];
				}
			}
		}

		wp_send_json_success( $result );
	}

	public function get_all_taxonomies_terms_data() {

		$taxonomies = get_taxonomies( [ 'public' => true ], 'objects' );
		$data = [];

		foreach ( $taxonomies as $taxonomy => $tax_obj ) {
			$terms = get_terms( [
				'taxonomy' => $taxonomy,
				'hide_empty' => false
			] );

			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				$data[] = [
					'value' => $term->term_id,
					'term_link' => get_term_link( $term ),
					'label' => "{$tax_obj->label}: {$term->name}",
				];
			}
		}

		wp_send_json_success( $data );
	}

	public function search_medias( $payload ) {

		global $wpdb;
		$search_query = $payload['query'] ?? '';
		$media_id = $payload['mediaId'] ?? 0;

		if ( empty( $search_query ) ) {
			$prepared_query = "SELECT ID, post_title FROM {$wpdb->posts} 
                WHERE post_type = 'attachment' AND post_status = 'inherit'
                ORDER BY post_date DESC LIMIT 5";
		} else {
			$prepared_query = $wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts} 
                WHERE post_type = 'attachment' AND post_status = 'inherit'
                AND post_title LIKE %s 
                ORDER BY post_date DESC LIMIT 5",
				'%' . $wpdb->esc_like( $search_query ) . '%'
			);
		}

		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared 
		$results = $wpdb->get_results( $prepared_query, ARRAY_A );

		$media_items = array_map(function ( $media ) {
			return [
				'value'     => $media['ID'],
				'label'     => $media['post_title'],
				'url'       => get_attachment_link( $media['ID'] )
			];
		}, $results);

		$media_item = null;
		$media_id_present_in_result = $media_id && ! in_array( "{$media_id}", array_column( $media_items, 'value' ) );

		if ( $media_id_present_in_result ) {
			// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching
			$media_item = $wpdb->get_row($wpdb->prepare(
				"SELECT ID, post_title FROM {$wpdb->posts} 
                    WHERE ID = %d AND post_type = 'attachment' AND post_status = 'inherit'",
				$media_id
			), ARRAY_A);
		}

		if ( $media_item ) {
			array_unshift($media_items, [
				'value'    => $media_item['ID'],
				'label' => $media_item['post_title'],
				'url'   => get_attachment_link( $media_item['ID'] )
			]);
		}

		wp_send_json_success( $media_items );
	}

	public function get_all_authors_data() {

		$args = array(
			'has_published_posts' => true,
			'orderby'     => 'display_name',
			'order'       => 'ASC',
			'fields'      => array( 'ID', 'display_name', 'user_email' ),
		);

		$users = get_users( $args );
		$authors = array();

		foreach ( $users as $user ) {
			$authors[] = array(
				'value'    => $user->ID,
				'label'  => $user->display_name . " ({$user->user_email})",
			);
		}

		wp_send_json_success( $authors );
	}

	public function get_author_profile_picture_data( $payload ) {

		$post_id = $payload['postID'] ?? 0;

		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		$author_id = get_post_field( 'post_author', $post_id );
		$result = get_avatar_url( $author_id );

		wp_send_json_success( $result );
	}

	public function get_academy_course_data( $payload ) {

		$post_id = $payload['post_id'] ?? 0;
		$meta_key = $payload['meta_key'] ?? '';

		if ( ! $post_id ) {
			$post_id = get_the_ID();
		}

		if ( ! Helper::is_active_academy() ) {
			wp_send_json_error( '' );
		}

		$Analytics = new \Academy\Classes\Analytics();
		$results = 0;
		if ( 'numberOfTopics' === $meta_key ) {
			$curriculums = \Academy\Helper::get_course_curriculums_number_of_counts( $post_id );
			$results = (int) isset( $curriculums['total_topics'] ) ? $curriculums['total_topics'] : 0;
		} elseif ( 'numberOfReviews' === $meta_key ) {
			$results = (int) $Analytics->get_total_number_of_reviews_by_course_id( $post_id );
		} elseif ( 'numberOfEnrolled' === $meta_key ) {
			$results = (int) $Analytics->get_total_number_of_enrolled_by_course_id( $post_id );
		} elseif ( 'totalDuration' === $meta_key ) {
			$results = (int) \Academy\Helper::get_course_duration( $post_id );
		}
		wp_send_json_success( $results );
	}
}
