<?php

namespace StoreEngine\Addons\Affiliate\Post;

use StoreEngine\Classes\AbstractPostHandler;
use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Affiliate\Models\Affiliate as AffiliateModel;
use StoreEngine\Addons\Affiliate\Models\Referral;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Affiliate extends AbstractPostHandler {
	public function __construct() {
		$this->actions = [
			'apply_for_affiliation'  => [
				'callback'             => [ $this, 'apply_for_affiliation' ],
				'allow_visitor_action' => false,
				'fields'               => [
					'website_url'         => 'string',
					'promotional_methods' => 'string',
					'agree_terms'         => 'string',
				],
			],
			'register_for_affiliate' => [
				'callback'             => [ $this, 'register_for_affiliate' ],
				'allow_visitor_action' => true,
				'fields'               => [
					'first_name'          => 'string',
					'last_name'           => 'string',
					'email'               => 'string',
					'website_url'         => 'string',
					'promotional_methods' => 'string',
					'agree_terms'         => 'string',
				],
			],
		];
	}

	public function apply_for_affiliation( $payload = [] ) {
		$user_id   = get_current_user_id();
		$affiliate = AffiliateModel::save( [ 'user_id' => $user_id ] );
		if ( is_wp_error( $affiliate ) ) {
			wp_die( esc_html( $affiliate->get_error_message() ), esc_html__( 'Error', 'storeengine' ), [
				'back_link' => true,
			] );
		}
		Referral::save([
			'affiliate_id'     => $affiliate['affiliate_id'],
			'referral_post_id' => Helper::get_settings('shop_page'),
		]);
		$this->save_affiliate_profile_meta( $user_id, $payload );
		wp_safe_redirect( Helper::sanitize_referer_url( wp_get_referer() ) );
	}

	public function register_for_affiliate( $payload ) {
		// Every field is required. Password is not collected — it's auto-generated
		// and the user gets a set-password email. Payout details are added later
		// from the dashboard payment settings.
		foreach ( [ 'first_name', 'last_name', 'email', 'website_url', 'promotional_methods' ] as $field ) {
			if ( empty( $payload[ $field ] ) ) {
				wp_die( esc_html__( 'All fields are required.', 'storeengine' ), esc_html__( 'Error', 'storeengine' ), [
					'back_link' => true,
				] );
			}
		}

		if ( empty( $payload['agree_terms'] ) ) {
			wp_die( esc_html__( 'You must agree to the affiliate program terms to continue.', 'storeengine' ), esc_html__( 'Error', 'storeengine' ), [
				'back_link' => true,
			] );
		}

		$existing_user = get_user_by( 'email', $payload['email'] );

		if ( $existing_user ) {
			$payload['user_id'] = $existing_user->ID;
		}

		$inserted = AffiliateModel::save( $payload );

		if ( is_wp_error( $inserted ) ) {
			wp_die( esc_html( $inserted->get_error_message() ), esc_html__( 'Error', 'storeengine' ), [
				'back_link' => true,
			] );
		}

		Referral::save([
			'affiliate_id'     => $inserted['affiliate_id'],
			'referral_post_id' => Helper::get_settings('shop_page'),
		]);

		if ( ! empty( $inserted['user_id'] ) ) {
			$this->save_affiliate_profile_meta( (int) $inserted['user_id'], $payload );

			// Brand-new account: password was auto-generated, so email the user a
			// link to set their own password (WP's standard new-user notification).
			if ( ! $existing_user ) {
				wp_new_user_notification( (int) $inserted['user_id'], null, 'user' );
			}
		}

		$redirect_url = add_query_arg( 'registration_success', 'true', Helper::sanitize_referer_url( wp_get_referer() ) );

		wp_safe_redirect( $redirect_url );
		exit;
	}

	/**
	 * Persist the affiliate profile fields collected at signup to user meta.
	 *
	 * Stored as user meta (not a schema change) alongside the existing payout
	 * details. The payout email also seeds the withdrawal PayPal email when the
	 * affiliate hasn't set one yet, so approved affiliates can be paid without a
	 * second data-entry step.
	 *
	 * @param int   $user_id Target user.
	 * @param array $payload Sanitized form payload.
	 */
	protected function save_affiliate_profile_meta( int $user_id, array $payload ) {
		if ( ! $user_id ) {
			return;
		}

		if ( isset( $payload['website_url'] ) && '' !== $payload['website_url'] ) {
			update_user_meta( $user_id, 'storeengine_affiliate_website_url', esc_url_raw( $payload['website_url'] ) );
		}

		if ( isset( $payload['payout_email'] ) && is_email( $payload['payout_email'] ) ) {
			$email = sanitize_email( $payload['payout_email'] );
			update_user_meta( $user_id, 'storeengine_affiliate_payout_email', $email );

			if ( ! get_user_meta( $user_id, 'storeengine_affiliate_withdraw_paypal_email', true ) ) {
				update_user_meta( $user_id, 'storeengine_affiliate_withdraw_paypal_email', $email );
			}
		}

		if ( isset( $payload['promotional_methods'] ) && '' !== $payload['promotional_methods'] ) {
			update_user_meta( $user_id, 'storeengine_affiliate_promotional_methods', sanitize_textarea_field( $payload['promotional_methods'] ) );
		}

		if ( ! empty( $payload['agree_terms'] ) ) {
			update_user_meta( $user_id, 'storeengine_affiliate_agreed_terms', current_time( 'mysql' ) );
		}
	}
}
