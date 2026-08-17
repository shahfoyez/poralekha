<?php

namespace StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

class Login {


	public function __construct() {
		add_shortcode( 'storeengine_login_form', array( $this, 'login_form' ) );
	}

	public function login_form( $atts ) {
		$attributes = shortcode_atts( [
			'form_title'             => __( 'Log In into your Account', 'storeengine' ),
			'username_label'         => __( 'Username or Email Address', 'storeengine' ),
			'username_placeholder'   => __( 'Username or Email Address', 'storeengine' ),
			'password_label'         => __( 'Password', 'storeengine' ),
			'password_placeholder'   => __( 'Password', 'storeengine' ),
			'remember_label'         => __( 'Remember me', 'storeengine' ),
			'login_button_label'     => __( 'Log in Now', 'storeengine' ),
			'reset_password_label'   => __( 'Reset password', 'storeengine' ),
			'show_logged_in_message' => true,
			// Link directly to our in-dashboard register endpoint rather than
			// going through wp_registration_url() (which returns wp-login.php).
			// We intentionally don't filter `register_url` globally — only the
			// StoreEngine login template should route to the branded form.
			'register_url'           => Helper::get_account_endpoint_url( 'register' ),
			'login_redirect_url'     => Helper::get_page_permalink( 'dashboard_page' ),
			'logout_redirect_url'    => get_the_permalink(),
		], $atts );

		ob_start();

		if ( apply_filters( 'storeengine/shortcode/login_form_is_user_logged_in', is_user_logged_in() ) ) {
			if ( filter_var( $attributes['show_logged_in_message'], FILTER_VALIDATE_BOOLEAN ) ) {
				Template::get_template( 'shortcode/logged-in-user.php', [
					'logout_redirect_url' => $attributes['logout_redirect_url'],
				] );
			}
		} else {
			Template::get_template( 'shortcode/login.php', $attributes );
		}

		return apply_filters( 'storeengine/templates/shortcode/login', ob_get_clean() );
	}
}
