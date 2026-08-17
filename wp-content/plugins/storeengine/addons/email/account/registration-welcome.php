<?php
/**
 * Branded Registration Welcome Email.
 *
 * Overrides WordPress's plain new-user email with a styled HTML welcome that
 * matches the rest of StoreEngine's emails. Hooks the
 * `wp_new_user_notification_email` filter (the *user-facing* one; the admin
 * copy uses `wp_new_user_notification_email_admin` and is left untouched) so
 * we can return the full [to, subject, message, headers] array.
 *
 * This fires for any account registration that calls wp_new_user_notification()
 * with the 'user' recipient. The order-flow credentials email
 * (order/new-user-notification.php) is separate and fires on
 * storeengine/checkout/customer_created, so a store can enable either or both.
 */

namespace StoreEngine\Addons\Email\account;

use StoreEngine\Addons\Email\HelperAddon;
use StoreEngine\Addons\Email\Traits\Email;
use StoreEngine\Utils\Helper;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class RegistrationWelcome {

	use Email {
		Email::__construct as private __EmailConstruct;
	}

	const SETTINGS_KEY = 'registration_welcome';

	public function __construct() {
		$this->__EmailConstruct( self::SETTINGS_KEY );

		if ( ! is_array( $this->settings ) || empty( $this->settings ) ) {
			$this->settings = self::default_template();
		}

		add_filter( 'wp_new_user_notification_email', [ $this, 'filter_email' ], 10, 3 );
	}

	public static function register_defaults( array $defaults ): array {
		if ( ! isset( $defaults[ self::SETTINGS_KEY ] ) ) {
			$defaults[ self::SETTINGS_KEY ] = self::default_template();
		}
		return $defaults;
	}

	public static function default_template(): array {
		return [
			'customer' => [
				'is_enable'     => false,
				'email_subject' => __( 'Welcome to {site_title}', 'storeengine' ),
				'email_heading' => __( 'Welcome aboard', 'storeengine' ),
				'email_content' => __(
					'<p>Hi {user_display_name},</p><p>Thanks for creating an account at {store_name}. You\'re all set.</p><p>Your username: <code>{user_login}</code></p><p>You can sign in and manage your account here:</p><p><a href="{login_url}">{login_url}</a></p><p>Happy to have you with us.</p>',
					'storeengine'
				),
			],
		];
	}

	/**
	 * @param array   $email     WP's default [to, subject, message, headers].
	 * @param WP_User $user      The newly-registered user.
	 * @param string  $blogname
	 */
	public function filter_email( $email, $user, $blogname ): array {
		$settings = $this->get_settings( 'customer' );
		if ( ! is_array( $settings ) || empty( $settings['is_enable'] ) ) {
			return $email;
		}

		if ( ! $user instanceof WP_User ) {
			return $email;
		}

		$login_url = function_exists( 'storeengine_login_url' )
			? storeengine_login_url( Helper::get_dashboard_url() )
			: wp_login_url();

		$replacements = [
			'{user_display_name}' => esc_html( $user->display_name ),
			'{user_login}'        => esc_html( $user->user_login ),
			'{user_email}'        => esc_html( $user->user_email ),
			'{login_url}'         => esc_url( $login_url ),
			'{site_title}'        => esc_html( get_bloginfo( 'name' ) ),
			'{site_url}'          => esc_html( home_url( '/' ) ),
			'{store_name}'        => esc_html( (string) Helper::get_settings( 'store_name' ) ),
		];

		$subject = str_replace( array_keys( $replacements ), array_values( $replacements ), $settings['email_subject'] );

		$email_type = HelperAddon::get_setting( 'email_content_type' );
		$footer     = HelperAddon::get_setting( 'footer_text' );

		if ( 'plainText' === $email_type ) {
			$body    = $this->prepare_text_body( $settings['email_heading'], $settings['email_content'], $footer );
			$body    = str_replace( array_keys( $replacements ), array_values( $replacements ), $body );
			$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
		} else {
			ob_start();
			Helper::get_template( 'email/order-status-customer.php', [
				'heading' => str_replace( array_keys( $replacements ), array_values( $replacements ), $settings['email_heading'] ),
				'content' => str_replace( array_keys( $replacements ), array_values( $replacements ), $settings['email_content'] ),
				'footer'  => $footer,
			] );
			$body    = $this->style_inline( ob_get_clean() );
			$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		}

		// Apply our From-name/address filters for the wp_mail call WP makes
		// after this filter returns (mirrors PasswordReset).
		add_filter( 'wp_mail_from_name', [ $this, 'get_from_name' ] );
		add_filter( 'wp_mail_from', [ $this, 'get_from_address' ] );
		add_action( 'phpmailer_init', [ $this, 'remove_from_filters' ], 99 );

		return [
			'to'      => $email['to'] ?? $user->user_email,
			'subject' => wp_specialchars_decode( $subject ),
			'message' => $body,
			'headers' => $headers,
		];
	}

	public function remove_from_filters(): void {
		remove_filter( 'wp_mail_from_name', [ $this, 'get_from_name' ] );
		remove_filter( 'wp_mail_from', [ $this, 'get_from_address' ] );
		remove_action( 'phpmailer_init', [ $this, 'remove_from_filters' ], 99 );
	}
}
