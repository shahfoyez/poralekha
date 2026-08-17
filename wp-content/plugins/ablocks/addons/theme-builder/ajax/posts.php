<?php

namespace ABlocksThemeBuilder\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\AbstractAjaxHandler;

class Posts extends AbstractAjaxHandler {
	protected $namespace = ABLOCKS_PLUGIN_SLUG . '_theme_builder';
	public function __construct() {
		$this->actions = array(
			'search_anything'      => array(
				'callback' => array( $this, 'search_anything' ),
				'fields' => array(
					'keyword' => 'string'
				)
			),
		);
	}
	public function search_anything() {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,  WordPress.Security.ValidatedSanitizedInput.MissingUnslash  
		$search_string = isset( $_POST['keyword'] ) ? sanitize_text_field( $_POST['keyword'] ) : '';
		$result = array();

		$args = array(
			'public'   => true,
			'_builtin' => false,
		);

		$post_types = get_post_types( $args, 'names', 'and' );
		unset( $post_types['ablocks_tb'] );
		$post_types['Posts'] = 'post';
		$post_types['Pages'] = 'page';

		foreach ( $post_types as $key => $post_type ) {
			$data = array();
			$query = new \WP_Query(array(
				's' => $search_string,
				'post_type' => $post_type,
				'posts_per_page' => -1,
			));

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();
					$title = get_the_title();
					$title .= ( 0 != $query->post->post_parent ) ? ' (' . get_the_title( $query->post->post_parent ) . ')' : '';
					$id = get_the_id();
					$data[] = [
						'label' => $title,
						'value' => 'post-' . $id,
					];
				}
			}

			if ( ! empty( $data ) ) {
				$result[] = [
					'label' => $key,
					'options' => $data,
				];
			}
		}//end foreach

		wp_reset_postdata();

		// Search in taxonomies
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects', 'and' );
		foreach ( $taxonomies as $taxonomy ) {
			$terms = get_terms(array(
				'taxonomy' => $taxonomy->name,
				'orderby' => 'count',
				'hide_empty' => 0,
				'name__like' => $search_string,
			));

			$data = array();
			$label = ucwords( $taxonomy->label );

			if ( ! empty( $terms ) ) {
				foreach ( $terms as $term ) {
					$term_taxonomy_name = ucfirst( str_replace( '_', ' ', $taxonomy->name ) );

					$data[] = [
						'label' => $term->name . ' archive page',
						'value' => 'tax-' . $term->term_id,
					];

					$data[] = [
						'label' => 'All singulars from ' . $term->name,
						'value' => 'tax-' . $term->term_id . '-single-' . $taxonomy->name,
					];
				}
			}

			if ( ! empty( $data ) ) {
				$result[] = [
					'label' => $label,
					'options' => $data,
				];
			}
		}//end foreach

		wp_send_json_success( $result );
	}
}
