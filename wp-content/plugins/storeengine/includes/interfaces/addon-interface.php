<?php
/**
 * Addon Interface.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Interfaces;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface AddonInterface {

	/**
	 * Initialize singleton addon.
	 *
	 * @return AddonInterface
	 */
	public static function init();

	/**
	 * Define constants.
	 * Developers are encourage to use Class Constants
	 *
	 * @return void
	 */
	public function define_constants();

	/**
	 * Loads the addon.
	 *
	 * @return void
	 */
	public function init_addon();

	/**
	 * Trigger once during addon activation.
	 *
	 * @return void
	 */
	public function addon_activation_hook();

	/**
	 * Trigger once during addon deactivation.
	 *
	 * @return void
	 */
	public function addon_deactivation_hook();

	/**
	 * Current schema version of the addon's own tables.
	 *
	 * Return a version string (e.g. a STOREENGINE_<ADDON>_DB_VERSION constant).
	 * Bump it whenever the addon's schema files change so the central manager
	 * (Addons::sync_addon_schemas) reruns install_tables(). Return an empty
	 * string when the addon has no tables of its own.
	 *
	 * @return string
	 */
	public function get_db_version(): string;

	/**
	 * (Re)create the addon's tables via dbDelta.
	 *
	 * Called by the central schema manager when get_db_version() differs from
	 * the stored value. Must be idempotent.
	 *
	 * @return void
	 */
	public function install_tables(): void;
}
