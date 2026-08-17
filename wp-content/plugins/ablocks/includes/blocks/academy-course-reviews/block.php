<?php
namespace ABlocks\Blocks\AcademyCourseReviews;

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
	protected $block_name = 'academy-course-reviews';

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .feedback-title',
			$this->get_feedback_heading_css( $attributes ),
			$this->get_feedback_heading_css( $attributes, 'Tablet' ),
			$this->get_feedback_heading_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .feedback-title:hover',
			$this->get_feedback_heading_hover_css( $attributes ),
			$this->get_feedback_heading_hover_css( $attributes, 'Tablet' ),
			$this->get_feedback_heading_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings',
			$this->get_feedback_section_css( $attributes ),
			$this->get_feedback_section_css( $attributes, 'Tablet' ),
			$this->get_feedback_section_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings:hover',
			$this->get_feedback_section_hover_css( $attributes ),
			$this->get_feedback_section_hover_css( $attributes, 'Tablet' ),
			$this->get_feedback_section_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-avg-rating',
			$this->get_avg_rating_css( $attributes ),
			$this->get_avg_rating_css( $attributes, 'Tablet' ),
			$this->get_avg_rating_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-avg-rating:hover',
			$this->get_avg_rating_hover_css( $attributes, '' ),
			$this->get_avg_rating_hover_css( $attributes, 'Tablet' ),
			$this->get_avg_rating_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-avg-rating-html .academy-icon::before',
			$this->get_rating_star_css( $attributes ),
			$this->get_rating_star_css( $attributes, 'Tablet' ),
			$this->get_rating_star_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-avg-rating-html .academy-icon:hover::before',
			$this->get_rating_star_hover_css( $attributes, '' ),
			$this->get_rating_star_hover_css( $attributes, 'Tablet' ),
			$this->get_rating_star_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-avg-rating-total',
			$this->get_total_rating_css( $attributes, '' ),
			$this->get_total_rating_css( $attributes, 'Tablet' ),
			$this->get_total_rating_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-avg-rating-total:hover',
			$this->get_total_rating_hover_css( $attributes, '' ),
			$this->get_total_rating_hover_css( $attributes, 'Tablet' ),
			$this->get_total_rating_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-ratings-list-item .academy-ratings-list-item-col,
			{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-ratings-list-item .academy-ratings-list-item-label,
			{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-ratings-list-item .academy-ratings-list-item-label span',
			$this->get_feedback_css( $attributes, '' ),
			$this->get_feedback_css( $attributes, 'Tablet' ),
			$this->get_feedback_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-ratings-list-item .academy-ratings-list-item-col:hover,
			{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-ratings-list-item .academy-ratings-list-item-label:hover,
			{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-ratings-list-item .academy-ratings-list-item-label span:hover',
			$this->get_feedback_hover_css( $attributes, '' ),
			$this->get_feedback_hover_css( $attributes, 'Tablet' ),
			$this->get_feedback_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-ratings-list-item .academy-icon',
			$this->get_start_css( $attributes, '' ),
			$this->get_start_css( $attributes, 'Tablet' ),
			$this->get_start_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-ratings-list-item .academy-icon:hover',
			$this->get_start_hover_css( $attributes, '' ),
			$this->get_start_hover_css( $attributes, 'Tablet' ),
			$this->get_start_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-ratings-list-item .academy-ratings-list-item-fill',
			$this->get_fill_css( $attributes, '' ),
			$this->get_fill_css( $attributes, 'Tablet' ),
			$this->get_fill_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-ratings-list-item .academy-ratings-list-item-fill:hover',
			$this->get_fill_hover_css( $attributes, '' ),
			$this->get_fill_hover_css( $attributes, 'Tablet' ),
			$this->get_fill_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-ratings-list-item .academy-ratings-list-item-fill-bar',
			$this->get_active_fill_css( $attributes, '' ),
			$this->get_active_fill_css( $attributes, 'Tablet' ),
			$this->get_active_fill_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--feedback .academy-student-course-feedback-ratings .academy-ratings-list-item .academy-ratings-list-item-fill-bar:hover',
			$this->get_active_fill_hover_css( $attributes, '' ),
			$this->get_active_fill_hover_css( $attributes, 'Tablet' ),
			$this->get_active_fill_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_feedback_heading_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['heading_typographyGlobal'] ) ? $attributes['heading_typographyGlobal'] : '';
		$typography_value = ! empty( $attributes['heading_typography'] ) ?
				Typography::get_css( $attributes['heading_typography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['heading_color'] ) ? $attributes['heading_color'] : '' ) ],
			$typography_value
		);
	}

	public function get_feedback_heading_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['heading_hover_color'] ) ? $attributes['heading_hover_color'] : '' ) ];
	}

	public function get_feedback_section_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['section_bg'] ) ? $attributes['section_bg'] : '#FFF' ) ],
			Border::get_css( $attributes['border'], '', $device ),
			Dimensions::get_css( $attributes['padding'], 'padding', $device ),
			BoxShadow::get_css( $attributes['boxShadow'], '', $device ),
			Range::get_css([
				'attributeValue' => $attributes['section_height'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'height',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['section_width'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'width',
				'device' => $device,
			]),
		);

	}

	public function get_feedback_section_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['section_bg_hover'] ) ? $attributes['section_bg_hover'] : '#FFF' ) ],
			Border::get_hover_css( $attributes['border'], '', $device ),
			BoxShadow::get_hover_css( $attributes['boxShadow'], '', $device ),
			Range::get_css([
				'attributeValue' => $attributes['transition'],
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

	public function get_avg_rating_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['avg_typographyGlobal'] ) ? $attributes['avg_typographyGlobal'] : '';
		$typography_value = ! empty( $attributes['avg_typography'] ) ?
				Typography::get_css( $attributes['avg_typography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['avg_color'] ) ? $attributes['avg_color'] : '#111' ) ],
			$typography_value
		);
	}

	public function get_avg_rating_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['avg_color_hover'] ) ? $attributes['avg_color_hover'] : '#111' ) ],
			Range::get_css([
				'attributeValue' => $attributes['avg_transition'],
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

	public function get_rating_star_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['rating_color'] ) ? $attributes['rating_color'] : '#111' ) ],
			Range::get_css([
				'attributeValue' => $attributes['rating_size'],
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

	public function get_rating_star_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['rating_color_hover'] ) ? $attributes['rating_color_hover'] : '#111' ) ],
			Range::get_css([
				'attributeValue' => $attributes['rating_transition'],
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
	public function get_total_rating_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['total_typographyGlobal'] ) ? $attributes['total_typographyGlobal'] : '';
		$typography_value = ! empty( $attributes['total_typography'] ) ?
				Typography::get_css( $attributes['total_typography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['total_rating_color'] ) ? $attributes['total_rating_color'] : '#999' ) ],
			$typography_value
		);
	}
	public function get_total_rating_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['total_rating_hover'] ) ? $attributes['total_rating_hover'] : '#999' ) ],
			Range::get_css([
				'attributeValue' => $attributes['total_transition'],
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

	public function get_feedback_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['listTypographyGlobal'] ) ? $attributes['listTypographyGlobal'] : '';
		$typography_value = ! empty( $attributes['listTypography'] ) ?
				Typography::get_css( $attributes['listTypography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['listColor'] ) ? $attributes['listColor'] : '#999' ) ],
			$typography_value
		);
	}

	public function get_feedback_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['listColorH'] ) ? $attributes['listColorH'] : '#999' ) ],
			Range::get_css([
				'attributeValue' => $attributes['listT'],
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

	public function get_start_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['starColor'] ) ? $attributes['starColor'] : '#f4c150' ) ],
			Range::get_css([
				'attributeValue' => $attributes['startSize'],
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

	public function get_start_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['starColorH'] ) ? $attributes['starColorH'] : '#999' ) ],
			Range::get_css([
				'attributeValue' => $attributes['startT'],
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

	public function get_fill_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['fillBg'] ) ? $attributes['fillBg'] : '#e7e7e7' ) ];
	}

	public function get_fill_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['fillBgH'] ) ? $attributes['fillBgH'] : '#e7e7e7' ) ],
			Range::get_css([
				'attributeValue' => $attributes['fillT'],
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

	public function get_active_fill_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['fillABg'] ) ? $attributes['fillABg'] : '#e7e7e7' ) ];
	}

	public function get_active_fill_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['fillABgH'] ) ? $attributes['fillABgH'] : '#e7e7e7' ) ],
			Range::get_css([
				'attributeValue' => $attributes['fillAT'],
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
		$course_addition_info_id = method_exists( Helper::class, 'get_attribute_value' ) ? (int) Helper::get_attribute_value( $attributes, 'course_id' ) : (int) ( $attributes['course_id'] ?? 0 );
		$course_addition_info_attr = method_exists( Helper::class, 'attr_shortcode' ) ? Helper::attr_shortcode( [ 'course_id' => $course_addition_info_id ] ) : 'course_id="' . esc_attr( $course_addition_info_id ) . '"';
		$course_addition_info_shortcode = '[academy_single_course_review_rating ' . trim( $course_addition_info_attr ) . ']';
		// phpcs:ignore  WordPress.Security.NonceVerification.Recommended,  WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized 
		$course_addition_info_is_edit = defined( 'REST_REQUEST' ) && REST_REQUEST && ( $_REQUEST['context'] ?? '' ) === 'edit';
		if ( $course_addition_info_is_edit && $course_addition_info_id && ( $course_addition_info_post = get_post( $course_addition_info_id ) ) ) {
			global $post, $wp_query;
			$course_addition_info_prev_post = $post;
			$course_addition_info_prev_query = $wp_query;
			$post = $course_addition_info_post;
			setup_postdata( $post );
			$wp_query = new \WP_Query( [
				'p' => $course_addition_info_id,
				'post_type' => $course_addition_info_post->post_type,
				'no_found_rows' => 1,
				'posts_per_page' => 1
			] );
			$wp_query->is_singular = 1;
			$wp_query->is_single = ( $course_addition_info_post->post_type !== 'page' );
			$wp_query->is_page = ( $course_addition_info_post->post_type === 'page' );
			$wp_query->is_home = $wp_query->is_front_page = 0;
			if ( method_exists( $wp_query, 'set' ) ) {
				$wp_query->set( 'is_main_query', 1 );
			}
			$course_addition_info_html = do_shortcode( $course_addition_info_shortcode );
			wp_reset_postdata();
			$wp_query = $course_addition_info_prev_query;
			$post = $course_addition_info_prev_post;
		} else {
			$course_addition_info_html = do_shortcode( $course_addition_info_shortcode );
		}//end if
		if ( $course_addition_info_is_edit && trim( wp_strip_all_tags( $course_addition_info_html ) ) === '' ) {
			$course_addition_info_html = '<div class="no-data-message"><strong>Editor Only:</strong> No output. Check course ID or data.</div>';
		}
		return $course_addition_info_html;
	}

}
