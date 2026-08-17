<?php
/**
 * Forgot-password / reset-password form handlers.
 *
 * Two `admin-post.php` actions, both available to logged-out visitors:
 *
 *   - `storeengine/forgot_password/request_reset`
 *       Accept an email, hand off to WP core's retrieve_password() so the
 *       branded HTML reset email (PasswordReset class) goes out, then
 *       redirect back with a generic success flag. Generic on purpose —
 *       differentiating "email found" vs "email not found" would leak
 *       account existence.
 *
 *   - `storeengine/forgot_password/set_password`
 *       Validate the {key, login} pair against WP core's
 *       check_password_reset_key(), then call reset_password() so the
 *       standard `password_reset` action chain fires for integrations.
 *
 * Both flows route through the existing AbstractPostHandler pipeline so
 * we inherit its nonce verification, field sanitization, and
 * allow_visitor_action plumbing without reimplementing it.
 */

namespace StoreEngine\Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractPostHandler;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_User;

class ForgotPassword extends AbstractPostHandler {

	public function __construct() {
		$this->actions = [
			'forgot_password/request_reset' => [
				'callback'             => [ $this, 'request_reset' ],
				'capability'           => '',
				'allow_visitor_action' => true,
				'fields'               => [
					'email' => 'string',
				],
			],
			'forgot_password/set_password'  => [
				'callback'             => [ $this, 'set_password' ],
				'capability'           => '',
				'allow_visitor_action' => true,
				'fields'               => [
					'key'   => 'string',
					'login' => 'string',
					'pass1' => 'string',
					'pass2' => 'string',
				],
			],
		];
	}

	/**
	 * Step 1: visitor enters email, we ask WP to send the reset email.
	 *
	 * We always redirect to the same success state regardless of whether
	 * the email matches a real account — leaking existence here is the
	 * classic account-enumeration mistake.
	 */
	public function request_reset( array $payload ) {
		$endpoint_url = Helper::get_account_endpoint_url( 'forgot-password' );

		$email = isset( $payload['email'] ) ? sanitize_email( $payload['email'] ) : '';
		if ( ! $email || ! is_email( $email ) ) {
			wp_safe_redirect( add_query_arg( 'reset_error', 'invalid_email', $endpoint_url ) );
			die();
		}

		// retrieve_password() validates that the account exists, generates
		// a reset key, persists it via update_user_meta, and then fires
		// `retrieve_password_notification_email` — which our PasswordReset
		// email class hooks to send the branded HTML version.
		//
		// On unknown email it returns a WP_Error; we deliberately swallow it
		// so the response is identical to the success path.
		retrieve_password( $email );

		wp_safe_redirect( add_query_arg( 'reset_sent', '1', $endpoint_url ) );
		die();
	}

	/**
	 * Step 2: visitor returns via the email link with a {key, login} pair,
	 * picks a new password, submits.
	 */
	public function set_password( array $payload ) {
		$endpoint_url = Helper::get_account_endpoint_url( 'forgot-password' );

		$key   = isset( $payload['key'] ) ? sanitize_text_field( $payload['key'] ) : '';
		$login = isset( $payload['login'] ) ? sanitize_text_field( $payload['login'] ) : '';
		$pass1 = $payload['pass1'] ?? '';
		$pass2 = $payload['pass2'] ?? '';

		if ( ! $key || ! $login ) {
			return new WP_Error(
				'invalid_reset_link',
				__( 'This reset link is missing required information. Please request a new one.', 'storeengine' ),
				[ 'status' => 400, 'title' => __( 'Invalid reset link', 'storeengine' ) ]
			);
		}

		if ( '' === $pass1 || '' === $pass2 ) {
			wp_safe_redirect( add_query_arg(
				[ 'key' => $key, 'login' => $login, 'reset_error' => 'empty' ],
				$endpoint_url
			) );
			die();
		}

		// Minimum-length policy. Filterable so a site can tighten it (e.g.
		// require 12+ chars) without having to fork this handler. WP doesn't
		// enforce a minimum at the reset_password() layer, so without this
		// check `'a'` would be a valid new password.
		$min_length = (int) apply_filters( 'storeengine/auth/min_password_length', 8 );
		if ( strlen( $pass1 ) < $min_length ) {
			wp_safe_redirect( add_query_arg(
				[ 'key' => $key, 'login' => $login, 'reset_error' => 'too_short' ],
				$endpoint_url
			) );
			die();
		}

		if ( $pass1 !== $pass2 ) {
			wp_safe_redirect( add_query_arg(
				[ 'key' => $key, 'login' => $login, 'reset_error' => 'mismatch' ],
				$endpoint_url
			) );
			die();
		}

		$user = check_password_reset_key( $key, $login );
		if ( is_wp_error( $user ) || ! ( $user instanceof WP_User ) ) {
			return new WP_Error(
				'expired_reset_link',
				__( 'This reset link is invalid or has expired. Please request a new one.', 'storeengine' ),
				[ 'status' => 400, 'title' => __( 'Invalid reset link', 'storeengine' ) ]
			);
		}

		reset_password( $user, $pass1 );

		// Land the customer on the StoreEngine dashboard, not wp-login.php.
		// For logged-out visitors, the dashboard page's [storeengine_dashboard]
		// shortcode renders [storeengine_login_form] — the branded sign-in
		// form. Using storeengine_login_url() here was unsafe because it
		// falls back to wp_login_url() whenever auth_redirect_type isn't
		// exactly 'storeengine' or the dashboard_page setting is missing,
		// dropping the customer on WP's default login screen at the end of
		// what's supposed to be a fully branded flow.
		wp_safe_redirect( add_query_arg( 'password_updated', '1', Helper::get_dashboard_url() ) );
		die();
	}
}
