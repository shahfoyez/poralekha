<?php

namespace StoreEngine\Addons\Webhooks\Incoming\Handlers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Webhooks\Interfaces\IncomingHandlerInterface;
use StoreEngine\Classes\Customer;

/**
 * Create a customer, or update the existing one matching the email — CRM /
 * marketing sync ("lead became a customer", contact details changed). Payload:
 * { email, first_name?, last_name?, phone? }.
 */
class CustomerUpsert implements IncomingHandlerInterface {

	public function handle( array $payload, array $context ): array {
		$email = sanitize_email( (string) ( $payload['email'] ?? '' ) );

		if ( '' === $email || ! is_email( $email ) ) {
			return [ 'success' => false, 'status' => 422, 'message' => __( 'A valid email is required.', 'storeengine' ) ];
		}

		$existing = get_user_by( 'email', $email );
		$created  = false;

		if ( $existing ) {
			$customer = new Customer( (int) $existing->ID );
		} else {
			$customer = new Customer();
			$customer->set_email( $email );
			$created = true;
		}

		if ( isset( $payload['first_name'] ) ) {
			$customer->set_first_name( sanitize_text_field( (string) $payload['first_name'] ) );
		}
		if ( isset( $payload['last_name'] ) ) {
			$customer->set_last_name( sanitize_text_field( (string) $payload['last_name'] ) );
		}
		if ( isset( $payload['phone'] ) ) {
			$customer->set_billing_phone( sanitize_text_field( (string) $payload['phone'] ) );
		}

		$result = $customer->save();

		if ( is_wp_error( $result ) ) {
			return [ 'success' => false, 'status' => 500, 'message' => $result->get_error_message() ];
		}

		return [
			'success' => true,
			'message' => $created
				/* translators: %s: email */
				? sprintf( __( 'Customer created: %s', 'storeengine' ), $email )
				/* translators: %s: email */
				: sprintf( __( 'Customer updated: %s', 'storeengine' ), $email ),
			'data'    => [ 'customer_id' => $customer->get_id(), 'created' => $created ],
		];
	}
}
