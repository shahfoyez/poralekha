<?php
/**
 * Affiliate Suspended Email.
 *
 * Fires on storeengine/addons/affiliate/update_status when an affiliate is
 * moved to 'suspended' — the "your account is paused" notice.
 */

namespace StoreEngine\Addons\Email\affiliate;

use StoreEngine\Addons\Affiliate\models\Affiliate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Suspended extends AbstractAffiliateMail {

	const SETTINGS_KEY = 'affiliate_suspended';

	protected function register_hooks(): void {
		add_action( 'storeengine/addons/affiliate/update_status', [ $this, 'send_mail' ], 10, 2 );
	}

	public static function default_template(): array {
		return [
			'customer' => [
				'is_enable'     => true,
				'email_subject' => __( 'Your affiliate account has been suspended', 'storeengine' ),
				'email_heading' => __( 'Your affiliate account is suspended', 'storeengine' ),
				'email_content' => __(
					'<p>Hi {affiliate_name},</p><p>Your account in the {store_name} affiliate program has been suspended. While suspended, your referral links will not track new referrals and no new commissions will be earned.</p><p>If you have questions or believe this was a mistake, please reply to this email.</p>',
					'storeengine'
				),
			],
		];
	}

	public function send_mail( $affiliate_id, $status ): void {
		if ( Affiliate::STATUS_SUSPENDED !== (string) $status ) {
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

		$this->dispatch( $settings, (string) $affiliate['user_email'], $replacements );
	}
}
