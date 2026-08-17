<?php
/**
 * Affiliate Payout Completed Email.
 *
 * Fires on storeengine/addons/affiliate/update_payout_status when a payout is
 * marked 'completed' — tells the affiliate their money has been sent, with the
 * amount, method and transaction reference.
 */

namespace StoreEngine\Addons\Email\affiliate;

use StoreEngine\Addons\Affiliate\models\Payout;
use StoreEngine\Utils\Formatting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PayoutCompleted extends AbstractAffiliateMail {

	const SETTINGS_KEY = 'affiliate_payout_completed';

	protected function register_hooks(): void {
		add_action( 'storeengine/addons/affiliate/update_payout_status', [ $this, 'send_mail' ], 10, 2 );
	}

	public static function default_template(): array {
		return [
			'customer' => [
				'is_enable'     => true,
				'email_subject' => __( 'Your affiliate payout has been sent', 'storeengine' ),
				'email_heading' => __( 'Payout completed', 'storeengine' ),
				'email_content' => __(
					'<p>Hi {affiliate_name},</p><p>Your affiliate payout has been processed and sent.</p><p><strong>Amount:</strong> {payout_amount}<br/><strong>Method:</strong> {payout_method}<br/><strong>Reference:</strong> {payout_transaction_id}</p><p>Thanks for being part of the {store_name} affiliate program.</p>',
					'storeengine'
				),
			],
		];
	}

	public function send_mail( $payout_id, $status ): void {
		if ( 'completed' !== (string) $status ) {
			return;
		}

		$settings = $this->get_settings( 'customer' );
		if ( ! is_array( $settings ) || empty( $settings['is_enable'] ) ) {
			return;
		}

		$payout = Payout::get_payouts( [ 'payout_id' => (int) $payout_id ] );
		if ( empty( $payout ) || empty( $payout['user_email'] ) ) {
			return;
		}

		$replacements = $this->base_replacements(
			(string) ( $payout['display_name'] ?? '' ),
			(string) $payout['user_email']
		);
		$replacements['{payout_amount}']         = Formatting::price( (float) ( $payout['payout_amount'] ?? 0 ) );
		$replacements['{payout_method}']         = esc_html( (string) ( $payout['payment_method'] ?? '' ) );
		$replacements['{payout_transaction_id}'] = esc_html( (string) ( $payout['transaction_id'] ?? '' ) );

		$this->dispatch( $settings, (string) $payout['user_email'], $replacements );
	}
}
