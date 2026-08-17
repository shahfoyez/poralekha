<?php

namespace ABlocksThemeBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use ABlocks\Interfaces\AddonInterface;

final class ThemeBuilder implements AddonInterface {
	private $addon_name = 'theme-builder';
	private function __construct() {
		$this->define_constants();
		$this->init_addon();
	}

	public function define_constants() {
		/**
		 * Defines CONSTANTS for Whole Addon.
		 */
		define( 'ABLOCKS_THEME_BUILDER_VERSION', '1.0' );
		define( 'ABLOCKS_THEME_BUILDER_ADDON_NAME', $this->addon_name );
	}

	public function init_addon() {
		// fire addon activation hook
		add_action( "ablocks/addons/activated_{$this->addon_name}", array( $this, 'addon_activation_hook' ) );
		// if disable then stop running addon
		if ( ! \ABlocks\Helper::get_addon_active_status( $this->addon_name ) ) {
			return;
		}

		database::init();
		CompatibilityManager::init();
		Assets::init();
		( new Ajax\Posts() )->dispatch_actions();
		Shortcode::init();
		if ( ! is_admin() ) {
			Frontend::init();
		}
	}

	public static function init() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	public function addon_activation_hook() {

	}
}
