<?php
/**
 * Paged collection of email log entries.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @method int|EmailLog next_result()
 * @method array<EmailLog|int> get_results()
 */
class EmailLogCollection extends AbstractCollection {
	protected string $primary_key = 'id';

	protected string $table = 'storeengine_email_log';

	protected string $object_type = 'email_log';

	protected string $returnType = EmailLog::class; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
}
