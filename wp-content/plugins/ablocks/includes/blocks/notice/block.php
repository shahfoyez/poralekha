<?php
namespace ABlocks\Blocks\Notice;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Icon;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'notice';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-notice-header',
			$this->get_notice_header_css( $attributes ),
			$this->get_notice_header_css( $attributes, 'Tablet' ),
			$this->get_notice_header_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-notice-header .ablocks-notice-title',
			$this->get_notice_header_title_css( $attributes ),
			$this->get_notice_header_title_css( $attributes, 'Tablet' ),
			$this->get_notice_header_title_css( $attributes, 'Mobile' )
		);

		// Icon CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-notice-header .ablocks-icon-wrap',
			Icon::get_wrapper_css( $attributes ),
			Icon::get_wrapper_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-notice-header .ablocks-icon-wrap:hover',
			Icon::get_wrapper_hover_css( $attributes ),
			Icon::get_wrapper_hover_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-notice-header .ablocks-icon-wrap img.ablocks-image-icon',
			Icon::get_element_image_css( $attributes ),
			Icon::get_element_image_css( $attributes, 'Tablet' ),
			Icon::get_element_image_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-notice-header .ablocks-icon-wrap img.ablocks-image-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-notice-header .ablocks-icon-wrap svg.ablocks-svg-icon',
			Icon::get_element_css( $attributes ),
			Icon::get_element_css( $attributes, 'Tablet' ),
			Icon::get_element_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-notice-header .ablocks-icon-wrap svg.ablocks-svg-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-notice-header',
			$this->get_notice_header_css( $attributes ),
			$this->get_notice_header_css( $attributes, 'Tablet' ),
			$this->get_notice_header_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-notice-header .ablocks-notice-title',
			$this->get_notice_header_title_css( $attributes ),
			$this->get_notice_header_title_css( $attributes, 'Tablet' ),
			$this->get_notice_header_title_css( $attributes, 'Mobile' )
		);

		// Icon CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-notice-header .ablocks-icon-wrap',
			Icon::get_wrapper_css( $attributes ),
			Icon::get_wrapper_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-notice-header .ablocks-icon-wrap:hover',
			Icon::get_wrapper_hover_css( $attributes ),
			Icon::get_wrapper_hover_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-notice-header .ablocks-icon-wrap img.ablocks-image-icon',
			Icon::get_element_image_css( $attributes ),
			Icon::get_element_image_css( $attributes, 'Tablet' ),
			Icon::get_element_image_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-notice-header .ablocks-icon-wrap img.ablocks-image-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-notice-header .ablocks-icon-wrap svg.ablocks-svg-icon',
			Icon::get_element_css( $attributes ),
			Icon::get_element_css( $attributes, 'Tablet' ),
			Icon::get_element_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-notice-header .ablocks-icon-wrap svg.ablocks-svg-icon:hover',
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
	public function get_notice_header_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['backgroundColor'] ) ? $attributes['backgroundColor'] : '' ) ],
			Dimensions::get_css( $attributes['noticeHeaderPadding'], 'padding', $device )
		);
	}

	public function get_notice_header_title_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['textColor'] ) ? $attributes['textColor'] : '' ) ],
			Alignment::get_css( $attributes['alignment'], 'justify-content', $device ),
			Typography::get_css( $attributes['typography'], '', $device, $typographyValueGlobal ),
			TextShadow::get_css( $attributes['textShadow'], '', $device ),
			TextStroke::get_css( $attributes['textStroke'], '', $device ),
		);
	}

}
