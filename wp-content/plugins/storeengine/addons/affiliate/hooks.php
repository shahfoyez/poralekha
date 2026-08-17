<?php

namespace StoreEngine\Addons\Affiliate;

use StoreEngine\Addons\Affiliate\Helper as HelperAddon;
use StoreEngine\Addons\Affiliate\models\Affiliate as AffiliateModel;
use StoreEngine\Addons\Affiliate\models\AffiliateReport;
use StoreEngine\Addons\Affiliate\models\Commission;
use StoreEngine\Addons\Affiliate\models\Payout;
use StoreEngine\Addons\Affiliate\Models\Referral;
use StoreEngine\Addons\Affiliate\Settings\Affiliate;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

if ( ! defined('ABSPATH') ) {
	exit;
}

class Hooks {
	public static function init() {
		$self = new self();
		add_action( 'storeengine/frontend/dashboard_affiliate-partner_endpoint', [ $self, 'dashboard_affiliate_partner_content' ] );
		add_filter( 'storeengine/frontend_dashboard_menu_items', [ $self, 'dashboard_menu_items' ] );
		add_filter( 'storeengine/admin_menu_list', [ $self, 'admin_menu_items' ] );
		add_filter( 'storeengine/api/settings', [ $self, 'integrate_affiliate_settings' ] );
		add_action( 'storeengine/order/payment_status_changed', [ $self, 'auto_approve_commission' ], 10, 2 );

		// Add affiliate pages to tools.
		add_filter( 'storeengine/settings/tools/pages', [ $self, 'add_pages_to_tools' ] );
		add_filter( 'display_post_states', array( $self, 'add_display_post_states' ), 10, 2 );

		// Surface the affiliate profile (website, payout email, promo methods,
		// terms) on the WP admin user-edit screen.
		add_action( 'edit_user_profile', [ $self, 'render_admin_affiliate_profile' ] );
		add_action( 'show_user_profile', [ $self, 'render_admin_affiliate_profile' ] );
	}

