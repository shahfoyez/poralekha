<?php
namespace ABlocks\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;

class Notice {
	public static function init() {
		$self = new self();
		add_action( 'admin_notices', array( $self, 'admin_offer_notice' ) );
		add_action( 'admin_init', array( $self, 'dismiss_admin_offer_notice' ) );
	}

	public function admin_offer_notice() {
		// Check if dismissed
		if ( ! $this->has_upgrade_to_pro_notice() || get_user_meta( get_current_user_id(), 'ablocks_dismiss_offer_notice', true ) ) {
			return;
		}

		// Build dismissal URL (adds parameter to current page)
		$dismiss_url = add_query_arg(
			array( 'ablocks_dismiss_offer_notice' => '1' ),
			esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) )
		);

		?>
		<div class="notice notice-info ablocks-offer-notice" style="position: relative; display: flex; column-gap: 24px; padding: 24px; border-left-color: #13191b;">
			<div class="ablocks-offer-notice__thumbnail">
				<img src="<?php echo esc_url( ABLOCKS_ASSETS_URL . 'images/logo-shape.svg' ); ?>" alt="logo" style="width: 100px;" />
			</div>
			<div>
				<h2>Upgrade to aBlocks Pro Today!</h2>
				<p>🔥 1 Site Only <strong>$29 LTD</strong> | <strong>$129 Unlimited</strong> LTD </p>
				<p>Apply Code: <code>SpecialDeal</code> to Upgrade Now</p>
				<p>Ends Sept 29</p>
				<a class="button button-primary" href="https://ablocks.pro/pricing/" target="_blank" style="background: #13191b; color: #fff;">Get The Deal</a>
				<a href="<?php echo esc_url( $dismiss_url ); ?>" style="position: absolute; top: 10px; right: 10px;">
					<?php esc_html_e( 'Dismiss', 'ablocks' ); ?>
				</a>
			</div>
		</div>
		<?php
	}

	public function dismiss_admin_offer_notice() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended 
		if ( isset( $_GET['ablocks_dismiss_offer_notice'] ) && $_GET['ablocks_dismiss_offer_notice'] == '1' ) {
			update_user_meta( get_current_user_id(), 'ablocks_dismiss_offer_notice', 1 );

			// Redirect to remove query param
			wp_redirect( remove_query_arg( 'ablocks_dismiss_offer_notice' ) );
			exit;
		}
	}

	public static function has_upgrade_to_pro_notice() {
		if ( Helper::is_active_ablocks_pro() ) {
			return false;
		}

		$saved_time = get_option( 'ablocks_first_install_time' );
		$three_days_ago = Helper::get_time() - ( 150 * DAY_IN_SECONDS );

		if ( $saved_time < $three_days_ago ) {
			return true;
		}

		return false;
	}
}
