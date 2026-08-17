<?php

namespace StoreEngine\Addons\MultiVendor;

use StoreEngine\Addons\MultiVendor\Classes\Vendor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers the "Vendors" entry in the StoreEngine admin SPA menu.
 *
 * The list view is rendered by the React BackendDashboard. When the URL has
 * `?page=storeengine-vendors&details=<user_id>`, a PHP-only detail view is
 * rendered instead (no React rebuild needed) so admins can review the full
 * application submitted by a vendor.
 */
class Admin {

	const MENU_SLUG = 'storeengine-vendors';

	public static function init() {
		$self = new self();
		add_filter( 'storeengine/admin_menu_list', [ $self, 'register_menu' ] );
		add_action( 'admin_init', [ $self, 'maybe_handle_details_action' ] );
		add_action( 'all_admin_notices', [ $self, 'maybe_render_details_view' ] );
	}

	public function register_menu( array $menu_items ): array {
		$menu_items[ self::MENU_SLUG ] = [
			'title'      => __( 'Vendors', 'storeengine' ),
			'capability' => 'manage_options',
			'priority'   => 35,
		];

		// The shared "Withdrawals" menu (vendor + affiliate payouts) is no
		// longer registered here. It is owned centrally by
		// StoreEngine\Admin\Menu::inject_withdrawals_menu_item() so it stays
		// reachable when this addon is off but the affiliate addon is on.
		return $menu_items;
	}

	protected function is_details_request(): bool {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		return is_admin()
			&& isset( $_GET['page'], $_GET['details'] )
			&& self::MENU_SLUG === $_GET['page']
			&& (int) $_GET['details'] > 0;
		// phpcs:enable
	}

	public function maybe_handle_details_action(): void {
		if ( ! $this->is_details_request() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( empty( $_POST['storeengine_vendor_details_action'] ) ) {
			return;
		}
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['_wpnonce'] ), 'storeengine_vendor_details_action' ) ) {
			return;
		}

		$user_id = isset( $_GET['details'] ) ? (int) $_GET['details'] : 0;
		$vendor  = new Vendor( $user_id );
		if ( ! $vendor->exists() ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['storeengine_vendor_details_action'] ) );
		// Defence in depth: never let a caller approve their own vendor account.
		if ( 'approve' === $action && get_current_user_id() === $user_id ) {
			return;
		}
		if ( 'approve' === $action ) {
			$vendor->approve();
			wp_safe_redirect( add_query_arg( [ 'page' => self::MENU_SLUG, 'details' => $user_id, 'updated' => 'approved' ], admin_url( 'admin.php' ) ) );
			exit;
		}
		if ( 'suspend' === $action ) {
			$vendor->suspend();
			wp_safe_redirect( add_query_arg( [ 'page' => self::MENU_SLUG, 'details' => $user_id, 'updated' => 'suspended' ], admin_url( 'admin.php' ) ) );
			exit;
		}
	}

	public function maybe_render_details_view(): void {
		if ( ! $this->is_details_request() ) {
			return;
		}
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only, capability-gated (manage_options) admin display view; no state change.
		$user_id = isset( $_GET['details'] ) ? (int) $_GET['details'] : 0;
		$vendor  = new Vendor( $user_id );
		$user    = get_userdata( $user_id );

		// Hide the React SPA mount, render our own view in its place.
		echo '<style>#storeengine-admin{display:none !important;}</style>';

		$back_url = add_query_arg( [ 'page' => self::MENU_SLUG ], admin_url( 'admin.php' ) );

		echo '<div class="wrap storeengine-vendor-details" style="background:#fff;padding:24px;border:1px solid #dcdcde;border-radius:6px;max-width:900px;">';

		printf(
			'<p><a href="%s" class="button">%s</a></p>',
			esc_url( $back_url ),
			esc_html__( '← Back to vendors list', 'storeengine' )
		);

		if ( ! $vendor->exists() || ! $user ) {
			echo '<h1>' . esc_html__( 'Vendor not found', 'storeengine' ) . '</h1>';
			echo '</div>';
			return;
		}

		// Success notice after approve/suspend.
		if ( ! empty( $_GET['updated'] ) ) {
			$updated = sanitize_key( wp_unslash( $_GET['updated'] ) );
			$msg     = 'approved' === $updated
				? __( 'Vendor approved.', 'storeengine' )
				: ( 'suspended' === $updated ? __( 'Vendor suspended.', 'storeengine' ) : '' );
			if ( $msg ) {
				echo '<div class="notice notice-success"><p>' . esc_html( $msg ) . '</p></div>';
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		echo '<h1 style="margin-top:0;">' . esc_html( $vendor->get_store_name() ) . '</h1>';

		echo '<p><strong>' . esc_html__( 'Status:', 'storeengine' ) . '</strong> ';
		echo '<span class="storeengine-vendors__status storeengine-vendors__status--' . esc_attr( $vendor->get_status() ) . '">';
		echo esc_html( $vendor->get_status() );
		echo '</span></p>';

		$rows = [
			__( 'Store slug', 'storeengine' )       => $vendor->get_store_slug(),
			__( 'Owner email', 'storeengine' )      => $user->user_email,
			__( 'Owner name', 'storeengine' )       => trim( $user->first_name . ' ' . $user->last_name ) ?: $vendor->get_full_name(),
			__( 'Phone', 'storeengine' )            => $vendor->get_phone(),
			__( 'Address', 'storeengine' )          => $vendor->get_address(),
			__( 'Business type', 'storeengine' )    => $vendor->get_business_type(),
			__( 'About the store', 'storeengine' )  => $vendor->get_store_description(),
			__( 'Payout email', 'storeengine' )     => $vendor->get_payout_email(),
			__( 'Commission rate', 'storeengine' )  => null === $vendor->get_commission_rate() ? __( 'Global default', 'storeengine' ) : $vendor->get_commission_rate() . ( 'flat' === $vendor->get_commission_type() ? '' : '%' ),
			__( 'Registered at', 'storeengine' )    => $vendor->get_date_registered(),
			__( 'Approved at', 'storeengine' )      => $vendor->get_date_approved() ?: '—',
			__( 'Terms accepted at', 'storeengine' ) => $vendor->get_terms_accepted_at() ?: '—',
		];

		echo '<table class="widefat striped" style="margin:16px 0;"><tbody>';
		foreach ( $rows as $label => $value ) {
			$value = (string) $value;
			printf(
				'<tr><th style="width:200px;text-align:left;">%s</th><td>%s</td></tr>',
				esc_html( $label ),
				$value !== '' ? wp_kses_post( nl2br( esc_html( $value ) ) ) : '<em>—</em>'
			);
		}
		echo '</tbody></table>';

		echo '<form method="post" style="display:inline-block;margin-right:8px;">';
		wp_nonce_field( 'storeengine_vendor_details_action' );
		if ( ! $vendor->is_approved() ) {
			echo '<button type="submit" name="storeengine_vendor_details_action" value="approve" class="button button-primary">' . esc_html__( 'Approve vendor', 'storeengine' ) . '</button> ';
		}
		if ( Vendor::STATUS_SUSPENDED !== $vendor->get_status() ) {
			echo '<button type="submit" name="storeengine_vendor_details_action" value="suspend" class="button">' . esc_html__( 'Suspend vendor', 'storeengine' ) . '</button>';
		}
		echo '</form>';

		echo '</div>';
	}
}
