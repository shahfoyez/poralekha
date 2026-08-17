<?php
/**
 * Seeds managed stock + opening stock movements on the seeded products, for
 * the Inventory addon. Only registered while the inventory addon is active
 * (see the seeder addon's register_providers()); the matching `stock_movement`
 * cleanup handler lives on the seeder addon's delete_object().
 *
 * @package StoreEngine\Addons\Seeder\Providers
 */

namespace StoreEngine\Addons\Seeder\Providers;

use StoreEngine\Addons\Seeder\Classes\AbstractSeederProvider;
use StoreEngine\Addons\Seeder\Classes\SeederContext;
use StoreEngine\Classes\Product\SimpleProduct;
use StoreEngine\Classes\StockManager;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class InventoryProvider extends AbstractSeederProvider {

	public function get_key(): string {
		return 'inventory_stock';
	}

	public function get_label(): string {
		return 'Inventory stock';
	}

	public function get_dependencies(): array {
		return [ 'products' ];
	}

	/**
	 * Count is ignored — this enhances every seeded product rather than
	 * creating a fixed number of new rows.
	 */
	public function get_default_count(): int {
		return 0;
	}

	public function seed( SeederContext $context, int $count ): void {
		$product_ids = $context->ids( 'products', 'product' );

		foreach ( $product_ids as $product_id ) {
			$product = new SimpleProduct( (int) $product_id );
			if ( ! $product->get_id() ) {
				continue;
			}

			$qty = wp_rand( 0, 250 );

			$product->update_metadata( '_storeengine_manage_stock', true );
			$product->set_stock_quantity( $qty );
			$product->set_stock_status( $qty > 0 ? 'instock' : 'outofstock' );
			$product->save();

			$movement_id = StockManager::record_movement( [
				'product_id' => (int) $product_id,
				'type'       => StockManager::TYPE_RECEIVED,
				'reason'     => 'initial_stock',
				'qty_change' => $qty,
				'qty_before' => 0,
				'qty_after'  => $qty,
				'note'       => 'Opening stock (seeded)',
				'user_id'    => get_current_user_id() ?: null,
			] );

			if ( $movement_id ) {
				$context->record( 'stock_movement', (int) $movement_id );
			}
		}
	}
}
