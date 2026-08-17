<?php
namespace ABlocks\Blocks\AcademyCourseDescription;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Helper;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Color;


class Block extends BlockBaseAbstract {
	protected $block_name = 'academy-course-description';

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--description-title',
			$this->get_overview_heading_css( $attributes, '' ),
			$this->get_overview_heading_css( $attributes, 'Tablet' ),
			$this->get_overview_heading_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--description-title:hover',
			$this->get_overview_heading_hover_css( $attributes, '' ),
			$this->get_overview_heading_hover_css( $attributes, 'Tablet' ),
			$this->get_overview_heading_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--description p,
		{{WRAPPER}} .academy-single-course__content-item--description',
			$this->get_description_css( $attributes, '' ),
			$this->get_description_css( $attributes, 'Tablet' ),
			$this->get_description_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--description p:hover,
		{{WRAPPER}} .academy-single-course__content-item--description:hover',
			$this->get_description_hover_css( $attributes, '' ),
			$this->get_description_hover_css( $attributes, 'Tablet' ),
			$this->get_description_hover_css( $attributes, 'Mobile' )
		);
		return $css_generator->generate_css();
	}


	public function get_overview_heading_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['heading_typographyGlobal'] ) ? $attributes['heading_typographyGlobal'] : '';
			$typography_value = ! empty( $attributes['heading_typography'] ) ?
				Typography::get_css( $attributes['heading_typography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['heading_color'] ) ? $attributes['heading_color'] : '#999' ) ],
			$typography_value,
		);
	}

	public function get_overview_heading_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['heading_colorH'] ) ? $attributes['heading_colorH'] : '#999' ) ],
			Range::get_css([
				'attributeValue' => $attributes['heading_transition'],
				'attribute_object_key' => 'value',
				'isResponsive' => false,
				'defaultValue' => 0,
				'hasUnit' => false,
				'unitDefaultValue' => 's',
				'property' => 'transition-duration',
				'device' => $device,
			]),
		);

	}
	public function get_description_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['description_typographyGlobal'] ) ? $attributes['description_typographyGlobal'] : '';
			$typography_value = ! empty( $attributes['description_typography'] ) ?
				Typography::get_css( $attributes['description_typography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['description_color'] ) ? $attributes['description_color'] : '#999' ) ],
			$typography_value,
		);
	}

	public function get_description_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['description_colorH'] ) ? $attributes['description_colorH'] : '#999' ) ],
			Range::get_css([
				'attributeValue' => $attributes['description_transition'],
				'attribute_object_key' => 'value',
				'isResponsive' => false,
				'defaultValue' => 0,
				'hasUnit' => false,
				'unitDefaultValue' => 's',
				'property' => 'transition-duration',
				'device' => $device,
			]),
		);

	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		static $guard = false;
		if ( $guard ) {
			return '';
		}
		$guard = true;
		$attr_array = [];
		$shortcode = '[academy_single_course_description ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
		$guard = false;
		return '';
	}

}
