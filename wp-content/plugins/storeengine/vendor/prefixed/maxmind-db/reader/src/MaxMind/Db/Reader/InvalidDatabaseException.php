<?php
/**
 * @license Apache-2.0
 *
 * Modified by kodezen using {@see https://github.com/BrianHenryIE/strauss}.
 */

declare(strict_types=1);

namespace StoreEngine\MaxMind\Db\Reader;

/**
 * This class should be thrown when unexpected data is found in the database.
 */
// phpcs:disable
class InvalidDatabaseException extends \Exception {}
