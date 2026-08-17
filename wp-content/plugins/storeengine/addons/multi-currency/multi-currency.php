<?php
/**
 * Multi-Currency Addon
 *
 * Displays product prices in the customer's local currency using live exchange
 * rates fetched from Frankfurter (free, ECB-sourced) or Open Exchange Rates,
 * ExchangeRate-API, or Currency Beacon.
 *
 * Architecture
 * ────────────
 * Exchange rates  → separate transient (not in storeengine_settings blob)
 * Active currency → cookie + URL param + geolocation
 * Integration     → storeengine/currency filter (single hook point)
 * Orders          → always created and charged in base currency
 *
 * File layout
 * ───────────
 * multi-currency.php               — addon entry, registered in addons.php
 * classes/settings.php             — settings stored in own wp_options key
 * classes/exchange-rates.php       — fetch, cache, and serve rates
 * classes/active-currency.php      — resolve display currency per request
 * classes/hooks.php                — all filter/action integrations with core
 * classes/schedule.php             — ActionScheduler cron for rate refresh
 * classes/ajax.php                 — switcher, settings save, manual refresh
 * classes/currency-switcher.php    — shortcode [se_currency_switcher] + widget
 * assets/js/currency-switcher.js   — frontend dropdown handler
 *
 * Registration
 * ────────────
 * Add to addons.php loader array:
 *   'multi-currency' => 'MultiCurrency',
 *
 * @package StoreEngine\Addons\MultiCurrency
 */

namespace StoreEngine\Addons\MultiCurrency;

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;
use StoreEngine\Addons\MultiCurrency\Classes\Settings;
use StoreEngine\Addons\MultiCurrency\Classes\Hooks;
use StoreEngine\Addons\MultiCurrency\Classes\Ajax;
use StoreEngine\Addons\MultiCurrency\Classes\Schedule;
use StoreEngine\Addons\MultiCurrency\Classes\CurrencySwitcher;
use StoreEngine\Addons\MultiCurrency\Classes\ExchangeRates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class MultiCurrency extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'multi-currency';

	public function define_constants(): void {
		define( 'STOREENGINE_MULTICURRENCY_VERSION', '1.0.0' );
		define( 'STOREENGINE_MULTICURRENCY_DIR',     __DIR__ . '/' );
	}

	public function init_addon(): void {
		new Settings();
		new Hooks();
		new Ajax();
		new Schedule();
		new CurrencySwitcher();
	}

	public function addon_activation_hook(): void {
		// Write default settings once on first activation.
		Settings::save_default_settings_once();
		// Schedule the recurring cron refresh.
		Schedule::register();
		// Immediately fetch rates so the addon works straight away.
		ExchangeRates::refresh();
	}

	public function addon_deactivation_hook(): void {
		Schedule::unschedule();
		delete_transient( ExchangeRates::TRANSIENT_KEY );
	}
}
