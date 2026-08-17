<?php

namespace ABlocks\Blocks\EcmUpvoteList;

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
	protected $block_name = 'ecm-upvote-list';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--upvote__list-item',
			$this->getListCSS( $attributes ),
			$this->getListCSS( $attributes, 'Tablet' ),
			$this->getListCSS( $attributes, 'Mobile' ),

		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--upvote__list',
			$this->getListALignmentCSS( $attributes ),
			$this->getListALignmentCSS( $attributes, 'Tablet' ),
			$this->getListALignmentCSS( $attributes, 'Mobile' ),

		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--upvote__list-title',
			$this->getListTitleCSS( $attributes ),
			$this->getListTitleCSS( $attributes, 'Tablet' ),
			$this->getListTitleCSS( $attributes, 'Mobile' ),

		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--upvote__list-date',
			$this->getListDescriptionCSS( $attributes ),
			$this->getListDescriptionCSS( $attributes, 'Tablet' ),
			$this->getListDescriptionCSS( $attributes, 'Mobile' ),

		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} ul.ecm-shortcode--upvote__list',
			$this->getListStyleCSS( $attributes ),
			$this->getListStyleCSS( $attributes, 'Tablet' ),
			$this->getListStyleCSS( $attributes, 'Mobile' ),

		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--upvote__list-item:hover',
			$this->getListHoverCSS( $attributes ),
			$this->getListHoverCSS( $attributes, 'Tablet' ),
			$this->getListHoverCSS( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'user_id'     				  => Helper::get_attribute_value( $attributes, 'user_id' ),
			'not_found_text'     		  => Helper::get_attribute_value( $attributes, 'not_found_text' ),
		];

		$shortcode = '[ecm_upvote_list ' . Helper::attr_shortcode( $attr_array ) . ']';
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
	public function getListALignmentCSS( $attributes, $device = '' ) {
		$css = [];
		$css['display'] = 'flex';
		$css['flex-direction'] = 'column';
		$alignment_css = [];

		if ( ! empty( $attributes['listAlignment'][ 'value' . $device ] ) ) {
			$alignment_css['align-items'] = $attributes['listAlignment'][ 'value' . $device ];
		}
		return array_merge(
			$alignment_css,
			$css
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

	public function getListStyleCSS( $attributes, $device = '' ) {
		$css = [];
		 if ( $attributes['listStyle'] !== '' ) {
			$css['list-style'] = $attributes['listStyle'];
		}
		return array_merge(
			$css,
		);
	}

	public function getListHoverCSS( $attributes, $device = '' ) {
		return array_merge(
			Border::get_hover_css( $attributes['listBorder'], '', $device ),
			BoxShadow::get_hover_css( $attributes['listBoxShadow'], '', $device )
		);
	}

}
