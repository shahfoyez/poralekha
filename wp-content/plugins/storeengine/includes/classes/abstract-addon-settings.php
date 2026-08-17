<?php
/**
 * Abstract Addon Settings.
 *
 * @version 1.0.0
 * @since StoreEngine v1.6.7
 */

namespace StoreEngine\Classes;

use StoreEngine\Admin\Settings\Base;
use StoreEngine\Interfaces\AddonSettingsInterface;
use StoreEngine\Traits\AbstractSingleton;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\StringUtil;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractAddonSettings implements AddonSettingsInterface {
	use AbstractSingleton;

	/**
	 * Addon Settings Name.
	 * Unique name for the addon settings.
	 *
	 * @var string
	 */
	protected ?string $settings_name = null;

	protected ?array $settings = null;

	protected bool $validate_on_save = false;

	protected function __construct() {

		if ( ! $this->settings_name ) {
			$this->settings_name = str_replace( ['storeengine-addons-', '-settings'], '', StringUtil::get_class_slug( $this, false ) );
		}

		$this->dispatch_hooks();
	}

	public function get_settings_name(): string {
		return $this->settings_name;
	}

	/**
	 * Get settings.
	 *
	 * @param string $key
	 * @param mixed $default
	 *
	 * @return mixed
	 */
	public function get_settings( string $key, $default = null ) {
		$this->load_settings();
		// Settings loaded on init so it can use i18n.
		$value = $this->settings[ $key ] ?? $default;

		return apply_filters( 'storeengine/' . $this->get_settings_name() . '/get_settings', $value, $key );
	}

	public function load_settings( bool $reload = false ) {
		if ( null === $this->settings || $reload ) {
			$this->settings = apply_filters(
				'storeengine/' . $this->get_settings_name() . '/settings',
				array_merge(
					$this->get_default_settings(), // Make sure settings populated correctly without missing any field.
					(array) Helper::get_settings( $this->get_settings_name(), [] )
				)
			);
		}

		return $this->settings;
	}

	public function dispatch_hooks() {
		// These hooks shouldn't be removed.

		add_action( 'init', [ $this, 'load_settings' ] );
		add_filter( 'storeengine/admin/settings_default_data', fn( $settings ): array => array_merge( $settings, [ $this->get_settings_name() => $this->get_default_settings() ] ) );
		add_filter( 'storeengine/ajax/settings_fields', fn( array $fields ): array => array_merge( $fields, [ $this->get_settings_name() => $this->get_settings_fields() ] ) );

		if ( $this->validate_on_save ) {
			add_filter(
				'storeengine/ajax/validate_settings',
				function( WP_Error $errors, array $payload ): WP_Error {

					// Only validate this addon's settings when they're part of the
					// current save. Base-settings saves from other tabs (General,
					// Compliance, etc.) legitimately omit this key; their stored
					// values are preserved by Base::prepare_settings_data(), so
					// there is nothing to validate or reject here.
					if ( ! array_key_exists( $this->get_settings_name(), $payload ) ) {
						return $errors;
					}

					if ( empty( $payload[ $this->get_settings_name() ] ) ) {
						$errors->add( $this->get_settings_name() . '-settings_data', __( 'Settings data are missing. Try disabling the addon and enable it again.', 'storeengine' ) );
						return $errors;
					}

					return $this->validate_settings( $errors, $payload[ $this->get_settings_name() ] );
				},
				10,
				2
			);
		}
	}

	public function save_default_settings(): void {
		if ( false === Helper::get_settings( $this->get_settings_name(), false ) ) {
			Base::save_settings( [ $this->get_settings_name() => $this->get_default_settings() ] );
		}
	}

	public function update_settings_name( string $old_name ): void {
		$old_settings = Helper::get_settings( $old_name, false );

		if ( ! $old_settings ) {
			return;
		}

		Base::save_settings( [ $this->get_settings_name() => $old_settings ] );
		Base::delete_settings( $old_name );
	}

	public function validate_settings( WP_Error $errors, array $payload ): WP_Error {
		return $errors;
	}
}

// End of file abstract-addon-settings.php.
