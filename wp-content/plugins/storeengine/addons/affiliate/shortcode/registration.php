<?php

namespace StoreEngine\Addons\Affiliate\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Affiliate\models\Affiliate;
use StoreEngine\Addons\Affiliate\Helper as HelperAddon;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

class Registration {

	public function __construct() {
		add_shortcode( 'storeengine_affiliate_application', [ $this, 'registration_form' ] );
		add_action( 'init', [ $this, 'register_shortcode_block' ], 20 );
	}

	/**
	 * Expose the affiliate registration form as a configurable block via the
	 * StoreEngine shortcode → block bridge, so the Affiliate Registration page
	 * (and any other page) can drop it in with editor controls. No-op when the
	 * bridge isn't present; the shortcode still works everywhere.
	 */
	public function register_shortcode_block() {
		if ( ! function_exists( 'storeengine_register_shortcode_block' ) ) {
			return;
		}

		storeengine_register_shortcode_block( [
			'tag'         => 'storeengine_affiliate_application',
			'owner'       => 'storeengine',
			'title'       => __( 'Affiliate Registration', 'storeengine' ),
			'category'    => __( 'StoreEngine', 'storeengine' ),
			'description' => __( 'The affiliate program application / registration form.', 'storeengine' ),
			'icon'        => 'groups',
			'keywords'    => [ 'affiliate', 'registration', 'application', 'referral' ],
			'attributes'  => [
				[
					'name'        => 'form_title',
					'label'       => __( 'Form title', 'storeengine' ),
					'type'        => 'text',
					'default'     => __( 'Affiliate Registration Form', 'storeengine' ),
					'sanitize'    => 'text',
				],
				[
					'name'        => 'button_text',
					'label'       => __( 'Submit button label', 'storeengine' ),
					'type'        => 'text',
					'default'     => __( 'Register', 'storeengine' ),
					'sanitize'    => 'text',
				],
				[
					'name'        => 'alert_text',
					'label'       => __( 'Logged-out message', 'storeengine' ),
					'type'        => 'text',
					'default'     => __( 'Please login to apply for affiliate', 'storeengine' ),
					'group'       => __( 'Advanced', 'storeengine' ),
					'sanitize'    => 'text',
				],
				[
					'name'        => 'terms_url',
					'label'       => __( 'Terms & conditions URL', 'storeengine' ),
					'type'        => 'text',
					'default'     => '',
					'placeholder' => __( 'Uses the Affiliate settings URL', 'storeengine' ),
					'group'       => __( 'Advanced', 'storeengine' ),
					'sanitize'    => 'url',
				],
			],
		] );
	}

	public function registration_form( $atts ) {
		$attributes = shortcode_atts( [
			'form_title'                      => esc_html__( 'Affiliate Registration Form', 'storeengine' ),
			'alert_text'                      => esc_html__( 'Please login to apply for affiliate', 'storeengine' ),
			'button_text'                     => esc_html__( 'Register', 'storeengine' ),
			'first_name_label'                => esc_html__( 'First Name', 'storeengine' ),
			'first_name_placeholder'          => esc_html__( 'First Name', 'storeengine' ),
			'last_name_label'                 => esc_html__( 'Last Name', 'storeengine' ),
			'last_name_placeholder'           => esc_html__( 'Last Name', 'storeengine' ),
			'email_label'                     => esc_html__( 'Email', 'storeengine' ),
			'email_placeholder'               => esc_html__( 'Email', 'storeengine' ),
			// Affiliate profile fields (also collected on the logged-in apply form).
			// Password is auto-generated (a set-password email is sent); payout
			// details are collected later from the dashboard payment settings.
			'website_url_label'               => esc_html__( 'Website / Promotional URL', 'storeengine' ),
			'website_url_placeholder'         => esc_html__( 'https://your-site.com', 'storeengine' ),
			'promotional_methods_label'       => esc_html__( 'How will you promote us?', 'storeengine' ),
			'promotional_methods_placeholder' => esc_html__( 'Blog, social media, email list, paid ads…', 'storeengine' ),
			'terms_label'                     => esc_html__( 'I agree to the affiliate program terms & conditions', 'storeengine' ),
			// Defaults to the Terms & Conditions URL set in Affiliate settings;
			// a shortcode `terms_url` attribute still overrides it.
			'terms_url'                       => (string) HelperAddon::get_affiliate_setting( 'terms_url' ),
			'registration_button_label'       => esc_html__( 'Registration', 'storeengine' ),
			'show_logged_in_message'          => true,
		], $atts );

		/**
		 * Filters whether the affiliate registration shortcode treats the
		 * current visitor as logged in.
		 *
		 * Returning `false` forces the public registration form to render even
		 * when a user is signed in — its purpose is page-builder editors
		 * (Elementor, Bricks, …), where the editing admin is logged in and would
		 * otherwise only ever see the "already logged in" branch. Consumers MUST
		 * scope their override to the builder's edit/preview context; leaving it
		 * on for the real front end would show signed-in visitors the
		 * account-creating form.
		 *
		 * @since 2.2.0
		 *
		 * @param bool  $is_user_logged_in Default WordPress login state.
		 * @param array $attributes        Resolved shortcode attributes.
		 */
		$is_user_logged_in = (bool) apply_filters( 'storeengine/shortcode/affiliate_registration_is_user_logged_in', is_user_logged_in(), $attributes );

		$affiliate_pending = false;
		if ( $is_user_logged_in ) {
			$affiliate_details = Affiliate::get_affiliates( [ 'user_id' => get_current_user_id() ] );
			$affiliate_pending = isset( $affiliate_details['status'] ) && 'pending' === $affiliate_details['status'];
		}

		// Pass the resolved state through so the template reuses this single
		// evaluation instead of re-running the filter (which could drift if a
		// consumer's callback isn't deterministic).
		$attributes = wp_parse_args( $attributes, [
			'affiliate_pending' => $affiliate_pending,
			'is_user_logged_in' => $is_user_logged_in,
		] );

		ob_start();
		Template::get_template( 'affiliate/registration.php', $attributes);
		return apply_filters( 'storeengine_affiliate/templates/shortcode/registration', ob_get_clean() );
	}
}