	/**
	 * Read-only affiliate profile panel on the WP user-edit screen so admins can
	 * see the details captured at signup (stored as user meta).
	 *
	 * @param \WP_User $user User being edited.
	 */
	public function render_admin_affiliate_profile( $user ) {
		if ( ! ( $user instanceof \WP_User ) || ! user_can( $user, 'storeengine_affiliate' ) ) {
			return;
		}

		$website = get_user_meta( $user->ID, 'storeengine_affiliate_website_url', true );
		$payout  = get_user_meta( $user->ID, 'storeengine_affiliate_payout_email', true );
		$methods = get_user_meta( $user->ID, 'storeengine_affiliate_promotional_methods', true );
		$agreed  = get_user_meta( $user->ID, 'storeengine_affiliate_agreed_terms', true );
		?>
		<h2><?php esc_html_e( 'StoreEngine Affiliate Profile', 'storeengine' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th><label><?php esc_html_e( 'Website / Promotional URL', 'storeengine' ); ?></label></th>
				<td>
					<?php
					if ( $website ) {
						printf( '<a href="%1$s" target="_blank" rel="noopener noreferrer">%1$s</a>', esc_url( $website ) );
					} else {
						echo '&mdash;';
					}
					?>
				</td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Payout Email', 'storeengine' ); ?></label></th>
				<td><?php echo $payout ? esc_html( $payout ) : '&mdash;'; ?></td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Promotional Methods', 'storeengine' ); ?></label></th>
				<td><?php echo $methods ? nl2br( esc_html( $methods ) ) : '&mdash;'; ?></td>
			</tr>
			<tr>
				<th><label><?php esc_html_e( 'Agreed to Terms', 'storeengine' ); ?></label></th>
				<td><?php echo $agreed ? esc_html( $agreed ) : esc_html__( 'No', 'storeengine' ); ?></td>
			</tr>
		</table>
		<?php
	}

	public function auto_approve_commission( Order $order, string $status ) {
		// Reverse the commission when an order is refunded / cancelled / failed,
		// independent of the auto-commission setting.
		if ( in_array( $status, [ 'refunded', 'cancelled', 'failed' ], true ) ) {
			$this->reverse_commission_for_order( $order );

			return;
		}

		// Previously a single `&&` guard let a paid order auto-approve even with
		// auto-commission off, and let non-paid statuses approve when it was on.
		// Both conditions must hold: feature enabled AND the order is paid.
		if ( ! HelperAddon::get_affiliate_setting( 'allow_auto_commission' ) ) {
			return;
		}

		if ( 'paid' !== $status ) {
			return;
		}

		$commission = Commission::get_commission( [ 'order_id' => $order->get_id() ] );

		if ( ! $commission || empty( $commission['commission_id'] ) ) {
			return;
		}

		// Only a still-pending commission should be approved; never re-credit
		// the balance for one already approved or paid.
		if ( 'pending' !== ( $commission['status'] ?? '' ) ) {
			return;
		}

		$update = Commission::update( $commission['commission_id'], [ 'status' => 'approved' ] );

		if ( $update && ! is_wp_error( $update ) ) {
			HelperAddon::update_affiliate_commission( $commission['affiliate_id'], $commission['commission_amount'] );

			// Fires whenever a commission transitions into `approved` (auto here,
			// manual in Commission ajax). The affiliate-approved email listens on
			// this to congratulate/motivate the affiliate.
			do_action( 'storeengine/addons/affiliate/commission_approved', (int) $commission['commission_id'] );
		}
	}

	/**
	 * Reverse a commission when its order is refunded / cancelled / failed.
	 *
	 * An approved commission is clawed back from the affiliate's totals and
	 * available balance. A commission already paid out is flagged rejected but
	 * the withdrawn funds are left untouched (they cannot be reclaimed here).
	 */
	protected function reverse_commission_for_order( Order $order ) {
		$commission = Commission::get_commission( [ 'order_id' => $order->get_id() ] );

		if ( ! $commission || empty( $commission['commission_id'] ) ) {
			return;
		}

		if ( 'rejected' === ( $commission['status'] ?? '' ) ) {
			return;
		}

		if ( 'approved' === ( $commission['status'] ?? '' ) ) {
			AffiliateReport::reverse_commission( (int) $commission['affiliate_id'], (float) $commission['commission_amount'] );
		}

		Commission::update( $commission['commission_id'], [ 'status' => 'rejected' ] );

		do_action( 'storeengine/addons/affiliate/commission_reversed', $commission['commission_id'], $order );
	}

	public function dashboard_menu_items( array $items ): array {
		return array_merge( $items, [
			'affiliate-partner' => [
				'label'    => __( 'Affiliate', 'storeengine' ),
				'icon'     => 'storeengine-icon storeengine-icon--affiliate',
				'public'   => current_user_can( 'storeengine_affiliate' ),
				'priority' => 15,
				'group'    => 'earnings',
			],
			'payment-settings'  => [
				'label'    => __( 'Payment Settings', 'storeengine' ),
				'public'   => false,
				'priority' => 16,
			],
		] );
	}

	public function admin_menu_items( array $items ): array {
		return array_merge( $items, [
			STOREENGINE_PLUGIN_SLUG . '-affiliates' => [
				'title'      => __( 'Affiliates', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 70,
				'sub_items'  => [
					[
						'slug'  => '',
						'title' => __( 'All Affiliates', 'storeengine' ),
					],
					[
						'slug'  => 'commissions',
						'title' => __( 'Commissions', 'storeengine' ),
					],
				],
			],
		] );
	}

	public function dashboard_affiliate_partner_content() {
		$user_id        = get_current_user_id();
		$affiliate_data = AffiliateModel::get_affiliates( [ 'user_id' => $user_id ] );

		// Self-heal: anyone who can reach this tab holds the affiliate role, but
		// may have no affiliate record yet (e.g. admins auto-granted the role by
		// Role::add_affiliate_role). Provision one so their referral link shows.
		if ( empty( $affiliate_data ) && current_user_can( 'storeengine_affiliate' ) ) {
			$status  = current_user_can( 'manage_storeengine_affiliate' ) ? 'active' : 'pending';
			$ensured = AffiliateModel::ensure_affiliate_for_user( $user_id, $status );
			if ( ! is_wp_error( $ensured ) ) {
				$affiliate_data = $ensured;
			}
		}

		// Rejected / suspended affiliates are locked out of the earnings dashboard
		// — they cannot earn or track (referral tracking is gated on 'active'), so
		// the dashboard would only show a dead referral link. Show a notice instead.
		$affiliate_status = is_array( $affiliate_data ) ? ( $affiliate_data['status'] ?? '' ) : '';
		if ( in_array( $affiliate_status, [ AffiliateModel::STATUS_REJECTED, AffiliateModel::STATUS_SUSPENDED ], true ) ) {
			Template::get_template(
				'frontend-dashboard/pages/affiliate-locked.php',
				[ 'status' => $affiliate_status ]
			);
			return;
		}

		// Ensure the affiliate has a referral code (referral row). Covers both a
		// freshly self-healed record and any legacy affiliate missing its row.
		if ( ! empty( $affiliate_data['affiliate_id'] ) && empty( $affiliate_data['referral_code'] ) ) {
			Referral::save( [
				'affiliate_id'     => $affiliate_data['affiliate_id'],
				'referral_post_id' => Helper::get_settings( 'shop_page' ),
			] );
			$affiliate_data = AffiliateModel::get_affiliates( [ 'user_id' => $user_id ] );
		}

		$payment_history         = Payout::get_payouts([ 'user_id' => $user_id ]);
		$total_earning           = $affiliate_data ? $affiliate_data['total_commissions'] : 0;
		$available_balance       = $affiliate_data ? $affiliate_data['current_balance'] : 0;
		$selected_payment_method = get_user_meta( $user_id, 'storeengine_affiliate_withdraw_method_type', true ) ?? '';
		$payment_settings_url    = storeengine_get_dashboard_endpoint_url( 'payment-settings' ) ?? '';
		$affiliate_settings      = Affiliate::get_settings_saved_data();
		$minimum_withdraw_amount = $affiliate_settings['minimum_withdraw_amount'] ?? 0;
		$show_withdraw_button    = (float) $available_balance >= (float) $minimum_withdraw_amount;

		$referral_url = '';
		if ( ! empty( $affiliate_data['referral_code'] ) ) {
			$referral_url = Referral::create_link( $affiliate_data['referral_code'], Helper::get_settings( 'shop_page' ) );
		}

		Template::get_template(
			'frontend-dashboard/pages/affiliate-partner.php',
			array(
				'total_amount'         => $total_earning,
				'available_amount'     => $available_balance,
				'withdraw_history'     => $payment_history,
				'withdraw_method_type' => $selected_payment_method,
				'current_user_id'      => $user_id,
				'payment_settings_url' => $payment_settings_url,
				'show_withdraw_button' => $show_withdraw_button,
				'referral_url'         => $referral_url,
			)
		);
	}

	public function integrate_affiliate_settings( $settings ) {
		$settings->affiliate = Affiliate::get_settings_saved_data();

		return $settings;
	}

	public function add_pages_to_tools( $pages ) {
		$pages['affiliate_registration_page'] = __( 'Store Affiliate Registration', 'storeengine' );

		return $pages;
	}

	public function add_display_post_states( $post_states, $post ) {
		if ( (int) Helper::get_settings( 'affiliate_registration_page' ) === $post->ID ) {
			$post_states['storeengine_page_for_affiliate_registration'] = __( 'StoreEngine Affiliate Registration Page', 'storeengine' );
		}

		return $post_states;
	}
}
