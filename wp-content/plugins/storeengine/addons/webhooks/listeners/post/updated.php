<?php
/**
 * Post Updated listener.
 *
 * @package StoreEngine\Addons\Webhooks\Listeners\Post
 */

namespace StoreEngine\Addons\Webhooks\Listeners\Post;

use StoreEngine\Addons\Webhooks\Classes\AbstractListener;
use StoreEngine\Addons\Webhooks\Listeners\Cms\Payload;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class Updated extends AbstractListener {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action(
			'save_post_post',
			function ( int $post_id, \WP_Post $post, bool $update ) use ( $deliver_callback, $webhook ) {
				if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
					return;
				}
				if ( ! $update || 'publish' !== $post->post_status ) {
					return;
				}
				call_user_func_array( $deliver_callback, [ $webhook, Payload::for_post( $post, 'post_updated' ) ] );
			},
			10,
			3
		);
	}
}
