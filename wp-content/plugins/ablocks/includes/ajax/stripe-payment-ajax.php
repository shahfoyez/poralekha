<?php

namespace ABlocks\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\AbstractAjaxHandler;
use ABlocks\Classes\FileUpload;
use ABlocks\Helper;
use ABlocks\Classes\Sanitizer;
use ABlocks\Blocks\FormBuilder\Actions\Email;
use ABlocks\Blocks\FormBuilder\Actions\Email2;
use ABlocks\Blocks\FormBuilder\Actions\Submission;

use ABlocks\Blocks\StripeButton\StripePayment;

class StripePaymentAjax extends AbstractAjaxHandler {
	public function __construct() {
		$this->actions = [
			'stripe_get_tax_rates'      => [
				'callback' => [ $this, 'get_tax_rates' ],
				'capability' => 'edit_posts',
				'fields' => array(
					'api_key' => 'string',
				)
			],
			'stripe_payment_process'    => [
				'callback' => [ $this, 'payment_process' ],
				'allow_visitor_action' => true,
				'fields' => array(
					'current_post_id' => 'integer',
					'block_id' => 'string',
				)
			],
		];
	}

	public function get_tax_rates( $payload ) {

		$api_key = $payload['api_key'] ?? '';
		if (
			empty( $api_key )
		) {
			wp_send_json_error([
				'message' => __( 'API KEY is required.', 'ablocks' )
			]);
		}

		$data = ( new StripePayment( $api_key, [] ) )->taxe_rates();
		$error = ( $data['error']['message'] ?? false );
		if ( $error ) {
			wp_send_json_error([
				'message' => sanitize_text_field( $error )
			]);
		}

		wp_send_json_success( $data );
	}
	public function payment_process( $payload ) {
		$block_data = Helper::get_block_attributes(
			$payload['current_post_id'],
			$payload['block_id'],
			'ablocks/stripe-button'
		);

		// check api key exists or not
		$api_key = $block_data['parentAttributes']['stripeApi'] ?? '';
		if (
			empty( $api_key )
		) {
			wp_send_json_error([
				'message' => __( 'API KEY is required.', 'ablocks' )
			]);
		}

		$data = [];

		// product name
		$product_name = $block_data['parentAttributes']['itemName'] ?? '';
		if (
			! empty( $product_name )
		) {
			$data['product_name'] = $product_name;
		}
		// currency
		$currency = $block_data['parentAttributes']['currency'] ?? '';
		if (
			! empty( $currency )
		) {
			$data['currency'] = $currency;
		}
		// unit price
		$price = floatval( $block_data['parentAttributes']['price'] ?? 0 );
		if (
			is_float( $price )
		) {
			$data['unit_amount'] = $price * 100; // convert to cent
		}
		// quatity
		$quantity = intval( $block_data['parentAttributes']['quantity'] ?? 0 );
		if (
			is_int( $quantity )
		) {
			$data['quantity'] = $quantity;
		}
		// shpping charge
		$shipping_charge = floatval( $block_data['parentAttributes']['shippingPrice'] ?? 0 );
		if (
			is_float( $shipping_charge )
		) {
			$data['shipping_charge'] = $shipping_charge * 100; // convert to cent
		}
		// tax rate
		$tax = $block_data['parentAttributes']['taxId'] ?? '';
		if (
			! empty( $tax )
		) {
			$data['tax_rate'] = $tax;
		}
		// redirection after payment
		$success_url = ( $block_data['parentAttributes']['redirectionAfterPayment'] ?? '' );
		if (
			! empty( $success_url )
		) {
			$data['success_url'] = esc_url( $success_url );
		} else {
			$data['success_url'] = esc_url( get_permalink( $payload['current_post_id'] ) );
		}

		// check if data is insufficient or empty
		if (
			empty( $data ) ||
			! isset( $data['unit_amount'] ) ||
			! isset( $data['quantity'] )
		) {
			wp_send_json_error([
				'message' => __( 'Error. [1]', 'ablocks' ),
			]);
		}

		$res = ( new StripePayment( $api_key, $data ) )->process();
		$error = $res['error']['message'] ?? false;
		if ( empty( $res ) || ( $error ) ) {
			// check if custom error msg is defined or not
			$msg = $block_data['parentAttributes']['redirectionAfterPayment']['errorMessage'] ?? false;
			if (
				( $block_data['parentAttributes']['redirectionAfterPayment']['customMessage'] ?? false ) !== false &&
				! empty( $msg )
			) {
				wp_send_json_error([
					'message' => sanitize_text_field( $msg )
				]);
			}

			wp_send_json_error([
				'message' => __( 'Error. [2]', 'ablocks' )
			]);
		}

		wp_send_json_success([
			'message'      => __( 'Success!', 'ablocks' ),
			'redirect_url' => $res['url']
		]);
	}


}
