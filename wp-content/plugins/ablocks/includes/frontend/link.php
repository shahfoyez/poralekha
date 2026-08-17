<?php
namespace  ABlocks\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use ABlocks\Helper;

class Link {
	public static function init() {
		$self = new self();
		add_filter( 'the_content', array( $self, 'link_placement' ) );
	}
	public static function link_placement( $content ) {
		$replacements = [
			'{{ablocks_link_login_page}}' => esc_url( get_the_permalink( Helper::get_settings( 'login_page' ) ) ),
			'{{ablocks_link_registration_page}}' => esc_url( get_the_permalink( Helper::get_settings( 'registration_page' ) ) ),
			'{{ablocks_link_forget_password_page}}' => esc_url( get_the_permalink( Helper::get_settings( 'forget_password_page' ) ) ),
		];
		if ( $replacements ) {
			$content = str_replace( array_keys( $replacements ), array_values( $replacements ), $content );
		}
		return $content;
	}
}
