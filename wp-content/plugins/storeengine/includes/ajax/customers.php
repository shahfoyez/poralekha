<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\AccountMover;
use StoreEngine\Classes\Customer;
use StoreEngine\Utils\Helper;

class Customers extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = [
			'create_new_customer'  => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'create_new_customer' ],
				'fields'     => [
					'first_name' => 'string',
					'last_name'  => 'string',
					'email'      => 'email',
					'password'   => 'string',
					'address_1'  => 'string',
					'address_2'  => 'string',
					'city'       => 'string',
					'state'      => 'string',
					'postcode'   => 'string',
					'country'    => 'string',
					'phone'      => 'string',
				],
			],
			'top_customers'        => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'top_customers' ],
			],
			'preview_account_move' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'preview_account_move' ],
				'fields'     => [
					'from' => 'absint',
					'to'   => 'absint',
				],
			],
			'move_account'         => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'move_account' ],
				'fields'     => [
					'from' => 'absint',
					'to'   => 'absint',
				],
			],
		];
	}

	public function preview_account_move( $payload ) {
		$mover   = new AccountMover( (int) $payload['from'], (int) $payload['to'] );
		$preview = $mover->preview();

		if ( is_wp_error( $preview ) ) {
			wp_send_json_error( $preview->get_error_message() );
		}

		wp_send_json_success( [
			'counts' => $preview,
			'total'  => array_sum( $preview ),
		] );
	}

	public function move_account( $payload ) {
		$mover  = new AccountMover( (int) $payload['from'], (int) $payload['to'] );
		$result = $mover->move();

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message() );
		}

		wp_send_json_success( [
			'message' => esc_html__( 'Account data moved successfully.', 'storeengine' ),
			'result'  => $result,
		] );
	}

	public function create_new_customer( $payload ) {
		if ( empty( $payload['email'] ) || empty( $payload['password'] ) ) {
			wp_send_json_error( esc_html__( 'Email and Password are required', 'storeengine' ) );
		}

		if ( ! is_email( $payload['email'] ) ) {
			wp_send_json_error( esc_html__( 'Invalid email', 'storeengine' ) );
		}

		$customer = new Customer();
		$customer->set_first_name( $payload['first_name'] );
		$customer->set_last_name( $payload['last_name'] );
		$customer->set_display_name( $payload['first_name'] );
		$customer->set_email( $payload['email'] );
		$customer->set_password( $payload['password'] );
		$customer = $customer->save();

		if ( is_wp_error( $customer ) ) {
			wp_send_json_error( $customer->get_error_message() );
		}

		wp_send_json_success( [
			'id'              => $customer->get_id(),
			'user_login'      => $customer->get_username(),
			'user_nicename'   => $customer->get_nicename(),
			'user_email'      => $customer->get_email(),
			'user_url'        => $customer->get_url(),
			'user_registered' => $customer->get_user_registered() ? $customer->get_user_registered()->date( 'Y-m-d H:i:s' ) : null,
			'display_name'    => $customer->get_display_name(),
			'first_name'      => $customer->get_first_name(),
			'last_name'       => $customer->get_last_name(),
		] );
	}

	public function top_customers() {
		$customers     = Helper::get_top_customers();
		$top_customers = [];

		foreach ( $customers as $customer ) {
			$top_customers[] = [
				'id'           => $customer->get_id(),
				'avatar'       => get_avatar_url( $customer->get_email(), [ 'size' => 50 ] ),
				'name'         => ( ! empty( $customer->get_first_name() ) && ! empty( $customer->get_last_name() ) ) ? $customer->get_first_name() . ' ' . $customer->get_last_name() : null,
				'billing_name' => $customer->get_billing_first_name() . ' ' . $customer->get_billing_last_name(),
				'total_spent'  => $customer->get_total_spent(),
			];
		}

		wp_send_json_success( $top_customers );
	}

}
