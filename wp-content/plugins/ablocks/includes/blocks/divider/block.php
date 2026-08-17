<?php

namespace ABlocks\Blocks\Divider;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Icon;
use ABlocks\Controls\Range;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Color;
class Block extends BlockBaseAbstract {

	protected $block_name = 'divider';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container',
			$this->get_divider_container_css( $attributes ),
			$this->get_divider_container_css( $attributes, 'Tablet' ),
			$this->get_divider_container_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-divider',
			$this->get_divider_css( $attributes ),
			$this->get_divider_css( $attributes, 'Tablet' ),
			$this->get_divider_css( $attributes, 'Mobile' ),
		);
		// element text

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-divider__element-text',
			$this->get_divider_element_text_css( $attributes ),
			$this->get_divider_element_text_css( $attributes, 'Tablet' ),
			$this->get_divider_element_text_css( $attributes, 'Mobile' ),
		);
		// Icon Style
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-divider__element-icon .ablocks-icon-wrap',
			Icon::get_wrapper_css( $attributes ),
			Icon::get_wrapper_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-divider__element-icon .ablocks-icon-wrap:hover',
			Icon::get_wrapper_hover_css( $attributes ),
			Icon::get_wrapper_hover_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-divider__element-icon .ablocks-icon-wrap',
			array_merge( Icon::get_wrapper_css( $attributes ), $this->get_icon_spacing_margins( $attributes, '' ) ),
			array_merge( Icon::get_wrapper_css( $attributes, 'Tablet' ), $this->get_icon_spacing_margins( $attributes, 'Tablet' ) ),
			array_merge( Icon::get_wrapper_css( $attributes, 'Mobile' ), $this->get_icon_spacing_margins( $attributes, 'Mobile' ) )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-divider__element-icon .ablocks-icon-wrap img.ablocks-image-icon',
			Icon::get_element_image_css( $attributes ),
			Icon::get_element_image_css( $attributes, 'Tablet' ),
			Icon::get_element_image_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-divider__element-icon .ablocks-icon-wrap img.ablocks-image-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-divider__element-icon .ablocks-icon-wrap svg.ablocks-svg-icon',
			Icon::get_element_css( $attributes ),
			Icon::get_element_css( $attributes, 'Tablet' ),
			Icon::get_element_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-divider__element-icon .ablocks-icon-wrap svg.ablocks-svg-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--divider:not(.ablocks-has-block-container),
          .ablocks-block--divider .ablocks-block-container',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--divider:not(.ablocks-has-block-container),
          .ablocks-block--divider .ablocks-block-container',
			$this->get_divider_container_css( $attributes ),
			$this->get_divider_container_css( $attributes, 'Tablet' ),
			$this->get_divider_container_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-divider',
			$this->get_divider_css( $attributes ),
			$this->get_divider_css( $attributes, 'Tablet' ),
			$this->get_divider_css( $attributes, 'Mobile' ),
		);
		// element text

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-divider__element-text',
			$this->get_divider_element_text_css( $attributes ),
			$this->get_divider_element_text_css( $attributes, 'Tablet' ),
			$this->get_divider_element_text_css( $attributes, 'Mobile' ),
		);
		// Icon Style
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-divider__element-icon .ablocks-icon-wrap',
			Icon::get_wrapper_css( $attributes ),
			Icon::get_wrapper_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-divider__element-icon .ablocks-icon-wrap:hover',
			Icon::get_wrapper_hover_css( $attributes ),
			Icon::get_wrapper_hover_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-divider__element-icon .ablocks-icon-wrap',
			array_merge( Icon::get_wrapper_css( $attributes ), $this->get_icon_spacing_margins( $attributes, '' ) ),
			array_merge( Icon::get_wrapper_css( $attributes, 'Tablet' ), $this->get_icon_spacing_margins( $attributes, 'Tablet' ) ),
			array_merge( Icon::get_wrapper_css( $attributes, 'Mobile' ), $this->get_icon_spacing_margins( $attributes, 'Mobile' ) )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-divider__element-icon .ablocks-icon-wrap img.ablocks-image-icon',
			Icon::get_element_image_css( $attributes ),
			Icon::get_element_image_css( $attributes, 'Tablet' ),
			Icon::get_element_image_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-divider__element-icon .ablocks-icon-wrap img.ablocks-image-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-divider__element-icon .ablocks-icon-wrap svg.ablocks-svg-icon',
			Icon::get_element_css( $attributes ),
			Icon::get_element_css( $attributes, 'Tablet' ),
			Icon::get_element_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-divider__element-icon .ablocks-icon-wrap svg.ablocks-svg-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
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
		if ( isset( $attributes['alignment'] ) ) {
			return Alignment::get_css( $attributes['alignment'], 'text-align', $device );
		}
		return [];
	}

	public function get_divider_element_text_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['elementTextTypographyGlobal'] ) ? $attributes['elementTextTypographyGlobal'] : '';
		$typography_value = isset( $attributes['elementTextTypographyGlobal'] ) ? $attributes['elementTextTypographyGlobal'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['elementTextColor'] ) ? $attributes['elementTextColor'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['elementTextSpacing'],
				'attributeObjectKey' => 'value',
				'isResponsive' => true,
				'defaultValue' => 0,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'padding-right',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['elementTextSpacing'],
				'attributeObjectKey' => 'value',
				'isResponsive' => true,
				'defaultValue' => 0,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'padding-left',
				'device' => $device,
			]),
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
			TextStroke::get_css( $attributes['elementTextStroke'], '', $device ),
		);
	}


	public function get_divider_container_css( $attributes, $device = '' ) {
		$divider_container_css = [];
		if ( ! empty( $attributes['alignment'][ 'value' . $device ] ) ) {
			$divider_container_css['justify-content'] = $attributes['alignment'][ 'value' . $device ];
		}
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['gap'],
				'attributeObjectKey' => 'value',
				'isResponsive' => true,
				'defaultValue' => 10,
				'unitDefaultValue' => 'px',
				'property' => 'padding-block-start',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['gap'],
				'attributeObjectKey' => 'value',
				'isResponsive' => true,
				'defaultValue' => 10,
				'unitDefaultValue' => 'px',
				'property' => 'padding-block-end',
				'device' => $device,
			]),
			$divider_container_css
		);
	}

	public function get_divider_css( $attributes, $device = '' ) {
		$css = [];

		$prepared_container = Range::get_css([
			'attributeValue' => $attributes['width'],
			'attributeObjectKey' => 'value',
			'isResponsive' => true,
			'defaultValue' => 100,
			'hasUnit' => true,
			'unitDefaultValue' => '%',
			'property' => 'value',
			'device' => $device,
		]);
		if ( ! empty( $prepared_container['value'] ) && ( '100%' !== $prepared_container['value'] . $prepared_container['valueUnit'] || $device !== '' ) ) {
			$css['max-width'] = "min(100%, {$prepared_container['value']}{$prepared_container['valueUnit']})";
		}
		return array_merge(
			$css
		);
	}

	public function get_icon_spacing_margins( $attributes, $device = '' ) {
		$marginLeft = Range::get_css([
			'attributeValue' => $attributes['elementIconSpacing'],
			'attributeObjectKey' => 'value',
			'isResponsive' => true,
			'defaultValue' => 0,
			'hasUnit' => true,
			'unitDefaultValue' => 'px',
			'property' => 'margin-left',
			'device' => $device,
		]);
		$marginRight = Range::get_css([
			'attributeValue' => $attributes['elementIconSpacing'],
			'attributeObjectKey' => 'value',
			'isResponsive' => true,
			'defaultValue' => 0,
			'hasUnit' => true,
			'unitDefaultValue' => 'px',
			'property' => 'margin-right',
			'device' => $device,
		]);
		return array_merge( $marginLeft, $marginRight );
	}
}
