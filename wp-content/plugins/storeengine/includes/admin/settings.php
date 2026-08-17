<?php
namespace StoreEngine\Admin;

use StoreEngine\Admin\Settings\Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	public static function init() {
		$self = new self();
		$self->save_settings();
	}

	/**
	 * Load settings.
	 * Load store settings in global variable as `$storeengine_settings` and `$storeengine_addons`.
	 * use `Helper::get_settings()` to retrieve setting by settings name.
	 *
	 * @see \StoreEngine\Utils\Helper::get_settings()
	 *
	 * @since 1.6.4
	 *
	 * @return void
	 */
	public static function load_settings() {
		$GLOBALS['storeengine_settings'] = apply_filters( 'storeengine_settings', json_decode( get_option( STOREENGINE_SETTINGS_NAME, '{}' ) ) );
		$GLOBALS['storeengine_addons']   = json_decode( get_option( STOREENGINE_ADDONS_SETTINGS_NAME, '{}' ) );
	}

	/**
	 * Save base settings.
	 *
	 * @return void
	 */
	public static function save_settings() {
		Base::save_settings();
		self::load_settings();
	}
}
