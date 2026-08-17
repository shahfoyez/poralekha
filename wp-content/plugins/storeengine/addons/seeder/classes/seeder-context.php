<?php
/**
 * Shared state passed through a single seeder run.
 *
 * Providers record what they create here (for cleanup + downstream lookup) and
 * read ids produced by their dependencies. There is one context per run.
 *
 * @package StoreEngine\Addons\Seeder\Classes
 */

namespace StoreEngine\Addons\Seeder\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SeederContext {

	/**
	 * Key of the provider currently running (set by the manager).
	 */
	private string $provider_key = '';

	/**
	 * Flat manifest of everything created this run.
	 *
	 * @var array<int, array{provider:string,type:string,id:int}>
	 */
	private array $records = [];

	/**
	 * Free-form run arguments (passed through from the CLI).
	 *
	 * @var array<string, mixed>
	 */
	private array $args;

	/**
	 * @param array<string, mixed> $args
	 */
	public function __construct( array $args = [] ) {
		$this->args = $args;
	}

	/**
	 * Set the active provider. Called by the manager before each provider runs.
	 *
	 * @internal
	 */
	public function set_provider( string $key ): void {
		$this->provider_key = $key;
	}

	/**
	 * Record a persisted object so it can be cleaned up and looked up.
	 *
	 * @param string $type Object type understood by the cleanup dispatcher
	 *                     (customer|product|price|order|subscription|coupon|refund
	 *                     or an addon type handled via the delete filter).
	 * @param int    $id   The object id.
	 */
	public function record( string $type, int $id ): void {
		if ( $id <= 0 ) {
			return;
		}

		$this->records[] = [
			'provider' => $this->provider_key,
			'type'     => $type,
			'id'       => $id,
		];
	}

	/**
	 * Ids created earlier this run.
	 *
	 * @param string      $provider Provider key to read from.
	 * @param string|null $type     Optional object-type filter.
	 *
	 * @return int[]
	 */
	public function ids( string $provider, ?string $type = null ): array {
		$ids = [];
		foreach ( $this->records as $record ) {
			if ( $record['provider'] !== $provider ) {
				continue;
			}
			if ( null !== $type && $record['type'] !== $type ) {
				continue;
			}
			$ids[] = $record['id'];
		}

		return $ids;
	}

	/**
	 * The full run manifest.
	 *
	 * @return array<int, array{provider:string,type:string,id:int}>
	 */
	public function get_records(): array {
		return $this->records;
	}

	/**
	 * Read a pass-through run argument.
	 *
	 * @param mixed $default
	 *
	 * @return mixed
	 */
	public function get_arg( string $key, $default = null ) {
		return $this->args[ $key ] ?? $default;
	}
}
