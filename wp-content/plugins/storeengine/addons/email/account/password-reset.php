<?php
/**
 * Branded Password Reset Email.
 *
 * Replaces WordPress's plain-text password reset email with a styled HTML
 * version that matches the rest of StoreEngine's emails. Hooks the
 * `retrieve_password_notification_email` filter (WP 5.3+) so we can return
 * the full `[subject, message, headers, attachments]` tuple — no need to
 * juggle wp_mail_content_type globally.
 */

namespace StoreEngine\Addons\Email\account;

use StoreEngine\Addons\Email\HelperAddon;
use StoreEngine\Addons\Email\Traits\Email;
use StoreEngine\Utils\Helper;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PasswordReset {

	use Email {
		Email::__construct as private __EmailConstruct;
	}

	const SETTINGS_KEY = 'password_reset';

	public function __construct() {
		$this->__EmailConstruct( self::SETTINGS_KEY );

		if ( ! is_array( $this->settings ) || empty( $this->settings ) ) {
			$this->settings = self::default_template();
		}

		add_filter( 'retrieve_password_notification_email', [ $this, 'filter_email' ], 10, 4 );
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
				'email_subject' => __( 'Reset your password at {site_title}', 'storeengine' ),
				'email_heading' => __( 'Password reset requested', 'storeengine' ),
				'email_content' => __(
					'<p>Hi {user_display_name},</p><p>Someone requested a password reset for your {site_title} account. If this wasn\'t you, you can safely ignore this email and your password will stay the same.</p><p>To reset your password, click the link below:</p><p><a href="{reset_url}">{reset_url}</a></p><p>This link expires in 24 hours.</p><p>Username: <code>{user_login}</code></p>',
					'storeengine'
				),
			],
		];
	}

	/**
	 * @param array  $defaults  WP's default [subject, message, headers, attachments].
	 * @param string $key       Reset key (one-time token).
	 * @param string $user_login
	 * @param WP_User|null $user_data
	 */
	public function filter_email( $defaults, $key, $user_login, $user_data ): array {
		$settings = $this->get_settings( 'customer' );
		if ( ! is_array( $settings ) || empty( $settings['is_enable'] ) ) {
			return $defaults;
		}

		$user = $user_data instanceof WP_User ? $user_data : get_user_by( 'login', $user_login );
		if ( ! $user ) {
			return $defaults;
		}

		// Land the customer on our in-dashboard form, not wp-login.php. The
		// template at templates/frontend-dashboard/pages/forgot-password.php
		// detects the key/login query vars, calls check_password_reset_key()
		// itself, and renders the "set new password" state.
		//
		// Pass values raw to add_query_arg — it urlencodes them internally,
		// so pre-encoding with rawurlencode() would produce `%2540` etc. and
		// silently break the reset for any user whose user_login contains an
		// `@`, space, or other reserved character.
		$reset_url = add_query_arg(
			[
				'key'   => $key,
				'login' => $user_login,
			],
			Helper::get_account_endpoint_url( 'forgot-password' )
		);

		$replacements = [
			'{user_display_name}' => esc_html( $user->display_name ),
			'{user_login}'        => esc_html( $user_login ),
			'{user_email}'        => esc_html( $user->user_email ),
			'{reset_url}'         => esc_url( $reset_url ),
			'{site_title}'        => esc_html( get_bloginfo( 'name' ) ),
			'{site_url}'          => esc_html( home_url( '/' ) ),
			'{store_name}'        => esc_html( Helper::get_settings( 'store_name' ) ),
		];

		$subject = str_replace( array_keys( $replacements ), array_values( $replacements ), $settings['email_subject'] );

		$email_type = HelperAddon::get_setting( 'email_content_type' );
		$footer     = HelperAddon::get_setting( 'footer_text' );

		if ( 'plainText' === $email_type ) {
			$body = $this->prepare_text_body( $settings['email_heading'], $settings['email_content'], $footer );
			$body = str_replace( array_keys( $replacements ), array_values( $replacements ), $body );
			$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
		} else {
			ob_start();
			Helper::get_template( 'email/order-status-customer.php', [
				'heading' => str_replace( array_keys( $replacements ), array_values( $replacements ), $settings['email_heading'] ),
				'content' => str_replace( array_keys( $replacements ), array_values( $replacements ), $settings['email_content'] ),
				'footer'  => $footer,
			] );
			$body = $this->style_inline( ob_get_clean() );
			$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
		}

		// Apply our From-name/address filters for this call.
		add_filter( 'wp_mail_from_name', [ $this, 'get_from_name' ] );
		add_filter( 'wp_mail_from', [ $this, 'get_from_address' ] );
		// They'll persist for the wp_mail call WP makes after this filter
		// returns — WP doesn't re-clear them. Remove them on the next
		// `phpmailer_init` to scope cleanly.
		add_action( 'phpmailer_init', [ $this, 'remove_from_filters' ], 99 );

		return [
			'subject'     => wp_specialchars_decode( $subject ),
			'message'     => $body,
			'headers'     => $headers,
			'attachments' => $defaults['attachments'] ?? [],
		];
	}

	public function remove_from_filters(): void {
		remove_filter( 'wp_mail_from_name', [ $this, 'get_from_name' ] );
		remove_filter( 'wp_mail_from', [ $this, 'get_from_address' ] );
		remove_action( 'phpmailer_init', [ $this, 'remove_from_filters' ], 99 );
	}
}
