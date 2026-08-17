<?php
/**
 * CLI commands for StoreEngine
 * @version 1.0.0
 * @since 1.8.2
 */

namespace StoreEngine;

use StoreEngine\Cli\Account;
use StoreEngine\Cli\License;
use StoreEngine\Cli\Order;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cli {
	use Singleton;

	protected function __construct() {
		add_action( 'cli_init', [ __CLASS__, 'register_cli_commands' ] );
	}

	public static function register_cli_commands() {
		WP_CLI::add_command( 'storeengine order', Order::class );
		WP_CLI::add_command( 'storeengine account', Account::class );
		WP_CLI::add_command( 'storeengine backup', \StoreEngine\Backup\Cli::class );

		if ( Helper::get_addon_active_status( 'license-management', true ) ) {
			WP_CLI::add_command( 'storeengine license', License::class );
		}
	}
}

// End of the file cli.php
