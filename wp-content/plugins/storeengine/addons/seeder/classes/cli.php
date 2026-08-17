<?php
/**
 * `wp storeengine seed` — generate / remove dummy data for quick local testing.
 *
 * Registered by the seeder addon on `cli_init`, so the command only exists when
 * the addon is active.
 *
 * @package StoreEngine\Addons\Seeder\Classes
 */

namespace StoreEngine\Addons\Seeder\Classes;

use WP_CLI;
use WP_CLI_Command;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Cli extends WP_CLI_Command {

	/**
	 * Generate dummy data across registered providers (customers, products,
	 * coupons, orders, and anything addons register).
	 *
	 * ## OPTIONS
	 *
	 * [--count=<n>]
	 * : Default number of records per provider. Each provider has its own
	 * sensible default if omitted.
	 *
	 * [--only=<keys>]
	 * : Comma-separated provider keys to run (dependencies are pulled in
	 * automatically). Example: --only=products,orders
	 *
	 * [--<field>=<value>]
	 * : Override the count for a single provider by its key.
	 * Example: --customers=50 --orders=200
	 *
	 * [--force]
	 * : Allow running on a production environment (refused by default).
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeengine seed run
	 *     wp storeengine seed run --customers=50 --orders=200
	 *     wp storeengine seed run --only=products,orders --count=20
	 *
	 * @subcommand run
	 */
	public function run( $args, $assoc_args ) {
		$manager = Manager::get_instance();

		if ( 'production' === wp_get_environment_type() && empty( $assoc_args['force'] ) ) {
			WP_CLI::error( 'Refusing to seed dummy data on a production environment. Re-run with --force if you really mean it.' );
		}

		$only = [];
		if ( ! empty( $assoc_args['only'] ) ) {
			$only = array_filter( array_map( 'trim', explode( ',', $assoc_args['only'] ) ) );
		}

		$default = isset( $assoc_args['count'] ) ? (int) $assoc_args['count'] : null;

		// Per-provider count overrides (e.g. --orders=200).
		$counts = [];
		foreach ( $manager->get_providers() as $key => $provider ) {
			if ( isset( $assoc_args[ $key ] ) && is_numeric( $assoc_args[ $key ] ) ) {
				$counts[ $key ] = (int) $assoc_args[ $key ];
			}
		}

		$logger = function ( $phase, $provider, $info ) {
			if ( 'before' === $phase ) {
				WP_CLI::log( sprintf( '  → %s (%d)…', $provider->get_label(), $info ) );
			} elseif ( 'error' === $phase ) {
				WP_CLI::warning( sprintf( '%s provider error: %s', $provider->get_label(), $info ) );
			}
		};

		$context = $manager->run( [
			'only'          => $only,
			'counts'        => $counts,
			'default_count' => $default,
			'logger'        => $logger,
		] );

		$summary = [];
		foreach ( $context->get_records() as $record ) {
			$summary[ $record['type'] ] = ( $summary[ $record['type'] ] ?? 0 ) + 1;
		}

		if ( empty( $summary ) ) {
			WP_CLI::warning( 'No records were created.' );

			return;
		}

		foreach ( $summary as $type => $n ) {
			WP_CLI::log( sprintf( '    • %d %s', $n, $type ) );
		}

		WP_CLI::success( sprintf(
			'Seeded %d object(s). Remove them anytime with `wp storeengine seed reset`.',
			array_sum( $summary )
		) );
	}

	/**
	 * Delete every object created by previous seed runs (tracked in a manifest).
	 *
	 * ## OPTIONS
	 *
	 * [--force]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeengine seed reset
	 *     wp storeengine seed reset --force
	 *
	 * @subcommand reset
	 */
	public function reset( $args, $assoc_args ) {
		$manager = Manager::get_instance();

		if ( ! $manager->has_manifest() ) {
			WP_CLI::warning( 'Nothing to reset — no seeded data is on record.' );

			return;
		}

		$total = count( $manager->get_manifest() );

		if ( empty( $assoc_args['force'] ) ) {
			WP_CLI::confirm( sprintf( 'Delete all %d previously seeded object(s)?', $total ) );
		}

		$deleted = $manager->reset();

		foreach ( $deleted as $type => $n ) {
			WP_CLI::log( sprintf( '    • removed %d %s', $n, $type ) );
		}

		WP_CLI::success( sprintf( 'Removed %d seeded object(s).', array_sum( $deleted ) ) );
	}

	/**
	 * List the registered seeder providers in dependency order.
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeengine seed list
	 *
	 * @subcommand list
	 */
	public function list_providers( $args, $assoc_args ) {
		$manager = Manager::get_instance();

		$items = [];
		foreach ( $manager->resolve_order() as $provider ) {
			$deps    = $provider->get_dependencies();
			$items[] = [
				'Key'           => $provider->get_key(),
				'Label'         => $provider->get_label(),
				'Default count' => $provider->get_default_count(),
				'Depends on'    => $deps ? implode( ', ', $deps ) : '—',
			];
		}

		if ( empty( $items ) ) {
			WP_CLI::warning( 'No seeder providers registered.' );

			return;
		}

		WP_CLI\Utils\format_items( 'table', $items, [ 'Key', 'Label', 'Default count', 'Depends on' ] );
	}
}
