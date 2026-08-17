<?php
/**
 * Seeds StoreEngine customers (WordPress users with the customer role).
 *
 * @package StoreEngine\Addons\Seeder\Providers
 */

namespace StoreEngine\Addons\Seeder\Providers;

use StoreEngine\Addons\Seeder\Classes\AbstractSeederProvider;
use StoreEngine\Addons\Seeder\Classes\SeederContext;
use StoreEngine\Addons\Seeder\Classes\SeederData;
use StoreEngine\Classes\Customer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CustomerProvider extends AbstractSeederProvider {

	/**
	 * Known password set on every seeded customer so you can log in as one to
	 * test the storefront / customer dashboard. Safe for dev only — seeding is
	 * refused on production unless explicitly forced.
	 */
	const DEFAULT_PASSWORD = 'password';

	public function get_key(): string {
		return 'customers';
	}

	public function get_label(): string {
		return 'Customers';
	}

	public function get_default_count(): int {
		return 15;
	}

	public function seed( SeederContext $context, int $count ): void {
		for ( $i = 0; $i < $count; $i++ ) {
			$first   = SeederData::first_name();
			$last    = SeederData::last_name();
			$email   = SeederData::email( $first, $last );
			$address = SeederData::address();

			$customer = new Customer();
			$customer->set_email( $email );
			$customer->set_password( self::DEFAULT_PASSWORD );
			$customer->set_first_name( $first );
			$customer->set_last_name( $last );
			$customer->set_billing_first_name( $first );
			$customer->set_billing_last_name( $last );
			$customer->set_billing_email( $email );
			$customer->set_billing_phone( SeederData::phone() );
			$customer->set_billing_address_1( $address['address_1'] );
			$customer->set_billing_city( $address['city'] );
			$customer->set_billing_state( $address['state'] );
			$customer->set_billing_postcode( $address['postcode'] );
			$customer->set_billing_country( $address['country'] );

			$result = $customer->save();
			if ( is_wp_error( $result ) ) {
				continue;
			}

			$user_id = $customer->get_id();
			if ( ! $user_id ) {
				continue;
			}

			update_user_meta( $user_id, SeederData::MARKER_META, 1 );
			$context->record( 'customer', $user_id );
		}
	}
}
