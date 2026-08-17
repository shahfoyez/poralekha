<?php

namespace StoreEngine\Addons\Affiliate\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Addons\Affiliate\models\Commission;
use StoreEngine\Addons\Affiliate\models\AffiliateReport;
use StoreEngine\Addons\Affiliate\Helper as HelperAddon;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\OrderCollection;

class CommissionAjax extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'get_all_commissions'         => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_all_commissions' ],
				'fields'     => [
					'page'     => 'integer',
					'per_page' => 'integer',
					'status'   => 'string',
					'search'   => 'string',
				],
			],
			'get_a_commission'            => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_a_commission' ],
				'fields'     => [
					'commission_id' => 'integer',
				],
			],
			'add_a_commission'            => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'add_a_commission' ],
				'fields'     => [
					'affiliate_id' => 'integer',
					'order_id'     => 'integer',
				],
			],
			'update_a_commissions_status' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'update_a_commissions_status' ],
				'fields'     => [
					'commission_id' => 'integer',
					'status'        => 'string',
				],
			],
			'get_all_order_ids'           => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_all_order_ids' ],
			],
		];
	}

	public function update_a_commissions_status( $payload ) {
		if ( empty( $payload['commission_id'] ) ) {
			wp_send_json_error( esc_html__( 'Commission ID is required.', 'storeengine' ) );
		}
		if ( empty( $payload['status'] ) ) {
			wp_send_json_error( esc_html__( 'Commission status is required.', 'storeengine' ) );
		}

		$current     = Commission::get_commission( [ 'commission_id' => $payload['commission_id'] ] );
		$prev_status = $current['status'] ?? '';

		if ( 'approved' === $payload['status'] ) {
			// Self-guards against re-crediting a non-pending commission.
			$this->update_the_affiliate_commission( $payload['commission_id'] );

			// Notify the affiliate only on the first transition into approved.
			if ( 'approved' !== $prev_status ) {
				do_action( 'storeengine/addons/affiliate/commission_approved', (int) $payload['commission_id'] );
			}
		}

		// Manually rejecting an already-approved commission claws the funds back.
		if ( 'rejected' === $payload['status'] && 'approved' === $prev_status ) {
			AffiliateReport::reverse_commission( (int) $current['affiliate_id'], (float) $current['commission_amount'] );
		}

		$ret = Commission::update( $payload['commission_id'], [ 'status' => $payload['status'] ] );
		if ( is_wp_error( $ret ) ) {
			wp_send_json_error( $ret->get_error_message() );
		}

		do_action( 'storeengine/addons/affiliate/update_commission_status', $payload['commission_id'], $payload['status'] );

		wp_send_json_success( $ret );
	}

	public function add_a_commission( $payload ) {
		if ( empty( $payload['affiliate_id'] ) ) {
			wp_send_json_error( esc_html__( 'Affiliate ID is required.', 'storeengine' ) );
		}

		if ( empty( $payload['order_id'] ) ) {
			wp_send_json_error( esc_html__( 'Order ID is required.', 'storeengine' ) );
		}

		try {
			$payload['commission_amount'] = HelperAddon::get_commission_amount( (float) storeengine_get_order( $payload['order_id'] )->get_total(), (int) $payload['affiliate_id'] );
		} catch ( StoreEngineException $e ) {
			$payload['commission_amount'] = 0;
		}

		wp_send_json_success( Commission::save( $payload ) );
	}

	public function get_a_commission( $payload ) {
		if ( empty( $payload['commission_id'] ) ) {
			wp_send_json_error( esc_html__( 'Commission ID is required.', 'storeengine' ) );
		}

		wp_send_json_success( Commission::get_commission(
			[
				'commission_id' => $payload['commission_id'],
			]
		));
	}

	public function get_all_commissions( $payload ) {
		$page     = ! empty( $payload['page'] ) ? $payload['page'] : 1;
		$per_page = ! empty( $payload['per_page'] ) ? $payload['per_page'] : 10;
		$status   = ! empty( $payload['status'] ) ? $payload['status'] : 'any';
		$search   = ! empty( $payload['search'] ) ? $payload['search'] : '';
		$offset   = ( $page - 1 ) * $per_page;

		// Set the x-wp-total header
		header( 'X-WP-TOTAL: ' . Commission::get_commission([ 'count' => true ]) );
		wp_send_json_success( Commission::get_commission([
			'offset'   => $offset,
			'per_page' => $per_page,
			'status'   => $status,
			'search'   => $search,
		]) );
	}

	public function get_all_order_ids() {
		// @FIXME Should use pagination or other filter logic (E.g. Search by customer, date, etc.).
		$query = new OrderCollection( [
			'per_page' => - 1,
			'page'     => 1,
			'fields'   => 'ids',
			'where'    => [
				'relation' => 'AND',
				[
					'key'   => 'type',
					'value' => 'order',
				],
				[
					'key'     => 'status',
					'value'   => [ 'draft', 'auto-draft', 'trash' ],
					'compare' => 'NOT IN',
				],
			],
		] );

		$orders = [];
		foreach ( $query->get_results() as $order_id ) {
			$orders[] = [ 'order_id' => $order_id ];
		}

		wp_send_json_success( $orders );
	}

	protected function update_the_affiliate_commission( $commission_id = 0 ) {
		if ( ! $commission_id ) {
			return false;
		}

		$commission_row = Commission::get_commission( [ 'commission_id' => $commission_id ] );

		// Only credit the balance on a pending -> approved transition, and use
		// the commission amount stored at capture time (which already reflects
		// the per-affiliate rate) rather than recomputing from the order total.
		if ( empty( $commission_row ) || 'pending' !== ( $commission_row['status'] ?? '' ) ) {
			return false;
		}

		return HelperAddon::update_affiliate_commission(
			$commission_row['affiliate_id'],
			(float) $commission_row['commission_amount']
		);
	}
}
