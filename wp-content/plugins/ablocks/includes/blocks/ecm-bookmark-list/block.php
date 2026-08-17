<?php

namespace ABlocks\Blocks\EcmBookmarkList;

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
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;

class Block extends BlockBaseAbstract {
	protected $block_name = 'ecm-bookmark-list';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--bookmark__list-item',
			$this->getListCSS( $attributes ),
			$this->getListCSS( $attributes, 'Tablet' ),
			$this->getListCSS( $attributes, 'Mobile' ),

		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--bookmark__list-item:hover',
			$this->getListItemHoverCSS( $attributes ),
			$this->getListItemHoverCSS( $attributes, 'Tablet' ),
			$this->getListItemHoverCSS( $attributes, 'Mobile' ),

		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--bookmark__list',
			$this->getListALignmentCSS( $attributes ),
			$this->getListALignmentCSS( $attributes, 'Tablet' ),
			$this->getListALignmentCSS( $attributes, 'Mobile' ),

		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--bookmark__list-title',
			$this->getListTitleCSS( $attributes ),
			$this->getListTitleCSS( $attributes, 'Tablet' ),
			$this->getListTitleCSS( $attributes, 'Mobile' ),

		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--bookmark__list-date',
			$this->getListDescriptionCSS( $attributes ),
			$this->getListDescriptionCSS( $attributes, 'Tablet' ),
			$this->getListDescriptionCSS( $attributes, 'Mobile' ),

		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->getListNotFoundCSS($attributes),
			$this->getListNotFoundCSS($attributes, 'Tablet'),
			$this->getListNotFoundCSS($attributes, 'Mobile')
		);

		return $css_generator->generate_css();
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'user_id'     				  => Helper::get_attribute_value( $attributes, 'user_id' ),
			'not_found_text'     		  => Helper::get_attribute_value( $attributes, 'not_found_text' ),
		];

		$shortcode = '[ecm_bookmark_list ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

	public function getListCSS( $attributes, $device = '' ) {
		$css = [];
		$cssPadding = [];
		if ( $attributes['listBackground'] !== '' ) {
			$css['background'] = $attributes['listBackground'];
		}
		if ( ! empty( $attributes['listPadding'] ) ) {
			$cssPadding = Dimensions::get_css( $attributes['listPadding'], 'padding', $device );
		}
		return array_merge(
				[ 'background' => Color::get_css( isset( $attributes['listBackground'] ) ? $attributes['listBackground'] : '' ) ],
				$css,
				Range::get_css([
					'attributeValue' => $attributes['listWidth'],
					'attribute_object_key' => 'value',
					'isResponsive' => true,
					'hasUnit' => true,
					'defaultValue' => 33,
					'unitDefaultValue' => '%',
					'property' => 'width',
					'device' => $device,
				]),
				Border::get_css( $attributes['listBorder'], '', $device ),
				$cssPadding,
				BoxShadow::get_css( $attributes['listBoxShadow'], '', $device ),
			);
	}
	public function getListItemHoverCSS( $attributes, $device = '' ) {
		$css = [];
		return array_merge(
			$css,
			BoxShadow::get_hover_css( $attributes['listBoxShadow'], '', $device ),
			Border::get_hover_css( $attributes['listBorder'], '', $device )
		);
	}
	public function getListALignmentCSS( $attributes, $device = '' ) {
		$css = [];
		$alignment_css = [];
		$alignment_css['display'] = 'flex';

		if ( ! empty( $attributes['listAlignment'][ 'value' . $device ] ) ) {
			$alignment_css['align-items'] = $attributes['listAlignment'][ 'value' . $device ];
		}
		return array_merge(
			$alignment_css,
		);
	}
	public function getListTitleCSS( $attributes, $device = '' ) {
		$css = [];
		if ( $attributes['titleColor'] !== '' ) {
			$css['color'] = $attributes['titleColor'];
		}
		$typographyValueGlobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : '';
		$typography_css = ! empty( $attributes['titleTypography'] ) ? Typography::get_css( $attributes['titleTypography'], '', $device, $typographyValueGlobal ) : array();
		$textShadowCss = ! empty( $attributes['titleTextShadow'] ) ? TextShadow::get_css( $attributes['titleTextShadow'], '', $device ) : array();
		$textStrokeCss = ! empty( $attributes['titleTextStroke'] ) ? TextStroke::get_css( $attributes['titleTextStroke'], '', $device ) : array();

		return array_merge(
			$css,
			$typography_css,
			$textShadowCss,
			$textStrokeCss
		);
	}
	public function getListDescriptionCSS( $attributes, $device = '' ) {
		$css = [];
		if ( $attributes['desColor'] !== '' ) {
			$css['color'] = $attributes['desColor'];
		}
		$typographyValueGlobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : '';
		$typography_css = ! empty( $attributes['desTypography'] ) ? Typography::get_css( $attributes['desTypography'], '', $device, $typographyValueGlobal ) : array();
		$textShadowCss = ! empty( $attributes['desTextShadow'] ) ? TextShadow::get_css( $attributes['desTextShadow'], '', $device ) : array();
		$textStrokeCss = ! empty( $attributes['desTextStroke'] ) ? TextStroke::get_css( $attributes['desTextStroke'], '', $device ) : array();

		return array_merge(
			$css,
			$typography_css,
			$textShadowCss,
			$textStrokeCss
		);
	}
	public function getListNotFoundCSS( $attributes, $device = '' ) {
		$css = [];
		if ( ! empty( $attributes['notFoundAlignment'][ 'value' . $device ] ) ) {
			$css['text-align'] = $attributes['notFoundAlignment'][ 'value' . $device ];
		}
		$color_css = ! empty( $attributes['notFoundColor'] ) ? [ 'color' => Color::get_css( $attributes['notFoundColor'] ) ] : [];
		$typographyValueGlobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : '';
		$typography_css = ! empty( $attributes['notFoundTypography'] ) ? Typography::get_css( $attributes['notFoundTypography'], '', $device, $typographyValueGlobal ) : [];
		return array_merge( $css, $color_css, $typography_css );
	}
}
