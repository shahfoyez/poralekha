<?php
namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * @method array<LogItem> get_results()
 * @method LogItem next_result()
 */
class LogItemCollection extends AbstractCollection {
    protected string $table = 'storeengine_logs';
    protected string $object_type = 'log';
    protected string $primary_key = 'id';
    protected string $returnType = LogItem::class;

}
