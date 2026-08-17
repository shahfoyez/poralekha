<?php
/**
 * Base class for dummy-data seeder providers.
 *
 * A provider knows how to create (and let the framework clean up) sample data
 * for ONE feature area. The seeder addon registers the core + free-addon
 * providers; the seeder-pro addon registers the Pro ones. See the addon README.
 *
 * @package StoreEngine\Addons\Seeder\Classes
 */

namespace StoreEngine\Addons\Seeder\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class AbstractSeederProvider {

	/**
	 * Unique, stable key for this provider (e.g. "customers", "orders").
	 *
	 * Used as the CLI selector (`--only=customers`), the per-provider count
	 * flag (`--customers=20`) and the bucket downstream providers read from.
	 */
	abstract public function get_key(): string;

	/**
	 * Human-readable label shown in CLI output.
	 */
	abstract public function get_label(): string;

	/**
	 * Keys of other providers that MUST run before this one.
	 *
	 * @return string[]
	 */
	public function get_dependencies(): array {
		return [];
	}

	/**
	 * How many records to create when no count is supplied on the CLI.
	 */
	public function get_default_count(): int {
		return 10;
	}

	/**
	 * Whether this data set is ticked by default in Tools → Dummy Data (and
	 * included in a bare `wp storeengine seed run` with no `--only`).
	 *
	 * Return false for feature-specific or heavy demos a user should opt into
	 * deliberately rather than get swept in by a "seed everything" pass. Such a
	 * set still seeds normally once its box is checked (or it's named on the
	 * CLI), and is still pulled in automatically when another selected set
	 * depends on it.
	 */
	public function is_default_selected(): bool {
		return true;
	}

	/**
	 * Create the dummy records.
	 *
	 * Implementations MUST:
	 *  - record every persisted object via {@see SeederContext::record()} so it
	 *    can be removed by `wp storeengine seed reset`;
	 *  - read upstream ids via {@see SeederContext::ids()} rather than querying;
	 *  - never fatal on a single bad record — wrap risky work in try/catch and
	 *    keep going (the manager surfaces failures to the caller).
	 *
	 * @param SeederContext $context Shared run context.
	 * @param int           $count   Number of records to create.
	 */
	abstract public function seed( SeederContext $context, int $count ): void;
}
