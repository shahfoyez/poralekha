<?php
/**
 * Seeds orders across a realistic spread of statuses, backdated over the last
 * few months, each with 1-3 line items drawn from the seeded products and
 * attributed to a seeded customer.
 *
 * @package StoreEngine\Addons\Seeder\Providers
 */

namespace StoreEngine\Addons\Seeder\Providers;

use StoreEngine\Addons\Seeder\Classes\AbstractSeederProvider;
use StoreEngine\Addons\Seeder\Classes\SeederContext;
use StoreEngine\Addons\Seeder\Classes\SeederData;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\OrderStatus\OrderStatus;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class OrderProvider extends AbstractSeederProvider {

	/**
	 * Weighted status pool — completed/processing dominate, like a real store.
	 *
	 * @var string[]
	 */
	private array $status_pool = [
		OrderStatus::COMPLETED,
		OrderStatus::COMPLETED,
		OrderStatus::COMPLETED,
		OrderStatus::PROCESSING,
		OrderStatus::PROCESSING,
		OrderStatus::ON_HOLD,
		OrderStatus::PAYMENT_PENDING,
		OrderStatus::CANCELLED,
		OrderStatus::REFUNDED,
	];

	/**
	 * Statuses that represent money received.
	 *
	 * @var string[]
	 */
	private array $paid_statuses = [
		OrderStatus::COMPLETED,
		OrderStatus::PROCESSING,
		OrderStatus::REFUNDED,
	];

	public function get_key(): string {
		return 'orders';
	}

	public function get_label(): string {
		return 'Orders';
	}

	public function get_default_count(): int {
		return 30;
	}

	public function get_dependencies(): array {
		return [ 'customers', 'products' ];
	}

	public function seed( SeederContext $context, int $count ): void {
		$price_ids    = $context->ids( 'products', 'price' );
		$customer_ids = $context->ids( 'customers', 'customer' );

		if ( empty( $price_ids ) ) {
			// Nothing to sell — skip silently; the manager logs the empty result.
			return;
		}

		for ( $i = 0; $i < $count; $i++ ) {
			$customer_id = $customer_ids ? (int) SeederData::pick( $customer_ids ) : 0;
			$status      = SeederData::pick( $this->status_pool );
			$created_ts  = SeederData::past_timestamp();

			$order = new Order();
			$order->set_currency( 'USD' );
			$order->set_customer_id( $customer_id );
			$order->set_payment_method( 'seed_manual' );
			$order->set_payment_method_title( 'Seeded (manual)' );
			$this->apply_billing( $order, $customer_id );
			$order->set_date_created( $created_ts );

			// 1-3 distinct line items.
			$line_count = min( wp_rand( 1, 3 ), count( $price_ids ) );
			$chosen     = (array) array_rand( array_flip( $price_ids ), $line_count );
			foreach ( $chosen as $price_id ) {
				$order->add_product( (int) $price_id, wp_rand( 1, 3 ) );
			}

			$order->calculate_totals();

			if ( in_array( $status, $this->paid_statuses, true ) ) {
				$order->set_date_paid( $created_ts );
			}

			$order->set_status( $status );

			$order_id = $order->save();
			if ( ! $order_id || is_wp_error( $order_id ) ) {
				continue;
			}

			$context->record( 'order', (int) $order_id );
		}
	}

	private function apply_billing( Order $order, int $customer_id ): void {
		$first = SeederData::first_name();
		$last  = SeederData::last_name();
		$email = SeederData::email( $first, $last );

		if ( $customer_id ) {
			$user = get_userdata( $customer_id );
			if ( $user ) {
				$first = $user->first_name ?: $first;
				$last  = $user->last_name ?: $last;
				$email = $user->user_email ?: $email;
			}
		}

		$address = SeederData::address();

		$order->set_billing_first_name( $first );
		$order->set_billing_last_name( $last );
		$order->set_billing_email( $email );
		$order->set_order_email( $email );
		$order->set_billing_address_1( $address['address_1'] );
		$order->set_billing_city( $address['city'] );
		$order->set_billing_state( $address['state'] );
		$order->set_billing_postcode( $address['postcode'] );
		$order->set_billing_country( $address['country'] );
	}
}
