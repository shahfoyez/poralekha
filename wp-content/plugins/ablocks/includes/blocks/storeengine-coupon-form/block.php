<?php
namespace ABlocks\Blocks\StoreengineCouponForm;

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
use ABlocks\Controls\Range;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'storeengine-coupon-form';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-apply-coupon-form-shortcode .storeengine-ajax-apply-coupon-form',
			$this->get_coupon_form_css( $attributes ),
			$this->get_coupon_form_css( $attributes, 'Tablet' ),
			$this->get_coupon_form_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-apply-coupon-form-shortcode .storeengine-ajax-apply-coupon-form input[type=text]',
			$this->get_coupon_form_input_css( $attributes ),
			$this->get_coupon_form_input_css( $attributes, 'Tablet' ),
			$this->get_coupon_form_input_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-apply-coupon-form-shortcode .storeengine-ajax-apply-coupon-form input[type=text]:hover',
			$this->get_coupon_form_input_hover_css( $attributes ),
			$this->get_coupon_form_input_hover_css( $attributes, 'Tablet' ),
			$this->get_coupon_form_input_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-apply-coupon-form-shortcode .storeengine-ajax-apply-coupon-form input[type=submit]',
			$this->get_coupon_form_button_css( $attributes ),
			$this->get_coupon_form_button_css( $attributes, 'Tablet' ),
			$this->get_coupon_form_button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .storeengine-apply-coupon-form-shortcode .storeengine-ajax-apply-coupon-form input[type=submit]:hover',
			$this->get_coupon_form_button_hover_css( $attributes ),
			$this->get_coupon_form_button_hover_css( $attributes, 'Tablet' ),
			$this->get_coupon_form_button_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function get_coupon_form_css( $attributes, $device = '' ) {
		$css = [];

		if ( $attributes['direction'] !== '' ) {
			$css['flex-direction'] = $attributes['direction'];
		}

		if ( ! empty( $attributes['formAlignment'][ 'value' . $device ] ) ) {
			$css['justify-content'] = $attributes['formAlignment'][ 'value' . $device ];
		}

		return $css;

	}

	public function get_coupon_form_input_css( $attributes, $device = '' ) {
		// $css = [];
		// $css['width'] = $attributes['inputWidth'].'px';

		return array_merge(
			// $css,
			Border::get_css( $attributes['inputBorder'], '', $device ),
			Range::get_css([
				'attributeValue' => $attributes['inputWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 200,
				'property' => 'width',
				'unitDefaultValue' => 'px',
				'device' => $device,
			]),
		);

	}

	public function get_coupon_form_input_hover_css( $attributes, $device = '' ) {
		return array_merge(
			Border::get_hover_css( $attributes['inputBorder'] ?? [], '', $device ),
		);
	}

	public function get_coupon_form_button_css( $attributes, $device = '' ) {
		$typography = isset( $attributes['buttonTypography'] ) && is_array( $attributes['buttonTypography'] )
			? $attributes['buttonTypography']
			: [];

		$typography_value = array_merge( $typography, [ 'font-weight' => '400' ] );
		$typgraphyGlobal = ( isset( $attributes['buttonTypographyGlobal'] ) ? $attributes['buttonTypographyGlobal'] : '' );

		if ( ! empty( $attributes['padding'] ) ) {
			$cssPadding = Dimensions::get_css( $attributes['padding'], 'padding', $device );
		}

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['buttonColor'] ) ? $attributes['buttonColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['buttonBackground'] ) ? $attributes['buttonBackground'] : '' ) ],
			Typography::get_css( $typography_value, '', $device, $typgraphyGlobal ),
			Border::get_css( $attributes['buttonBorder'], '', $device ),
			$cssPadding,
			BoxShadow::get_css( $attributes['boxShadow'], '', $device ),
			Range::get_css([
				'attributeValue' => $attributes['buttonWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 200,
				'property' => 'width',
				'unitDefaultValue' => 'px',
				'device' => $device,
			]),
		);
	}
	public function get_coupon_form_button_hover_css( $attributes, $device = '' ) {
			return array_merge(
				Border::get_hover_css( $attributes['buttonBorder'] ?? [], '', $device ),
				BoxShadow::get_hover_css( $attributes['boxShadow'], '', $device ),
				[ 'color' => Color::get_css( isset( $attributes['buttonColorH'] ) ? $attributes['buttonColorH'] : '' ) ],
				[ 'background' => Color::get_css( isset( $attributes['buttonBackgroundH'] ) ? $attributes['buttonBackgroundH'] : '' ) ]
			);
	}


	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'placeholder' => Helper::get_attribute_value( $attributes, 'input_placeholder' ),
			'buttonTitle' => Helper::get_attribute_value( $attributes, 'buttonTitle' ),
		];
		$shortcode = '[storeengine_apply_coupon_form ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
