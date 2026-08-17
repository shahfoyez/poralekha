<?php

namespace ABlocks\API;

use ABlocks\Helper;
use WP_REST_Server;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LoopBuilderController {

	public function __construct() {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	public function register_routes() {

		register_rest_route(
			ABLOCKS_REST_NAMESPACE,
			'/loop-builder',
			[
				'methods'  => WP_REST_Server::READABLE,
				'callback' => [ $this, 'render_loop_builder' ],
				'permission_callback' => '__return_true',
				'args' => [
					'loop_builder_block_id' => [
						'required' => true,
						'type'     => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'loop_template_block_id' => [
						'required' => true,
						'type'     => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					],
					'taxonomy' => [
						'required' => false,
						'type'     => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
					'post_id' => [
						'required' => false,
						'type'     => 'integer',
						'sanitize_callback' => 'absint',
					],
					'term_id' => [
						'required' => false,
						'type'     => 'integer',
						'sanitize_callback' => 'absint',
					],
					'page' => [
						'required' => false,
						'type'     => 'integer',
						'default'  => 1,
						'sanitize_callback' => 'absint',
					],
					'is_archive' => [
						'required' => false,
						'type'     => 'boolean',
						'default'  => false,
					],
					'archive_post_type' => [
						'required' => false,
						'type'     => 'string',
						'sanitize_callback' => 'sanitize_key',
					],
				],
			]
		);
	}

	public function render_loop_builder( $payload ) {

		$post_id                = $payload['post_id'];
		$loop_builder_block_id  = $payload['loop_builder_block_id'];
		$loop_template_block_id = $payload['loop_template_block_id'];
		$taxonomy               = $payload['taxonomy'];
		$term_id                = (int) $payload['term_id'];
		$page                   = (int) ( isset( $payload['page'] ) ? $payload['page'] : 1 );
		$is_archive             = (bool) ( isset( $payload['is_archive'] ) ? $payload['is_archive'] : false );

		/* ---------------- ARCHIVE LOGIC (UNCHANGED) ---------------- */

		if ( $is_archive ) {

			if ( ! empty( $payload['archive_post_type'] ) ) {
				$template_slug = 'archive-' . $payload['archive_post_type'];

			} elseif ( ! empty( $payload['taxonomy'] ) ) {

				if ( ! empty( $payload['term_slug'] ) ) {
					$template_slug = 'archive-' . $payload['taxonomy'] . '-' . $payload['term_slug'];
				} else {
					$template_slug = 'archive-' . $payload['taxonomy'];
				}
			}

			$theme_slug = wp_get_theme()->get_stylesheet();

			$template_posts = get_posts([
				'post_type'   => 'wp_template',
				'post_status' => 'publish',
				'numberposts' => 1,
				'name'        => $template_slug,
				'tax_query'   => [
					[
						'taxonomy' => 'wp_theme',
						'field'    => 'slug',
						'terms'    => $theme_slug,
					],
				],
			]);

			$post_content = current( $template_posts )->post_content;
			$blocks       = parse_blocks( $post_content );

			$block_data = Helper::get_block_attributes_recursive(
				$loop_builder_block_id,
				'ablocks/loop-builder',
				$blocks
			);

		} else {

			$block_data = Helper::get_block_attributes(
				$post_id,
				$loop_builder_block_id,
				'ablocks/loop-builder'
			);

			$post         = get_post( $post_id );
			$post_content = $post->post_content;
			$blocks       = parse_blocks( $post_content );
		}//end if

		/* ---------------- QUERY LOGIC (UNCHANGED) ---------------- */

		$query_vars = isset( $block_data['parentAttributes']['query'] )
			? $this->convert_to_wp_query_args( $block_data['parentAttributes']['query'] )
			: [
				'post_type'      => 'post',
				'post_status'    => 'publish',
				'posts_per_page' => get_option( 'posts_per_page' ),
			];

		if ( $is_archive && ! empty( $payload['archive_post_type'] ) ) {
			$query_vars['post_type'] = $payload['archive_post_type'];
		}

		$query_vars['posts_per_page'] = $query_vars['posts_per_page'] * $page;

		if ( ! empty( $taxonomy ) && $term_id ) {
			$query_vars['tax_query'] = [
				[
					'taxonomy' => $taxonomy,
					'field'    => 'term_id',
					'terms'    => [ $term_id ],
					'operator' => 'IN',
				],
			];
		}

		$blocks = $this->get_loop_builder_blocks_by_block_id(
			$blocks,
			$loop_builder_block_id
		);

		$updated_blocks = $this->update_loop_builder_query(
			$blocks,
			$loop_template_block_id,
			$query_vars,
			$term_id
		);

		$html = '';

		foreach ( $updated_blocks as $block ) {
			$html .= serialize_block( $block );
		}

		$rendered = apply_filters( 'the_content', do_blocks( $html ) );

		/* ---------------- REST RESPONSE (ONLY CHANGE) ---------------- */

		return new WP_REST_Response(
			[
				'success' => true,
				'data' => [
					'html'    => $rendered,
					'term_id' => $term_id,
					'post_id' => $post_id,
				],
			],
			200
		);
	}

	public function get_loop_builder_blocks_by_block_id( $blocks, $target_block_id ) {
		foreach ( $blocks as $block ) {
			if ( ! empty( $block['attrs']['block_id'] ) && $block['attrs']['block_id'] === $target_block_id ) {
				return [ $block ];
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				$found = $this->get_loop_builder_blocks_by_block_id( $block['innerBlocks'], $target_block_id );
				if ( ! empty( $found ) ) {
					return $found;
				}
			}
		}
		return [];
	}

	public function update_loop_builder_query( $blocks, $loop_template_block_id, $query_vars, $term_id ) {
		foreach ( $blocks as &$block ) {
			// perfectly not worked if multiple loop used
			// !empty($block['attrs']['block_id']) && $block['attrs']['block_id'] === $loop_template_block_id

			if ( ! empty( $block['blockName'] ) && $block['blockName'] === 'ablocks/loop-template' ) {
				if ( ! isset( $block['attrs']['query'] ) ) {
					$block['attrs']['query'] = [];
				}
				// Merge new query vars
				$block['attrs']['query'] = array_merge( $block['attrs']['query'], $query_vars );
			}

			if ( 'ablocks/loop-filter' === $block['blockName'] ) {
				$block['attrs']['active_term_id'] = $term_id;
			}

			// Recurse into inner blocks
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->update_loop_builder_query( $block['innerBlocks'], $loop_template_block_id, $query_vars, $term_id );
			}
		}//end foreach
		return $blocks;
	}

	public function convert_to_wp_query_args( array $input ): array {
		$args = [
			'post_status'    => 'publish',
		];

		// Basic pagination
		$args['posts_per_page'] = isset( $input['perPage'] ) ? (int) $input['perPage'] : get_option( 'posts_per_page' );
		$args['paged'] = isset( $input['pages'] ) && $input['pages'] > 0 ? (int) $input['pages'] : 1;

		// Post type
		if ( ! empty( $input['postType'] ) ) {
			$args['post_type'] = $input['postType'];
		}

		// Order and orderby
		if ( ! empty( $input['order'] ) ) {
			$args['order'] = $input['order'];
		}

		if ( ! empty( $input['orderBy'] ) ) {
			$args['orderby'] = $input['orderBy'];
		}

		// Author
		if ( ! empty( $input['author'] ) ) {
			$args['author'] = $input['author'];
		}

		// Search
		if ( ! empty( $input['search'] ) ) {
			$args['s'] = $input['search'];
		}

		// Exclude posts
		if ( ! empty( $input['exclude'] ) && is_array( $input['exclude'] ) ) {
			$args['post__not_in'] = array_map( 'intval', $input['exclude'] );
		}

		// Sticky posts
		if ( ! empty( $input['sticky'] ) ) {
			if ( $input['sticky'] === 'include' ) {
				$args['ignore_sticky_posts'] = 0;
			} elseif ( $input['sticky'] === 'exclude' ) {
				$args['ignore_sticky_posts'] = 1;
			}
		}

		// Post parent (for hierarchical post types like pages)
		if ( ! empty( $input['parents'] ) && is_array( $input['parents'] ) ) {
			$args['post_parent__in'] = array_map( 'intval', $input['parents'] );
		}

		// Offset
		if ( isset( $input['offset'] ) ) {
			$args['offset'] = (int) $input['offset'];
		}

		// Format (post format taxonomy)
		if ( ! empty( $input['format'] ) ) {
			$args['tax_query'][] = [
				'taxonomy' => 'post_format',
				'field'    => 'slug',
				'terms'    => [ 'post-format-' . sanitize_title( $input['format'] ) ],
			];
		}

		// Category
		if ( ! empty( $input['category'] ) ) {
			$args['category_name'] = $input['category'];
		}

		// Tag
		if ( ! empty( $input['tag'] ) ) {
			$args['tag'] = $input['tag'];
		}

		// Generic taxonomy query
		if ( ! empty( $input['taxonomy'] ) && ! empty( $input['value'] ) ) {
			$args['tax_query'][] = [
				'taxonomy' => $input['taxonomy'],
				'field'    => 'slug',
				'terms'    => is_array( $input['value'] ) ? $input['value'] : [ $input['value'] ],
			];
		}

		return $args;
	}
}
