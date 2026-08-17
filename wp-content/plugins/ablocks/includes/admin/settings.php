<?php
namespace ABlocks\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Admin\Settings\Base as BaseSettings;
use ABlocks\Admin\Settings\Blocks as BlocksSettings;

class Settings {

	public static function init() {
		$self = new self();
		$self->save_settings();
	}

	public static function save_settings() {
		BaseSettings::save_settings();
		BlocksSettings::save_settings();
	}
}
