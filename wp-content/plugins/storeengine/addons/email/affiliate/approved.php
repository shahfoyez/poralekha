<?php
/**
 * Affiliate Approved Email.
 *
 * Fires on storeengine/addons/affiliate/update_status when an affiliate is
 * moved to 'active' — the congratulations / "you're in" email that hands over
 * the referral code.
 */

namespace StoreEngine\Addons\Email\affiliate;

use StoreEngine\Addons\Affiliate\models\Affiliate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Approved extends AbstractAffiliateMail {

	const SETTINGS_KEY = 'affiliate_approved';

	protected function register_hooks(): void {
		add_action( 'storeengine/addons/affiliate/update_status', [ $this, 'send_mail' ], 10, 2 );
	}

	public static function default_template(): array {
		return [
			'customer' => [
				'is_enable'     => true,
				'email_subject' => __( 'You\'re approved — welcome to the affiliate program', 'storeengine' ),
				'email_heading' => __( 'Your affiliate account is active', 'storeengine' ),
				'email_content' => __(
					'<p>Hi {affiliate_name},</p><p>Good news — your application to the {store_name} affiliate program has been approved and your account is now active.</p><p>Your referral code: <strong>{referral_code}</strong></p><p>Log in to your dashboard to grab your referral link and start earning commissions.</p><p>Welcome aboard.</p>',
					'storeengine'
				),
			],
		];
	}

	public function send_mail( $affiliate_id, $status ): void {
		if ( 'active' !== (string) $status ) {
			return;
		}

		$settings = $this->get_settings( 'customer' );
		if ( ! is_array( $settings ) || empty( $settings['is_enable'] ) ) {
			return;
		}

		$affiliate = Affiliate::get_affiliates( [ 'affiliate_id' => (int) $affiliate_id ] );
		if ( empty( $affiliate ) || empty( $affiliate['user_email'] ) ) {
			return;
		}

		$replacements = $this->base_replacements(
			(string) ( $affiliate['display_name'] ?? '' ),
			(string) $affiliate['user_email']
		);
		$replacements['{referral_code}'] = esc_html( (string) ( $affiliate['referral_code'] ?? '' ) );

		$this->dispatch( $settings, (string) $affiliate['user_email'], $replacements );
	}
}
