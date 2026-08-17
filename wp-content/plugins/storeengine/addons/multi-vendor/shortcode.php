<?php

namespace StoreEngine\Addons\MultiVendor;

use StoreEngine\Addons\MultiVendor\Classes\Vendor;
use StoreEngine\Addons\MultiVendor\Classes\Vendors;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Shortcode {

	public static function init() {
		$self = new self();
		add_shortcode( 'storeengine_vendor_signup', [ $self, 'render_signup' ] );
		add_action( 'init', [ $self, 'handle_signup_post' ] );
	}

	public function render_signup(): string {
		$user = wp_get_current_user();

		/**
		 * Filters whether the vendor signup shortcode treats the current user as
		 * an existing vendor.
		 *
		 * Returning `false` forces the signup form to render even for a vendor —
		 * intended for page-builder editors (Elementor, Bricks, …) so the form
		 * is previewable while an admin edits the page. Consumers MUST scope
		 * their override to the builder's edit/preview context.
		 *
		 * @since 2.2.0
		 *
		 * @param bool         $is_vendor Whether the current user already has the vendor role.
		 * @param \WP_User      $user     The current user object.
		 */
		$is_vendor = (bool) apply_filters( 'storeengine/shortcode/vendor_signup_is_vendor', $user && in_array( Role::ROLE, (array) $user->roles, true ), $user );

		if ( $is_vendor ) {
			return self::render_vendor_status_message( (int) $user->ID );
		}

		return \StoreEngine\Utils\Helper::get_template_content(
			'multi-vendor/shortcode/vendor-signup.php'
		);
	}

	protected static function render_vendor_status_message( int $user_id ): string {
		$vendor = new Vendor( $user_id );
		$status = $vendor->exists() ? $vendor->get_status() : Vendor::STATUS_PENDING;

		switch ( $status ) {
			case Vendor::STATUS_PENDING:
				$title = __( 'Your application was submitted', 'storeengine' );
				$body  = __( 'Thanks for applying. Your account is pending review — we\'ll send you an update by email as soon as the team has looked it over.', 'storeengine' );
				$class = 'storeengine-notice--success';
				break;

			case Vendor::STATUS_APPROVED:
				$title = __( 'You are an approved vendor', 'storeengine' );
				$body  = __( 'Your account is approved. Head to your dashboard to manage your store.', 'storeengine' );
				$class = 'storeengine-notice--info';
				break;

			case Vendor::STATUS_SUSPENDED:
				$title = __( 'Your vendor account is suspended', 'storeengine' );
				$body  = __( 'Please contact support if you believe this is a mistake.', 'storeengine' );
				$class = 'storeengine-notice--error';
				break;

			default:
				$title = __( 'You are already registered as a vendor', 'storeengine' );
				$body  = '';
				$class = 'storeengine-notice--info';
		}

		$out  = '<div class="storeengine-vendor-signup">';
		$out .= '<div class="storeengine-notice ' . esc_attr( $class ) . '">';
		$out .= '<h3>' . esc_html( $title ) . '</h3>';
		if ( $body ) {
			$out .= '<p>' . esc_html( $body ) . '</p>';
		}
		$out .= '</div>';
		$out .= '</div>';

		return $out;
	}

	public function handle_signup_post() {
		if ( ! isset( $_POST['storeengine_vendor_signup_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( $_POST['storeengine_vendor_signup_nonce'] ), 'storeengine_vendor_signup' ) ) {
			return;
		}

		$email             = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$store_name        = isset( $_POST['store_name'] ) ? sanitize_text_field( wp_unslash( $_POST['store_name'] ) ) : '';
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Password is passed to wp_insert_user() verbatim; unslashed only, any sanitization would corrupt valid passwords. Nonce verified above.
		$password          = isset( $_POST['password'] ) ? (string) wp_unslash( $_POST['password'] ) : '';
		$first_name        = isset( $_POST['first_name'] ) ? sanitize_text_field( wp_unslash( $_POST['first_name'] ) ) : '';
		$last_name         = isset( $_POST['last_name'] ) ? sanitize_text_field( wp_unslash( $_POST['last_name'] ) ) : '';
		$phone             = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';
		$address           = isset( $_POST['address'] ) ? sanitize_textarea_field( wp_unslash( $_POST['address'] ) ) : '';
		$store_description = isset( $_POST['store_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['store_description'] ) ) : '';
		$business_type     = isset( $_POST['business_type'] ) ? sanitize_key( wp_unslash( $_POST['business_type'] ) ) : 'individual';
		$business_type     = in_array( $business_type, [ 'individual', 'company' ], true ) ? $business_type : 'individual';
		$terms_accepted    = ! empty( $_POST['terms_accepted'] );

		if (
			! is_email( $email )
			|| ! $store_name
			|| ! $store_description
			|| ! $first_name
			|| ! $last_name
			|| ! $phone
			|| ! $address
			|| ! $terms_accepted
			|| strlen( $password ) < 8
		) {
			self::redirect_back( 'invalid' );
		}

		if ( email_exists( $email ) || username_exists( $email ) ) {
			self::redirect_back( 'exists' );
		}

		$user_id = wp_insert_user( [
			'user_login' => $email,
			'user_email' => $email,
			'user_pass'  => $password,
			'first_name' => $first_name,
			'last_name'  => $last_name,
			'role'       => Role::ROLE,
		] );

		if ( is_wp_error( $user_id ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Diagnostic for a failed account creation during vendor signup.
			error_log( '[StoreEngine multi-vendor] wp_insert_user failed: ' . $user_id->get_error_message() );
			self::redirect_back( 'error' );
		}

		$slug = Vendors::unique_slug( $store_name, (int) $user_id );

		$vendor = new Vendor( (int) $user_id );
		$vendor->set_user_id( (int) $user_id );
		$vendor->set_store_name( $store_name );
		$vendor->set_store_slug( $slug );
		$vendor->set_status( Vendor::STATUS_PENDING );
		$vendor->set_payout_email( $email );
		$vendor->set_first_name( $first_name );
		$vendor->set_last_name( $last_name );
		$vendor->set_phone( $phone );
		$vendor->set_address( $address );
		$vendor->set_store_description( $store_description );
		$vendor->set_business_type( $business_type );
		$vendor->set_terms_accepted_at( current_time( 'mysql', 1 ) );

		if ( ! $vendor->save() ) {
			// Roll back the WP user so the email is free for a retry — otherwise
			// the next attempt hits email_exists() with no vendor row to attach to.
			require_once ABSPATH . 'wp-admin/includes/user.php';
			wp_delete_user( (int) $user_id );
			global $wpdb;
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Diagnostic capturing the DB error when a vendor row fails to save after user rollback.
			error_log( '[StoreEngine multi-vendor] vendor save failed for ' . $email . '; rolled back user ' . $user_id . '. DB error: ' . $wpdb->last_error );
			self::redirect_back( 'error' );
		}

		self::notify_admin_of_new_vendor( $vendor, $email );

		do_action( 'storeengine/multi_vendor/vendor_registered', $vendor );

		// Auto-login after signup.
		wp_set_current_user( (int) $user_id );
		wp_set_auth_cookie( (int) $user_id );

		// Land the new vendor on the dashboard root. The status banner injected
		// by Hooks::render_vendor_status_banner shows the "pending review" notice.
		$dashboard_page_id = (int) \StoreEngine\Utils\Helper::get_settings( 'dashboard_page' );
		$dashboard_url     = $dashboard_page_id ? get_permalink( $dashboard_page_id ) : home_url( '/dashboard/' );

		wp_safe_redirect( $dashboard_url );
		exit;
	}

	/**
	 * Always lands the user back on the signup page, even when the browser strips
	 * the Referer header. Prefers the hidden _signup_url field rendered into the
	 * form; falls back to Referer; then home_url().
	 */
	protected static function redirect_back( string $status ): void {
		$target = '';
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Redirect-target read only; the calling signup handler verifies the nonce before this runs, and the value is validated via wp_validate_redirect().
		if ( ! empty( $_POST['_signup_url'] ) ) {
			$candidate = esc_url_raw( wp_unslash( (string) $_POST['_signup_url'] ) );
			if ( $candidate && wp_validate_redirect( $candidate ) ) {
				$target = $candidate;
			}
		}
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( ! $target ) {
			$target = wp_get_referer();
		}
		if ( ! $target ) {
			$target = home_url();
		}
		wp_safe_redirect( add_query_arg( 'storeengine_vendor_signup', $status, $target ) );
		exit;
	}

	protected static function notify_admin_of_new_vendor( Vendor $vendor, string $email ): void {
		$admin_email = get_option( 'admin_email' );
		if ( ! is_email( $admin_email ) ) {
			return;
		}

		$site_name  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$approve_url = admin_url( 'admin.php?page=storeengine-vendors' );

		/* translators: 1: site name, 2: store name */
		$subject = sprintf( __( '[%1$s] New vendor application: %2$s', 'storeengine' ), $site_name, $vendor->get_store_name() );

		$lines = [
			__( 'A new vendor has applied. Details below:', 'storeengine' ),
			'',
			/* translators: %s: vendor store name */
			sprintf( __( 'Store: %s', 'storeengine' ), $vendor->get_store_name() ),
			/* translators: %s: vendor owner full name */
			sprintf( __( 'Owner: %s', 'storeengine' ), $vendor->get_full_name() ),
			/* translators: %s: vendor email address */
			sprintf( __( 'Email: %s', 'storeengine' ), $email ),
			/* translators: %s: vendor phone number */
			sprintf( __( 'Phone: %s', 'storeengine' ), $vendor->get_phone() ),
			/* translators: %s: vendor address */
			sprintf( __( 'Address: %s', 'storeengine' ), $vendor->get_address() ),
			/* translators: %s: vendor business type */
			sprintf( __( 'Business type: %s', 'storeengine' ), $vendor->get_business_type() ),
			/* translators: %s: vendor store description */
			sprintf( __( 'About: %s', 'storeengine' ), $vendor->get_store_description() ),
			/* translators: %s: vendor registration date */
			sprintf( __( 'Registered: %s', 'storeengine' ), $vendor->get_date_registered() ?: '' ),
			'',
			__( 'Review and approve:', 'storeengine' ),
			$approve_url,
		];

		wp_mail( $admin_email, $subject, implode( "\n", $lines ) );
	}
}
