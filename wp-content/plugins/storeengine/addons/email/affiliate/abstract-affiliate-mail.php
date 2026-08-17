<?php
/**
 * Base class for affiliate notification emails.
 *
 * Affiliate emails have no Order context, so they render like the account
 * emails (see account/password-reset.php) rather than through the order body
 * helpers: build a token map, run it through the shared heading/content
 * template (HTML) or plain-text builder, then hand off to mail_send().
 */

namespace StoreEngine\Addons\Email\affiliate;

use StoreEngine\Addons\Email\HelperAddon;
use StoreEngine\Addons\Email\Traits\Email;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractAffiliateMail {

	use Email {
		Email::__construct as private __EmailConstruct;
		Email::get_settings as protected;
	}

	public function __construct() {
		$this->__EmailConstruct( static::SETTINGS_KEY );

		if ( ! is_array( $this->settings ) || empty( $this->settings ) ) {
			$this->settings = static::default_template();
		}

		$this->register_hooks();
	}

	/**
	 * Wire the addon action(s) that fire this email.
	 */
	abstract protected function register_hooks(): void;

	/**
	 * Default settings tree for this email (subject/heading/content under a
	 * 'customer' sub-key, matching every other StoreEngine email).
	 */
	abstract public static function default_template(): array;

	public static function register_defaults( array $defaults ): array {
		if ( ! isset( $defaults[ static::SETTINGS_KEY ] ) ) {
			$defaults[ static::SETTINGS_KEY ] = static::default_template();
		}
		return $defaults;
	}

	/**
	 * Tokens shared by every affiliate email.
	 *
	 * @param string $name  Affiliate display name.
	 * @param string $email Affiliate email.
	 */
	protected function base_replacements( string $name, string $email ): array {
		return [
			'{affiliate_name}'  => esc_html( $name ),
			'{affiliate_email}' => esc_html( $email ),
			'{site_title}'      => esc_html( get_bloginfo( 'name' ) ),
			'{site_url}'        => esc_html( home_url( '/' ) ),
			'{store_name}'      => esc_html( (string) Helper::get_settings( 'store_name' ) ),
		];
	}

	/**
	 * Render (HTML or plain text) and send a non-order email.
	 *
	 * @param array  $settings      The 'customer' settings row (subject/heading/content).
	 * @param string $to            Recipient email.
	 * @param array  $replacements  Token => value map applied to subject + body.
	 */
	protected function dispatch( array $settings, string $to, array $replacements ): void {
		if ( ! $to || ! is_email( $to ) ) {
			return;
		}

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

		$this->mail_send( $to, $subject, $body, $headers );
	}
}
