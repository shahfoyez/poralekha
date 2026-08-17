<?php
namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unified payouts ledger — used by every addon that pays a person
 * (affiliates, vendors, future supplier reimbursements, instructors, …).
 * The `payee_type` discriminator + `payee_id` column lets each addon
 * read/write its own slice without needing its own table.
 */
class CreatePayouts {

	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_payouts';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			`payee_type` VARCHAR(40) NOT NULL,
			`payee_id` BIGINT(20) UNSIGNED NOT NULL,
			`amount` DECIMAL(12,2) NOT NULL DEFAULT 0,
			`payment_method` VARCHAR(40) DEFAULT NULL,
			`reference` VARCHAR(255) NOT NULL DEFAULT '',
			`status` VARCHAR(20) NOT NULL DEFAULT 'pending',
			`notes` TEXT NULL,
			`meta_json` LONGTEXT NULL,
			`created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			`paid_at` DATETIME NULL,
			PRIMARY KEY (`id`),
			KEY `payee` (`payee_type`, `payee_id`),
			KEY `status` (`status`),
			KEY `created_at` (`created_at`)
		) $charset_collate;";

		dbDelta( $sql );
	}
}
