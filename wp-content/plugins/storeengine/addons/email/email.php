<?php
namespace StoreEngine\Addons\Email;

use StoreEngine\Addons\Email\Admin\Settings;
use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Email extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'email';

	public function define_constants() {
		define( 'STOREENGINE_EMAIL_VERSION', '1.0.1' );
	}

	public function init_addon() {
		new Hooks();
		new Ajax();
		EmailLogger::init();
		ResendRegistry::init();
	}

	public function addon_activation_hook() {
		Admin\Settings::save_settings();
	}
}
