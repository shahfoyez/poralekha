<?php
namespace ABlocks\Blocks\SingleAccordion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;


class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'accordion';
	protected $block_name = 'single-accordion';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--single-accordion',
			$this->get_item_css( $attributes ),
			$this->get_item_css( $attributes, 'Tablet' ),
			$this->get_item_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--single-accordion:hover',
			$this->get_item_hover_css( $attributes ),
			$this->get_item_hover_css( $attributes, 'Tablet' ),
			$this->get_item_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion__heading .ablocks-block-accordion-title',
			$this->get_title_css( $attributes ),
			$this->get_title_css( $attributes, 'Tablet' ),
			$this->get_title_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion__heading:hover  .ablocks-block-accordion-title', $this->get_title_hover_css( $attributes ),
			$this->get_title_hover_css( $attributes, 'Tablet' ),
			$this->get_title_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--single-accordion-is-selected .ablocks-block-accordion-title',
			$this->get_title_active_css( $attributes ),
			$this->get_title_active_css( $attributes, 'Tablet' ),
			$this->get_title_active_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion__heading',
			$this->get_panel_css( $attributes ),
			$this->get_panel_css( $attributes, 'Tablet' ),
			$this->get_panel_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion__heading:hover',
			$this->get_panel_hover_css( $attributes ),
			$this->get_panel_hover_css( $attributes, 'Tablet' ),
			$this->get_panel_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--single-accordion-is-selected .ablocks-block--single-accordion__heading',
			$this->get_panel_active_css( $attributes ),
			$this->get_panel_active_css( $attributes, 'Tablet' ),
			$this->get_panel_active_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion__heading svg.ablocks-svg-icon',
			$this->get_icon_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion__heading:hover svg.ablocks-svg-icon',
			$this->get_icon_hover_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion__body-content',
			$this->get_content_css( $attributes ),
			$this->get_content_css( $attributes, 'Tablet' ),
			$this->get_content_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion__body-content:hover',
			$this->get_content_hover_css( $attributes ),
			$this->get_content_hover_css( $attributes, 'Tablet' ),
			$this->get_content_hover_css( $attributes, 'Mobile' )
		);
		return $css_generator->generate_css();
	}
	public function get_item_css( $attributes, $device = '' ) {
		$css = [];
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['itemSpace'],
				'attribute_object_key' => 'value',
				'isResponsive' => false,
				'property' => 'margin-bottom',
				'hasUnit' => false,
				'unitDefaultValue' => 'px',
				'device' => $device,
			] ),
			$css,
			Border::get_css( $attributes['itemBorder'], '', $device )
		);
	}
	public function get_item_hover_css( $attributes, $device = '' ) {
		return array_merge(
			Border::get_hover_css( $attributes['itemBorder'], '', $device )
		);
	}
	public function get_title_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['headerTypographyGlobal'] ) ? $attributes['headerTypographyGlobal'] : array();
		$typography_css = Typography::get_css( $attributes['headerTypography'], '', $device, $typographyValueGlobal );
		$textShadowCss = TextShadow::get_css( $attributes['headerTextShadow'], '', $device );
		$textStrokeCss = TextStroke::get_css( $attributes['headerTextStroke'], '', $device );
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['headerTextColor'] ) ? $attributes['headerTextColor'] : '' ) ],
			$typography_css,
			$textShadowCss,
			$textStrokeCss
		);
	}

	public function get_title_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['headerTextColorH'] ) ? $attributes['headerTextColorH'] : '' ) ];
	}
	public function get_title_active_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['headerTextActiveColor'] ) ? $attributes['headerTextActiveColor'] : '' ) ];
	}
	public function get_panel_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['headerBackgroundColor'] ) ? $attributes['headerBackgroundColor'] : '' ) ],
			Border::get_css( $attributes['headerBorder'], '', $device ),
			Dimensions::get_css( $attributes['headerPadding'], 'padding', $device )
		);
	}
	public function get_panel_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['headerBackgroundColorH'] ) ? $attributes['headerBackgroundColorH'] : '' ) ],
			Border::get_hover_css( $attributes['headerBorder'], '', $device )
		);
	}
	public function get_panel_active_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['headerBackgroundActiveColor'] ) ? $attributes['headerBackgroundActiveColor'] : '' ) ];
	}
	public function get_icon_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'fill' => Color::get_css( isset( $attributes['iconColor'] ) ? $attributes['iconColor'] : '' ) . ' !important' ],
			Range::get_css( [
				'attributeValue' => $attributes['iconSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => false,
				'property' => 'font-size',
				'device' => $device,
			] )
		);
	}

	public function get_icon_hover_css( $attributes ) {
		return [ 'fill' => Color::get_css( isset( $attributes['iconColorH'] ) ? $attributes['iconColorH'] : '' ) . ' !important' ];

	}
	public function get_content_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['bodyBackground'] ) ? $attributes['bodyBackground'] : '' ) ],
			Dimensions::get_css( $attributes['bodyPadding'], 'padding', $device )
		);
	}
	public function get_content_hover_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['bodyBackgroundH'] ) ? $attributes['bodyBackgroundH'] : '' ) ];
	}
}
