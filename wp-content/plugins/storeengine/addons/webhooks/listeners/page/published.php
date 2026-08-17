<?php
/**
 * Page Published listener.
 *
 * Fires on the FIRST publish of a page (status transition non-publish → publish).
 *
 * @package StoreEngine\Addons\Webhooks\Listeners\Page
 */

namespace StoreEngine\Addons\Webhooks\Listeners\Page;

use StoreEngine\Addons\Webhooks\Classes\AbstractListener;
use StoreEngine\Addons\Webhooks\Listeners\Cms\Payload;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class Published extends AbstractListener {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action(
			'transition_post_status',
			function ( string $new, string $old, \WP_Post $post ) use ( $deliver_callback, $webhook ) {
				if ( 'page' !== $post->post_type ) {
					return;
				}
				if ( 'publish' === $new && 'publish' !== $old ) {
					call_user_func_array( $deliver_callback, [ $webhook, Payload::for_post( $post, 'page_published' ) ] );
				}
			},
			10,
			3
		);
	}
}
