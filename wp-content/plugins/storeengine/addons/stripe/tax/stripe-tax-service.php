<?php
/**
 * Thin wrapper around the Stripe Tax Calculations + Transactions APIs.
 */

namespace StoreEngine\Addons\Stripe\Tax;

use StoreEngine\Stripe\StripeClient;
use StoreEngine\Stripe\Exception\ApiErrorException;
use StoreEngine\Addons\Stripe\StripeService;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class StripeTaxService {

	private StripeClient $client;

	public function __construct( ?StripeClient $client = null ) {
		$this->client = $client ?? StripeService::init()->get_client();
	}

	/**
	 * Create a tax calculation for the given payload.
	 *
	 * @return array|WP_Error  ['id' => ..., 'tax_amount_exclusive' => ..., 'breakdown' => [...], 'raw' => stdClass]
	 */
	public function calculate( array $payload ) {
		try {
			$calc = $this->client->tax->calculations->create( $payload );
		} catch ( ApiErrorException $e ) {
			return new WP_Error( 'storeengine_stripe_tax_calc_failed', $e->getMessage(), [ 'status' => 502 ] );
		}

		return [
			'id'                   => $calc->id,
			'tax_amount_exclusive' => $calc->tax_amount_exclusive,
			'tax_amount_inclusive' => $calc->tax_amount_inclusive,
			'amount_total'         => $calc->amount_total,
			'currency'             => $calc->currency,
			'tax_breakdown'        => self::to_array( $calc->tax_breakdown ?? [] ),
			'line_items'           => self::to_array( $calc->line_items->data ?? [] ),
			'shipping_cost'        => self::to_array( $calc->shipping_cost ?? null ),
			'expires_at'           => $calc->expires_at ?? null,
			'raw'                  => $calc,
		];
	}

	/**
	 * Commit a calculation as a recorded transaction (post-payment).
	 *
	 * @return string|WP_Error  Transaction id on success.
	 */
	public function commit( string $calculation_id, string $reference ) {
		try {
			$tx = $this->client->tax->transactions->createFromCalculation( [
				'calculation' => $calculation_id,
				'reference'   => $reference,
			] );
		} catch ( ApiErrorException $e ) {
			return new WP_Error( 'storeengine_stripe_tax_commit_failed', $e->getMessage(), [ 'status' => 502 ] );
		}

		return (string) $tx->id;
	}

	/**
	 * Reverse a previously committed transaction (refund).
	 *
	 * @param array $params  See https://docs.stripe.com/api/tax/transactions/create_reversal
	 *
	 * @return string|WP_Error  Reversal id on success.
	 */
	public function reverse( array $params ) {
		try {
			$tx = $this->client->tax->transactions->createReversal( $params );
		} catch ( ApiErrorException $e ) {
			return new WP_Error( 'storeengine_stripe_tax_reverse_failed', $e->getMessage(), [ 'status' => 502 ] );
		}

		return (string) $tx->id;
	}

	/**
	 * Lightweight connectivity probe used by the admin status endpoint.
	 *
	 * @return array  ['key_valid' => bool, 'registrations_count' => int, 'error' => ?string]
	 */
	public function probe(): array {
		$result = [
			'key_valid'           => false,
			'registrations_count' => 0,
			'error'               => null,
		];

		try {
			$registrations                  = $this->client->tax->registrations->all( [ 'limit' => 1 ] );
			$result['key_valid']            = true;
			$result['registrations_count']  = isset( $registrations->data ) ? count( $registrations->data ) : 0;

			if ( ! empty( $registrations->has_more ) ) {
				$result['registrations_count'] = -1;
			}
		} catch ( ApiErrorException $e ) {
			$result['error'] = $e->getMessage();
		}

		return $result;
	}

	private static function to_array( $maybe ): array {
		if ( null === $maybe ) {
			return [];
		}
		if ( is_array( $maybe ) ) {
			return array_map( [ self::class, 'to_array_recursive' ], $maybe );
		}
		if ( is_object( $maybe ) && method_exists( $maybe, 'toArray' ) ) {
			return $maybe->toArray();
		}
		if ( is_object( $maybe ) ) {
			return json_decode( wp_json_encode( $maybe ), true ) ?? [];
		}

		return [];
	}

	private static function to_array_recursive( $maybe ) {
		if ( is_object( $maybe ) && method_exists( $maybe, 'toArray' ) ) {
			return $maybe->toArray();
		}
		if ( is_object( $maybe ) ) {
			return json_decode( wp_json_encode( $maybe ), true );
		}

		return $maybe;
	}
}
