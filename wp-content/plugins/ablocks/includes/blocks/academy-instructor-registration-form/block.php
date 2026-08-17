<?php
namespace ABlocks\Blocks\AcademyInstructorRegistrationForm;

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
	protected $block_name = 'academy-instructor-registration-form';

	public function __construct() {
		parent::__construct();

		add_filter( 'academy/shortcode/instructor_registration_form_is_user_logged_in', [ $this, 'force_showing_instructor_registration_form_in_editor' ] );

	}

	public function force_showing_instructor_registration_form_in_editor( $flag ) {

		if ( Helper::is_gutenberg_editor() ) {
			return false;
		}
		return $flag;
	}

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-reg-form--instructor .academy-form-group label',
			$this->get_form_input_label_css( $attributes ),
			$this->get_form_input_label_css( $attributes, 'Tablet' ),
			$this->get_form_input_label_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-reg-form--instructor .academy-form-group input',
			$this->get_form_input_field_css( $attributes ),
			$this->get_form_input_field_css( $attributes, 'Tablet' ),
			$this->get_form_input_field_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-reg-form--instructor .academy-form-group input:hover',
			$this->get_form_input_field_hover_css( $attributes ),
			$this->get_form_input_field_hover_css( $attributes, 'Tablet' ),
			$this->get_form_input_field_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-reg-form--instructor .academy-form-group input::placeholder',
			$this->get_form_input_field_placeholder_css( $attributes ),
			$this->get_form_input_field_placeholder_css( $attributes, 'Tablet' ),
			$this->get_form_input_field_placeholder_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-reg-form--instructor .academy-form-group button',
			$this->get_form_button_css( $attributes ),
			$this->get_form_button_css( $attributes, 'Tablet' ),
			$this->get_form_button_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-reg-form--instructor .academy-form-group button:hover',
			$this->get_form_button_hover_css( $attributes ),
			$this->get_form_button_hover_css( $attributes, 'Tablet' ),
			$this->get_form_button_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-reg-form',
			$this->get_form_css( $attributes ),
			$this->get_form_css( $attributes, 'Tablet' ),
			$this->get_form_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-reg-form:hover',
			$this->get_form_hover_css( $attributes ),
			$this->get_form_hover_css( $attributes, 'Tablet' ),
			$this->get_form_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_form_input_label_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['input_label_typhographyGlobal'] ) ? $attributes['input_label_typhographyGlobal'] : '';
		$input_label_typography_css  = isset( $attributes['input_label_typhography'] ) ? $attributes['input_label_typhography'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['input_label_color'] ) ? $attributes['input_label_color'] : '' ) ],
			Typography::get_css( $input_label_typography_css, '', $device, $typographyValueGlobal ),
		);
	}

	public function get_form_input_field_css( $attributes, $device = '' ) {
		$input_field_border_css = ! empty( $attributes['form_field_border'] ) ? Border::get_css( $attributes['form_field_border'], '', $device ) : array();

		return array_merge(
			$input_field_border_css,
			[ 'color' => Color::get_css( isset( $attributes['input_field_color'] ) ? $attributes['input_field_color'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['input_field_bg_color'] ) ? $attributes['input_field_bg_color'] : '' ) ]
		);
	}
	public function get_form_input_field_hover_css( $attributes, $device = '' ) {
		$input_field_border_hover_css = ! empty( $attributes['form_field_border'] ) ? Border::get_hover_css( $attributes['form_field_border'], '', $device ) : array();

		return $input_field_border_hover_css;
	}
	public function get_form_input_field_placeholder_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['input_field_placeholder_color'] ) ? $attributes['input_field_placeholder_color'] : '' ) ];
	}

	public function get_form_button_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['form_button_typhographyGlobal'] ) ? $attributes['form_button_typhographyGlobal'] : '';
		$form_button_typography_css = isset( $attributes['form_button_typhography'] ) ? $attributes['form_button_typhography'] : [];
		$form_button_padding = ! empty( $attributes['form_button_padding'] ) ? Dimensions::get_css( $attributes['form_button_padding'], 'padding', $device ) : array();
		$form_button_border_css = ! empty( $attributes['form_button_border'] ) ? Border::get_css( $attributes['form_button_border'], '', $device ) : array();
		return array_merge(
			Typography::get_css( $form_button_typography_css, '', $device, $typographyValueGlobal ),
			$form_button_padding,
			$form_button_border_css,
			[ 'color' => Color::get_css( isset( $attributes['form_button_color'] ) ? $attributes['form_button_color'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['form_button_background'] ) ? $attributes['form_button_background'] : '' ) ]
		);
	}

	public function get_form_css( $attributes, $device = '' ) {
		$form_background = ! empty( $attributes['form_background'] ) ? Background::get_css( $attributes['form_background'], 'background', $device ) : array();
		$form_padding = ! empty( $attributes['form_padding'] ) ? Dimensions::get_css( $attributes['form_padding'], 'padding', $device ) : array();
		$form_border = ! empty( $attributes['form_border'] ) ? Border::get_css( $attributes['form_border'], '', $device ) : array();

		return array_merge(
			$form_background,
			$form_padding,
			$form_border
		);
	}

	public function get_form_hover_css( $attributes, $device = '' ) {
		$form_hover_background = ! empty( $attributes['form_background'] ) ? Background::get_hover_css( $attributes['form_background'], 'background', $device ) : array();
		$form_hover_border = ! empty( $attributes['form_border'] ) ? Border::get_hover_css( $attributes['form_border'], '', $device ) : array();

		return array_merge(
			$form_hover_background,
			$form_hover_border
		);
	}

	public function get_form_button_hover_css( $attributes, $device = '' ) {
		$form_btn_hover_border = ! empty( $attributes['form_button_border'] ) ? Border::get_hover_css( $attributes['form_button_border'], 'background', $device ) : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['form_button_hover_color'] ) ? $attributes['form_button_hover_color'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['form_button_hover_background'] ) ? $attributes['form_button_hover_background'] : '' ) ],
			$form_btn_hover_border,
		);
	}





	public function render_block_content( $attributes, $content, $block_instance ) {
		$shortcode = '[academy_instructor_registration_form]';
		echo do_shortcode( $shortcode );
	}

}
