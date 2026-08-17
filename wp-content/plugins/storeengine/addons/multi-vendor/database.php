<?php

namespace StoreEngine\Addons\MultiVendor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\MultiVendor\Database\CreateVendors;
use StoreEngine\Addons\MultiVendor\Database\CreateWithdrawals;
use StoreEngine\Addons\MultiVendor\Database\MigrateLookupTable;
use StoreEngine\Utils\Helper;

class Database {

	public static function init() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		global $wpdb;

		$prefix          = $wpdb->prefix . Helper::DB_PREFIX;
		$charset_collate = $wpdb->get_charset_collate();

		CreateVendors::up( $prefix, $charset_collate );
		MigrateLookupTable::migrate_vendors_table();
		CreateWithdrawals::up( $prefix, $charset_collate );

		// vendor_id + commission_amount on storeengine_order_product_lookup are owned by core; dbDelta on DB version bump adds them.

		// Schedule a one-shot backfill for existing lookup rows.
		MigrateLookupTable::schedule_backfill();
	}
}
