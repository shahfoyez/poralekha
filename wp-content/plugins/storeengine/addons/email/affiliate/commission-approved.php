<?php
/**
 * Affiliate Commission Approved Email.
 *
 * Fires on storeengine/addons/affiliate/commission_approved when a commission is
 * approved (auto on a paid order, or manually by an admin) — a motivating
 * "you just earned a commission" nudge to keep the affiliate promoting.
 */

namespace StoreEngine\Addons\Email\affiliate;

use StoreEngine\Addons\Affiliate\models\Commission;
use StoreEngine\Utils\Formatting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CommissionApproved extends AbstractAffiliateMail {

	const SETTINGS_KEY = 'affiliate_commission_approved';

	protected function register_hooks(): void {
		add_action( 'storeengine/addons/affiliate/commission_approved', [ $this, 'send_mail' ], 10, 1 );
	}

	public static function default_template(): array {
		return [
			'customer' => [
				'is_enable'     => true,
				'email_subject' => __( 'You just earned a commission!', 'storeengine' ),
				'email_heading' => __( 'Nice work — a commission is on its way', 'storeengine' ),
				'email_content' => __(
					'<p>Hi {affiliate_name},</p><p>Great news — a referral you sent to {store_name} converted and your commission of <strong>{commission_amount}</strong> (order {order_id}) has been approved.</p><p>It has been added to your balance. Keep sharing your referral link to earn even more.</p><p>Thanks for promoting {store_name}!</p>',
					'storeengine'
				),
			],
		];
	}

	public function send_mail( $commission_id ): void {
		$settings = $this->get_settings( 'customer' );
		if ( ! is_array( $settings ) || empty( $settings['is_enable'] ) ) {
			return;
		}

		$commission = Commission::get_commission( [ 'commission_id' => (int) $commission_id ] );
		if ( empty( $commission ) || empty( $commission['user_email'] ) ) {
			return;
		}

		$replacements = $this->base_replacements(
			(string) ( $commission['display_name'] ?? '' ),
			(string) $commission['user_email']
		);
		$replacements['{commission_amount}'] = Formatting::price( (float) ( $commission['commission_amount'] ?? 0 ) );
		$replacements['{order_id}']          = '#' . (string) ( $commission['order_id'] ?? '' );

		$this->dispatch( $settings, (string) $commission['user_email'], $replacements );
	}
}
