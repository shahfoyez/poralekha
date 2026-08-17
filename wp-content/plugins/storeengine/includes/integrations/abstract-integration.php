<?php
/**
 * Abstract integration.
 *
 * @version 2.0
 * @since StoreEngine v1.0.0
 */

namespace StoreEngine\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\AbstractOrder;
use StoreEngine\Classes\Data\IntegrationRepositoryData;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Integration;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Interfaces\IntegrationInterface;
use StoreEngine\Utils\Helper;
use StoreEnginePro\Addons\InstallmentPlan\Classes\InstallmentPlan;
use Throwable;

abstract class AbstractIntegration implements IntegrationInterface {
	/**
	 * Integration instances.
	 *
	 * @var IntegrationInterface[]
	 */
	protected static array $integrations = [];

	/**
	 * Integration type id.
	 * @var string
	 */
	protected string $id;

	/**
	 * Integration display name.
	 * @var string
	 */
	protected string $label;

	/**
	 * Integration items label.
	 * @var string
	 */
	protected string $items_label;

	/**
	 * Integration logo/icon.
	 * @var string
	 */
	protected string $logo;

	/**
	 * Whether the integration is enabled (with all the dependencies).
	 * @var bool
	 */
	protected bool $isEnabled = false;

	public function __construct() {
		if ( ! empty( self::$integrations[ static::ID ] ) ) {
			_doing_it_wrong(
				__METHOD__,
				sprintf(
				// translators: %1$s. The integration runner class name, %2$s. Integration type (e.g. storeengine/academylms, storeengine/membership, etc.).
					esc_html__( 'Direct instantiation of integration runners is not allowed. Attempted to create an instance of %1$s. Please retrieve the integration using AbstractIntegration::get_integration( %2$s ) instead.', 'storeengine' ),
					esc_html( get_called_class() ),
					esc_html( static::ID )
				),
				'1.8.0'
			);

			// Already booted.
			return;
		}

		self::$integrations[ static::ID ] = $this;

		$this->setup();
		$this->dispatch_common_hooks();
	}

	/**
	 * Get integration class instance by type.
	 * Access integration singleton class instance.
	 *
	 * @param string $id
	 *
	 * @return ?IntegrationInterface
	 */
	public static function get_integration( string $id ): ?IntegrationInterface {
		return self::$integrations[ $id ] ?? null;
	}

	/**
	 * Sets-up class props before dispatch.
	 * @return void
	 */
	abstract public function setup(): void;

	/**
	 * Dispatch Hooks.
	 *
	 * @return void
	 */
	abstract public function dispatch_hooks(): void;

	/**
	 * Integration type id.
	 *
	 * @return string
	 */
	public function get_id(): string {
		return $this->id;
	}

	/**
	 * Display name/label.
	 *
	 * @return string
	 */
	public function get_label(): string {
		return $this->label;
	}

	/**
	 * Logo/icon.
	 *
	 * @return string
	 */
	public function get_logo(): string {
		return $this->logo;
	}

	/**
	 * Is enabled?.
	 *
	 * @return bool
	 */
	public function enabled(): bool {
		return $this->isEnabled;
	}

	/**
	 * Items label.
	 *
	 * @return string
	 */
	public function get_items_label(): string {
		return $this->items_label;
	}

	/**
	 * Search integration items.
	 *
	 * @param array $args Search params.
	 *
	 * @return array<object{value:string, label:string}> Search results.
	 */
	abstract public function get_items( array $args = [] ): array;

	/**
	 * Triggers a purchase event for integration.
	 *
	 * @param Integration $integration
	 * @param AbstractOrder $order
	 *
	 * @return void
	 */
	abstract protected function purchase_created( Integration $integration, AbstractOrder $order );

	/**
	 * Triggers a return/refund/void-sale event for integration.
	 *
	 * @param Integration $integration
	 * @param AbstractOrder $order
	 *
	 * @return void
	 */
	abstract protected function purchase_removed( Integration $integration, AbstractOrder $order );

	/**
	 * Common hooks.
	 *
	 * Triggers purchase & refund/refound related events from order status events.
	 *
	 * @return void
	 */
	protected function dispatch_common_hooks(): void {
		if ( ! $this->enabled() ) {
			return;
		}

		add_action( 'storeengine/order/payment_status_changed', [ $this, 'order_payment_status_changed' ], 10, 2 );
		add_action( 'storeengine/subscription/status_updated', [ $this, 'subscription_status_updated' ], 10, 3 );
		add_action( 'storeengine_pro/installment-plan/status_updated', [ $this, 'installment_status_updated' ], 10, 3 );

		$this->dispatch_hooks();
	}

	/**
	 * Checks if the integration should trigger its events.
	 *
	 * Third-party plugin/addon request not to trigger certain event and handle themselves.
	 *
	 * @param OrderItemProduct $order_item
	 *
	 * @return bool
	 */
	protected function should_run( OrderItemProduct $order_item ): bool {
		$should_run = true;

		if ( has_filter( 'storeengine/integrations/run_integration_outside' ) ) {
			$should_run = ! apply_filters_deprecated(
				'storeengine/integrations/run_integration_outside',
				[ false, null, $order_item ],
				'1.8.0',
				'storeengine/integrations/maybe_run_integration'
			);
		}

		/**
		 * Flag whether integration needs to be running.
		 */
		return (bool) apply_filters( 'storeengine/integrations/maybe_run_integration', $should_run, $order_item, $this );
	}

