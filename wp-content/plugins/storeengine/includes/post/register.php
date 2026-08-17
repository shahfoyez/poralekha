<?php
/**
 * Customer registration form handler (non-JS path).
 *
 * Mirrors the REST endpoint at /storeengine/v1/auth/register so the same
 * server-side behavior (username derivation, NewUserNotification email
 * trigger, auto-login) runs regardless of submission style. JS clients
 * should prefer the REST route; this handler exists so the form keeps
 * working without JavaScript and to match the ForgotPassword pattern.
 */

namespace StoreEngine\Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractPostHandler;
use StoreEngine\Utils\Helper;

class Register extends AbstractPostHandler {

	public function __construct() {
		$this->actions = [
			'auth/register' => [
				'callback'             => [ $this, 'register' ],
				'capability'           => '',
				'allow_visitor_action' => true,
				'fields'               => [
					'email'      => 'string',
					'password'   => 'string',
					'first_name' => 'string',
					'last_name'  => 'string',
				],
			],
		];
	}

	public function register( array $payload ) {
		$endpoint_url = Helper::get_account_endpoint_url( 'register' );

		if ( ! get_option( 'users_can_register' ) ) {
			wp_safe_redirect( add_query_arg( 'register_error', 'closed', $endpoint_url ) );
			die();
		}

		$email      = isset( $payload['email'] ) ? sanitize_email( $payload['email'] ) : '';
		$password   = $payload['password'] ?? '';
		$first_name = isset( $payload['first_name'] ) ? sanitize_text_field( $payload['first_name'] ) : '';
		$last_name  = isset( $payload['last_name'] ) ? sanitize_text_field( $payload['last_name'] ) : '';

		if ( ! $email || ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( 'register_error', 'invalid_email', $endpoint_url ) );
			die();
		}
		if ( '' === $password ) {
			wp_safe_redirect( add_query_arg( 'register_error', 'password', $endpoint_url ) );
			die();
		}

		$min_length = (int) apply_filters( 'storeengine/auth/min_password_length', 8 );
		if ( strlen( $password ) < $min_length ) {
			wp_safe_redirect( add_query_arg( 'register_error', 'too_short', $endpoint_url ) );
			die();
		}

		if ( email_exists( $email ) ) {
			wp_safe_redirect( add_query_arg( 'register_error', 'email_taken', $endpoint_url ) );
			die();
		}

		// Match the REST controller's username derivation (same algorithm as
		// CheckoutService::create_customer) so both signup paths produce
		// identical usernames for the same email.
		$base     = strstr( $email, '@', true ) ?: 'user';
		$username = $base;
		$counter  = 1;
		while ( username_exists( $username ) ) {
			$username = $base . $counter;
			$counter++;
		}

		$userdata = apply_filters( 'storeengine/auth/register_userdata', [
			'user_login'   => $username,
			'user_email'   => $email,
			'user_pass'    => $password,
			'role'         => 'storeengine_customer',
			'display_name' => trim( $first_name . ' ' . $last_name ) ?: $username,
			'first_name'   => $first_name,
			'last_name'    => $last_name,
		], null );

		$user_id = wp_insert_user( $userdata );
		if ( is_wp_error( $user_id ) ) {
			wp_safe_redirect( add_query_arg( 'register_error', 'failed', $endpoint_url ) );
			die();
		}

		// Same hook the checkout uses on auto-register, so NewUserNotification
		// fires for the welcome-with-credentials email.
		do_action( 'storeengine/checkout/customer_created', $user_id, $userdata );

		// Auto-login so the customer lands on the dashboard ready to shop.
		wp_clear_auth_cookie();
		wp_signon( [
			'user_login'    => $username,
			'user_password' => $password,
			'remember'      => true,
		], is_ssl() );

		wp_safe_redirect( Helper::get_dashboard_url() );
		die();
	}
}
