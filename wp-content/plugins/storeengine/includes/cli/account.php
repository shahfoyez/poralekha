<?php

namespace StoreEngine\Cli;

use StoreEngine\Classes\AccountMover;
use WP_CLI;
use WP_CLI_Command;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Account extends WP_CLI_Command {

	/**
	 * Move a customer's StoreEngine data (orders, subscriptions, licenses, etc.)
	 * from one WordPress user to another. Neither account is deleted — only the
	 * ownership of the records changes. The transfer is additive.
	 *
	 * ## OPTIONS
	 *
	 * --from=<from>
	 * : Source user ID (data is moved FROM here).
	 *
	 * --to=<to>
	 * : Target user ID (data is moved TO here).
	 *
	 * [--dry-run]
	 * : Show what would move without changing anything.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeengine account move --from=12 --to=34 --dry-run
	 *     wp storeengine account move --from=12 --to=34 --yes
	 *
	 * @subcommand move
	 */
	public function move( $args, $assoc_args ) {
		$from    = isset( $assoc_args['from'] ) ? absint( $assoc_args['from'] ) : 0;
		$to      = isset( $assoc_args['to'] ) ? absint( $assoc_args['to'] ) : 0;
		$dry_run = isset( $assoc_args['dry-run'] );

		if ( ! $from || ! $to ) {
			WP_CLI::error( 'Please provide both --from=<id> and --to=<id>.' );
		}

		$mover   = new AccountMover( $from, $to );
		$preview = $mover->preview();

		if ( is_wp_error( $preview ) ) {
			WP_CLI::error( $preview->get_error_message() );
		}

		$items = [];
		foreach ( $preview as $table => $count ) {
			$items[] = [
				'Entity' => str_replace( 'storeengine_', '', $table ),
				'Rows'   => $count,
			];
		}
		WP_CLI\Utils\format_items( 'table', $items, [ 'Entity', 'Rows' ] );

		$total = array_sum( $preview );

		if ( $dry_run ) {
			WP_CLI::success( sprintf( 'Dry run: %d record(s) would move from user #%d to user #%d.', $total, $from, $to ) );

			return;
		}

		if ( 0 === $total ) {
			WP_CLI::warning( 'Nothing to move — the source user owns no StoreEngine records.' );

			return;
		}

		WP_CLI::confirm( sprintf( 'Move %d record(s) from user #%d to user #%d?', $total, $from, $to ), $assoc_args );

		$result = $mover->move();

		if ( is_wp_error( $result ) ) {
			WP_CLI::error( $result->get_error_message() );
		}

		WP_CLI::success( sprintf( 'Moved %d record(s) from user #%d to user #%d.', array_sum( $result['moved'] ), $from, $to ) );
	}
}
