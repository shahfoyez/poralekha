<?php
namespace StoreEngine\Database;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class CreateLog {
    public static function up( $prefix, $charset_collate ) {
        $table_name = $prefix . 'storeengine_logs';
        $sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
            `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            `date` datetime NOT NULL,
            `module` varchar(100) NOT NULL,
            `title` varchar(255) NOT NULL,
            `status` varchar(20) NOT NULL,
            `content` longtext NOT NULL,
            PRIMARY KEY (`id`),
            KEY `date` (`date`),
            KEY `status` (`status`),
            KEY `module` (`module`)
        ) $charset_collate;";

        dbDelta( $sql );
    }
}
