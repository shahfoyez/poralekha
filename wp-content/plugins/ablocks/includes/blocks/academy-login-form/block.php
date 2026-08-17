<?php
namespace ABlocks\Blocks\AcademyLoginForm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Helper;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Color;


class Block extends BlockBaseAbstract {
	protected $block_name = 'academy-login-form';

	public function __construct() {
		parent::__construct();

		add_filter( 'academy/shortcode/login_form_is_user_logged_in', [ $this, 'force_showing_login_form_in_editor' ] );

	}

	public function force_showing_login_form_in_editor( $flag ) {
		if ( Helper::is_gutenberg_editor() ) {
			return false;
		}
		return $flag;
	}

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-login-form-wrapper .academy-login-form .academy-form-group button',
			$this->get_login_form_button_css( $attributes ),
			$this->get_login_form_button_css( $attributes, 'Tablet' ),
			$this->get_login_form_button_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-login-form-wrapper .academy-login-form .academy-form-group button:hover',
			$this->get_login_btn_hover_css( $attributes ),
			$this->get_login_btn_hover_css( $attributes, 'Tablet' ),
			$this->get_login_btn_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-login-form-wrapper .academy-login-form-info,
			{{WRAPPER}} .academy-login-form-wrapper .academy-login-form-info a ',
			$this->get_login_form_footer_css( $attributes ),
			$this->get_login_form_footer_css( $attributes, 'Tablet' ),
			$this->get_login_form_footer_css( $attributes, 'Mobile' )
		);

		$form_title_desktop_css = $this->get_form_title_css( $attributes );
		if ( ! empty( $attributes['title_color'] ) ) {
			$form_title_desktop_css['color'] = $attributes['title_color'];
		}
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-login-form-wrapper .academy-login-form-heading',
			$this->get_form_title_css( $attributes ),
			$this->get_form_title_css( $attributes, 'Tablet' ),
			$this->get_form_title_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-login-form-wrapper .academy-login-form-heading:hover',
			$this->form_title_desktop_hover_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-login-form-wrapper .academy-login-form label,
			{{WRAPPER}} .academy-login-form-wrapper .academy-login-form a',
			$this->get_input_field_label_css( $attributes ),
			$this->get_input_field_label_css( $attributes, 'Tablet' ),
			$this->get_input_field_label_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-login-form-wrapper .academy-login-form label:hover,
			{{WRAPPER}} .academy-login-form-wrapper .academy-login-form a:hover',
			$this->get_input_field_label_hover_css( $attributes ),
			$this->get_input_field_label_hover_css( $attributes, 'Tablet' ),
			$this->get_input_field_label_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-login-form-wrapper .academy-login-form .academy-form-group input',
			$this->get_input_field_css( $attributes ),
			$this->get_input_field_css( $attributes, 'Tablet' ),
			$this->get_input_field_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-login-form-wrapper .academy-login-form .academy-form-group input::placeholder',
			$this->get_input_field_placeholder_css( $attributes ),
			$this->get_input_field_placeholder_css( $attributes, 'Tablet' ),
			$this->get_input_field_placeholder_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-login-form-wrapper',
			$this->get_form_card_css( $attributes ),
			$this->get_form_card_css( $attributes, 'Tablet' ),
			$this->get_form_card_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-login-form-wrapper:hover',
			$this->get_form_card_hover_css( $attributes ),
			$this->get_form_card_hover_css( $attributes, 'Tablet' ),
			$this->get_form_card_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_login_form_button_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['login_btn_typographyGlobal'] ) ? $attributes['login_btn_typographyGlobal'] : '';
		$login_form_button_typography_css = isset( $attributes['login_btn_typography'] ) ? $attributes['login_btn_typography'] : [];
		return array_merge(
			Typography::get_css( $login_form_button_typography_css, '', $device, $typographyValueGlobal ),
			[ 'color' => Color::get_css( isset( $attributes['login_btn_color'] ) ? $attributes['login_btn_color'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['login_btn_bg_color'] ) ? $attributes['login_btn_bg_color'] : '' ) ]
		);
	}

	public function get_login_form_footer_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['form_footer_title_typographyGlobal'] ) ? $attributes['form_footer_title_typographyGlobal'] : '';
		$login_form_footer_typography_css = isset( $attributes['form_footer_title_typography'] ) ? $attributes['form_footer_title_typography'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['form_footer_title_color'] ) ? $attributes['form_footer_title_color'] : '' ) ],
			Typography::get_css( $login_form_footer_typography_css, '', $device, $typographyValueGlobal ),
		);
	}

	public function get_form_title_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['title_typographyGlobal'] ) ? $attributes['title_typographyGlobal'] : '';
		$form_title_typography_css  = isset( $attributes['title_typography'] ) ? $attributes['title_typography'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['title_color'] ) ? $attributes['title_color'] : '' ) ],
			Typography::get_css( $form_title_typography_css, '', $device, $typographyValueGlobal ),
		);
	}
	public function form_title_desktop_hover_css( $attributes ) {
		return [ 'color' => Color::get_css( isset( $attributes['title_hover_color'] ) ? $attributes['title_hover_color'] : '' ) ];

	}

	public function get_course_card_title_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['title_typographyGlobal'] ) ? $attributes['title_typographyGlobal'] : '';
		$course_title_typography_css = isset( $attributes['title_typography'] ) ? $attributes['title_typography'] : [];
		return array_merge(
			Typography::get_css( $course_title_typography_css, '', $device, $typographyValueGlobal ),
		);
	}

	public function get_input_field_label_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['input_field_label_typographyGlobal'] ) ? $attributes['input_field_label_typographyGlobal'] : '';
		$input_field_label_typography_css = isset( $attributes['input_field_label_typography'] ) ? $attributes['input_field_label_typography'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['input_field_label_color'] ) ? $attributes['input_field_label_color'] : '' ) ],
			Typography::get_css( $input_field_label_typography_css, '', $device, $typographyValueGlobal ),
		);
	}

	public function get_input_field_label_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['input_field_label_hover_color'] ) ? $attributes['input_field_label_hover_color'] : '' ) ];
	}

	public function get_input_field_css( $attributes, $device = '' ) {
		$input_border_css = ! empty( $attributes['input_field_border'] ) ? Border::get_css( $attributes['input_field_border'], '', $device ) : array();
		$input_field_padding = ! empty( $attributes['input_field_padding'] ) ? Dimensions::get_css( $attributes['input_field_padding'], 'padding', $device ) : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['inputFieldColor'] ) ? $attributes['inputFieldColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['input_field_bg_color'] ) ? $attributes['input_field_bg_color'] : '' ) ],
			$input_border_css,
			$input_field_padding
		);
	}
	public function get_input_field_placeholder_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['inputFieldColor'] ) ? $attributes['inputFieldColor'] : '' ) ];
	}

	public function get_login_btn_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['login_btn_hover_color'] ) ? $attributes['login_btn_hover_color'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['login_btn_bg_hover_color'] ) ? $attributes['login_btn_bg_hover_color'] : '' ) ]
		);
	}

	public function get_form_card_css( $attributes, $device = '' ) {
		$form_background_css = ! empty( $attributes['form_bg_color'] ) ? Background::get_css( $attributes['form_bg_color'], 'background', $device ) : array();
		$form_border = ! empty( $attributes['form_border'] ) ? Border::get_css( $attributes['form_border'], '', $device ) : array();
		$form_padding = ! empty( $attributes['form_padding'] ) ? Dimensions::get_css( $attributes['form_padding'], 'padding', $device ) : array();

		return array_merge(
			$form_background_css,
			$form_border,
			$form_padding
		);
	}

	public function get_form_card_hover_css( $attributes, $device = '' ) {
		$form_background_hover_css = ! empty( $attributes['form_bg_color'] ) ? Background::get_hover_css( $attributes['form_bg_color'], 'background', $device ) : array();
		$form_border = ! empty( $attributes['form_border'] ) ? Border::get_hover_css( $attributes['form_border'], '', $device ) : array();
		return array_merge(
			$form_background_hover_css,
			$form_border
		);
	}


	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'form_title'               => sanitize_text_field( Helper::get_attribute_value( $attributes, 'form_title' ) ),
			'username_label'           => sanitize_text_field( Helper::get_attribute_value( $attributes, 'username_label' ) ),
			'username_placeholder'     => sanitize_text_field( Helper::get_attribute_value( $attributes, 'username_placeholder' ) ),
			'password_label'           => sanitize_text_field( Helper::get_attribute_value( $attributes, 'password_label' ) ),
			'password_placeholder'     => sanitize_text_field( Helper::get_attribute_value( $attributes, 'password_placeholder' ) ),
			'remember_label'           => sanitize_text_field( Helper::get_attribute_value( $attributes, 'remember_label' ) ),
			'login_button_label'       => sanitize_text_field( Helper::get_attribute_value( $attributes, 'login_button_label' ) ),
			'reset_password_label'     => sanitize_text_field( Helper::get_attribute_value( $attributes, 'reset_password_label' ) ),
			'show_logged_in_message'   => filter_var( Helper::get_attribute_value( $attributes, 'show_logged_in_message' ), FILTER_VALIDATE_BOOLEAN ),
			'student_register_url'     => esc_url_raw( Helper::get_attribute_value( $attributes, 'student_register_url' ) ),
			'login_redirect_url'       => esc_url_raw( Helper::get_attribute_value( $attributes, 'login_redirect_url' ) ),
			'logout_redirect_url'      => esc_url_raw( Helper::get_attribute_value( $attributes, 'logout_redirect_url' ) ),
		];

		$shortcode = '[academy_login_form ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}


}
