<?php
namespace ABlocks\Blocks\ScrollToTop;

use ABlocks\Controls\Icon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Helper;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Color;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;

class Block extends BlockBaseAbstract {
	protected $block_name = 'scroll-to-top';
	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );
			$css_generator->add_class_styles(
				'{{WRAPPER}}',
				$this->get_wrapper_css( $attributes ),
				$this->get_wrapper_css( $attributes, 'Tablet' ),
				$this->get_wrapper_css( $attributes, 'Mobile' )
			);
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-scroll-to-top-button-text',
				$this->get_button_text_css( $attributes ),
				$this->get_button_text_css( $attributes, 'Tablet' ),
				$this->get_button_text_css( $attributes, 'Mobile' )
			);
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-scroll-to-top-button-text:hover',
				$this->get_button_text_hover_css( $attributes ),
				$this->get_button_text_hover_css( $attributes, 'Tablet' ),
				$this->get_button_text_hover_css( $attributes, 'Mobile' )
			);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-wrap',
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
			'{{WRAPPER}} .ablocks-icon-wrap img.ablocks-image-icon',
			Icon::get_element_image_css( $attributes ),
			Icon::get_element_image_css( $attributes, 'Tablet' ),
			Icon::get_element_image_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-wrap img.ablocks-image-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-wrap svg.ablocks-svg-icon',
			Icon::get_element_css( $attributes ),
			Icon::get_element_css( $attributes, 'Tablet' ),
			Icon::get_element_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-wrap svg.ablocks-svg-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}

	public function get_wrapper_css( $attributes, $device = '' ) {
		$css = [];
		if ( $attributes['position'] === 'left' || $attributes['position'] === 'right' ) {
			$css = array_merge(
				[
					'position' => 'fixed',
					'z-index'  => '9999',
				],
				Range::get_css([
					'attributeValue'      => $attributes['positionBottom'],
					'attribute_object_key' => 'value',
					'isResponsive'        => true,
					'hasUnit'             => true,
					'defaultValue'        => 20,
					'unitDefaultValue'    => 'px',
					'property'            => 'bottom',
					'device'              => $device,
				])
			);
			if ( $attributes['position'] === 'left' ) {
				$css = array_merge(
					$css,
					Range::get_css([
						'attributeValue'      => $attributes['positionLeft'],
						'attribute_object_key' => 'value',
						'isResponsive'        => true,
						'hasUnit'             => true,
						'defaultValue'        => 20,
						'unitDefaultValue'    => 'px',
						'property'            => 'left',
						'device'              => $device,
					])
				);
			} elseif ( $attributes['position'] === 'right' ) {
				$css = array_merge(
					$css,
					Range::get_css([
						'attributeValue'      => $attributes['positionRight'],
						'attribute_object_key' => 'value',
						'isResponsive'        => true,
						'hasUnit'             => true,
						'defaultValue'        => 20,
						'unitDefaultValue'    => 'px',
						'property'            => 'right',
						'device'              => $device,
					])
				);
			}//end if
		}//end if
		return array_merge(
			$css,
			Alignment::get_css( $attributes['alignment'], 'justify-content', $device )
		);
	}



	public function get_button_text_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['buttonTextColor'] ) ? $attributes['buttonTextColor'] : '' ) ],
			Typography::get_css( $attributes['typography'], '', $device, $typographyValueGlobal ),
			TextShadow::get_css( $attributes['textShadow'], '', $device ),
			TextStroke::get_css( $attributes['textStroke'], '', $device ),
			[ 'background' => Color::get_css( isset( $attributes['buttonTextColorBg'] ) ? $attributes['buttonTextColorBg'] : '' ) ],
			Border::get_css( $attributes['border'], '', $device ),
			Dimensions::get_css( isset( $attributes['padding'] ) ? $attributes['padding'] : [], 'padding', $device ),
		);
	}
	public function get_button_text_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['buttonTextColorH'] ) ? $attributes['buttonTextColorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['buttonTextColorBgH'] ) ? $attributes['buttonTextColorBgH'] : '' ) ],
			Border::get_hover_css( $attributes['border'], '', $device )
		);
	}


}
