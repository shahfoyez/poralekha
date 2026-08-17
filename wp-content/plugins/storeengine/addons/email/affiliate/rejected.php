<?php
/**
 * Affiliate Rejected Email.
 *
 * Fires on storeengine/addons/affiliate/update_status when an affiliate is
 * moved to 'rejected' — the "application not approved" notice.
 */

namespace StoreEngine\Addons\Email\affiliate;

use StoreEngine\Addons\Affiliate\models\Affiliate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Rejected extends AbstractAffiliateMail {

	const SETTINGS_KEY = 'affiliate_rejected';

	protected function register_hooks(): void {
		add_action( 'storeengine/addons/affiliate/update_status', [ $this, 'send_mail' ], 10, 2 );
	}

	public static function default_template(): array {
		return [
			'customer' => [
				'is_enable'     => true,
				'email_subject' => __( 'Update on your affiliate application', 'storeengine' ),
				'email_heading' => __( 'Your affiliate application', 'storeengine' ),
				'email_content' => __(
					'<p>Hi {affiliate_name},</p><p>Thank you for your interest in the {store_name} affiliate program. After reviewing your application, we\'re unable to approve it at this time.</p><p>If you believe this was a mistake or would like more information, please reply to this email.</p>',
					'storeengine'
				),
			],
		];
	}

	public function send_mail( $affiliate_id, $status ): void {
		if ( Affiliate::STATUS_REJECTED !== (string) $status ) {
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
