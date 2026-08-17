<?php
/**
 * Block Theme Template saved listener.
 *
 * Fires when a `wp_template` post (FSE template) is saved in the Site Editor.
 *
 * @package StoreEngine\Addons\Webhooks\Listeners\Template
 */

namespace StoreEngine\Addons\Webhooks\Listeners\Template;

use StoreEngine\Addons\Webhooks\Classes\AbstractListener;
use StoreEngine\Addons\Webhooks\Listeners\Cms\Payload;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class Saved extends AbstractListener {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action(
			'save_post_wp_template',
			function ( int $post_id, \WP_Post $post, bool $update ) use ( $deliver_callback, $webhook ) {
				if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
					return;
				}
				call_user_func_array(
					$deliver_callback,
					[ $webhook, Payload::for_template( $post, 'template_saved' ) ]
				);
			},
			10,
			3
		);
	}
}
