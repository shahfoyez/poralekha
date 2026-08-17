<?php
namespace ABlocks\CreatePage\Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class ShowPageState {
	public static function init() : void {
		$instance = new static();
		add_filter( 'display_post_states', [ $instance, 'show_page_state' ], 10, 2 );
	}
	public function show_page_state( $states, $post ) {
		if ( $post->post_type !== 'page' ) {
			return $states;
		}

		$pages = [];
		if ( property_exists( $GLOBALS['ablocks_settings'] ?? '', 'login_page' ) ) {
			$pages[ $GLOBALS['ablocks_settings']->login_page ] = 'login_page';
		}
		if ( property_exists( $GLOBALS['ablocks_settings'] ?? '', 'registration_page' ) ) {
			$pages[ $GLOBALS['ablocks_settings']->registration_page ] = 'registration_page';
		}
		if ( property_exists( $GLOBALS['ablocks_settings'] ?? '', 'forget_password_page' ) ) {
			$pages[ $GLOBALS['ablocks_settings']->forget_password_page ] = 'forget_password_page';
		}
		$page_type = get_post_meta( $post->ID, 'ablock_page_type', true );
		if (
			! empty( $page_type )
		) {
			$states[] = esc_html( str_replace( '_', ' ', ucfirst( $page_type ) ) . ' page' );
		} elseif (
			array_key_exists( $post->ID, $pages )
		) {
			$states[] = esc_html( str_replace( '_', ' ', ucfirst( $pages[ $post->ID ] ) ) . ' page' );
		}

		return $states;
	}

}
