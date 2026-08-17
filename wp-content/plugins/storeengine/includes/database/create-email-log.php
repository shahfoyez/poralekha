<?php
namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CreateEmailLog {
	public static function up( $prefix, $charset_collate ) {
		$table_name = $prefix . 'storeengine_email_log';
		$sql        = "CREATE TABLE IF NOT EXISTS {$table_name} (
			`id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`sent_at_gmt` datetime NOT NULL,
			`email_type` varchar(100) NOT NULL,
			`recipient` varchar(320) NOT NULL,
			`subject` varchar(500) NOT NULL,
			`status` varchar(20) NOT NULL,
			`customer_id` bigint(20) unsigned NULL DEFAULT NULL,
			`order_id` bigint(20) unsigned NULL DEFAULT NULL,
			`related_entity_type` varchar(50) NULL DEFAULT NULL,
			`related_entity_id` bigint(20) unsigned NULL DEFAULT NULL,
			`error_message` text NULL DEFAULT NULL,
			`payload` longtext NULL DEFAULT NULL,
			PRIMARY KEY (`id`),
			KEY `sent_at_gmt` (`sent_at_gmt`),
			KEY `email_type` (`email_type`),
			KEY `status` (`status`),
			KEY `recipient` (`recipient`(191)),
			KEY `customer_id` (`customer_id`),
			KEY `order_id` (`order_id`),
			KEY `related` (`related_entity_type`, `related_entity_id`)
		) $charset_collate;";

		dbDelta( $sql );
	}
}
