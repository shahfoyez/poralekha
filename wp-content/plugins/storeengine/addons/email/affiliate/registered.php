<?php
/**
 * Affiliate Registered Email.
 *
 * Fires on storeengine/addons/affiliate/after_registration — sends the new
 * affiliate a "we received your application" acknowledgement. Affiliates are
 * created with status 'pending', so this is the application receipt; approval
 * gets its own email (affiliate/approved.php).
 */

namespace StoreEngine\Addons\Email\affiliate;

use StoreEngine\Addons\Affiliate\models\Affiliate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Registered extends AbstractAffiliateMail {

	const SETTINGS_KEY = 'affiliate_registered';

	protected function register_hooks(): void {
		add_action( 'storeengine/addons/affiliate/after_registration', [ $this, 'send_mail' ], 10, 1 );
	}

	public static function default_template(): array {
		return [
			'customer' => [
				'is_enable'     => true,
				'email_subject' => __( 'We received your affiliate application', 'storeengine' ),
				'email_heading' => __( 'Application received', 'storeengine' ),
				'email_content' => __(
					'<p>Hi {affiliate_name},</p><p>Thanks for applying to the {store_name} affiliate program. Your application has been received and is now under review.</p><p>We\'ll email you again as soon as it\'s approved and your referral link is ready.</p><p>Thanks for partnering with us.</p>',
					'storeengine'
				),
			],
		];
	}

	public function send_mail( $affiliate_id ): void {
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
