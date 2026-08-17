<?php

namespace StoreEngine\Addons\Affiliate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Addons\Affiliate\Settings\Affiliate;
use StoreEngine\Addons\Affiliate\models\Affiliate as AffiliateModel;
use StoreEngine\Addons\Affiliate\models\AffiliateReport;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper as UtilsHelper;

class Helper {
	/**
	 * @throws StoreEngineException
	 */
	public static function generate_random_code( string $code_type, int $code_length = 8 ) {
		global $wpdb;
		if ( 'payouts' === $code_type ) {
			// Unified payouts ledger; reference column replaces the legacy transaction_id.
			$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_payouts WHERE payee_type = 'affiliate' AND reference = %s;";
		} elseif ( 'referrals' === $code_type ) {
			$sql = "SELECT COUNT(*) FROM {$wpdb->prefix}storeengine_affiliate_referrals WHERE referral_code = %s;";
		} else {
			throw new StoreEngineException( esc_html__( 'Invalid code type', 'storeengine' ), 'invalid_code_type' );
		}

		do {
			$random_code   = wp_generate_password( $code_length, false, false );
			$existing_code = $wpdb->get_var( $wpdb->prepare( $sql, $random_code ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- query prepared above.
		} while ( $existing_code > 0 );

		return $random_code;
	}

	public static function get_affiliate_setting( $setting_name ) {
		// Fall back to defaults for keys the stored settings blob doesn't have
		// yet (e.g. a setting added after the store last saved its options), so
		// a missing key inherits its intended default instead of returning null.
		$affiliate_settings = wp_parse_args(
			Affiliate::get_settings_saved_data(),
			Affiliate::get_settings_default_data()
		);

		return $affiliate_settings[ $setting_name ] ?? null;
	}

	/**
	 * The public URL query parameter that carries the referral code, e.g.
	 * `?ref=CODE`. Kept short and generic; filterable so a store can rename it.
	 * This is intentionally separate from the internal cookie name
	 * (STOREENGINE_AFFILIATE_COOKIE_KEY) so the URL stays clean while the stored
	 * cookie remains namespaced.
	 */
	public static function get_referral_param(): string {
		$param = (string) apply_filters( 'storeengine/affiliate/referral_param', 'ref' );

		return sanitize_key( $param ) ?: 'ref';
	}

	/**
	 * Resolve the commission for an order amount.
	 *
	 * When `$affiliate_id` is given, that affiliate's own commission_type /
	 * commission_rate (stored on the affiliates row) takes precedence over the
	 * global default — previously these per-affiliate columns were saved but
	 * never applied.
	 *
	 * @param float    $total_amount Base amount to commission on.
	 * @param int|null $affiliate_id Optional affiliate for a per-affiliate rate.
	 */
	public static function get_commission_amount( float $total_amount = 0, ?int $affiliate_id = null ) {
		$commission_type = self::get_affiliate_setting( 'commission_type' );
		$commission_rate = (float) self::get_affiliate_setting( 'commission_rate' );

		if ( $affiliate_id ) {
			$affiliate = AffiliateModel::get_affiliates( [ 'affiliate_id' => $affiliate_id ] );
			if ( ! empty( $affiliate['commission_type'] ) ) {
				$commission_type = $affiliate['commission_type'];
			}
			if ( isset( $affiliate['commission_rate'] ) && '' !== $affiliate['commission_rate'] ) {
				$commission_rate = (float) $affiliate['commission_rate'];
			}
		}

		if ( 'percentage' === $commission_type ) {
			return Formatting::format_decimal( $total_amount * ( $commission_rate / 100 ), 2 );
		}

		return Formatting::format_decimal( $commission_rate, 2 );
	}

	public static function format_payment_method( $payment_method = null ): string {
		switch ( $payment_method ) {
			case 'bank_transfer':
				return __( 'Bank Transfer', 'storeengine' );
			case 'check_payment':
				return __( 'Check Payment', 'storeengine' );
			case 'cash_on_delivery':
				return __( 'Cash on Delivery', 'storeengine' );
			case 'paypal':
				return __( 'PayPal', 'storeengine' );
			case 'stripe':
				return __( 'Stripe', 'storeengine' );
			case 'echeck':
				return __( 'E-Check', 'storeengine' );
			default:
				return '';
		}
	}

	/**
	 * Resolve a referral code to its referral row.
	 *
	 * The code is valid on *any* landing page — a referral link can point at a
	 * product, a blog post, the home page, or the shop. Previously a click was
	 * only credited when the landing page matched the stored referral_post_id,
	 * so links to anything but the shop page silently failed to track.
	 *
	 * @param string|null $referral_code Referral code from the URL.
	 *
	 * @return array|false Referral row (with affiliate status) or false.
	 */
	public static function is_valid_referrer( $referral_code = null ) {
		if ( ! $referral_code ) {
			return false;
		}

		global $wpdb;
		$result = $wpdb->get_row( $wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			"SELECT
			r.referral_id,
			r.affiliate_id,
			r.referral_post_id,
			r.click_counts,
			a.status
			FROM
				{$wpdb->prefix}storeengine_affiliate_referrals r
			LEFT JOIN
				{$wpdb->prefix}storeengine_affiliates a ON r.affiliate_id = a.affiliate_id
			WHERE
				r.referral_code = %s;",
			$referral_code
		), ARRAY_A );

		return $result ?: false;
	}

	public static function update_affiliate_commission( $affiliate_id, $commission_amount ) {
		$report_row = AffiliateReport::get_affiliate_reports( null, $affiliate_id );
		if ( $report_row ) {
			return AffiliateReport::update(
				$affiliate_id,
				[
					'total_commissions' => $report_row['total_commissions'] + $commission_amount,
					'current_balance'   => $report_row['current_balance'] + $commission_amount,
				],
				'affiliate_id'
			);
		}

		return false;
	}

	public static function get_payment_card( $payment_method = '', $minimum_withdraw = 0 ) {
		if ( ! $payment_method ) {
			return;
		}

		$payment_method_classes = sprintf( 'storeengine-withdraw-method%s storeengine-withdraw-method--selected', $payment_method );
		?>
		<label class="<?php echo esc_attr( $payment_method_classes ); ?>" id="<?php echo esc_attr( $payment_method ); ?>-label">
			<h3 class="storeengine-withdraw-method__heading"><?php echo esc_html( self::format_payment_method( $payment_method ) ); ?></h3>
			<p class="storeengine-withdraw-method__subheading">
				<?php
				echo sprintf(
				/* translators: %s) Minimum withdrawal amount. */
					esc_html__( 'Min withdraw %s', 'storeengine' ),
					wp_kses_post( Formatting::price( $minimum_withdraw ) )
				);
				?>
			</p>
			<input name="withdrawMethodType" type="radio" value="<?php echo esc_attr( $payment_method ); ?>" <?php checked( $payment_method, 'paypal' ); ?>>
		</label>
		<?php
	}
}
