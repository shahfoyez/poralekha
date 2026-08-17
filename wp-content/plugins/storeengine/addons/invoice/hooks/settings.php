<?php

namespace StoreEngine\Addons\Invoice\Hooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Settings {

	public static function init() {
		$self = new self();
		add_filter( 'storeengine/api/settings', [ $self, 'integrate_invoice_settings' ] );
	}

	public function integrate_invoice_settings( $settings ) {
		// Merge defaults so newly-added keys (and their defaults, e.g. the
		// §14 fields) are always present in the admin form even before the
		// store re-saves the invoice settings.
		$settings->invoice = wp_parse_args(
			\StoreEngine\Addons\Invoice\Settings::get_settings_saved_data(),
			\StoreEngine\Addons\Invoice\Settings::get_settings_default_data()
		);

		return $settings;
	}

}
