<?php
/**
 * Shared payload builder for CMS / FSE listeners.
 *
 * Keeps page/post/template/template-part deliveries on a single, stable
 * shape so headless renderers can write one handler that covers all of
 * them.
 *
 * @package StoreEngine\Addons\Webhooks\Listeners\Cms
 */

namespace StoreEngine\Addons\Webhooks\Listeners\Cms;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

final class Payload {

	/**
	 * Build a payload for a page/post (or any standard public post type).
	 */
	public static function for_post( \WP_Post $post, string $event ): array {
		$permalink = get_permalink( $post );
		$path      = $permalink ? ( wp_parse_url( $permalink, PHP_URL_PATH ) ?: '/' ) : '/';

		return [
			'event'        => $event,
			'resource'     => $post->post_type,
			'post_id'      => $post->ID,
			'post_type'    => $post->post_type,
			'slug'         => $post->post_name,
			'title'        => get_the_title( $post ),
			'status'       => $post->post_status,
			'path'         => $path,
			'permalink'    => $permalink ?: '',
			'modified'     => $post->post_modified_gmt ?: $post->post_modified,
			'author_id'    => (int) $post->post_author,
		];
	}

	/**
	 * Build a payload for a block-theme template or template part.
	 * Template slug is `theme//slug`; we surface both pieces.
	 */
	public static function for_template( \WP_Post $post, string $event ): array {
		$theme_slug = (string) get_post_meta( $post->ID, 'theme', true );
		$area       = (string) get_post_meta( $post->ID, 'area', true ); // template parts only

		return [
			'event'      => $event,
			'resource'   => $post->post_type, // wp_template | wp_template_part
			'post_id'    => $post->ID,
			'slug'       => $post->post_name,
			'title'      => get_the_title( $post ),
			'theme'      => $theme_slug,
			'area'       => $area,
			'updated_at' => $post->post_modified_gmt ?: $post->post_modified,
		];
	}
}
