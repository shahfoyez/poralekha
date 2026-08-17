<?php
/**
 * EU/UK VAT Addon
 *
 * Collects EU/UK VAT numbers at checkout, validates them against VIES (EU)
 * and HMRC (UK), and exempts cross-border B2B buyers from VAT when the
 * number is valid.
 *
 * File layout
 * ───────────
 * eu-vat.php                     — addon entry, registered in addons.php
 * helpers.php                    — pure helper functions (parse_vat, eu_countries)
 * classes/settings.php           — settings stored in own wp_options key
 * classes/vat-number.php         — value object for parsed VAT numbers
 * classes/vies-validator.php     — SOAP/cURL/file_get_contents fallback chain
 * classes/hooks.php              — checkout, tax, order, customer, account hooks
 * classes/admin.php              — order list column + edit-screen meta box
 * classes/ajax.php               — validate AJAX endpoint + settings save
 * classes/assets.php             — enqueue JS/CSS on checkout + account
 *
 * Registration
 * ────────────
 * Already added to includes/addons.php loader array as:
 *   'eu-vat' => 'EuVat',
 *
 * @todo PRO: Payment-method gating by VAT validity.
 * @todo PRO: IP-country vs VAT-country match.
 * @todo PRO: Company-name match against VIES response.
 * @todo PRO: Local VAT numbers handling (non-VIES regions).
 * @todo PRO: EU standard rate importer tool.
 * @todo PRO: Period-based VAT report.
 * @todo PRO: Bulk re-validate stored VAT numbers.
 * @todo PRO: REST endpoint /storeengine/v1/eu-vat/validate for headless.
 *
 * @package StoreEngine\Addons\EuVat
 */

namespace StoreEngine\Addons\EuVat;

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;
use StoreEngine\Addons\EuVat\Classes\Ajax;
use StoreEngine\Addons\EuVat\Classes\Assets;
use StoreEngine\Addons\EuVat\Classes\Hooks;
use StoreEngine\Addons\EuVat\Classes\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EuVat extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'eu-vat';

	public function define_constants(): void {
		define( 'STOREENGINE_EU_VAT_VERSION', '1.0.0' );
		define( 'STOREENGINE_EU_VAT_DIR',     __DIR__ . '/' );
		define( 'STOREENGINE_EU_VAT_URL',     plugins_url( '/', __FILE__ ) );

		require_once __DIR__ . '/helpers.php';
	}

	public function init_addon(): void {
		new Settings();
		new Hooks();
		new Assets();

		// Settings now live as a tab inside the main StoreEngine React settings
		// UI (slug: eu-vat), persisted through the `eu_vat/save_settings` AJAX
		// action below. The old standalone PHP submenu (AdminSettingsPage) is
		// retired to avoid a duplicate settings surface.

		( new Ajax() )->dispatch_actions();
	}

	public function addon_activation_hook(): void {
		Settings::save_default_settings_once();
	}
}
