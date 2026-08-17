<?php
/**
 * Permission & capabilities.
 */

namespace StoreEngine\Hooks;

use StoreEngine\Classes\DownloadPermission;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PermissionsAndCapabilities {
	public static function init() {
		add_filter( 'user_has_cap', [__CLASS__, 'customer_has_capability'], 10, 3 );

		// Download Permissions.
		DownloadPermissionHooks::init();
	}

	/**
	 * Checks if a user has a certain capability.
	 *
	 * @param array $all_caps All capabilities.
	 * @param array $caps    Capabilities.
	 * @param array $args    Arguments.
	 *
	 * @return array The filtered array of all capabilities.
	 */
	public static function customer_has_capability( array $all_caps, array $caps, array $args ): array {
		if ( isset( $caps[0] ) ) {
			switch ( $caps[0] ) {
				case 'view_order':
					$user_id = intval( $args[1] );
					$order   = Helper::get_order( $args[2] );

					if ( ! is_wp_error( $order ) && $user_id === $order->get_user_id() ) {
						$all_caps['view_order'] = true;
					}
					break;
				case 'pay_for_order':
					$user_id  = intval( $args[1] );
					$order_id = $args[2] ?? null;

					// When no order ID, we assume it's a new order
					// and thus, customer can pay for it.
					if ( ! $order_id ) {
						$all_caps['pay_for_order'] = true;
						break;
					}

					$order = Helper::get_order( $order_id );

					if ( ! is_wp_error( $order ) && ( $user_id === $order->get_user_id() || ! $order->get_user_id() ) ) {
						$all_caps['pay_for_order'] = true;
					}
					break;
				case 'order_again':
					$user_id = intval( $args[1] );
					$order   = Helper::get_order( $args[2] );

					if ( ! is_wp_error( $order ) && $user_id === $order->get_user_id() ) {
						$all_caps['order_again'] = true;
					}
					break;
				case 'cancel_order':
					$user_id = intval( $args[1] );
					$order   = Helper::get_order( $args[2] );

					if ( ! is_wp_error( $order ) && $user_id === $order->get_user_id() ) {
						$all_caps['cancel_order'] = true;
					}
					break;
				case 'download_file':
					$user_id  = intval( $args[1] );
					$download = $args[2];

					if ( $download instanceof DownloadPermission && $user_id === $download->get_user_id() ) {
						$all_caps['download_file'] = true;
					}
					break;
			}
		}

		return $all_caps;
	}
}

// End of file permissions-and-capabilities.php.
