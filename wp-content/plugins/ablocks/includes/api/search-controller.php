<?php

namespace ABlocks\API;

use ABlocks\Helper;
use WP_Query;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SearchController {

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			ABLOCKS_REST_NAMESPACE,
			'/search',
			array(
				'methods'  => WP_REST_Server::READABLE,
				'callback' => array( $this, 'search_blocks' ),
				'permission_callback' => '__return_true',
				'args' => array(
					'current_page_id' => array(
						'required'          => true,
						'type'              => 'string',
						'description'       => 'Current page ID to exclude from results.',
						'sanitize_callback' => 'sanitize_text_field',
					),

					'searchQuery' => array(
						'required'          => true,
						'type'              => 'string',
						'description'       => 'Search keyword.',
						'sanitize_callback' => 'sanitize_text_field',
						'validate_callback' => function( $param ) {
							return is_string( $param ) && strlen( trim( $param ) ) >= 1;
						},
					),

					'source' => array(
						'required'          => true,
						'type'              => 'string',
						'description'       => 'Post type to search.',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => function( $param ) {
							return $param === 'any' || post_type_exists( $param );
						},
					),
				),
			)
		);
	}

	public function search_blocks( WP_REST_Request $request ) {

		$searchQuery     = $request->get_param( 'searchQuery' );
		$source          = $request->get_param( 'source' );
		$current_page_id = $request->get_param( 'current_page_id' );

		$args = array(
			's'              => $searchQuery,
			'posts_per_page' => -1,
			'post_type'      => $source,
			'post_status'    => 'publish',
		);

		if ( $current_page_id ) {
			$args['post__not_in'] = array( $current_page_id );
		}

		$query = new WP_Query( $args );

		if ( ! $query->have_posts() ) {
			return new WP_REST_Response(
				array(
					'html' => '',
					'data' => array(),
				),
				200
			);
		}

		$results      = array();
		$results_html = '';

		while ( $query->have_posts() ) {
			$query->the_post();

			$title     = get_the_title();
			$link      = get_permalink();
			$thumbnail = get_the_post_thumbnail_url( get_the_ID(), 'thumbnail' );

			ob_start();
			Helper::get_template(
				'search-block/search-results.php',
				array(
					'title'     => $title,
					'link'      => $link,
					'thumbnail' => $thumbnail,
				)
			);
			$results_html .= ob_get_clean();

			$results[] = array(
				'title'     => $title,
				'link'      => $link,
				'thumbnail' => $thumbnail,
			);
		}//end while

		wp_reset_postdata();

		return new WP_REST_Response(
			array(
				'html' => $results_html,
				'data' => $results,
			),
			200
		);
	}
}
