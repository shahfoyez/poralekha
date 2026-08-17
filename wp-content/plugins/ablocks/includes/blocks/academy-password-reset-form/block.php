<?php
namespace ABlocks\Blocks\AcademyPasswordResetForm;

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
	protected $block_name = 'academy-password-reset-form';

	public function __construct() {
		parent::__construct();

		add_filter( 'academy/shortcode/password_reset_form_is_user_logged_in', [ $this, 'force_showing_reset_form_in_editor' ] );

	}

	public function force_showing_reset_form_in_editor( $flag ) {
		if ( Helper::is_gutenberg_editor() ) {
			return false;
		}
		return $flag;
	}

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-password-reset-form-wrapper',
			$this->getResetFormCss( $attributes ),
			$this->getResetFormCss( $attributes, 'Tablet' ),
			$this->getResetFormCss( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-password-reset-form-wrapper:hover',
			$this->getResetFormHoverCss( $attributes ),
			$this->getResetFormHoverCss( $attributes, 'Tablet' ),
			$this->getResetFormHoverCss( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-password-reset-form-wrapper .academy-password-reset-form .academy-form-group label',
			$this->getResetFormLabelCss( $attributes ),
			$this->getResetFormLabelCss( $attributes, 'Tablet' ),
			$this->getResetFormLabelCss( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-password-reset-form-wrapper .academy-password-reset-form .academy-form-group input',
			$this->getResetFormInputCss( $attributes ),
			$this->getResetFormInputCss( $attributes, 'Tablet' ),
			$this->getResetFormInputCss( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-password-reset-form-wrapper .academy-password-reset-form .academy-form-group input:hover',
			$this->getResetFormInputHoverCss( $attributes ),
			$this->getResetFormInputHoverCss( $attributes, 'Tablet' ),
			$this->getResetFormInputHoverCss( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-password-reset-form-wrapper .academy-password-reset-form .academy-form-group  button',
			$this->getResetFormButtonCss( $attributes ),
			$this->getResetFormButtonCss( $attributes, 'Tablet' ),
			$this->getResetFormButtonCss( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-password-reset-form-wrapper .academy-password-reset-form .academy-form-group  button:hover',
			$this->getResetFormButtonHoverCss( $attributes ),
			$this->getResetFormButtonHoverCss( $attributes, 'Tablet' ),
			$this->getResetFormButtonHoverCss( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-password-reset-form-wrapper h2.academy-password-reset-form-heading',
			$this->getResetFormHeaderCss( $attributes ),
			$this->getResetFormHeaderCss( $attributes, 'Tablet' ),
			$this->getResetFormHeaderCss( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-password-reset-form-wrapper .academy-password-reset-form-info a',
			$this->getResetFormFooterCss( $attributes ),
			$this->getResetFormFooterCss( $attributes, 'Tablet' ),
			$this->getResetFormFooterCss( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function getResetFormCss( $attributes, $device = '' ) {
		$form_border_css = ! empty( $attributes['form_border'] ) ? Border::get_css( $attributes['form_border'], '', $device ) : array();
		$form_padding_css = ! empty( $attributes['form_padding'] ) ? Dimensions::get_css( $attributes['form_padding'], 'padding', $device ) : array();
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['form_background_color'] ) ? $attributes['form_background_color'] : '' ) ],
			$form_border_css,
			$form_padding_css,
		);
	}
	public function getResetFormHoverCss( $attributes, $device = '' ) {
		$form_border_hover_css = ! empty( $attributes['form_border'] ) ? Border::get_hover_css( $attributes['form_border'], '', $device ) : array();
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['form_background_hover_color'] ) ? $attributes['form_background_hover_color'] : '' ) ],
			$form_border_hover_css
		);
	}
	public function getResetFormLabelCss( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['label_typographyGlobal'] ) ? $attributes['label_typographyGlobal'] : '';
		$reset_form_label_typography_css = isset( $attributes['label_typography'] ) ? $attributes['label_typography'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['label_color'] ) ? $attributes['label_color'] : '' ) ],
			Typography::get_css( $reset_form_label_typography_css, '', $device, $typographyValueGlobal ),
		);
	}
	public function getResetFormInputCss( $attributes, $device = '' ) {
		$form_input_border_css = ! empty( $attributes['input_border'] ) ? Border::get_css( $attributes['input_border'], '', $device ) : array();
		$form_input_padding_css = ! empty( $attributes['input_padding'] ) ? Dimensions::get_css( $attributes['input_padding'], 'padding', $device ) : array();
		$typographyValueGlobal = ! empty( $attributes['input_field_typographyGlobal'] ) ? $attributes['input_field_typographyGlobal'] : '';
		$form_input_typography_css = isset( $attributes['input_field_typography'] ) ? $attributes['input_field_typography'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['input_field_color'] ) ? $attributes['input_field_color'] : '' ) ],
			$form_input_border_css,
			$form_input_padding_css,
			Typography::get_css( $form_input_typography_css, '', $device, $typographyValueGlobal ),
		);
	}
	public function getResetFormInputHoverCss( $attributes, $device = '' ) {

		$form_input_border_hover_css = ! empty( $attributes['input_border'] ) ? Border::get_hover_css( $attributes['input_border'], '', $device ) : array();
		return $form_input_border_hover_css;
	}

	public function getResetFormButtonCss( $attributes, $device = '' ) {
		$button_border = ! empty( $attributes['button_border'] ) ? Border::get_css( $attributes['button_border'], '', $device ) : array();
		$button_padding = ! empty( $attributes['button_padding'] ) ? Dimensions::get_css( $attributes['button_padding'], 'padding', $device ) : array();
		$typographyValueGlobal = ! empty( $attributes['button_typographyGlobal'] ) ? $attributes['button_typographyGlobal'] : '';
		$button_typography = isset( $attributes['button_typography'] ) ? $attributes['button_typography'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['button_color'] ) ? $attributes['button_color'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['button_background_color'] ) ? $attributes['button_background_color'] : '' ) ],
			$button_border,
			$button_padding,
			Typography::get_css( $button_typography, '', $device, $typographyValueGlobal ), $button_typography,
		);
	}
	public function getResetFormButtonHoverCss( $attributes, $device = '' ) {
		$button_hover_border = ! empty( $attributes['button_border'] ) ? Border::get_hover_css( $attributes['button_border'], '', $device ) : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['button_hover_color'] ) ? $attributes['button_hover_color'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['button_background_hover_color'] ) ? $attributes['button_background_hover_color'] : '' ) ],
			$button_hover_border,
		);
	}
	public function getResetFormHeaderCss( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['form_title_typographyGlobal'] ) ? $attributes['form_title_typographyGlobal'] : '';
		$form_title_typography = isset( $attributes['form_title_typography'] ) ? $attributes['form_title_typography'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['form_title_color'] ) ? $attributes['form_title_color'] : '' ) ],
			Typography::get_css( $form_title_typography, '', $device, $typographyValueGlobal ),
		);
	}
	public function getResetFormFooterCss( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['form_footer_title_typographyGlobal'] ) ? $attributes['form_footer_title_typographyGlobal'] : '';
		$form_footer_title_typography = isset( $attributes['form_footer_title_typography'] ) ? $attributes['form_footer_title_typography'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['form_footer_title_color'] ) ? $attributes['form_footer_title_color'] : '' ) ],
			Typography::get_css( $form_footer_title_typography, '', $device, $typographyValueGlobal ),
		);
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'form_title'            => sanitize_text_field( Helper::get_attribute_value( $attributes, 'form_title' ) ),
			'username_label'        => sanitize_text_field( Helper::get_attribute_value( $attributes, 'username_label' ) ),
			'reset_button_label'    => sanitize_text_field( Helper::get_attribute_value( $attributes, 'reset_button_label' ) ),
			'login_button_label'    => sanitize_text_field( Helper::get_attribute_value( $attributes, 'login_button_label' ) ),
			'show_logged_in_message' => filter_var( Helper::get_attribute_value( $attributes, 'show_logged_in_message' ), FILTER_VALIDATE_BOOLEAN ),
		];

		$shortcode = '[academy_password_reset_form ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
