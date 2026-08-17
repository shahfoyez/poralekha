<?php
/**
 * Secondary content-leak guard.
 *
 * The themed page is gated by TemplateRedirect, but a restricted post's full
 * body still leaks through two side channels that bypass template_redirect:
 *   1. the REST API  (GET /wp-json/wp/v2/<type>/<id> returns content.rendered)
 *   2. RSS / Atom feeds (/feed/ emits the_content for every item)
 *
 * This class strips the body of restricted items on both channels for viewers
 * who don't qualify, while leaving titles/links intact (so listings still work)
 * and preserving full access for editors of the post.
 *
 * @package StoreEngine\Addons\Membership
 */

namespace StoreEngine\Addons\Membership;

use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RestRestriction {

	public static function init() {
		$self = new self();

		add_action( 'rest_api_init', [ $self, 'register_rest_guards' ] );

		// Feeds.
		add_filter( 'the_content_feed', [ $self, 'guard_feed_content' ], 20 );
		add_filter( 'the_excerpt_rss', [ $self, 'guard_feed_content' ], 20 );
	}

	public function register_rest_guards() {
		$post_types = get_post_types( [
			'public'       => true,
			'show_in_rest' => true,
		], 'names' );

		foreach ( $post_types as $post_type ) {
			add_filter( "rest_prepare_{$post_type}", [ $this, 'guard_rest_response' ], 20, 2 );
		}
	}

	/**
	 * Strip content/excerpt from a restricted item in a REST response.
	 *
	 * @param WP_REST_Response $response
	 * @param \WP_Post         $post
	 *
	 * @return WP_REST_Response
	 */
	public function guard_rest_response( $response, $post ) {
		if ( ! ( $response instanceof WP_REST_Response ) || ! $post ) {
			return $response;
		}

		$user_id = get_current_user_id();

		// Editors of the post keep full access (matches TemplateRedirect leaving
		// authoring untouched).
		if ( $user_id && current_user_can( 'edit_post', $post->ID ) ) {
			return $response;
		}

		if ( ! Access::is_post_restricted( (int) $post->ID, $user_id ) ) {
			return $response;
		}

		$data   = $response->get_data();
		$notice = '<p>' . esc_html__( 'This content is restricted.', 'storeengine' ) . '</p>';

		if ( isset( $data['content'] ) && is_array( $data['content'] ) ) {
			$data['content']['rendered']  = $notice;
			$data['content']['protected'] = true;
		}
		if ( isset( $data['excerpt'] ) && is_array( $data['excerpt'] ) ) {
			$data['excerpt']['rendered']  = $notice;
			$data['excerpt']['protected'] = true;
		}

		$response->set_data( $data );

		return $response;
	}

	/**
	 * Replace restricted item bodies in RSS/Atom feeds.
	 *
	 * @param string $content
	 *
	 * @return string
	 */
	public function guard_feed_content( $content ) {
		$post_id = get_the_ID();
		if ( ! $post_id ) {
			return $content;
		}

		$user_id = get_current_user_id();

		if ( $user_id && current_user_can( 'edit_post', $post_id ) ) {
			return $content;
		}

		if ( ! Access::is_post_restricted( (int) $post_id, $user_id ) ) {
			return $content;
		}

		return esc_html__( 'This content is restricted.', 'storeengine' );
	}
}
