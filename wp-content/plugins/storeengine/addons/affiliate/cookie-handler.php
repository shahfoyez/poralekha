<?php

namespace StoreEngine\Addons\Affiliate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Order;
use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Affiliate\Helper as HelperAddon;
use StoreEngine\Addons\Affiliate\models\Commission;
use StoreEngine\Addons\Affiliate\models\Affiliate as AffiliateModel;
use StoreEngine\Addons\Affiliate\models\Referral;
use StoreEngine\Addons\Affiliate\models\ReferralTrack;
use StoreEngine\Addons\Affiliate\models\AffiliateReport;
use StoreEngine\Addons\Affiliate\Settings\Affiliate;

class CookieHandler {

	public static function init() {
		$self = new self();
		add_action( 'init', [ $self, 'set_affiliate_code_cookie' ] );
		add_action( 'storeengine/checkout/order_processed', [ $self, 'handle_order' ] );
	}

	public function set_affiliate_code_cookie() {
		if ( current_user_can( 'manage_storeengine_affiliate' ) ) {
			return;
		}

		$referral_param = HelperAddon::get_referral_param();

		// Read the current `?ref=` param, falling back to the legacy
		// `?storeengine_affiliate=` param so links shared before the rename keep
		// working (backward compatibility).
		$referral_key = null;
		if ( isset( $_GET[ $referral_param ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$referral_key = $referral_param;
		} elseif ( isset( $_GET[ STOREENGINE_AFFILIATE_COOKIE_KEY ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$referral_key = STOREENGINE_AFFILIATE_COOKIE_KEY;
		}

		if ( $referral_key ) {
			// $referral_key is only set above after an isset() check on the same key.
			$referral_code = sanitize_text_field( wp_unslash( $_GET[ $referral_key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
			$referral_row  = HelperAddon::is_valid_referrer( $referral_code );

			if ( ! $referral_row ) {
				return;
			}

			if ( 'active' !== $referral_row['status'] ) {
				return;
			}

			$is_exists = [];

			if ( isset( $_COOKIE[ STOREENGINE_AFFILIATE_COOKIE_KEY ] ) ) {
				$is_exists = sanitize_text_field( wp_unslash( $_COOKIE[ STOREENGINE_AFFILIATE_COOKIE_KEY ] ), true );
			}

			if ( ! empty( $is_exists ) ) {
				if ( 'first' === HelperAddon::get_affiliate_setting('referral_type') ) {
					return;
				}

				$cookie_data = json_decode( $is_exists );

				if ( $cookie_data->referral_id === $referral_row['referral_id'] ) {
					return;
				}
			}

			$track_id = 0;

			if ( HelperAddon::get_affiliate_setting('allow_referral_tracking') ) {
				$track_row = ReferralTrack::save( [
					'referral_id' => $referral_row['referral_id'],
					'referral_ip' => Helper::get_user_ip(),
					'status'      => 'pending',
				] );

				$track_id = $track_row['track_id'];

				Referral::update( $referral_row['referral_id'], [ 'click_counts' => $referral_row['click_counts'] + 1 ] );

				$report_row = AffiliateReport::get_affiliate_reports( null, $referral_row['affiliate_id'] );

				if ( $report_row ) {
					AffiliateReport::update($referral_row['affiliate_id'], [ 'total_clicks' => $report_row['total_clicks'] + 1 ], 'affiliate_id');
				} else {
					AffiliateReport::save([
						'affiliate_id' => $referral_row['affiliate_id'],
						'referral_id'  => $referral_row['referral_id'],
						'total_clicks' => 1,
					]);
				}
			}

			$this->set_affiliate_cookie( wp_json_encode( [
				'referral_id'  => $referral_row['referral_id'],
				'affiliate_id' => $referral_row['affiliate_id'],
				'track_id'     => $track_id,
			] ) );
		}
	}

	public function set_affiliate_cookie( $cookie_data ) {
		$cookie_time = time() + ( 60 * 60 * 24 * (int) HelperAddon::get_affiliate_setting('referral_tracking_length') );
		$cookie_path = '/';

		setcookie( STOREENGINE_AFFILIATE_COOKIE_KEY, $cookie_data, $cookie_time, $cookie_path );
	}

	public function handle_order( Order $order ) {
		if ( ! isset( $_COOKIE[ STOREENGINE_AFFILIATE_COOKIE_KEY ] ) ) {
			return;
		}

		$cookie_data    = sanitize_text_field( wp_unslash( $_COOKIE[ STOREENGINE_AFFILIATE_COOKIE_KEY ] ), true );
		$affiliate_data = json_decode( $cookie_data );

		if ( ! $affiliate_data || empty( $affiliate_data->affiliate_id ) ) {
			return;
		}

		// Guard against a duplicate commission for the same order —
		// order_processed can fire more than once (retried/updated payments).
		$existing = Commission::get_commission( [ 'order_id' => $order->get_id() ] );
		if ( ! empty( $existing ) ) {
			return;
		}

		$affiliate_id = (int) $affiliate_data->affiliate_id;

		// Block self-referrals: an affiliate must not earn a commission on their
		// own purchase (buyer matched to the affiliate by user id or email),
		// unless the store explicitly opts in.
		if ( ! HelperAddon::get_affiliate_setting( 'allow_self_referral' )
			&& self::is_self_referral( $order, $affiliate_id ) ) {
			return;
		}

		// Commission on product revenue only — exclude tax and shipping.
		// Filterable so stores can widen the base (e.g. include shipping).
		$commission_base = (float) apply_filters(
			'storeengine/affiliate/commission_base',
			(float) $order->get_total() - (float) $order->get_total_tax() - (float) $order->get_shipping_total(),
			$order,
			$affiliate_id
		);

		$commission_data = [
			'affiliate_id'      => $affiliate_id,
			'order_id'          => $order->get_id(),
			'commission_amount' => HelperAddon::get_commission_amount( $commission_base, $affiliate_id ),
			'status'            => 'pending',
		];

		Commission::save( $commission_data );

		if ( HelperAddon::get_affiliate_setting('allow_referral_tracking') ) {
			$referral_data = [
				'status' => 'converted',
			];
			ReferralTrack::update( $affiliate_data->track_id, $referral_data );
		}
	}

	/**
	 * Whether the order's buyer is the referring affiliate themselves — matched
	 * by WP user id (logged-in purchase) or by billing email (guest checkout
	 * using the affiliate's own email).
	 */
	protected static function is_self_referral( Order $order, int $affiliate_id ): bool {
		$affiliate = AffiliateModel::get_affiliates( [ 'affiliate_id' => $affiliate_id ] );
		if ( empty( $affiliate ) ) {
			return false;
		}

		$affiliate_user_id = (int) ( $affiliate['user_id'] ?? 0 );
		$order_user_id     = (int) $order->get_customer_id();
		if ( $affiliate_user_id && $order_user_id && $affiliate_user_id === $order_user_id ) {
			return true;
		}

		$affiliate_email = strtolower( trim( (string) ( $affiliate['user_email'] ?? '' ) ) );
		$order_email     = strtolower( trim( (string) $order->get_billing_email() ) );
		if ( $affiliate_email && $order_email && $affiliate_email === $order_email ) {
			return true;
		}

		return false;
	}

}
