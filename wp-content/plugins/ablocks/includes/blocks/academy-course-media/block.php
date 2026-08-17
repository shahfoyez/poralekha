<?php
namespace ABlocks\Blocks\AcademyCourseMedia;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Helper;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;
use ABlocks\Controls\BoxShadow;

class Block extends BlockBaseAbstract {
	protected $block_name = 'academy-course-media';

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--academy-course-image:not(.ablocks-block-container),
			{{WRAPPER}} .academy-default-featured-image,
			{{WRAPPER}} .plyr--video',
			$this->get_featured_image_css( $attributes ),
			$this->get_featured_image_css( $attributes, 'Tablet' ),
			$this->get_featured_image_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--academy-course-media:not(.ablocks-block-container),
			{{WRAPPER}}.ablocks-block--academy-course-media .ablocks-block-container',
			$this->get_featured_image_container_css( $attributes ),
			$this->get_featured_image_container_css( $attributes, 'Tablet' ),
			$this->get_featured_image_container_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--academy-course-image:not(.ablocks-block-container),
			{{WRAPPER}} .academy-default-featured-image:hover,
			{{WRAPPER}} .plyr--video:hover',
			$this->get_featured_image_hover_css( $attributes ),
			$this->get_featured_image_hover_css( $attributes, 'Tablet' ),
			$this->get_featured_image_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_featured_image_css( $attributes, $device = '' ) {
		$css = [];
		$css['opacity']  = $attributes['imageOpacity'] ?? '';

		return array_merge(
			$css,
			isset( $attributes['border'] ) ? Border::get_css( $attributes['border'], '', $device ) : [],
			isset( $attributes['boxShadow'] ) ? BoxShadow::get_css( $attributes['boxShadow'], $device ) : [],
			Range::get_css([
				'attributeValue' => $attributes['imageWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'width',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['imageHeight'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'height',
				'device' => $device,
			]),
		);

	}

	public function get_featured_image_container_css( $attributes, $device = '' ) {
		$alignment_value = $attributes['alignment'][ 'value' . $device ] ?? '';
		$css = [];
		if ( ! empty( $alignment_value ) ) {
			$css['display'] = 'flex';
			$css['width'] = '100%';
			$css['justify-content'] = $alignment_value;
		}
		return $css;
	}

	public function get_featured_image_hover_css( $attributes, $device = '' ) {
		$css = [];
		$css['opacity']  = $attributes['imageOpacityH'] ?? '';
		return array_merge(
			Border::get_hover_css( $attributes['border'], '', $device ),
			BoxShadow::get_hover_css( $attributes['boxShadow'], '', $device ),
		);
	}


	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [];

		$shortcode = '[academy_course_featured_image ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
