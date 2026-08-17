<?php
namespace ABlocks\Blocks\AcademyCourseCurriculums;

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
use ABlocks\Controls\Color;


class Block extends BlockBaseAbstract {
	protected $block_name = 'academy-course-curriculums';


	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--curriculum .academy-curriculum-title',
			$this->get_curriculum_heading_css( $attributes ),
			$this->get_curriculum_heading_css( $attributes, 'Tablet' ),
			$this->get_curriculum_heading_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--curriculum .academy-curriculum-title:hover',
			$this->get_curriculum_heading_hover_css( $attributes ),
			$this->get_curriculum_heading_hover_css( $attributes, 'Tablet' ),
			$this->get_curriculum_heading_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-accordion a.academy-accordion__title',
			$this->get_sub_heading_css( $attributes ),
			$this->get_sub_heading_css( $attributes, 'Tablet' ),
			$this->get_sub_heading_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-accordion a.academy-accordion__title:hover',
			$this->get_sub_heading_hover_css( $attributes ),
			$this->get_sub_heading_hover_css( $attributes, 'Tablet' ),
			$this->get_sub_heading_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-lesson-list__item .academy-entry-content .academy-entry-title',
			$this->get_lesson_title_css( $attributes ),
			$this->get_lesson_title_css( $attributes, 'Tablet' ),
			$this->get_lesson_title_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-lesson-list__item .academy-entry-content .academy-entry-title:hover',
			$this->get_lesson_title_hover_css( $attributes ),
			$this->get_lesson_title_hover_css( $attributes, 'Tablet' ),
			$this->get_lesson_title_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-lesson-list__item',
			$this->get_lesson_list_css( $attributes ),
			$this->get_lesson_list_css( $attributes, 'Tablet' ),
			$this->get_lesson_list_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-lesson-list__item:hover',
			$this->get_lesson_list_hover_css( $attributes ),
			$this->get_lesson_list_hover_css( $attributes, 'Tablet' ),
			$this->get_lesson_list_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-lesson-list__item .academy-entry-control .academy-btn-play i',
			$this->get_icon_css( $attributes, '' ),
			$this->get_icon_css( $attributes, 'Tablet' ),
			$this->get_icon_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-lesson-list__item .academy-entry-control .academy-btn-play i:hover',
			$this->get_icon_hover_css( $attributes, '' ),
			$this->get_icon_hover_css( $attributes, 'Tablet' ),
			$this->get_icon_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-lesson-list__item .academy-entry-content .academy-icon::before',
			$this->get_read_icon_css( $attributes, '' ),
			$this->get_read_icon_css( $attributes, 'Tablet' ),
			$this->get_read_icon_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-lesson-list__item .academy-entry-content .academy-icon:hover::before',
			$this->get_read_icon_hover_css( $attributes, '' ),
			$this->get_read_icon_hover_css( $attributes, 'Tablet' ),
			$this->get_read_icon_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_curriculum_heading_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['heading_typographyGlobal'] ) ? $attributes['heading_typographyGlobal'] : '';
				$typography_value = ! empty( $attributes['heading_typography'] ) ?
					Typography::get_css( $attributes['heading_typography'], '', $device, $typographyValueGlobal )
						: array();

			return array_merge(
				[ 'color' => Color::get_css( isset( $attributes['heading_color'] ) ? $attributes['heading_color'] : '' ) ],
				$typography_value,
			);
	}

	public function get_curriculum_heading_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['heading_color_hover'] ) ? $attributes['heading_color_hover'] : '' ) ];

	}
	public function get_sub_heading_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['title_typographyGlobal'] ) ? $attributes['title_typographyGlobal'] : '';
				$typography_value = ! empty( $attributes['title_typography'] ) ?
					Typography::get_css( $attributes['title_typography'], '', $device, $typographyValueGlobal )
						: array();

			return array_merge(
				[ 'color' => Color::get_css( isset( $attributes['title_color'] ) ? $attributes['title_color'] : '' ) ],
				[ 'background' => Color::get_css( isset( $attributes['title_bg'] ) ? $attributes['title_bg'] : '' ) ],
				$typography_value,
			);
	}

	public function get_sub_heading_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['title_color_hover'] ) ? $attributes['title_color_hover'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['title_bg_hover'] ) ? $attributes['title_bg_hover'] : '#fff' ) ],
		);
	}

	public function get_lesson_title_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['contentTypographyGlobal'] ) ? $attributes['contentTypographyGlobal'] : '';
				$typography_value = ! empty( $attributes['contentTypography'] ) ?
					Typography::get_css( $attributes['contentTypography'], '', $device, $typographyValueGlobal )
						: array();

			return array_merge(
				[ 'color' => Color::get_css( isset( $attributes['contentColor'] ) ? $attributes['contentColor'] : '' ) ],
				$typography_value,
			);
	}

	public function get_lesson_title_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['contentColorH'] ) ? $attributes['contentColorH'] : '' ) ];
	}
	public function get_lesson_list_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['lesson_list_bg'] ) ? $attributes['lesson_list_bg'] : '' ) ];
	}

	public function get_lesson_list_hover_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['lesson_list_hover'] ) ? $attributes['lesson_list_hover'] : '' ) ];
	}

	public function get_icon_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['lock_icon_hover'] ) ? $attributes['lock_icon_hover'] : '' ) ];
	}

	public function get_icon_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['lock_icon_color'] ) ? $attributes['lock_icon_color'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['lock_icon_size'],
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

	public function get_read_icon_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['read_icon_color'] ) ? $attributes['read_icon_color'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['readIconSize'],
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
	public function get_read_icon_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['read_icon_hover'] ) ? $attributes['read_icon_hover'] : '' ) ];
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$course_addition_info_id = method_exists( Helper::class, 'get_attribute_value' ) ? (int) Helper::get_attribute_value( $attributes, 'course_id' ) : (int) ( $attributes['course_id'] ?? 0 );
		$course_addition_info_attr = method_exists( Helper::class, 'attr_shortcode' ) ? Helper::attr_shortcode( [ 'course_id' => $course_addition_info_id ] ) : 'course_id="' . esc_attr( $course_addition_info_id ) . '"';
		$course_addition_info_shortcode = '[academy_single_course_curriculums ' . trim( $course_addition_info_attr ) . ']';
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
