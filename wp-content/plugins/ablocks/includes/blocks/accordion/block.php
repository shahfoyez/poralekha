<?php
namespace ABlocks\Blocks\Accordion;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Helper;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'accordion';

	public function render_callback( $attributes, $content, $block_instance ) {
		$content = parent::render_callback( $attributes, $content, $block_instance );
		if ( ! empty( $attributes['enableFaqSchema'] ) ) {
			static $faq_schema_rendered = false;
			if ( ! $faq_schema_rendered ) {
				$schema = $this->generate_faq_schema( $block_instance );
				if ( $schema ) {
					$content .= '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . '</script>';
					$faq_schema_rendered = true;
				}
			}
		}
		return $content;
	}

	private function generate_faq_schema( $block_instance ) {
		$items = [];
		foreach ( $block_instance->inner_blocks as $inner_block ) {
			if ( $inner_block->name !== 'ablocks/single-accordion' ) {
				continue;
			}
			$question = isset( $inner_block->attributes['accordionTitle'] ) ? wp_strip_all_tags( $inner_block->attributes['accordionTitle'] ) : '';
			$answer_parts = [];
			foreach ( $inner_block->inner_blocks as $child ) {
				$answer_parts[] = wp_strip_all_tags( render_block( $child->parsed_block ) );
			}
			$answer = trim( preg_replace( '/\s+/', ' ', implode( ' ', $answer_parts ) ) );
			if ( $question && $answer ) {
				$items[] = [
					'@type'          => 'Question',
					'name'           => $question,
					'acceptedAnswer' => [ '@type' => 'Answer', 'text' => $answer ],
				];
			}
		}
		if ( empty( $items ) ) {
			return null;
		}
		return [
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $items,
		];
	}

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion',
			$this->get_item_css( $attributes ),
			$this->get_item_css( $attributes, 'Tablet' ),
			$this->get_item_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion:hover',
			$this->get_item_hover_css( $attributes ),
			$this->get_item_hover_css( $attributes, 'Tablet' ),
			$this->get_item_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion__heading .ablocks-block-accordion-title',
			$this->get_title_css( $attributes ),
			$this->get_title_css( $attributes, 'Tablet' ),
			$this->get_title_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion__heading:hover .ablocks-block-accordion-title',
			$this->get_title_hover_css( $attributes ),
			$this->get_title_hover_css( $attributes, 'Tablet' ),
			$this->get_title_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion-is-selected .ablocks-block-accordion-title',
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
			'{{WRAPPER}} .ablocks-block--single-accordion-is-selected .ablocks-block--single-accordion__heading',
			$this->get_panel_active_css( $attributes ),
			$this->get_panel_active_css( $attributes, 'Tablet' ),
			$this->get_panel_active_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion__heading svg.ablocks-svg-icon',
			$this->get_icon_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-block--single-accordion__heading:hover svg.ablocks-svg-icon',
			$this->get_icon_hover_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion-is-selected svg.ablocks-svg-icon',
			$this->get_icon_active_css( $attributes )
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
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion',
			$this->get_item_css( $attributes ),
			$this->get_item_css( $attributes, 'Tablet' ),
			$this->get_item_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion:hover',
			$this->get_item_hover_css( $attributes ),
			$this->get_item_hover_css( $attributes, 'Tablet' ),
			$this->get_item_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion__heading .ablocks-block-accordion-title',
			$this->get_title_css( $attributes ),
			$this->get_title_css( $attributes, 'Tablet' ),
			$this->get_title_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion__heading:hover .ablocks-block-accordion-title',
			$this->get_title_hover_css( $attributes ),
			$this->get_title_hover_css( $attributes, 'Tablet' ),
			$this->get_title_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion-is-selected .ablocks-block-accordion-title',
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
			'{{WRAPPER}} .ablocks-block--single-accordion-is-selected .ablocks-block--single-accordion__heading',
			$this->get_panel_active_css( $attributes ),
			$this->get_panel_active_css( $attributes, 'Tablet' ),
			$this->get_panel_active_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion__heading svg.ablocks-svg-icon',
			$this->get_icon_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-block--single-accordion__heading:hover svg.ablocks-svg-icon',
			$this->get_icon_hover_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--single-accordion-is-selected svg.ablocks-svg-icon',
			$this->get_icon_active_css( $attributes )
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
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}
	public function get_item_css( $attributes, $device = '' ) {
		$css = [];

		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['itemSpace'],
				'attribute_object_key' => 'value',
				'isResponsive' => false,
				'defaultValue' => 10,
				'property' => 'margin-bottom',
				'hasUnit' => false,
				'unitDefaultValue' => 'px',
				'device' => $device,

			]),
			$css,
			isset( $attributes['itemBorder'] ) ? Border::get_css( $attributes['itemBorder'], '', $device ) : []
		);
	}
	public function get_item_hover_css( $attributes, $device = '' ) {
		return array_merge(
			isset( $attributes['itemBorder'] ) ? Border::get_hover_css( $attributes['itemBorder'], '', $device ) : []
		);
	}
	public function get_title_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['headerTypographyGlobal'] ) ? $attributes['headerTypographyGlobal'] : '';
		$typography_value = isset( $attributes['headerTypography'] ) ? $attributes['headerTypography'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['headerTextColor'] ) ? $attributes['headerTextColor'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['iconSpace'],
				'attribute_object_key' => 'value',
				'isResponsive' => false,
				'defaultValue' => 10,
				'property' => 'margin-left',
				'device' => $device,
			]),
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
			TextShadow::get_css( $attributes['headerTextShadow'], '', $device ),
			TextStroke::get_css( $attributes['headerTextStroke'], '', $device )
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
			isset( $attributes['headerBorder'] ) ? Border::get_css( $attributes['headerBorder'], '', $device ) : [],
			isset( $attributes['headerPadding'] ) ? Dimensions::get_css( $attributes['headerPadding'], 'padding', $device ) : []
		);
	}
	public function get_panel_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['headerBackgroundColorH'] ) ? $attributes['headerBackgroundColorH'] : '' ) ],
			isset( $attributes['headerBorder'] ) ? Border::get_hover_css( $attributes['headerBorder'], '', $device ) : []
		);
	}
	public function get_panel_active_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['headerBackgroundActiveColor'] ) ? $attributes['headerBackgroundActiveColor'] : '' ) ];
	}
	public function get_icon_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'fill' => Color::get_css( isset( $attributes['iconColor'] ) ? $attributes['iconColor'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['iconSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => false,
				'defaultValue' => 30,
				'property' => 'font-size',
				'device' => $device,
			]),
		);
	}
	public function get_icon_hover_css( $attributes ) {
		return [ 'fill' => Color::get_css( isset( $attributes['iconColorH'] ) ? $attributes['iconColorH'] : '' ) ];
	}
	public function get_icon_active_css( $attributes ) {
		return [ 'fill' => Color::get_css( isset( $attributes['iconActiveColor'] ) ? $attributes['iconActiveColor'] : '' ) ];
	}
	public function get_content_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['bodyBackground'] ) ? $attributes['bodyBackground'] : '' ) ],
			isset( $attributes['bodyPadding'] ) ? Dimensions::get_css( $attributes['bodyPadding'], 'padding', $device ) : []
		);
	}
	public function get_content_hover_css( $attributes, $device = '' ) {

		return [ 'background' => Color::get_css( isset( $attributes['bodyBackgroundH'] ) ? $attributes['bodyBackgroundH'] : '' ) ];

	}
}
