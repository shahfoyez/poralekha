<?php
/**
 * Page Deleted listener.
 *
 * @package StoreEngine\Addons\Webhooks\Listeners\Page
 */

namespace StoreEngine\Addons\Webhooks\Listeners\Page;

use StoreEngine\Addons\Webhooks\Classes\AbstractListener;
use StoreEngine\Addons\Webhooks\Listeners\Cms\Payload;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class Deleted extends AbstractListener {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action(
			'before_delete_post',
			function ( int $post_id, \WP_Post $post ) use ( $deliver_callback, $webhook ) {
				if ( 'page' !== $post->post_type ) {
					return;
				}
				call_user_func_array( $deliver_callback, [ $webhook, Payload::for_post( $post, 'page_deleted' ) ] );
			},
			10,
			2
		);
	}
}
