<?php
/**
 * Block Theme Template Part saved listener.
 *
 * Fires when a `wp_template_part` (FSE header / footer / sidebar) is saved.
 *
 * @package StoreEngine\Addons\Webhooks\Listeners\Template
 */

namespace StoreEngine\Addons\Webhooks\Listeners\Template;

use StoreEngine\Addons\Webhooks\Classes\AbstractListener;
use StoreEngine\Addons\Webhooks\Listeners\Cms\Payload;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class PartSaved extends AbstractListener {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action(
			'save_post_wp_template_part',
			function ( int $post_id, \WP_Post $post, bool $update ) use ( $deliver_callback, $webhook ) {
				if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
					return;
				}
				call_user_func_array(
					$deliver_callback,
					[ $webhook, Payload::for_template( $post, 'template_part_saved' ) ]
				);
			},
			10,
			3
		);
	}
}
