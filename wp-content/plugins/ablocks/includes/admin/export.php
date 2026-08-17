<?php

namespace ABlocks\admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Admin\Settings\Base;
use ABlocks\Helper;

class Export {

	public static function init() {
		$self = new self();
		add_action( 'rss2_head', [ $self, 'add_options_to_rss' ] );
		add_action( 'export_filters', [ $self, 'add_patterns_radio' ] );
		add_action( 'init', [ $self, 'allow_template_export' ] );
	}

	/**
	 * Add options to WXR.
	 *
	 * @return void
	 */
	public function add_options_to_rss() {
		global $pagenow;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Don't need nonce verification here, we're just checking if content's value.
		if ( 'export.php' !== $pagenow || ! isset( $_GET['content'] ) || 'all' !== sanitize_text_field( wp_unslash( $_GET['content'] ) ) ) {
			return;
		}

		$default_settings = Base::get_default_data();
		$options          = array(
			'show_on_front'                        => get_option( 'show_on_front', 'posts' ),
			'page_on_front'                        => get_option( 'page_on_front', 0 ),
			'page_for_posts'                       => get_option( 'page_for_posts', 0 ),
			'ablocks_login_page'                   => Helper::get_settings( 'login_page', 0 ),
			'ablocks_registration_page'            => Helper::get_settings( 'registration_page', 0 ),
			'ablocks_forget_password_page'         => Helper::get_settings( 'forget_password_page', 0 ),
			'ablocks_default_container_width'      => Helper::get_settings( 'default_container_width', $default_settings['default_container_width'] ),
			'ablocks_container_padding'            => Helper::get_settings( 'container_padding', $default_settings['container_padding'] ),
			'ablocks_container_element_gap'        => Helper::get_settings( 'container_element_gap', $default_settings['container_element_gap'] ),
			'ablocks_global_color'                 => $this->process_ablocks_json_data( 'global_color' ),
			'ablocks_global_typography'            => $this->process_ablocks_json_data( 'global_typography' ),
			'ablocks_global_font_family_fallback'  => Helper::get_settings( 'global_font_family_fallback', $default_settings['global_font_family_fallback'] ),
			'ablocks_global_body_text_color'       => Helper::get_settings( 'global_body_text_color', $default_settings['global_body_text_color'] ),
			'ablocks_global_body_typography'       => $this->process_ablocks_json_data( 'global_body_typography' ),
			'ablocks_global_body_paragraph_space'  => $this->process_ablocks_json_data( 'global_body_paragraph_space' ),
			'ablocks_global_link_color'            => Helper::get_settings( 'global_link_color', $default_settings['global_link_color'] ),
			'ablocks_global_link_hover_color'      => Helper::get_settings( 'global_link_hover_color', $default_settings['global_link_hover_color'] ),
			'ablocks_global_link_typography'       => $this->process_ablocks_json_data( 'global_link_typography' ),
			'ablocks_global_link_hover_typography' => $this->process_ablocks_json_data( 'global_link_hover_typography' ),
			'ablocks_global_h1_color'              => Helper::get_settings( 'global_h1_color', $default_settings['global_h1_color'] ),
			'ablocks_global_h1_typography'         => $this->process_ablocks_json_data( 'global_h1_typography' ),
			'ablocks_global_h2_color'              => Helper::get_settings( 'global_h2_color', $default_settings['global_h2_color'] ),
			'ablocks_global_h2_typography'         => $this->process_ablocks_json_data( 'global_h2_typography' ),
			'ablocks_global_h3_color'              => Helper::get_settings( 'global_h3_color', $default_settings['global_h3_color'] ),
			'ablocks_global_h3_typography'         => $this->process_ablocks_json_data( 'global_h3_typography' ),
			'ablocks_global_h4_color'              => Helper::get_settings( 'global_h4_color', $default_settings['global_h4_color'] ),
			'ablocks_global_h4_typography'         => $this->process_ablocks_json_data( 'global_h4_typography' ),
			'ablocks_global_h5_color'              => Helper::get_settings( 'global_h5_color', $default_settings['global_h5_color'] ),
			'ablocks_global_h5_typography'         => $this->process_ablocks_json_data( 'global_h5_typography' ),
			'ablocks_global_h6_color'              => Helper::get_settings( 'global_h6_color', $default_settings['global_h6_color'] ),
			'ablocks_global_h6_typography'         => $this->process_ablocks_json_data( 'global_h6_typography' ),
			'ablocks_frontend_dashboard_page'      => $this->process_ablocks_json_data( 'frontend_dashboard_page' ),
			'ablocks_frontend_dashboard_sub_pages' => base64_encode( get_option( 'ablocks_frontend_dashboard_sub_pages', '' ) )
		);

		if ( class_exists( 'StoreEngine' ) ) {
			$options['storeengine_shop_page']                   = \StoreEngine\Utils\Helper::get_settings( 'shop_page' );
			$options['storeengine_cart_page']                   = \StoreEngine\Utils\Helper::get_settings( 'cart_page' );
			$options['storeengine_checkout_page']               = \StoreEngine\Utils\Helper::get_settings( 'checkout_page' );
			$options['storeengine_thankyou_page']               = \StoreEngine\Utils\Helper::get_settings( 'thankyou_page' );
			$options['storeengine_dashboard_page']              = \StoreEngine\Utils\Helper::get_settings( 'dashboard_page' );
			$options['storeengine_membership_pricing_page']     = \StoreEngine\Utils\Helper::get_settings( 'membership_pricing_page' );
			$options['storeengine_affiliate_registration_page'] = \StoreEngine\Utils\Helper::get_settings( 'affiliate_registration_page' );
		}

		if ( class_exists( 'Academy' ) ) {
			$options['academy_frontend_dashboard_page']      = \Academy\Helper::get_settings( 'frontend_dashboard_page' );
			$options['academy_frontend_student_reg_page']    = \Academy\Helper::get_settings( 'frontend_student_reg_page' );
			$options['academy_password_reset_page']          = \Academy\Helper::get_settings( 'password_reset_page' );
			$options['academy_lessons_page']                 = \Academy\Helper::get_settings( 'lessons_page' );
			$options['academy_course_page']                  = \Academy\Helper::get_settings( 'course_page' );
			$options['academy_frontend_instructor_reg_page'] = \Academy\Helper::get_settings( 'frontend_instructor_reg_page' );
			$options['academy_tutor_booking_page']           = \Academy\Helper::get_settings( 'tutor_booking_page' );
		}

		$custom_data_xml = '<ablocks_options>';
		foreach ( $options as $key => $value ) {
			$custom_data_xml .= sprintf(
				'<%1$s>%2$s</%1$s>',
				esc_xml( $key ),
				esc_xml( $value )
			);
		}
		$custom_data_xml .= '</ablocks_options>';

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Want to put valid xml in RSS head.
		echo $custom_data_xml;
	}

	private function process_ablocks_json_data( string $key ): string {
		$default_settings = Base::get_default_data();

		return base64_encode( wp_json_encode( Helper::get_settings( $key, $default_settings[ $key ] ) ) );
	}

	/**
	 * Display "Patterns" radio in export page.
	 *
	 * @return void
	 */
	public function add_patterns_radio() {
		?>
		<p>
			<label>
				<input type="radio" name="content" value="wp_block"/>
				<?php esc_html_e( 'Patterns', 'ablocks' ); ?>
			</label>
		</p>
		<?php
	}

	public function allow_template_export() {
		global $wp_post_types;

		if ( isset( $wp_post_types['wp_template'] ) ) {
			$wp_post_types['wp_template']->can_export = true;
		}

		if ( isset( $wp_post_types['wp_template_part'] ) ) {
			$wp_post_types['wp_template_part']->can_export = true;
		}
	}

}
