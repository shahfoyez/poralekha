<?php

namespace StoreEngine\API;

use stdClass;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Request;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Coupon {

	/**
	 * Derived, queryable copy of the coupon end date/time as a single unix
	 * timestamp. The authoritative value (`_storeengine_coupon_end_date_time`)
	 * is a serialized `{date, time, timezone}` object, which cannot be compared
	 * in a meta_query — so we mirror it into this scalar on every save and
	 * filter the admin list against it. `0` means "no end date" (never expires).
	 */
	const END_TS_META = '_storeengine_coupon_end_ts';

	/**
	 * Meta storing which time mode a coupon uses. `set_time_limit` means it has a
	 * start/end window; anything else (default `forever_time`, or unset on legacy
	 * coupons) means it is open-ended.
	 */
	const TIME_TYPE_META = '_storeengine_coupon_time_type';

	public static function init() {
		$self = new self();
		add_filter( 'rest_pre_insert_storeengine_coupon', [ $self, 'validate_request_data' ], 10, 2 );

		// Keep the queryable end-timestamp mirror in sync on create/update.
		add_action( 'rest_after_insert_' . Helper::COUPON_POST_TYPE, [ $self, 'sync_end_timestamp' ], 10, 1 );

		// Expose the `se_expiry` filter on the coupon REST collection and turn it
		// into a meta_query against the mirrored timestamp.
		add_filter( 'rest_' . Helper::COUPON_POST_TYPE . '_collection_params', [ $self, 'register_expiry_param' ] );
		add_filter( 'rest_' . Helper::COUPON_POST_TYPE . '_query', [ $self, 'filter_by_expiry' ], 10, 2 );
	}

	/**
	 * Reduce the serialized end date/time meta to a single unix timestamp.
	 *
	 * Uses the same `strtotime( "date time" )` reading as the coupon-expiry
	 * validator (see Coupon::check_coupon_between_start_and_end_date) so the
	 * "expired" list filter and the real checkout expiry agree. Returns `0`
	 * when no end date is set, which is the sentinel for "never expires".
	 *
	 * @param mixed $end_date_time The `_storeengine_coupon_end_date_time` meta value.
	 */
	public static function compute_end_timestamp( $end_date_time ): int {
		if ( ! is_array( $end_date_time ) || empty( $end_date_time['date'] ) ) {
			return 0;
		}

		$time = $end_date_time['time'] ?? '';
		$ts   = strtotime( trim( $end_date_time['date'] . ' ' . $time ) );

		return $ts ? (int) $ts : 0;
	}

	/**
	 * Mirror the coupon end date/time into the queryable timestamp meta.
	 *
	 * @param \WP_Post $post The saved coupon post.
	 */
	public function sync_end_timestamp( $post ) {
		if ( ! $post || Helper::COUPON_POST_TYPE !== $post->post_type ) {
			return;
		}

		$end_date_time = get_post_meta( $post->ID, '_storeengine_coupon_end_date_time', true );
		update_post_meta( $post->ID, self::END_TS_META, self::compute_end_timestamp( $end_date_time ) );
	}

	/**
	 * Register the `se_expiry` query param on the coupon REST collection.
	 *
	 * @param array $params Collection params.
	 * @return array
	 */
	public function register_expiry_param( array $params ): array {
		$params['se_expiry'] = [
			'description' => __( 'Limit coupons by expiry state: "expired" (past end date), "no_end" (time limit set but no end date) or "forever" (open-ended, no time limit).', 'storeengine' ),
			'type'        => 'string',
			'enum'        => [ 'expired', 'no_end', 'forever' ],
		];

		return $params;
	}

	/**
	 * Translate the `se_expiry` param into a meta_query on the mirrored timestamp.
	 *
	 * @param array           $args    WP_Query args.
	 * @param WP_REST_Request $request REST request.
	 * @return array
	 */
	public function filter_by_expiry( array $args, WP_REST_Request $request ): array {
		$expiry = $request->get_param( 'se_expiry' );
		if ( ! $expiry ) {
			return $args;
		}

		$meta_query = $args['meta_query'] ?? [];

		if ( 'expired' === $expiry ) {
			// A set, in-the-past end date. Bounded below by 1 so "no end date"
			// (stored as 0) is never counted as expired.
			$meta_query[] = [
				'key'     => self::END_TS_META,
				'value'   => [ 1, time() ],
				'compare' => 'BETWEEN',
				'type'    => 'NUMERIC',
			];
		} elseif ( 'no_end' === $expiry ) {
			// A time limit was chosen but the end date left blank — the risky
			// case. Requires the `set_time_limit` mode so intentionally
			// open-ended ("Forever") coupons fall under the `forever` filter
			// instead. The mirror is 0, or absent on coupons predating it.
			$meta_query[] = [
				'relation' => 'AND',
				[
					'key'     => self::TIME_TYPE_META,
					'value'   => 'set_time_limit',
					'compare' => '=',
				],
				[
					'relation' => 'OR',
					[
						'key'     => self::END_TS_META,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'     => self::END_TS_META,
						'value'   => 0,
						'compare' => '=',
						'type'    => 'NUMERIC',
					],
				],
			];
		} elseif ( 'forever' === $expiry ) {
			// Open-ended by choice: any mode that is not `set_time_limit`,
			// including legacy coupons with the meta unset (default is
			// `forever_time`).
			$meta_query[] = [
				'relation' => 'OR',
				[
					'key'     => self::TIME_TYPE_META,
					'compare' => 'NOT EXISTS',
				],
				[
					'key'     => self::TIME_TYPE_META,
					'value'   => 'set_time_limit',
					'compare' => '!=',
				],
			];
		}

		$args['meta_query'] = $meta_query;

		return $args;
	}

	public function validate_request_data( stdClass $prepared_post, WP_REST_Request $request ) {
		$meta = $request->get_param( 'meta' );

		$customers = $meta['_storeengine_coupon_valid_customers'] ?? [];
		foreach ( $customers as $customer_id ) {
			if ( ! is_numeric( $customer_id ) || ! get_userdata( $customer_id ) ) {
				return new WP_Error( 'invalid_customer_id', esc_html__( 'Invalid customer ID provided.', 'storeengine' ), [ 'status' => 400 ] );
			}
		}

		$prices = $meta['_storeengine_coupon_valid_prices'] ?? [];

		foreach ( $prices as $index => $price_data ) {
			$product_id = absint( $price_data['product_id'] );
			if ( ! $product_id || ! get_post( $product_id ) ) {
				return new WP_Error( 'invalid_product_id', esc_html(
					sprintf(
						// translators: %s. Item index.
						__( 'Invalid product ID provided at %s item', 'storeengine' ),
						Formatting::append_numeral_suffix( $index + 1 )
					)
				), [ 'status' => 400 ] );
			}

			$price_ids = array_map( 'absint', $price_data['price_ids'] );
			foreach ( $price_ids as $price_id ) {
				try {
					$price = Helper::get_price( $price_id );
					if ( $price->get_product_id() !== $product_id ) {
						return new WP_Error(
							'invalid_price_id',
							esc_html(
								sprintf(
									// translators: %s. Item index.
									__( 'Invalid product & price combination at %s item.', 'storeengine' ),
									Formatting::append_numeral_suffix( $index + 1 )
								)
							),
							[ 'status' => 400 ]
						);
					}
				} catch ( StoreEngineException $e ) {
					return new WP_Error( 'invalid_price_id', esc_html(
						sprintf(
							// translators: %s. Item index.
							__( 'Invalid price selected at %s item.', 'storeengine' ),
							Formatting::append_numeral_suffix( $index + 1 )
						)
					), [ 'status' => 400 ] );
				}
			}
		}

		return $prepared_post;
	}

}