	/**
	 * Order payment status handler.
	 *
	 * @param Order $order
	 * @param string $status
	 *
	 * @return void
	 */
	public function order_payment_status_changed( Order $order, string $status ) {
		foreach ( $order->get_line_product_items() as $order_item ) {
			if ( ! $this->should_run( $order_item ) || 'onetime' !== $order_item->get_price_type() ) {
				continue;
			}

			if ( 'paid' === $status ) {
				$this->run_integration( $order_item, $order );
				continue;
			}

			$this->remove_integration( $order_item, $order );
		}
	}

	/**
	 * Subscription active status handler.
	 *
	 * @param Subscription $subscription
	 * @param string $new_status
	 *
	 * @return void
	 */
	public function subscription_status_updated( Subscription $subscription, string $new_status ) {
		foreach ( $subscription->get_line_product_items() as $order_item ) {
			if ( ! $this->should_run( $order_item ) ) {
				continue;
			}

			if ( 'active' === $new_status ) {
				$this->run_integration( $order_item, $subscription );
				continue;
			}

			$this->remove_integration( $order_item, $subscription );
		}
	}

	/**
	 * Installment plan status handler.
	 *
	 * @param InstallmentPlan $installment_plan
	 * @param string $new_status
	 *
	 * @return void
	 */
	public function installment_status_updated( InstallmentPlan $installment_plan, string $new_status ) {
		foreach ( $installment_plan->get_line_product_items() as $order_item ) {
			if ( ! $this->should_run( $order_item ) ) {
				continue;
			}

			if ( in_array( $new_status, [ 'active', 'completed' ], true ) ) {
				$this->run_integration( $order_item, $installment_plan );
				continue;
			}

			$this->remove_integration( $order_item, $installment_plan );
		}
	}

	/**
	 * Triggers purchase event for order item.
	 *
	 * @param OrderItemProduct $order_item
	 * @param AbstractOrder $order
	 *
	 * @return void
	 */
	public function run_integration( OrderItemProduct $order_item, AbstractOrder $order ): void {
		try {
			if ( 'bundled' === $order_item->get_product_type() ) {
				$bundles = $order_item->get_meta( '_bundles' );
				if ( ! $bundles || ! is_array( $bundles ) ) {
					return;
				}

				foreach ( $bundles as ['price_id' => $price_id] ) {
					$this->_run_integration( $price_id, $order );
				}

				return;
			}

			$this->_run_integration( $order_item->get_price_id(), $order );
		} catch ( Throwable $e ) {
			Helper::log_error( $e );
		}
	}

	/**
	 * Triggers return/refund/void-salse event for order item.
	 *
	 * @param OrderItemProduct $order_item
	 * @param AbstractOrder $order
	 *
	 * @return void
	 */
	public function remove_integration( OrderItemProduct $order_item, AbstractOrder $order ): void {
		try {
			if ( 'bundled' === $order_item->get_product_type() ) {
				$bundles = $order_item->get_meta( '_bundles' );
				if ( ! $bundles || ! is_array( $bundles ) ) {
					return;
				}

				foreach ( $bundles as ['price_id' => $price_id] ) {
					$this->_remove_integration( $price_id, $order );
				}

				return;
			}

			$this->_remove_integration( $order_item->get_price_id(), $order );
		} catch ( Throwable $e ) {
			Helper::log_error( $e );
		}
	}

	/**
	 * Triggers purchase event by price-id for order.
	 *
	 * @param int $price_id
	 * @param AbstractOrder $order
	 *
	 * @return void
	 * @throws StoreEngineException
	 */
	protected function _run_integration( int $price_id, AbstractOrder $order ): void {
		$integrations = Helper::get_integrations_by_price_id( $price_id );

		foreach ( $integrations as $integration ) {
			if ( $integration->get_provider() !== $this->get_id() ) {
				continue;
			}

			$this->purchase_created( $integration, $order );
		}
	}

	/**
	 * Triggers return/refund/void-salse event by price-id for order.
	 *
	 * @param int $price_id
	 * @param AbstractOrder $order
	 *
	 * @return void
	 * @throws StoreEngineException
	 */
	protected function _remove_integration( int $price_id, AbstractOrder $order ): void {
		$integrations = Helper::get_integrations_by_price_id( $price_id );

		if ( ! $integrations ) {
			return;
		}

		foreach ( $integrations as $integration ) {
			if ( $integration->get_provider() !== $this->get_id() ) {
				continue;
			}
			$this->purchase_removed( $integration, $order );
		}
	}

	/**
	 * Find integration objects by integration item (foreign) id.
	 * @param $integration_id
	 *
	 * @return array<IntegrationRepositoryData>
	 */
	public function get_integration_repository( $integration_id ): array {
		return Helper::get_integration_repository_by_id( $this->get_id(), $integration_id );
	}
}
