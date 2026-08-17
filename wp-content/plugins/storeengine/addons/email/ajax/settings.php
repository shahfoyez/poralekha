<?php

namespace StoreEngine\Addons\Email\Ajax;

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Addons\Email\Admin\Settings as EmailSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class Settings extends AbstractAjaxHandler {
	protected string $namespace = STOREENGINE_PLUGIN_SLUG . '_email';

	public function __construct() {
		$this->migrate_settings();
		$this->actions = [
			'get_email_settings'  => [
				'callback'   => [ $this, 'get_email_settings' ],
				'capability' => 'manage_options',
			],
			'save_email_settings' => [
				'callback'   => [ $this, 'save_email_settings' ],
				'capability' => 'manage_options',
				// The field whitelist is intentionally NOT declared here. It is
				// built lazily in prepare_payload() because it derives from
				// EmailSettings::get_settings_default_data(), which resolves
				// __() inside each email's default_template(). Doing that in the
				// constructor is unsafe: this handler is instantiated on
				// `plugins_loaded` (before `init`), and calling __() that early
				// triggers WP 6.7+'s _load_textdomain_just_in_time notice.
				// admin-ajax dispatch (prepare_payload's call site) runs after
				// `init`, so building the schema there is safe.
			],
		];
	}

	/**
	 * Build the save whitelist at request time from the registered email
	 * defaults, so every email that registers itself via the
	 * `storeengine/email/settings_default_data` filter is automatically
	 * savable — without a second, hand-maintained list.
	 *
	 * A stale static whitelist here is exactly what silently dropped every
	 * email added after the original set (password_reset, registration_welcome,
	 * subscription_*, affiliate_*, order_item_shipped, …): their toggles were
	 * stripped from the payload on save, so save_settings() re-applied the
	 * `is_enable => false` default and the switch appeared to "auto-disable".
	 *
	 * @param array|null $fields Schema declared on the action (unused for the
	 *                           save action — we always rebuild it here).
	 * @return array
	 */
	protected function prepare_payload( ?array $fields = null ): array {
		$action = isset( $_REQUEST['action'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nonce verified in prepare_response() before this runs.

		if ( $this->namespace . '/save_email_settings' === $action ) {
			$fields = self::inject_content_tree(
				apply_filters( 'storeengine/email/settings_fields', self::build_fields_schema() )
			);
		}

		return parent::prepare_payload( $fields );
	}

	/**
	 * Derive the sanitizer schema from the default settings structure.
	 *
	 * Top-level scalar options keep their explicit types; every registered
	 * email template (a nested channel → leaf array) is mapped uniformly.
	 *
	 * @return array
	 */
	protected static function build_fields_schema(): array {
		// Top-level scalar fields keep their explicit sanitizer types.
		$fields = [
			'form_name'          => 'string',
			'email_address'      => 'email',
			'email_content_type' => 'string',
			'header_image'       => 'string',
			'footer_text'        => 'post',
		];

		foreach ( EmailSettings::get_settings_default_data() as $key => $value ) {
			// Scalars are already covered above; only the nested email
			// templates need generating.
			if ( ! is_array( $value ) || isset( $fields[ $key ] ) ) {
				continue;
			}

			$fields[ $key ] = self::map_template_schema( $value );
		}

		return $fields;
	}

	/**
	 * Map a single email template (channel => [leaf => default, …]) to its
	 * sanitizer schema. Every email shares the same leaf shape, so the type is
	 * decided purely by the leaf name.
	 *
	 * @param array $template
	 * @return array
	 */
	protected static function map_template_schema( array $template ): array {
		$schema = [];

		foreach ( $template as $channel => $leaves ) {
			if ( ! is_array( $leaves ) ) {
				continue;
			}

			$channel_schema = [];
			foreach ( array_keys( $leaves ) as $leaf ) {
				$channel_schema[ $leaf ] = self::leaf_type( (string) $leaf );
			}

			$schema[ $channel ] = $channel_schema;
		}

		return $schema;
	}

	/**
	 * Sanitizer type for an email template leaf key.
	 *
	 * @param string $leaf
	 * @return string
	 */
	protected static function leaf_type( string $leaf ): string {
		switch ( $leaf ) {
			case 'is_enable':
				return 'boolean';
			case 'email_content':
				return 'post';
			default:
				// email_subject, email_heading, email_content_tree (base64url,
				// preserved by sanitize_text_field), and any future scalar leaf.
				return 'string';
		}
	}

	/**
	 * Whitelist a `email_content_tree` sibling wherever `email_content` is
	 * allowed, so the block editor's tree (base64url JSON) round-trips on save.
	 *
	 * The generated schema already includes the tree key (it exists in the
	 * defaults via {@see EmailSettings::inject_content_tree_defaults()}); this
	 * still runs so any email injected by a third-party `settings_fields`
	 * filter that only declares `email_content` also gets the tree key.
	 *
	 * @param array $fields Sanitizer schema.
	 * @return array
	 */
	private static function inject_content_tree( array $fields ): array {
		foreach ( $fields as $key => $schema ) {
			if ( is_array( $schema ) ) {
				$fields[ $key ] = self::inject_content_tree( $schema );
			} elseif ( 'email_content' === $key ) {
				$fields['email_content_tree'] = 'string';
			}
		}

		return $fields;
	}

	public function get_email_settings() {
		wp_send_json_success( EmailSettings::get_settings_saved_data() );
	}

	public function save_email_settings( $payload ) {
		$payload['email_content_type'] = ! empty( $payload['email_content_type'] ) && in_array( $payload['email_content_type'], [
			'html',
			'plainText',
		], true ) ? $payload['email_content_type'] : 'html';

		EmailSettings::save_settings( $payload );
		wp_send_json_success( EmailSettings::get_settings_saved_data() );
	}

	public function migrate_settings() {
		$storeengine_email_version = get_option( 'storeengine_email_addon_version' );

		if ( ! $storeengine_email_version || version_compare( $storeengine_email_version, STOREENGINE_EMAIL_VERSION, '<' ) ) {
			EmailSettings::save_settings();
			update_option( 'storeengine_email_addon_version', STOREENGINE_EMAIL_VERSION );
		}
	}
}
