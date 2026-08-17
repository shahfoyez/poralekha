<?php
namespace ABlocks\Blocks\AcademyCourseInstructor;

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
	protected $block_name = 'academy-course-instructor';

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--instructors .course-single-instructor .instructor-info__thumbnail img',
			$this->get_avatar_image_css( $attributes, '' ),
			$this->get_avatar_image_css( $attributes, 'Tablet' ),
			$this->get_avatar_image_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--instructors .course-single-instructor .instructor-review__title,
			{{WRAPPER}} .academy-single-course__content-item--instructors .course-single-instructor .instructor-info__content .instructor-title',
			$this->get_title_css( $attributes, '' ),
			$this->get_title_css( $attributes, 'Tablet' ),
			$this->get_title_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--instructors .course-single-instructor .instructor-review__title:hover,
			{{WRAPPER}} .academy-single-course__content-item--instructors .course-single-instructor .instructor-info__content .instructor-title:hover',
			$this->get_title_hover_css( $attributes, '' ),
			$this->get_title_hover_css( $attributes, 'Tablet' ),
			$this->get_title_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--instructors .course-single-instructor .instructor-review__rating span',
			$this->get_review_text_css( $attributes, '' ),
			$this->get_review_text_css( $attributes, 'Tablet' ),
			$this->get_review_text_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--instructors .course-single-instructor .instructor-review__rating span:hover',
			$this->get_review_text_hover_css( $attributes, '' ),
			$this->get_review_text_hover_css( $attributes, 'Tablet' ),
			$this->get_review_text_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--instructors .course-single-instructor .instructor-review__rating .academy-group-star .academy-icon:before',
			$this->get_review_icon_css( $attributes, '' ),
			$this->get_review_icon_css( $attributes, 'Tablet' ),
			$this->get_review_icon_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--instructors .course-single-instructor .instructor-review__rating .academy-group-star .academy-icon:hover:before',
			$this->get_review_icon_hover_css( $attributes, '' ),
			$this->get_review_icon_hover_css( $attributes, 'Tablet' ),
			$this->get_review_icon_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--instructors .course-single-instructor .instructor-info__content .instructor-name a',
			$this->get_instructor_css( $attributes, '' ),
			$this->get_instructor_css( $attributes, 'Tablet' ),
			$this->get_instructor_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--instructors .course-single-instructor .instructor-info__content .instructor-name a:hover',
			$this->get_instructor_hover_css( $attributes, '' ),
			$this->get_instructor_hover_css( $attributes, 'Tablet' ),
			$this->get_instructor_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_avatar_image_css( $attributes, $device = '' ) {
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['avatarH'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 62,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['avatarW'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 62,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			]),
		);
	}

	public function get_title_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['titleTypographyGlobal'] ) ? $attributes['titleTypographyGlobal'] : '';
			$typography_value = ! empty( $attributes['titleTypography'] ) ?
				Typography::get_css( $attributes['titleTypography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['titleColor'] ) ? $attributes['titleColor'] : '#999' ) ],
			$typography_value,
		);
	}

	public function get_title_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['titleColorH'] ) ? $attributes['titleColorH'] : '#999' ) ],
			Range::get_css([
				'attributeValue' => $attributes['titleTransition'],
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

	public function get_review_text_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['textTypographyGlobal'] ) ? $attributes['textTypographyGlobal'] : '';
			$typography_value = ! empty( $attributes['textTypography'] ) ?
				Typography::get_css( $attributes['textTypography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['textColor'] ) ? $attributes['textColor'] : '#111' ) ],
			$typography_value,
		);
	}

	public function get_review_text_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['textColorH'] ) ? $attributes['textColorH'] : '#111' ) ],
			Range::get_css([
				'attributeValue' => $attributes['textTransition'],
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
	public function get_review_icon_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['star_color'] ) ? $attributes['star_color'] : '#f4c150' ) ],
			Range::get_css([
				'attributeValue' => $attributes['star_size'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 16,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'font-size',
				'device' => $device,
			]),
		);
	}

	public function get_review_icon_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['star_colorH'] ) ? $attributes['star_colorH'] : '#f4c150' ) ];
	}
	public function get_instructor_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['insTypographyGlobal'] ) ? $attributes['insTypographyGlobal'] : '';
		$typography_value = ! empty( $attributes['insTypography'] ) ?
				Typography::get_css( $attributes['insTypography'], '', $device, $typographyValueGlobal )
				: array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['insColor'] ) ? $attributes['insColor'] : '#111' ) ],
			$typography_value
		);
	}

	public function get_instructor_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['insColorH'] ) ? $attributes['insColorH'] : '#111' ) ];
	}


	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [];

		$shortcode = '[academy_course_instructors ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
