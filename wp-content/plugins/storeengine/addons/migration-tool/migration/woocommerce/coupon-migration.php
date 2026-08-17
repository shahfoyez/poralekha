<?php

namespace StoreEngine\Addons\MigrationTool\Migration\Woocommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Database;
use WC_Coupon;

class CouponMigration {
	protected ?int $wc_coupon_id = null;
	protected WC_Coupon $wc_coupon;
	protected ?string $coupon_code;

	public static function get_by_wc_code( string $code ): string {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return (string) $wpdb->get_var( $wpdb->prepare(
			"
			SELECT m.meta_value FROM {$wpdb->postmeta} m
			INNER JOIN {$wpdb->posts} p ON p.ID = m.post_id
			WHERE p.post_type = 'storeengine_coupon' AND m.meta_key = %s AND m.meta_value = %s",
			'_storeengine_coupon_name',
			$code
		) );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}

	public function __construct( int $wc_coupon_id ) {
		// Restore WC Table info in wpdb.
		WC()->wpdb_table_fix();

		$this->wc_coupon_id = $wc_coupon_id;
		$this->wc_coupon    = new WC_Coupon( $this->wc_coupon_id );
		$this->coupon_code  = self::get_by_wc_code( $this->wc_coupon->get_code() );
	}

	public function is_exists(): bool {
		return ! empty( $this->coupon_code );
	}

	public function migrate(): ?string {
		if ( $this->is_exists() ) {
			return $this->coupon_code;
		} else {
			return $this->create_coupon();
		}
	}

	public function create_coupon(): ?string {
		$coupon_code = $this->wc_coupon->get_code();
		$meta_data   = self::prepare_meta_data( $this->wc_coupon );

		// Restore SE Table info in wpdb.
		Database::init()->register_database_table_name();

		// Create Coupon Post.
		$coupon_id = wp_insert_post( [
			'post_title'  => $coupon_code,
			'post_type'   => 'storeengine_coupon',
			'post_status' => 'publish',
		], true );

		if ( is_wp_error( $coupon_id ) ) {
			return null;
		}

		$used_by = $meta_data['_storeengine_coupon_used_by'];

		unset( $meta_data['_storeengine_coupon_used_by'] );

		foreach ( $meta_data as $key => $value ) {
			update_post_meta( $coupon_id, $key, $value );
		}

		foreach ( $used_by as $uid ) {
			add_post_meta( $coupon_id, '_storeengine_coupon_used_by', $uid );
		}

		return $coupon_code;
	}

	public static function prepare_meta_data( WC_Coupon $wc_coupon ): array {
		$start  = [
			'date'     => gmdate( 'Y-m-d', time() ),
			'time'     => gmdate( 'H:i', time() ),
			'timezone' => '',
		];
		$expire = [
			'date'     => '',
			'time'     => '',
			'timezone' => '',
		];

		if ( $wc_coupon->get_date_created() ) {
			$start = [
				'date'     => $wc_coupon->get_date_created()->date( 'Y-m-d' ),
				'time'     => $wc_coupon->get_date_created()->date( 'H:i:s' ),
				'timezone' => '',
			];
		}

		if ( $wc_coupon->get_date_expires() ) {
			$expire = [
				'date'     => $wc_coupon->get_date_expires()->date( 'Y-m-d' ),
				'time'     => $wc_coupon->get_date_expires()->date( 'H:i:s' ),
				'timezone' => '',
			];
		}

		return [
			'_storeengine_coupon_name'                    => $wc_coupon->get_code(),
			'_storeengine_coupon_type'                    => 'percent' === $wc_coupon->get_discount_type() ? 'percentage' : 'fixedAmount',
			'_storeengine_coupon_amount'                  => $wc_coupon->get_amount(),
			'_storeengine_coupon_usage_limit'             => absint( $wc_coupon->get_usage_limit() ),
			'_storeengine_coupon_time_type'               => $wc_coupon->get_date_expires() ? 'set_time_limit' : 'forever_time',
			'_storeengine_coupon_customer_usage_limit'    => absint( $wc_coupon->get_usage_limit_per_user() ),
			'_storeengine_coupon_min_purchase_quantity'   => 0,
			'_storeengine_coupon_min_purchase_amount'     => $wc_coupon->get_minimum_amount(),
			'_storeengine_coupon_who_can_use'             => 'allCustomer',
			'_storeengine_coupon_usage_count'             => $wc_coupon->get_usage_count(),
			'_storeengine_coupon_start_date_time'         => $start,
			'_storeengine_coupon_end_date_time'           => $expire,
			'_storeengine_coupon_type_of_min_requirement' => $wc_coupon->get_minimum_amount() > 0 ? 'amount' : 'none',
			'_storeengine_coupon_used_by'                 => $wc_coupon->get_used_by(),
		];
	}
}
