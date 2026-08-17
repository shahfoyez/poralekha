<?php
/**
 * Seeds coupons (percentage + fixed-amount).
 *
 * Coupons are a CPT with no model save(), so we create the post + the meta keys
 * that Coupon::get() reads back (the `_storeengine_coupon_*` family).
 *
 * Add-on discount types seed themselves — see the Pro Advanced Coupons BOGO
 * provider, which registers on the same `storeengine/seeder/providers` filter.
 *
 * @package StoreEngine\Addons\Seeder\Providers
 */

namespace StoreEngine\Addons\Seeder\Providers;

use StoreEngine\Addons\Seeder\Classes\AbstractSeederProvider;
use StoreEngine\Addons\Seeder\Classes\SeederContext;
use StoreEngine\Addons\Seeder\Classes\SeederData;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CouponProvider extends AbstractSeederProvider {

	public function get_key(): string {
		return 'coupons';
	}

	public function get_label(): string {
		return 'Coupons';
	}

	public function get_default_count(): int {
		return 5;
	}

	public function seed( SeederContext $context, int $count ): void {
		for ( $i = 0; $i < $count; $i++ ) {
			// Alternate between the two most common discount types.
			$is_percentage = 0 === $i % 2;
			$type          = $is_percentage ? 'percentage' : 'fixedAmount';
			$amount        = $is_percentage ? wp_rand( 5, 50 ) : SeederData::price();
			$code          = sprintf( 'SEED%s%d', $is_percentage ? 'PCT' : 'AMT', wp_rand( 100, 9999 ) );

			$post_id = wp_insert_post( [
				'post_type'   => Helper::COUPON_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $code,
			] );

			if ( ! $post_id || is_wp_error( $post_id ) ) {
				continue;
			}

			update_post_meta( $post_id, '_storeengine_coupon_name', $code );
			update_post_meta( $post_id, '_storeengine_coupon_type', $type );
			update_post_meta( $post_id, '_storeengine_coupon_amount', $amount );
			update_post_meta( $post_id, '_storeengine_coupon_time_type', 'forever_time' );
			update_post_meta( $post_id, SeederData::MARKER_META, 1 );

			$context->record( 'coupon', $post_id );
		}
	}
}
