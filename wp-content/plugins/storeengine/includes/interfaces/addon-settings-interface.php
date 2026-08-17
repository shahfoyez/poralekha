<?php
/**
 * Addon Settings Interface.
 *
 * @version 1.0.0
 * @since StoreEngine v1.6.7
 */

namespace StoreEngine\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface AddonSettingsInterface {
	public function get_default_settings(): array;

	public function get_settings_fields(): array;

	public function save_default_settings(): void;
}

// End of file addon-settings-interface.php.
