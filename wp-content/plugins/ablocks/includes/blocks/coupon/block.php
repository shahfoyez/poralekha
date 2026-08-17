<?php
namespace ABlocks\Blocks\Coupon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Range;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Icon;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Border;
use ABlocks\Controls\TextShadow;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Color;



class Block extends BlockBaseAbstract {
	protected $block_name = 'coupon';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap',
			Icon::get_wrapper_css( $attributes ),
			Icon::get_wrapper_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-wrap:hover',
			Icon::get_wrapper_hover_css( $attributes ),
			Icon::get_wrapper_hover_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap img.ablocks-image-icon',
			Icon::get_element_image_css( $attributes ),
			Icon::get_element_image_css( $attributes, 'Tablet' ),
			Icon::get_element_image_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap img.ablocks-image-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap svg.ablocks-svg-icon',
			Icon::get_element_css( $attributes ),
			Icon::get_element_css( $attributes, 'Tablet' ),
			Icon::get_element_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap svg.ablocks-svg-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-coupon-code',
			$this->get_coupon_text_css( $attributes ),
			$this->get_coupon_text_css( $attributes, 'Tablet' ),
			$this->get_coupon_text_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-coupon-clipboard',
			$this->get_btn_text_css( $attributes ),
			$this->get_btn_text_css( $attributes, 'Tablet' ),
			$this->get_btn_text_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap',
			Icon::get_wrapper_css( $attributes ),
			Icon::get_wrapper_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-wrap:hover',
			Icon::get_wrapper_hover_css( $attributes ),
			Icon::get_wrapper_hover_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap img.ablocks-image-icon',
			Icon::get_element_image_css( $attributes ),
			Icon::get_element_image_css( $attributes, 'Tablet' ),
			Icon::get_element_image_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap img.ablocks-image-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap svg.ablocks-svg-icon',
			Icon::get_element_css( $attributes ),
			Icon::get_element_css( $attributes, 'Tablet' ),
			Icon::get_element_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap svg.ablocks-svg-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-coupon-code',
			$this->get_coupon_text_css( $attributes ),
			$this->get_coupon_text_css( $attributes, 'Tablet' ),
			$this->get_coupon_text_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-coupon-clipboard',
			$this->get_btn_text_css( $attributes ),
			$this->get_btn_text_css( $attributes, 'Tablet' ),
			$this->get_btn_text_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}


	public function get_wrapper_css( $attributes, $device = '' ) {
		$css = [];

		if ( isset( $attributes['position'][ 'value' . $device ] ) ) {
			$css['justify-content'] = $attributes['position'][ 'value' . $device ];
		}

		return $css;
	}

	public function get_button_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['textColorH'] ) ? $attributes['textColorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['backgroundH'] ) ? $attributes['backgroundH'] : '' ) ],
			isset( $attributes['padding'] ) ? Dimensions::get_css( $attributes['padding'], 'padding', $device ) : [],
		);
	}


	public function get_coupon_text_css( $attributes, $device = '' ) {
		$typographyGlobal = ( isset( $attributes['couponTypographyGlobal'] ) ? $attributes['couponTypographyGlobal'] : '' );
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['couponCodeColor'] ) ? $attributes['couponCodeColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['couponCodeBgColor'] ) ? $attributes['couponCodeBgColor'] : '' ) ],
			isset( $attributes['couponBorder'] ) ? Border::get_css( $attributes['couponBorder'], '', $device ) : [],
			isset( $attributes['couponPadding'] ) ? Dimensions::get_css( $attributes['couponPadding'], 'padding', $device ) : [],
			Typography::get_css( $attributes['couponTypography'], '', $device, $typographyGlobal ),
			TextShadow::get_css( $attributes['couponTextShadow'], '', $device )
		);
	}

	public function get_btn_text_css( $attributes, $device = '' ) {
		$typographyGlobal = ( isset( $attributes['buttonTypographyGlobal'] ) ? $attributes['buttonTypographyGlobal'] : '' );
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['couponBtnTextColor'] ) ? $attributes['couponBtnTextColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['couponBtnBgColor'] ) ? $attributes['couponBtnBgColor'] : '' ) ],
			Border::get_css( $attributes['buttonBorder'], '', $device ),
			Dimensions::get_css( $attributes['buttonPadding'], 'padding', $device ),
			Typography::get_css( $attributes['buttonTypography'], '', $device, $typographyGlobal ),
			TextShadow::get_css( $attributes['buttonTextShadow'], '', $device )
		);
	}

	public function get_icon_css( $attributes, $device = '' ) {

		return ! empty( $attributes['iconRotate'] ) ? [ 'transform' => 'rotate(' . $attributes['iconRotate'] . 'deg)' ] : [];

	}

}
