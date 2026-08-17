<?php
/**
 * Navigation Menu updated listener.
 *
 * @package StoreEngine\Addons\Webhooks\Listeners\Menu
 */

namespace StoreEngine\Addons\Webhooks\Listeners\Menu;

use StoreEngine\Addons\Webhooks\Classes\AbstractListener;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class Updated extends AbstractListener {

	public static function dispatch( $deliver_callback, $webhook ) {
		add_action(
			'wp_update_nav_menu',
			function ( int $menu_id ) use ( $deliver_callback, $webhook ) {
				$term = get_term( $menu_id, 'nav_menu' );
				$slug = is_object( $term ) ? (string) $term->slug : '';

				$payload = [
					'event'      => 'nav_menu_updated',
					'resource'   => 'nav_menu',
					'menu_id'    => $menu_id,
					'name'       => is_object( $term ) ? (string) $term->name : '',
					'slug'       => $slug,
					'updated_at' => current_time( 'mysql', true ),
				];

				call_user_func_array( $deliver_callback, [ $webhook, $payload ] );
			},
			10,
			1
		);
	}
}
