<?php
/**
 * Global Styles (theme.json) saved listener.
 *
 * Fires after the Site Editor's global styles REST save. Headless renderers
 * use this to refresh CSS variables and re-fetch theme.json tokens.
 *
 * @package StoreEngine\Addons\Webhooks\Listeners\GlobalStyles
 */

namespace StoreEngine\Addons\Webhooks\Listeners\GlobalStyles;

use StoreEngine\Addons\Webhooks\Classes\AbstractListener;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class Saved extends AbstractListener {

	public static function dispatch( $deliver_callback, $webhook ) {
		// Fired by REST after the global-styles row is saved.
		add_action(
			'rest_after_save_global_styles',
			function ( $post, $request ) use ( $deliver_callback, $webhook ) {
				$payload = [
					'event'      => 'global_styles_saved',
					'resource'   => 'global_styles',
					'post_id'    => is_object( $post ) && isset( $post->ID ) ? (int) $post->ID : 0,
					'theme'      => is_object( $post ) ? (string) get_post_meta( $post->ID, 'theme', true ) : '',
					'updated_at' => current_time( 'mysql', true ),
				];

				call_user_func_array( $deliver_callback, [ $webhook, $payload ] );
			},
			10,
			2
		);

		// Belt-and-braces: the post type slug for global styles is `wp_global_styles`.
		add_action(
			'save_post_wp_global_styles',
			function ( int $post_id, \WP_Post $post ) use ( $deliver_callback, $webhook ) {
				if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
					return;
				}
				$payload = [
					'event'      => 'global_styles_saved',
					'resource'   => 'global_styles',
					'post_id'    => $post_id,
					'theme'      => (string) get_post_meta( $post_id, 'theme', true ),
					'updated_at' => current_time( 'mysql', true ),
				];
				call_user_func_array( $deliver_callback, [ $webhook, $payload ] );
			},
			10,
			2
		);
	}
}
