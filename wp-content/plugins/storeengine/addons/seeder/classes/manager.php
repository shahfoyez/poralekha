<?php
/**
 * Seeder manager — registry + runner.
 *
 * Collects providers (via the `storeengine/seeder/providers` filter), runs them
 * in dependency order, persists a manifest of everything created, and can
 * delete it all again. Driven by the CLI command and the admin page.
 *
 * @package StoreEngine\Addons\Seeder\Classes
 */

namespace StoreEngine\Addons\Seeder\Classes;

use StoreEngine\Classes\Price;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Manager {

	/**
	 * Option holding the manifest of seeded object ids (for cleanup).
	 */
	const MANIFEST_OPTION = 'storeengine_seeder_manifest';

	/**
	 * @var array<string, AbstractSeederProvider>
	 */
	private array $providers = [];

	private bool $loaded = false;

	private static ?Manager $instance = null;

	public static function get_instance(): Manager {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Build the provider registry once. Providers are contributed entirely
	 * through the filter — core + free providers by the seeder addon, Pro
	 * providers by the seeder-pro addon.
	 *
	 * @return array<string, AbstractSeederProvider>
	 */
	public function load_providers(): array {
		if ( $this->loaded ) {
			return $this->providers;
		}

		/**
		 * Register dummy-data seeder providers.
		 *
		 * @param AbstractSeederProvider[] $providers
		 */
		$providers = apply_filters( 'storeengine/seeder/providers', [] );

		foreach ( $providers as $provider ) {
			if ( $provider instanceof AbstractSeederProvider ) {
				$this->providers[ $provider->get_key() ] = $provider;
			}
		}

		$this->loaded = true;

		return $this->providers;
	}

	/**
	 * @return array<string, AbstractSeederProvider>
	 */
	public function get_providers(): array {
		return $this->load_providers();
	}

	public function get_provider( string $key ): ?AbstractSeederProvider {
		$this->load_providers();

		return $this->providers[ $key ] ?? null;
	}

	/**
	 * Keys of the providers ticked by default — the set a bare run seeds.
	 *
	 * @return string[]
	 */
	public function default_selected_keys(): array {
		$keys = [];
		foreach ( $this->load_providers() as $key => $provider ) {
			if ( $provider->is_default_selected() ) {
				$keys[] = $key;
			}
		}

		return $keys;
	}

	/**
	 * Resolve providers into dependency order (depth-first topological sort).
	 *
	 * @param string[] $keys Subset to include; empty means all providers.
	 *
	 * @return AbstractSeederProvider[]
	 */
	public function resolve_order( array $keys = [] ): array {
		$all = $this->load_providers();

		if ( empty( $keys ) ) {
			$keys = array_keys( $all );
		}

		$ordered  = [];
		$visiting = [];

		$visit = function ( string $key ) use ( &$visit, &$ordered, &$visiting, $all ) {
			if ( isset( $ordered[ $key ] ) || ! isset( $all[ $key ] ) || isset( $visiting[ $key ] ) ) {
				// Already added, unknown, or a dependency cycle — skip.
				return;
			}

			$visiting[ $key ] = true;
			foreach ( $all[ $key ]->get_dependencies() as $dependency ) {
				$visit( $dependency );
			}
			unset( $visiting[ $key ] );

			$ordered[ $key ] = $all[ $key ];
		};

		foreach ( $keys as $key ) {
			$visit( $key );
		}

		return array_values( $ordered );
	}

	/**
	 * Run a seed.
	 *
	 * @param array{only?:string[],counts?:array<string,int>,default_count?:?int,logger?:?callable,args?:array} $opts
	 *
	 * @return SeederContext
	 */
	public function run( array $opts = [] ): SeederContext {
		$this->load_providers();

		$only    = $opts['only'] ?? [];
		$counts  = $opts['counts'] ?? [];
		$default = $opts['default_count'] ?? null;
		$logger  = $opts['logger'] ?? null;

		// A bare run (no explicit selection) seeds only the default-selected
		// sets; opt-in sets are skipped unless named here or pulled in as a
		// dependency. An explicit selection is always honoured verbatim.
		if ( empty( $only ) ) {
			$only = $this->default_selected_keys();
		}

		$context   = new SeederContext( $opts['args'] ?? [] );
		$providers = $this->resolve_order( $only );

		foreach ( $providers as $provider ) {
			$key   = $provider->get_key();
			$count = $counts[ $key ] ?? ( null !== $default ? $default : $provider->get_default_count() );
			$count = max( 0, (int) $count );

			$context->set_provider( $key );

			if ( is_callable( $logger ) ) {
				$logger( 'before', $provider, $count );
			}

			try {
				$provider->seed( $context, $count );
			} catch ( \Throwable $e ) {
				if ( is_callable( $logger ) ) {
					$logger( 'error', $provider, $e->getMessage() );
				}
			}

			if ( is_callable( $logger ) ) {
				$logger( 'after', $provider, $count );
			}
		}

		// Append to any existing manifest so successive runs all stay cleanable.
		$manifest = $this->get_manifest();
		$manifest = array_merge( $manifest, $context->get_records() );
		update_option( self::MANIFEST_OPTION, $manifest, false );

		return $context;
	}

	/**
	 * Delete everything recorded in the manifest.
	 *
	 * @param callable|null $logger Receives (string $type, int $id, bool $ok).
	 *
	 * @return array<string,int> Count of deleted objects by type.
	 */
	public function reset( ?callable $logger = null ): array {
		$manifest = $this->get_manifest();
		if ( empty( $manifest ) ) {
			return [];
		}

		$deleted = [];

		// Reverse the manifest so dependents (orders) are removed before their
		// dependencies (customers/products).
		foreach ( array_reverse( $manifest ) as $record ) {
			$type = $record['type'] ?? '';
			$id   = (int) ( $record['id'] ?? 0 );
			if ( ! $type || ! $id ) {
				continue;
			}

			// Never let one bad record abort the whole cleanup. A manifest can
			// outlive the rows it points at (a price cascaded away with its
			// product, a hand-deleted row), and some entity constructors throw
			// rather than return empty when their row is missing — swallow that
			// so every other object still gets removed and the manifest cleared.
			try {
				$ok = $this->delete_object( $type, $id );
			} catch ( \Throwable $e ) {
				$ok = false;
			}
			if ( $ok ) {
				$deleted[ $type ] = ( $deleted[ $type ] ?? 0 ) + 1;
			}

			if ( is_callable( $logger ) ) {
				$logger( $type, $id, $ok );
			}
		}

		delete_option( self::MANIFEST_OPTION );

		return $deleted;
	}

	/**
	 * Central deletion dispatcher keyed by object type, so providers only have
	 * to record `{ type, id }` and the framework knows how to remove it.
	 */
	public function delete_object( string $type, int $id ): bool {
		switch ( $type ) {
			case 'customer':
				if ( ! function_exists( 'wp_delete_user' ) ) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
				}

				return (bool) wp_delete_user( $id );

			case 'product':
			case 'coupon':
				return (bool) wp_delete_post( $id, true );

			case 'price':
				$price = new Price( $id );

				return $price->get_id() ? $price->delete( true ) : false;

			case 'order':
			case 'subscription':
			case 'refund':
				$order = Helper::get_order( $id );
				if ( ! $order || is_wp_error( $order ) ) {
					return false;
				}

				// Order::delete() removes the order row but NOT its line items or
				// their meta, so drop those first to avoid orphaning them.
				if ( method_exists( $order, 'delete_items' ) ) {
					$order->delete_items();
				}

				return (bool) $order->delete( true );

			default:
				/**
				 * Delete a custom seeded object type registered by an addon.
				 *
				 * Return true once handled.
				 *
				 * @param bool   $handled
				 * @param string $type
				 * @param int    $id
				 */
				return (bool) apply_filters( 'storeengine/seeder/delete_object', false, $type, $id );
		}
	}

	/**
	 * @return array<int, array{provider:string,type:string,id:int}>
	 */
	public function get_manifest(): array {
		$manifest = get_option( self::MANIFEST_OPTION, [] );

		return is_array( $manifest ) ? $manifest : [];
	}

	public function has_manifest(): bool {
		return ! empty( $this->get_manifest() );
	}
}
