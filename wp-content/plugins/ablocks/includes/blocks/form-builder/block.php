<?php

namespace ABlocks\Blocks\FormBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Controls\Range;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Color;
class Block extends BlockBaseAbstract {

	protected $block_name = 'form-builder';

	public function __construct() {
		parent::__construct();
		add_filter( 'ablocks/get_render_block_content', [ $this, 'render_static_block_content' ], 10, 3 );
	}

	public function render_static_block_content( $content, $attributes, $block_instance ) {
		if ( $block_instance->name === $this->namespace . '/' . $this->block_name ) {
			if ( is_user_logged_in() &&
				in_array( $attributes['formType'], array( 'login', 'registration', 'forget_password' ), true )
			) {
				return '';
			}
			return $content;
		}
		return $content;
	}

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder',
			$this->get_row_column_displayCss( $attributes ),
			$this->get_row_column_displayCss( $attributes, 'Tablet' ),
			$this->get_row_column_displayCss( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__field',
			$this->get_field_css( $attributes ),
			$this->get_field_css( $attributes, 'Tablet' ),
			$this->get_field_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__label',
			$this->get_label_css( $attributes ),
			$this->get_label_css( $attributes, 'Tablet' ),
			$this->get_label_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__helper-text',
			$this->get_helper_text_css( $attributes ),
			$this->get_helper_text_css( $attributes, 'Tablet' ),
			$this->get_helper_text_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} input.ablocks-form-builder__input ,
			 {{WRAPPER}} input.ablocks-form-builder__select ,
			 {{WRAPPER}} input.ablocks-form-builder-timepicker-input , 
			 {{WRAPPER}} input.ablocks-form-builder-datepicker-input',
			$this->get_input_css( $attributes ),
			$this->get_input_css( $attributes, 'Tablet' ),
			$this->get_input_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__input:hover',
			$this->get_input_hover_css( $attributes ),
			$this->get_input_hover_css( $attributes, 'Tablet' ),
			$this->get_input_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__input:focus',
			$this->get_input_focus_css( $attributes ),
			$this->get_input_focus_css( $attributes, 'Tablet' ),
			$this->get_input_focus_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__input::placeholder',
			$this->get_input_placeholder_css( $attributes ),
			$this->get_input_placeholder_css( $attributes, 'Tablet' ),
			$this->get_input_placeholder_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__input-icon,{{WRAPPER}} .ablocks-form-builder__input-toggle-password',
			$this->get_input_position_css( $attributes ),
			$this->get_input_position_css( $attributes, 'Tablet' ),
			$this->get_input_position_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__submit-button',
			$this->get_alignment_button_css( $attributes ),
			$this->get_alignment_button_css( $attributes, 'Tablet' ),
			$this->get_alignment_button_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__submit-button',
			$this->get_submit_button_css( $attributes ),
			$this->get_submit_button_css( $attributes, 'Tablet' ),
			$this->get_submit_button_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__submit-button:hover',
			$this->get_submit_button_hover_css( $attributes ),
			$this->get_submit_button_hover_css( $attributes, 'Tablet' ),
			$this->get_submit_button_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--form-builder__navigator',
			$this->get_navigator_css( $attributes ),
			$this->get_navigator_css( $attributes, 'Tablet' ),
			$this->get_navigator_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--form-builder__navigator-redirect-page',
			$this->get_navigator_spacing_css( $attributes ),
			$this->get_navigator_spacing_css( $attributes, 'Tablet' ),
			$this->get_navigator_spacing_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--form-builder__success',
			$this->get_succsss_styles_css( $attributes ),
			$this->get_succsss_styles_css( $attributes, 'Tablet' ),
			$this->get_succsss_styles_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--form-builder__error',
			$this->get_error_styles_css( $attributes ),
			$this->get_error_styles_css( $attributes, 'Tablet' ),
			$this->get_error_styles_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--form-builder__feedback-message',
			$this->get_success_error_common_styles_css( $attributes ),
			$this->get_success_error_common_styles_css( $attributes, 'Tablet' ),
			$this->get_success_error_common_styles_css( $attributes, 'Mobile' ),
		);
		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

				$css_generator->add_class_styles(
					'{{WRAPPER}} .ablocks-form-builder',
					$this->get_row_column_displayCss( $attributes ),
					$this->get_row_column_displayCss( $attributes, 'Tablet' ),
					$this->get_row_column_displayCss( $attributes, 'Mobile' ),
				);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__field',
			$this->get_field_css( $attributes ),
			$this->get_field_css( $attributes, 'Tablet' ),
			$this->get_field_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__label',
			$this->get_label_css( $attributes ),
			$this->get_label_css( $attributes, 'Tablet' ),
			$this->get_label_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__helper-text',
			$this->get_helper_text_css( $attributes ),
			$this->get_helper_text_css( $attributes, 'Tablet' ),
			$this->get_helper_text_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
		    '{{WRAPPER}} .ablocks-form-builder__input ,
			 {{WRAPPER}} .ablocks-form-builder__select ,
			 {{WRAPPER}} .ablocks-form-builder-timepicker-input , 
			 {{WRAPPER}} .ablocks-form-builder-datepicker-input',
			$this->get_input_css( $attributes ),
			$this->get_input_css( $attributes, 'Tablet' ),
			$this->get_input_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__input:hover',
			$this->get_input_hover_css( $attributes ),
			$this->get_input_hover_css( $attributes, 'Tablet' ),
			$this->get_input_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__input:focus',
			$this->get_input_focus_css( $attributes ),
			$this->get_input_focus_css( $attributes, 'Tablet' ),
			$this->get_input_focus_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__input::placeholder',
			$this->get_input_placeholder_css( $attributes ),
			$this->get_input_placeholder_css( $attributes, 'Tablet' ),
			$this->get_input_placeholder_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__input-icon,{{WRAPPER}} .ablocks-form-builder__input-toggle-password',
			$this->get_input_position_css( $attributes ),
			$this->get_input_position_css( $attributes, 'Tablet' ),
			$this->get_input_position_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__submit-button',
			$this->get_alignment_button_css( $attributes ),
			$this->get_alignment_button_css( $attributes, 'Tablet' ),
			$this->get_alignment_button_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__submit-button',
			$this->get_submit_button_css( $attributes ),
			$this->get_submit_button_css( $attributes, 'Tablet' ),
			$this->get_submit_button_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-form-builder__submit-button:hover',
			$this->get_submit_button_hover_css( $attributes ),
			$this->get_submit_button_hover_css( $attributes, 'Tablet' ),
			$this->get_submit_button_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--form-builder__navigator',
			$this->get_navigator_css( $attributes ),
			$this->get_navigator_css( $attributes, 'Tablet' ),
			$this->get_navigator_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--form-builder__navigator-redirect-page',
			$this->get_navigator_spacing_css( $attributes ),
			$this->get_navigator_spacing_css( $attributes, 'Tablet' ),
			$this->get_navigator_spacing_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--form-builder__success',
			$this->get_succsss_styles_css( $attributes ),
			$this->get_succsss_styles_css( $attributes, 'Tablet' ),
			$this->get_succsss_styles_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--form-builder__error',
			$this->get_error_styles_css( $attributes ),
			$this->get_error_styles_css( $attributes, 'Tablet' ),
			$this->get_error_styles_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--form-builder__feedback-message',
			$this->get_success_error_common_styles_css( $attributes ),
			$this->get_success_error_common_styles_css( $attributes, 'Tablet' ),
			$this->get_success_error_common_styles_css( $attributes, 'Mobile' ),
		);
		return $css_generator->generate_css();
	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}
	public function get_field_css( $attributes, $device = '' ) {
		$field_css = array_merge(
			Range::get_css([
				'attributeValue' => $attributes['rowsSpacing'],
				'attributeObjectKey' => 'value',
				'isResponsive' => true,
				'defaultValue' => 0,
				'unitDefaultValue' => 'px',
				'property' => 'margin-top',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['rowsSpacing'],
				'attributeObjectKey' => 'value',
				'isResponsive' => true,
				'defaultValue' => 0,
				'unitDefaultValue' => 'px',
				'property' => 'margin-bottom',
				'device' => $device,
			]),
		);
		return $field_css;
	}

	public function get_label_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['labelTypographyGlobal'] ) ? $attributes['labelTypographyGlobal'] : array();
		$label_css = array_merge(
			[ 'color' => Color::get_css( isset( $attributes['labelColor'] ) ? $attributes['labelColor'] : '' ) ],
			Typography::get_css( $attributes['labelTypography'], '', $device, $typographyValueGlobal ),
			Alignment::get_css( $attributes['labelAlignment'], 'text-align', $device ),
			Range::get_css([
				'attributeValue' => $attributes['labelSpacing'],
				'attributeObjectKey' => 'value',
				'isResponsive' => true,
				'defaultValue' => 10,
				'unitDefaultValue' => 'px',
				'property' => 'margin-bottom',
				'device' => $device,
			]),
		);
		if ( ! empty( $label_css['margin-bottom'] ) ) {
			$label_css['margin-bottom'] = $label_css['margin-bottom'] . ' !important';
		}
		if ( ! $attributes['showLabels'] ) {
			$label_css['display'] = 'none';
		}

		return $label_css;
	}
	public function get_helper_text_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['helperTextTypographyGlobal'] ) ? $attributes['helperTextTypographyGlobal'] : array();
		$helper_text_css = array_merge(
			[ 'color' => Color::get_css( isset( $attributes['helperTextColor'] ) ? $attributes['helperTextColor'] : '' ) ],
			Typography::get_css( $attributes['helperTextTypography'], '', $device, $typographyValueGlobal ),
			Alignment::get_css( $attributes['labelAlignment'], 'text-align', $device ),
			Range::get_css([
				'attributeValue' => $attributes['helperTextSpacing'],
				'attributeObjectKey' => 'value',
				'isResponsive' => true,
				'defaultValue' => 10,
				'unitDefaultValue' => 'px',
				'property' => 'margin-bottom',
				'device' => $device,
			]),
		);

		if ( ! $attributes['showLabels'] ) {
			$helper_text_css['display'] = 'none';
		}

		if ( ! empty( $attributes['helperTextSpacing'][ 'value' . $device ] ) ) {
			$helper_text_css['margin-top'] = $attributes['helperTextSpacing'][ 'value' . $device ] . 'px';
			$helper_text_css['margin-bottom'] = $attributes['helperTextSpacing'][ 'value' . $device ] . 'px';
		}

		return $helper_text_css;
	}

	public function get_input_css( $attributes, $device = '' ) {
		$css = array();
		if ( isset( $attributes['inputBorder']['borderStyle'] ) && $attributes['inputBorder']['borderStyle'] === 'default' ) {
			$css['border'] = '1px solid #A7AAAD';
			$css['border-radius'] = '5px';
		}
		$typographyValueGlobal = ! empty( $attributes['inputTypographyGlobal'] ) ? $attributes['inputTypographyGlobal'] : array();
		$input_css = array_merge(
			[ 'background' => Color::get_css( isset( $attributes['inputBgColor'] ) ? $attributes['inputBgColor'] : '' ) ],
			$css,
			Alignment::get_css( $attributes['inputAlignment'], 'text-align', $device ),
			Typography::get_css( $attributes['inputTypography'], '', $device, $typographyValueGlobal ),
			Border::get_css( $attributes['inputBorder'], '', $device ),
			$this->add_important_to_css( Dimensions::get_css( $attributes['inputPadding'], 'padding', $device ) )
		);

		if ( ! empty( $attributes['inputColor'] ) ) {
			$input_css['color'] = Color::get_css(
			isset( $attributes['inputColor'] ) ? $attributes['inputColor'] : '') . '!important';
		}
		$input_css['box-sizing'] = 'border-box';

		return $input_css;
	}

	public function get_input_hover_css( $attributes, $device = '' ) {
		$css = array();
		if ( isset( $attributes['inputBorder']['borderStyle'] ) && $attributes['inputBorder']['borderStyle'] === 'default' ) {
			$css['border'] = '1px solid #A7AAAD';
			$css['border-radius'] = '5px';
		}
		$input_focus_css = array_merge(
			$css,
			Border::get_hover_css( $attributes['inputBorder'], '', $device )
		);

		return $input_focus_css;
	}
	public function get_input_focus_css( $attributes, $device = '' ) {
		$css = array();
		if ( isset( $attributes['inputBorder']['borderStyle'] ) && $attributes['inputBorder']['borderStyle'] === 'default' ) {
			$css['border'] = '1px solid #A7AAAD';
			$css['border-radius'] = '5px';
		}
		$input_focus_css = array_merge(
			$css,
			Border::get_hover_css( $attributes['inputBorder'], '', $device )
		);

		return $input_focus_css;
	}
	public function get_input_placeholder_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['inputTypographyGlobal'] ) ? $attributes['inputTypographyGlobal'] : array();
		$placeholder_css = array_merge(
			Typography::get_css( $attributes['inputTypography'], '', $device, $typographyValueGlobal ),
			Alignment::get_css( $attributes['inputAlignment'], 'text-align', $device )
		);
		if ( ! empty( $attributes['inputPlaceholderColor'] ) ) {
			$placeholder_css['color'] = Color::get_css(
			isset( $attributes['inputPlaceholderColor'] ) ? $attributes['inputPlaceholderColor'] : '') . '!important';
		}
		return $placeholder_css;

	}
	public function get_input_position_css( $attributes, $device = '' ) {
		$css = array_merge(
			Range::get_css([
				'attributeValue' => $attributes['inputIconPosition'],
				'isResponsive' => false,
				'defaultValue' => 75,
				'unitDefaultValue' => '%',
				'property' => 'top',
				'device' => $device,
			]),
		);
		return $css;
	}
	public function get_alignment_button_css( $attributes, $device = '' ) {
		$button_alignment_css = array_merge(
			Alignment::get_css( $attributes['buttonTextAlignment'], 'text-align', $device ),
			Alignment::get_css( $attributes['buttonAlignment'], 'align-self', $device )
		);

		return $button_alignment_css;
	}


	public function get_submit_button_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['buttonTypographyGlobal'] ) ? $attributes['buttonTypographyGlobal'] : array();
		$button_css = array_merge(
			[ 'color' => Color::get_css( isset( $attributes['buttonColor'] ) ? $attributes['buttonColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['buttonBgColor'] ) ? $attributes['buttonBgColor'] : '' ) ],
			Border::get_css( $attributes['buttonBorder'], '', $device ),
			Typography::get_css( $attributes['buttonTypography'], '', $device, $typographyValueGlobal ),
			Dimensions::get_css( $attributes['buttonPadding'], 'padding', $device ),
		);
		return $button_css;
	}

	public function get_submit_button_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['buttonHColor'] ) ? $attributes['buttonHColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['buttonBgHColor'] ) ? $attributes['buttonBgHColor'] : '' ) ],
			Border::get_hover_css( $attributes['buttonBorder'], '', $device )
		);
	}
	public function get_navigator_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['navigatorTypographyGlobal'] ) ? $attributes['navigatorTypographyGlobal'] : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['navigatorColor'] ) ? $attributes['navigatorColor'] : '' ) ],
			Typography::get_css( $attributes['navigatorTypography'], '', $device, $typographyValueGlobal ),
			Dimensions::get_css( $attributes['navigatorPadding'], 'padding', $device ),
			Alignment::get_css( $attributes['navigatorAlignment'], 'text-align', $device ),
		);
	}
	public function get_navigator_spacing_css( $attributes, $device = '' ) {
			// Access the inputIconPosition attribute from the attributes array
			$css = array_merge(
				Range::get_css([
					'attributeValue' => $attributes['navigatorSpacing'],
					'isResponsive' => false,
					'defaultValue' => 10,
					'unitDefaultValue' => 'px',
					'property' => 'margin-bottom',
					'device' => $device,
				]),
			);
			return $css;

	}
	public function get_succsss_styles_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['successColor'] ) ? $attributes['successColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['successBackground'] ) ? $attributes['successBackground'] : '' ) ]
		);
	}
	public function get_error_styles_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['errorColor'] ) ? $attributes['errorColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['errorBackground'] ) ? $attributes['errorBackground'] : '' ) ],
		);
	}
	public function get_success_error_common_styles_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['successErrorTypographyGlobal'] ) ? $attributes['successErrorTypographyGlobal'] : array();
		return array_merge(
			Typography::get_css( $attributes['successErrorTypography'], '', $device, $typographyValueGlobal ),
			Alignment::get_css( $attributes['successErrorAlignment'], 'text-align', $device ),
			Dimensions::get_css( $attributes['successErrorPadding'], 'padding', $device ),
		);
	}

	public function get_row_column_displayCss( $attributes, $device = '' ) {
		$css = [];

		if ( isset( $attributes['dir'][ 'value' . $device ] ) ) {
			$dir_value = $attributes['dir'][ 'value' . $device ];
			$css['flex-direction'] = $dir_value;

			if ( in_array( $dir_value, [ 'row', 'row-reverse' ], true ) ) {
				$css['align-items'] = 'center';
			} elseif ( in_array( $dir_value, [ 'column', 'column-reverse' ], true ) ) {
				$css['flex-wrap'] = 'wrap';
			}
		}

		return $css;
	}

	private function add_important_to_css( $css_array ) {
		foreach ( $css_array as $key => $value ) {
			$css_array[$key] = $value . ' !important';
		}
		return $css_array;
	}


}
