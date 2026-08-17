<?php

namespace ABlocks\Blocks\StoreengineLoginForm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Helper;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Color;


class Block extends BlockBaseAbstract {
	protected $block_name = 'storeengine-login-form';

	public function __construct() {
		parent::__construct();

		add_filter( 'storeengine/shortcode/login_form_is_user_logged_in', [
			$this,
			'force_showing_login_form_in_editor'
		] );

	}

	public function force_showing_login_form_in_editor( $flag ) {
		if ( Helper::is_gutenberg_editor() ) {
			return false;
		}

		return $flag;
	}

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-login-form-heading',
			$this->get_form_title_css( $attributes ),
			$this->get_form_title_css( $attributes, 'Tablet' ),
			$this->get_form_title_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-login-form-heading:hover',
			$this->form_title_desktop_hover_css( $attributes )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-login-form-wrapper .storeengine-login-form .storeengine-form-group
			label',
			$this->get_input_field_label_css( $attributes ),
			$this->get_input_field_label_css( $attributes, 'Tablet' ),
			$this->get_input_field_label_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-login-form-wrapper .storeengine-login-form .storeengine-form-group
			label:hover',
			$this->get_input_field_label_hover_css( $attributes ),
			$this->get_input_field_label_hover_css( $attributes, 'Tablet' ),
			$this->get_input_field_label_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-login-form-wrapper .storeengine-login-form .storeengine-form-group input',
			$this->get_input_field_css( $attributes ),
			$this->get_input_field_css( $attributes, 'Tablet' ),
			$this->get_input_field_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-login-form-wrapper .storeengine-login-form .storeengine-form-group input:hover',
			$this->get_input_field_hover_css( $attributes ),
			$this->get_input_field_hover_css( $attributes, 'Tablet' ),
			$this->get_input_field_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-login-form-wrapper .storeengine-login-form .storeengine-form-group input::placeholder',
			$this->get_input_field_placeholder_css( $attributes ),
			$this->get_input_field_placeholder_css( $attributes, 'Tablet' ),
			$this->get_input_field_placeholder_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-login-form-wrapper .storeengine-form-group button',
			$this->get_login_form_button_css( $attributes ),
			$this->get_login_form_button_css( $attributes, 'Tablet' ),
			$this->get_login_form_button_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-login-form-wrapper .storeengine-form-group button:hover',
			$this->get_login_btn_hover_css( $attributes ),
			$this->get_login_btn_hover_css( $attributes, 'Tablet' ),
			$this->get_login_btn_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-login-form-wrapper .storeengine-login-form-info p,
			{{WRAPPER}} .storeengine-login-form-wrapper .storeengine-login-form-info p a ',
			$this->get_login_form_footer_css( $attributes ),
			$this->get_login_form_footer_css( $attributes, 'Tablet' ),
			$this->get_login_form_footer_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-login-form-wrapper',
			$this->get_form_card_css( $attributes ),
			$this->get_form_card_css( $attributes, 'Tablet' ),
			$this->get_form_card_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-login-form-wrapper:hover',
			$this->get_form_card_hover_css( $attributes ),
			$this->get_form_card_hover_css( $attributes, 'Tablet' ),
			$this->get_form_card_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_form_title_css( $attributes, $device = '' ) {
		$form_title_typography_css = ! empty( $attributes['title_typography'] ) ? $attributes['title_typography'] : [];
		$typographyGlobal = ( isset( $attributes['title_typographyGlobal'] ) ? $attributes['title_typographyGlobal'] : '' );
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['title_color'] ) ? $attributes['title_color'] : '' ) ],
			Typography::get_css( $form_title_typography_css, '', $device, $typographyGlobal ),
		);
	}

	public function get_input_field_label_css( $attributes, $device = '' ) {
		$input_field_label_typography_css = ! empty( $attributes['input_field_label_typography'] ) ? $attributes['input_field_label_typography'] : [];
		$typographyGlobal = ( isset( $attributes['input_field_label_typographyGlobal'] ) ? $attributes['input_field_label_typographyGlobal'] : '' );

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['input_field_label_color'] ) ? $attributes['input_field_label_color'] : '' ) ],
			Typography::get_css( $input_field_label_typography_css, '', $device, $typographyGlobal ),
		);
	}

	public function get_input_field_label_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['input_field_label_hover_color'] ) ? $attributes['input_field_label_hover_color'] : '' ) ];
	}

	public function get_input_field_css( $attributes, $device = '' ) {
		$input_border_css    = ! empty( $attributes['input_field_border'] ) ? Border::get_css( $attributes['input_field_border'], '', $device ) : array();
		$input_field_padding = ! empty( $attributes['input_field_padding'] ) ? Dimensions::get_css( $attributes['input_field_padding'], 'padding', $device ) : array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['inputFieldColor'] ) ? $attributes['inputFieldColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['input_field_bg_color'] ) ? $attributes['input_field_bg_color'] : '' ) ],
			$input_border_css,
			$input_field_padding
		);
	}

	public function get_input_field_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['inputFieldColorH'] ) ? $attributes['inputFieldColorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['input_field_bg_hover_color'] ) ? $attributes['input_field_bg_hover_color'] : '' ) ],
		);
	}


	public function get_input_field_placeholder_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['inputFieldColor'] ) ? $attributes['inputFieldColor'] : '' ) ];
	}


	public function get_login_form_button_css( $attributes, $device = '' ) {
		$login_form_button_typography_css = ! empty( $attributes['login_btn_typography'] ) ? $attributes['login_btn_typography'] : [];
		$typographyGlobal = ( isset( $attributes['login_btn_typographyGlobal'] ) ? $attributes['login_btn_typographyGlobal'] : '' );
		return array_merge(
			Typography::get_css( $login_form_button_typography_css, '', $device, $typographyGlobal ),
			[ 'background' => Color::get_css( isset( $attributes['login_btn_bg_color'] ) ? $attributes['login_btn_bg_color'] : '' ) ],
			[ 'color' => Color::get_css( isset( $attributes['login_btn_color'] ) ? $attributes['login_btn_color'] : '' ) ],
		);
	}

	public function get_login_btn_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['login_btn_hover_color'] ) ? $attributes['login_btn_hover_color'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['login_btn_bg_hover_color'] ) ? $attributes['login_btn_bg_hover_color'] : '' ) ],
		);
	}

	public function get_login_form_footer_css( $attributes, $device = '' ) {
		$login_form_footer_typography_css = ! empty( $attributes['form_footer_title_typography'] ) ? $attributes['form_footer_title_typography'] : [];
		$typographyGlobal = ( isset( $attributes['form_footer_title_typographyGlobal'] ) ? $attributes['form_footer_title_typographyGlobal'] : '' );

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['form_footer_title_color'] ) ? $attributes['form_footer_title_color'] : '' ) ],
			// $login_form_footer_typography_css
			Typography::get_css( $login_form_footer_typography_css, '', $device, $typographyGlobal ),
		);
	}

	public function get_form_card_css( $attributes, $device = '' ) {
		$form_background_css = ! empty( $attributes['form_bg_color'] ) ? Background::get_css( $attributes['form_bg_color'], 'background', $device ) : array();
		$form_border         = ! empty( $attributes['form_border'] ) ? Border::get_css( $attributes['form_border'], '', $device ) : array();
		$form_padding        = ! empty( $attributes['form_padding'] ) ? Dimensions::get_css( $attributes['form_padding'], 'padding', $device ) : array();

		return array_merge(
			$form_background_css,
			$form_border,
			$form_padding
		);
	}

	public function get_form_card_hover_css( $attributes, $device = '' ) {
		$form_background_hover_css = ! empty( $attributes['form_bg_color'] ) ? Background::get_hover_css( $attributes['form_bg_color'], 'background', $device ) : array();

		return $form_background_hover_css;
	}
	public function form_title_desktop_hover_css( $attributes, $device = '' ) {

		return [ 'color' => Color::get_css( isset( $attributes['title_hover_color'] ) ? $attributes['title_hover_color'] : '' ) ];
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'form_title'             => Helper::get_attribute_value( $attributes, 'form_title' ),
			'username_label'         => Helper::get_attribute_value( $attributes, 'username_label' ),
			'username_placeholder'   => Helper::get_attribute_value( $attributes, 'username_placeholder' ),
			'password_label'         => Helper::get_attribute_value( $attributes, 'password_label' ),
			'password_placeholder'   => Helper::get_attribute_value( $attributes, 'password_placeholder' ),
			'remember_label'         => Helper::get_attribute_value( $attributes, 'remember_label' ),
			'login_button_label'     => Helper::get_attribute_value( $attributes, 'login_button_label' ),
			'reset_password_label'   => Helper::get_attribute_value( $attributes, 'reset_password_label' ),
			'show_logged_in_message' => Helper::get_attribute_value( $attributes, 'show_logged_in_message' ),
			'register_url'   => Helper::get_attribute_value( $attributes, 'register_url' ),
			'login_redirect_url'     => Helper::get_attribute_value( $attributes, 'login_redirect_url' ),
			'logout_redirect_url'    => Helper::get_attribute_value( $attributes, 'logout_redirect_url' ),
		];
		// phpcs:ignore  WordPress.Security.NonceVerification.Recommended,  WordPress.Security.ValidatedSanitizedInput.MissingUnslash 
		if ( isset( $_GET['context'] ) && 'edit' === sanitize_text_field( $_GET['context'] ) ) {
			add_filter( 'storeengine/shortcode/login_form_is_user_logged_in', function () {
				return false;
			} );
		}

		$shortcode = '[storeengine_login_form ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
