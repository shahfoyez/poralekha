<?php

namespace StoreEngine\Addons\Affiliate\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Addons\Affiliate\models\Payout;
use StoreEngine\Addons\Affiliate\models\AffiliateReport;
use StoreEngine\Addons\Affiliate\models\Commission;

class PayoutAjax extends AbstractAjaxHandler {

	public function __construct() {
		$this->actions = [
			'get_all_payouts'                => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_all_payouts' ],
				'fields'     => [
					'page'     => 'absint',
					'per_page' => 'integer',
					'status'   => 'string',
					'search'   => 'string',
				],
			],
			'get_a_payout'                   => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_a_payout' ],
				'fields'     => [
					'payout_id' => 'absint',
				],
			],
			'add_a_payout'                   => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'add_a_payout' ],
				'fields'     => [
					'affiliate_id'   => 'absint',
					'payout_amount'  => 'float',
					'payment_method' => 'string',
				],
			],
			'update_a_payouts_status'        => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'update_a_payouts_status' ],
				'fields'     => [
					'payout_id' => 'absint',
					'status'    => 'string',
				],
			],
			'get_affiliates_current_balance' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_affiliates_current_balance' ],
				'fields'     => [
					'affiliate_id' => 'absint',
				],
			],
		];
	}

	public function update_a_payouts_status( $payload ) {
		$payout_id = ! empty( $payload['payout_id'] ) ? $payload['payout_id'] : '';
		$status    = ! empty( $payload['status'] ) ? $payload['status'] : '';

		if ( ! $payout_id ) {
			wp_send_json_error( esc_html__( 'Payout ID is required.', 'storeengine' ) );
		}
		if ( ! $status ) {
			wp_send_json_error( esc_html__( 'Payout status is required.', 'storeengine' ) );
		}

		// The withdrawal amount was already held from the affiliate's balance
		// when the payout was created (see Post\Settings::affiliate_earning_withdrawal
		// and self::add_a_payout). Completing a payout therefore needs no balance
		// change; only rejecting/cancelling/failing it returns the held funds —
		// and only on the first transition into such a state.
		$payout         = Payout::get_payouts( [ 'payout_id' => $payout_id ] );
		$prev_status    = $payout['status'] ?? '';
		$release_states = [ 'rejected', 'cancelled', 'failed' ];

		if ( in_array( $status, $release_states, true ) && ! in_array( $prev_status, $release_states, true ) ) {
			AffiliateReport::release_balance( (int) $payout['affiliate_id'], (float) $payout['payout_amount'] );
		}

		// On the first transition into a completed/paid state, settle the
		// affiliate's approved commissions (FIFO) so their commission rows reflect
		// that they've been paid out — the payout ledger and the commission list
		// stay in sync instead of "Paid" being a manual-only label.
		$paid_states = [ 'completed', 'paid' ];
		if ( in_array( $status, $paid_states, true ) && ! in_array( $prev_status, $paid_states, true ) ) {
			Commission::mark_approved_as_paid( (int) $payout['affiliate_id'], (float) $payout['payout_amount'] );
		}

		$ret = Payout::update( $payout_id, [ 'status' => $status ] );

		if ( is_wp_error( $ret ) ) {
			wp_send_json_error( $ret->get_error_message() );
		}

		do_action( 'storeengine/addons/affiliate/update_payout_status', $payout_id, $status );

		wp_send_json_success( $ret );
	}

	public function add_a_payout( $payload ) {
		$args = [
			'affiliate_id'   => ! empty( $payload['affiliate_id'] ) ? $payload['affiliate_id'] : '',
			'payout_amount'  => ! empty( $payload['payout_amount'] ) ? $payload['payout_amount'] : '',
			'payment_method' => ! empty( $payload['payment_method'] ) ? $payload['payment_method'] : '',
		];

		if ( empty( array_filter( array_values( $args ) ) ) ) {
			wp_send_json_error( esc_html__( 'Missing required fields.', 'storeengine' ) );
		}

		if ( ! in_array( $payload['payment_method'], [ 'PayPal', 'Bank Transfer', 'Stripe', 'Check Payment', 'E-Check' ], true ) ) {
			wp_send_json_error( esc_html__( 'Invalid payment method.', 'storeengine' ) );
		}

		if ( $payload['payout_amount'] <= 0 ) {
			wp_send_json_error( esc_html__( 'Invalid amount. Amount must be greater then zero.', 'storeengine' ) );
		}

		// Hold the amount from the affiliate's balance up front (same reservation
		// model as frontend withdrawals) so an admin payout can't overdraw and so
		// completing it later needs no further balance change.
		if ( ! AffiliateReport::reserve_balance( (int) $args['affiliate_id'], (float) $args['payout_amount'] ) ) {
			wp_send_json_error( esc_html__( 'Affiliate does not have sufficient balance for this payout.', 'storeengine' ) );
		}

		$ret = ( new Payout() )->save( $args );

		if ( is_wp_error( $ret ) ) {
			AffiliateReport::release_balance( (int) $args['affiliate_id'], (float) $args['payout_amount'] );
			wp_send_json_error( $ret->get_error_message() );
		}

		wp_send_json_success( $ret );
	}

	public function get_a_payout( $payload ) {
		if ( ! empty( $payload['payout_id'] ) ) {
			wp_send_json_error( esc_html__( 'Payout ID missing.', 'storeengine' ) );
		}

		$payout = new Payout();
		$ret    = Payout::get_payouts([
			'payout_id' => $payload['payout_id'],
		]);

		if ( is_wp_error( $ret ) ) {
			wp_send_json_error( $ret->get_error_message() );
		}

		wp_send_json_success( $ret );
	}

	public function get_all_payouts( $payload ) {
		$page     = ! empty( $payload['page'] ) ? $payload['page'] : 1;
		$per_page = ! empty( $payload['per_page'] ) ? $payload['per_page'] : 10;
		$status   = ! empty( $payload['status'] ) ? $payload['status'] : 'any';
		$search   = ! empty( $payload['search'] ) ? $payload['search'] : '';
		$offset   = ( $page - 1 ) * $per_page;
		$payout   = new Payout();

		// Set the x-wp-total header
		header( 'X-WP-TOTAL: ' . Payout::get_payouts([ 'count' => true ]) );
		wp_send_json_success( Payout::get_payouts([
			'offset'   => $offset,
			'per_page' => $per_page,
			'status'   => $status,
			'search'   => $search,
		]));
	}

	public function get_affiliates_current_balance( $payload ) {
		if ( $payload['affiliate_id'] ) {
			wp_send_json_success( AffiliateReport::get_current_balance( $payload['affiliate_id'] ) );
		}

		wp_send_json_error( esc_html__( 'Affiliate ID missing.', 'storeengine' ) );
	}
}
