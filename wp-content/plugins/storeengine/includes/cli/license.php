<?php

namespace StoreEngine\Cli;

use StoreEnginePro\Addons\LicenseManagement\Classes\License as LicenseEntity;
use StoreEnginePro\Addons\LicenseManagement\Classes\LicenseCollection;
use StoreEnginePro\Addons\LicenseManagement\Classes\Utils;
use WP_CLI;
use WP_CLI_Command;
use function WP_CLI\Utils\make_progress_bar;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class License extends WP_CLI_Command {

	/**
	 * List licenses.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : License status to filter by (e.g. active, inactive, expired, revoked).
	 *
	 * [--limit=<limit>]
	 * : Maximum number of licenses to list. Defaults to 10. Set to -1 for all.
	 * ---
	 * default: 10
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeengine license list --status=completed --limit=50
	 *
	 * @subcommand list
	 */
	public function list( $args, $assoc_args ) {
		$status = $assoc_args['status'] ?? '';
		$limit  = $assoc_args['limit'] ?? 10;

		$query_args = [
			'per_page' => $limit,
			'orderby'  => 'id',
			'order'    => 'DESC',
		];

		if ( $status ) {
			$query_args['where'][] = [
				'key'   => 'status',
				'value' => $status,
			];
		}


		$query = new LicenseCollection( $query_args );

		if ( ! $query->have_results() ) {
			WP_CLI::warning( 'No licenses found.' );

			return;
		}

		$items = [];
		foreach ( $query->get_results() as $license ) {
			$items[] = [
				'ID'          => $license->get_id(),
				'Key'         => $license->get_license_key(),
				'Status'      => $license->get_status(),
				'Customer'    => $license->get_customer_name() . ' [#' . $license->get_customer_id() . ']',
				'Activations' => $license->get_activation_count() . '/' . $license->get_activation_limit(),
				'Expire'      => $license->get_expires_at() ? $license->get_expires_at()->format( 'Y-m-d H:i:s' ) : '♾️',
				'Updated'     => $license->get_updated_at() ? $license->get_updated_at()->format( 'Y-m-d H:i:s' ) : '',
				'Created'     => $license->get_created_at() ? $license->get_created_at()->format( 'Y-m-d H:i:s' ) : '',
			];
		}

		WP_CLI\Utils\format_items( 'table', $items, [
			'ID',
			'Key',
			'Status',
			'Customer',
			'Activations',
			'Expire',
			'Updated',
			'Created',
		] );
	}

	/**
	 * Sync activations for licenses.
	 *
	 * ## OPTIONS
	 *
	 * [--id=<id>]
	 *  : Specific license ID to delete. Set to "all" to delete all licenses.
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeengine license sync --id=50
	 *     wp storeengine license sync --id=all
	 *
	 * @subcommand sync-activations
	 */
	public function sync_activations( $args, $assoc_args ) {
		$id         = $assoc_args['id'] ?? null;

		if ( ! $id ) {
			WP_CLI::error( 'ID is required. E.g. --id=<id>' );
		}

		if ( $id && 'all' !== $id ) {
			$query = new LicenseCollection( [ 'id' => $id ] );
			if ( ! $query->have_results() ) {
				WP_CLI::error( "License $id not found." );
			}
		} else {
			$query_args = [ 'per_page' => - 1 ];

			$query = new LicenseCollection( $query_args );

			if ( ! $query->have_results() ) {
				WP_CLI::error( "No licenses found." );
			}
		}

		$progress = make_progress_bar( 'Synchronizing license activations', $query->get_found_results() );

		$count = 0;

		foreach ( $query->get_results() as $license ) {
			try {
				if ( $license->sync_activation_count() ) {
					$count ++;
				} else {
					WP_CLI::warning( "Failed to sync license {$license->get_id()}." );
				}
			} catch ( \Throwable $e ) {
				WP_CLI::warning( "Failed to sync license {$license->get_id()}. Error: {$e->getMessage()}" );
			}

			$progress->tick();
		}

		$progress->finish();

		WP_CLI::success( "Successfully synced $count license(s)." );
	}

	/**
	 * Delete licenses.
	 *
	 * ## OPTIONS
	 *
	 * [--id=<id>]
	 * : Specific license ID to delete. Set to "all" to delete all licenses.
	 *
	 * [--status=<status>]
	 * : Delete all licenses matching a specific status.
	 *
	 * [--force]
	 * : Skip confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeengine license delete --id=123
	 *     wp storeengine license delete --status=inactive --force
	 *
	 * @subcommand delete
	 */
	public function delete( $args, $assoc_args ) {
		$id         = $assoc_args['id'] ?? null;
		$status     = $assoc_args['status'] ?? null;
		$force      = $assoc_args['force'] ?? false;
		$skip_trash = $assoc_args['skip-trash'] ?? false;

		if ( ! $id && ! $status ) {
			WP_CLI::error( 'Please provide either --id=<id>, --id=all, or --status=<status>' );
		}

		if ( $id && 'all' !== $id ) {
			$query = new LicenseCollection( [ 'id' => $id ] );
			if ( ! $query->have_results() ) {
				WP_CLI::error( "License $id not found." );
			}
		} else {
			$query_args = [ 'per_page' => - 1 ];

			if ( $status ) {
				$query_args['where'][] = [
					'key'   => 'status',
					'value' => $status,
				];
			}

			$query = new LicenseCollection( $query_args );

			if ( ! $query->have_results() ) {
				$msg = $status ? "No licenses found with status '$status'." : "No licenses found.";
				WP_CLI::success( $msg );

				return;
			}
		}

		if ( ! $force ) {
			WP_CLI::confirm( sprintf( "Are you sure you want to delete %s license(s)?", $query->get_found_results() ) );
		}

		$progress = make_progress_bar( 'Deleting licenses', $query->get_found_results() );

		$deleted_count = 0;

		foreach ( $query->get_results() as $license ) {
			try {
				if ( $license->delete( $skip_trash ) ) {
					$deleted_count ++;
				} else {
					WP_CLI::warning( "Failed to delete license {$license->get_id()}." );
				}
			} catch ( \Throwable $e ) {
				WP_CLI::warning( "Failed to delete license {$license->get_id()}. Error: {$e->getMessage()}" );
			}

			$progress->tick();
		}

		$progress->finish();

		WP_CLI::success( "Successfully deleted $deleted_count license(s)." );
	}

	/**
	 * Detect and repair license keys that fail client activation.
	 *
	 * A key containing characters the client SDK strips on submit — notably "%"
	 * followed by two hex digits — can never match what was issued, so activation
	 * returns "Invalid license". This scans for such keys and reissues each one
	 * with a fresh, URL-safe key. Keys that are already valid are left untouched,
	 * so working activations are never disturbed.
	 *
	 * ## OPTIONS
	 *
	 * [--id=<id>]
	 * : Only check a single license ID.
	 *
	 * [--dry-run]
	 * : Detect and report affected licenses without changing anything.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     # Detect only — list keys that would fail activation.
	 *     wp storeengine license heal-keys --dry-run
	 *
	 *     # Detect and regenerate every affected key.
	 *     wp storeengine license heal-keys
	 *
	 *     # Repair a single license non-interactively.
	 *     wp storeengine license heal-keys --id=3065 --yes
	 *
	 * @subcommand heal-keys
	 */
	public function heal_keys( $args, $assoc_args ) {
		$id      = $assoc_args['id'] ?? null;
		$dry_run = isset( $assoc_args['dry-run'] );
		$yes     = isset( $assoc_args['yes'] );

		if ( $id ) {
			$query = new LicenseCollection( [ 'id' => $id ] );
			if ( ! $query->have_results() ) {
				WP_CLI::error( "License $id not found." );
			}
		} else {
			$query = new LicenseCollection( [ 'per_page' => - 1 ] );
			if ( ! $query->have_results() ) {
				WP_CLI::error( 'No licenses found.' );
			}
		}

		// 1) Detect.
		$unsafe = [];
		foreach ( $query->get_results() as $license ) {
			if ( ! Utils::is_license_key_safe( $license->get_license_key( 'edit' ) ) ) {
				$unsafe[] = $license;
			}
		}

		if ( ! $unsafe ) {
			WP_CLI::success( 'All license keys are valid — nothing to repair.' );

			return;
		}

		WP_CLI::log( sprintf( 'Found %d license(s) with an unsafe key:', count( $unsafe ) ) );
		WP_CLI\Utils\format_items( 'table', array_map( static function ( $license ) {
			return [
				'ID'       => $license->get_id(),
				'Key'      => $license->get_license_key(),
				'Status'   => $license->get_status(),
				'Customer' => $license->get_customer_name() . ' [#' . $license->get_customer_id() . ']',
			];
		}, $unsafe ), [ 'ID', 'Key', 'Status', 'Customer' ] );

		if ( $dry_run ) {
			WP_CLI::log( 'Dry run — no keys were changed. Re-run without --dry-run to repair.' );

			return;
		}

		if ( ! $yes ) {
			WP_CLI::confirm( sprintf( 'Regenerate %d license key(s)? The old keys will stop working.', count( $unsafe ) ) );
		}

		// 2) Regenerate.
		$progress = make_progress_bar( 'Regenerating license keys', count( $unsafe ) );
		$healed   = [];

		foreach ( $unsafe as $license ) {
			try {
				$change = $license->heal_unsafe_key();
				if ( $change ) {
					$healed[] = [
						'ID'  => $license->get_id(),
						'Old' => $change['old'],
						'New' => $change['new'],
					];
				}
			} catch ( \Throwable $e ) {
				WP_CLI::warning( "Failed to regenerate license {$license->get_id()}. Error: {$e->getMessage()}" );
			}

			$progress->tick();
		}

		$progress->finish();

		if ( $healed ) {
			WP_CLI\Utils\format_items( 'table', $healed, [ 'ID', 'Old', 'New' ] );
		}

		WP_CLI::success( sprintf( 'Regenerated %d license key(s).', count( $healed ) ) );
	}

	/**
	 * Force-regenerate the key for specific license(s).
	 *
	 * Unlike `heal-keys` (which only touches keys that would fail activation),
	 * this reissues the key for the given license regardless of its current
	 * value — e.g. to hand a customer a fresh key on request. The OLD key stops
	 * working immediately, so the new key must be shared.
	 *
	 * ## OPTIONS
	 *
	 * --id=<id>
	 * : License ID to regenerate. Accepts a comma-separated list of IDs.
	 *
	 * [--yes]
	 * : Skip the confirmation prompt.
	 *
	 * ## EXAMPLES
	 *
	 *     wp storeengine license regenerate --id=3065
	 *     wp storeengine license regenerate --id=3065,3066 --yes
	 *
	 * @subcommand regenerate
	 */
	public function regenerate( $args, $assoc_args ) {
		$id  = $assoc_args['id'] ?? null;
		$yes = isset( $assoc_args['yes'] );

		if ( ! $id ) {
			WP_CLI::error( 'License ID is required. E.g. --id=3065' );
		}

		$ids = array_values( array_unique( array_filter( array_map( 'absint', explode( ',', (string) $id ) ) ) ) );

		if ( ! $ids ) {
			WP_CLI::error( 'No valid license ID provided.' );
		}

		if ( ! $yes ) {
			WP_CLI::confirm( sprintf( 'Regenerate %d license key(s)? The old key(s) will stop working.', count( $ids ) ) );
		}

		$rows = [];

		foreach ( $ids as $license_id ) {
			try {
				$license = new LicenseEntity( $license_id );

				if ( ! $license->get_id() ) {
					WP_CLI::warning( "License $license_id not found." );
					continue;
				}

				$old = $license->get_license_key( 'edit' );
				$new = $license->regenerate_license_key();
				$license->save();

				$rows[] = [
					'ID'  => $license_id,
					'Old' => $old,
					'New' => $new,
				];
			} catch ( \Throwable $e ) {
				WP_CLI::warning( "Failed to regenerate license $license_id. Error: {$e->getMessage()}" );
			}
		}

		if ( $rows ) {
			WP_CLI\Utils\format_items( 'table', $rows, [ 'ID', 'Old', 'New' ] );
		}

		WP_CLI::success( sprintf( 'Regenerated %d license key(s).', count( $rows ) ) );
	}
}
