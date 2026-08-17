<?php
namespace ABlocks\Blocks\StoreengineCheckoutForm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Helper;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'storeengine-checkout-form';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-ajax-checkout-form__contact-information .storeengine-checkout-form-section-heading,
			{{WRAPPER}} .storeengine-ajax-checkout-form__billing-address .storeengine-checkout-form-section-heading',
			$this->get_Cheackout_form_css( $attributes ),
			$this->get_Cheackout_form_css( $attributes, 'Tablet' ),
			$this->get_Cheackout_form_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-ajax-checkout-form__contact-information .storeengine-checkout-form-section-heading:hover,
			{{WRAPPER}} .storeengine-ajax-checkout-form__billing-address .storeengine-checkout-form-section-heading:hover',
			$this->get_Cheackout_form_hover_css( $attributes ),
			$this->get_Cheackout_form_hover_css( $attributes, 'Tablet' ),
			$this->get_Cheackout_form_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-form-group .storeengine-form__inner label,
			{{WRAPPER}} .storeengine-ajax-checkout-form__contact-information .storeengine-form-field  .storeengine-form-field__inner label',
			$this->get_Cheackout_form_label_css( $attributes ),
			$this->get_Cheackout_form_label_css( $attributes, 'Tablet' ),
			$this->get_Cheackout_form_label_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-form-group .storeengine-form__inner label:hover,
			{{WRAPPER}} .storeengine-ajax-checkout-form__contact-information .storeengine-form-field  .storeengine-form-field__inner label:hover',
			$this->get_Cheackout_form_label_hover_css( $attributes ),
			$this->get_Cheackout_form_label_hover_css( $attributes, 'Tablet' ),
			$this->get_Cheackout_form_label_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-form-group .storeengine-form__inner input,
			{{WRAPPER}} .storeengine-ajax-checkout-form input',
			$this->get_Cheackout_form_input_css( $attributes ),
			$this->get_Cheackout_form_input_css( $attributes, 'Tablet' ),
			$this->get_Cheackout_form_input_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-form-group .storeengine-form__inner input:hover,
			{{WRAPPER}} .storeengine-ajax-checkout-form input:hover',
			$this->get_Cheackout_form_input_hover_css( $attributes ),
			$this->get_Cheackout_form_input_hover_css( $attributes, 'Tablet' ),
			$this->get_Cheackout_form_input_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-form-group .storeengine-form__inner select',
			$this->get_cheackout_form_select_css( $attributes ),
			$this->get_cheackout_form_select_css( $attributes, 'Tablet' ),
			$this->get_cheackout_form_select_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-form-group .storeengine-form__inner select:hover',
			$this->get_cheackout_form_select_hover_css( $attributes ),
			$this->get_cheackout_form_select_hover_css( $attributes, 'Tablet' ),
			$this->get_cheackout_form_select_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-ajax-checkout-form .storeengine-checkout__order-btn button',
			$this->get_cheackout_form_button_css( $attributes ),
			$this->get_cheackout_form_button_css( $attributes, 'Tablet' ),
			$this->get_cheackout_form_button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-ajax-checkout-form .storeengine-checkout__order-btn button:hover',
			$this->get_cheackout_form_button_hover_css( $attributes ),
			$this->get_cheackout_form_button_hover_css( $attributes, 'Tablet' ),
			$this->get_cheackout_form_button_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function get_Cheackout_form_css( $attributes, $device = '' ) {
		$typography = isset( $attributes['titleTypography'] ) && is_array( $attributes['titleTypography'] )
			? $attributes['titleTypography']
			: [];

		$typography_value = array_merge( $typography, [ 'font-weight' => '400' ] );
		$typographyValueGlobal = ( isset( $attributes['titleTypographyGlobal'] ) ? $attributes['titleTypographyGlobal'] : '' );

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['titleColor'] ) ? $attributes['titleColor'] : '' ) ],
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
		);

	}

	public function get_Cheackout_form_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['titleColorH'] ) ? $attributes['titleColorH'] : '' ) ];
	}

	public function get_Cheackout_form_label_css( $attributes, $device = '' ) {
		$typography = isset( $attributes['labelTypography'] ) && is_array( $attributes['labelTypography'] )
			? $attributes['labelTypography']
			: [];

		$typography_value = array_merge( $typography, [ 'font-weight' => '400' ] );
		$typographyValueGlobal = ( isset( $attributes['labelTypographyGlobal'] ) ? $attributes['labelTypographyGlobal'] : '' );

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['labelColor'] ) ? $attributes['labelColor'] : '' ) ],
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
		);
	}
	public function get_Cheackout_form_label_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['labelColorH'] ) ? $attributes['labelColorH'] : '' ) ];
	}
	public function get_Cheackout_form_input_css( $attributes, $device = '' ) {

		if ( ! empty( $attributes['padding'] ) ) {
			$cssPadding = Dimensions::get_css( $attributes['padding'], 'padding', $device );
		}

		return array_merge(
			$cssPadding,
			Border::get_css( $attributes['inputBorder'], '', $device ),
		);

	}
	public function get_Cheackout_form_input_hover_css( $attributes, $device = '' ) {
		return array_merge(
			Border::get_hover_css( $attributes['buttonBorder'] ?? [], '', $device ),
		);
	}

	public function get_cheackout_form_select_css( $attributes, $device = '' ) {
		$typographyValue = ! empty( $attributes['selectTypography'] ) ? $attributes['selectTypography'] : [];
		$typographyValueGlobal = ( isset( $attributes['selectTypographyGlobal'] ) ? $attributes['selectTypographyGlobal'] : '' );
		if ( ! empty( $attributes['selectPadding'] ) ) {
			$cssPadding = Dimensions::get_css( $attributes['selectPadding'], '', $device );
		}

		if ( ! empty( $attributes['selectBorder'] ) ) {
			$cssBorder = Border::get_css( $attributes['selectBorder'], '', $device );
		}

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['selectTextcolor'] ) ? $attributes['selectTextcolor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['selectBackground'] ) ? $attributes['selectBackground'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['selectWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 200,
				'property' => 'width',
				'unitDefaultValue' => 'px',
				'device' => $device,
			]),
			$cssBorder,
			$cssPadding,
			Typography::get_css( $typographyValue, '', $device, $typographyValueGlobal )
			// $cssWidth,
		);

	}

	public function get_cheackout_form_button_css( $attributes, $device = '' ) {
		$typographyValue = ! empty( $attributes['buttonTypography'] ) ? $attributes['buttonTypography'] : [];
		$typographyValueGlobal = ( isset( $attributes['buttonTypographyGlobal'] ) ? $attributes['buttonTypographyGlobal'] : '' );

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['buttonColor'] ) ? $attributes['buttonColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['buttonBackground'] ) ? $attributes['buttonBackground'] : '' ) ],
			Typography::get_css( $typographyValue, '', $device, $typographyValueGlobal )
		);
	}

	public function get_cheackout_form_button_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['buttonColorH'] ) ? $attributes['buttonColorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['buttonBackgroundH'] ) ? $attributes['buttonBackgroundH'] : '' ) ],
		);
	}

	public function get_cheackout_form_select_hover_css( $attributes, $device = '' ) {
		if ( ! empty( $attributes['selectBorder'] ) ) {
				$cssBorder = Border::get_hover_css( $attributes['selectBorder'] ?? [], '', $device );

		}
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['selectTextcolorH'] ) ? $attributes['selectTextcolorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['selectBackgroundH'] ) ? $attributes['selectBackgroundH'] : '' ) ],
			$cssBorder,
		);
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'ids'  => Helper::get_attribute_value( $attributes, 'products_ids' ),
		];
		$shortcode = '[storeengine_checkout_form ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}
}
