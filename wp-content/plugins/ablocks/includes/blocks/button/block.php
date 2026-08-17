<?php
namespace ABlocks\Blocks\Button;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Border;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\Icon;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'button';
	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);
		// Generate wrapper CSS end

		// Generate button CSS start
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container .ablocks-button',
			$this->get_button_css( $attributes ),
			$this->get_button_css( $attributes, 'Tablet' ),
			$this->get_button_css( $attributes, 'Mobile' )
		);
		// Generate button CSS end

		// Generate button hover CSS start
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container .ablocks-button:hover',
			$this->get_button_hover_css( $attributes ),
			$this->get_button_hover_css( $attributes, 'Tablet' ),
			$this->get_button_hover_css( $attributes, 'Mobile' )
		);

		// Generate button icon hover CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container .ablocks-button:hover .ablocks-icon-wrap svg.ablocks-svg-icon',
			$this->get_icon_hover_css( $attributes ),
			$this->get_icon_hover_css( $attributes, 'Tablet' ),
			$this->get_icon_hover_css( $attributes, 'Mobile' )
		);

		// Generate button text CSS start
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container .ablocks-button .ablocks-button__text',
			$this->get_button_text_css( $attributes )
		);

		// Generate button icon CSS start
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
			'{{WRAPPER}} .ablocks-icon-wrap img.ablocks-image-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap svg.ablocks-svg-icon',
			Icon::get_element_css( $attributes ),
			Icon::get_element_css( $attributes, 'Tablet' ),
			Icon::get_element_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-wrap svg.ablocks-svg-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' )
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
		// Generate wrapper CSS end

		// Generate button CSS start
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-button',
			$this->get_button_css( $attributes ),
			$this->get_button_css( $attributes, 'Tablet' ),
			$this->get_button_css( $attributes, 'Mobile' )
		);

		// Generate button hover CSS start
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-button:hover',
			$this->get_button_hover_css( $attributes ),
			$this->get_button_hover_css( $attributes, 'Tablet' ),
			$this->get_button_hover_css( $attributes, 'Mobile' )
		);

		// Generate button icon hover CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-button:hover .ablocks-icon-wrap svg.ablocks-svg-icon',
			$this->get_icon_hover_css( $attributes ),
			$this->get_icon_hover_css( $attributes, 'Tablet' ),
			$this->get_icon_hover_css( $attributes, 'Mobile' )
		);

		// Generate button text CSS start
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-button .ablocks-button__text',
			$this->get_button_text_css( $attributes )
		);

		// Generate button icon CSS start
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
			'{{WRAPPER}} .ablocks-icon-wrap img.ablocks-image-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-icon-wrap svg.ablocks-svg-icon',
			Icon::get_element_css( $attributes ),
			Icon::get_element_css( $attributes, 'Tablet' ),
			Icon::get_element_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-wrap svg.ablocks-svg-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' )
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
		$position = isset( $attributes['position'][ 'value' . $device ] ) && $attributes['position'][ 'value' . $device ] !== 'stretch';

		if ( $position ) {
				$css['text-align'] = $attributes['position'][ 'value' . $device ];
		}

		return $css;
	}

	public function get_button_css( $attributes, $device = '' ) {
		$css = [];
		if ( ! empty( $attributes['buttonType'] ) && $attributes['buttonType'] === 'link' ) {
			$css['background'] = 'none';
			$css['padding'] = '0';
			$css['cursor'] = 'pointer';
		} elseif ( ! empty( $attributes['background'] ) ) {
			$css['background'] = Color::get_css(
			isset( $attributes['background'] ) ? $attributes['background'] : '');
		} elseif ( ! empty( $attributes['buttonType'] ) ) {
			$css['background'] = $attributes['buttonType'];
		}

		if ( isset( $attributes['alignment'][ 'value' . $device ] ) ) {
			$css['justify-content'] = $attributes['alignment'][ 'value' . $device ];
		}

		if ( isset( $attributes['position'][ 'value' . $device ] ) && $attributes['position'][ 'value' . $device ] === 'stretch' ) {
			$css['width'] = '100%';
		}
		$typographyValueGlobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : '';
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['iconSpace'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 10,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'column-gap',
				'device' => $device,
			]),
			$css,
			[ 'color' => Color::get_css( isset( $attributes['textColor'] ) ? $attributes['textColor'] : '' ) ],
			Border::get_css( $attributes['border'], '', $device ),
			BoxShadow::get_css( $attributes['boxShadow'], '', $device ),
			Typography::get_css( $attributes['typography'], '', $device, $typographyValueGlobal ),
			Dimensions::get_css( $attributes['padding'], 'padding', $device ),
		);
	}

	public function get_button_hover_css( $attributes, $device = '' ) {
		$css = [];
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['transition'],
				'attribute_object_key' => 'value',
				'defaultValue' => 10,
				'unitDefaultValue' => 's',
				'property' => 'transition-duration',
				'device' => $device,
			]),
			$css,
			[ 'color' => Color::get_css( isset( $attributes['textColorH'] ) ? $attributes['textColorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['backgroundH'] ) ? $attributes['backgroundH'] : '' ) ],
			Border::get_hover_css( $attributes['border'], '', $device ),
			BoxShadow::get_hover_css( $attributes['boxShadow'], '', $device )
		);
	}

	public function get_button_text_css( $attributes, $device = '' ) {
		return TextShadow::get_css( $attributes['textShadow'] );
	}

	public function get_icon_hover_css( $attributes, $device = '' ) {
		return [ 'fill' => Color::get_css( isset( $attributes['textColorH'] ) ? $attributes['textColorH'] : '' ) ];
	}


}
