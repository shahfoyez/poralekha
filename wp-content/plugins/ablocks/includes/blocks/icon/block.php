<?php
namespace ABlocks\Blocks\Icon;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Icon;
use ABlocks\Classes\CssGeneratorV2;

class Block extends BlockBaseAbstract {
	protected $block_name = 'icon';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		// Generate wrapper CSS start
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);

		// Icon Style
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
			Icon::get_element_image_css( $attributes, 'Mobile' )
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
			Icon::get_element_image_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		// Generate wrapper CSS start
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);

		// Icon Style
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
			Icon::get_element_image_css( $attributes, 'Mobile' )
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

	public function get_wrapper_css( $attributes = [], $device = '' ) {
		$css = [];
		$css['display'] = 'flex';
		$css['align-items'] = 'flex-start';
		if ( ! empty( $attributes['alignment'][ 'value' . $device ] ) ) {
			$css['justify-content'] = $attributes['alignment'][ 'value' . $device ];
		}
		return $css;
	}
}
