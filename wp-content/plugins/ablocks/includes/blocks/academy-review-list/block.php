<?php
namespace ABlocks\Blocks\AcademyReviewList;

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
	protected $block_name = 'academy-review-list';

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-list li .academy-review_container .academy-review-thumnail img',
			$this->get_avatar_css( $attributes, '' ),
			$this->get_avatar_css( $attributes, 'Tablet' ),
			$this->get_avatar_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-list li .academy-review_container .academy-review-content .academy-review-meta__author',
			$this->get_author_css( $attributes, '' ),
			$this->get_author_css( $attributes, 'Tablet' ),
			$this->get_author_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-list li .academy-review_container .academy-review-content .academy-review-meta__author:hover',
			$this->get_author_hover_css( $attributes, '' ),
			$this->get_author_hover_css( $attributes, 'Tablet' ),
			$this->get_author_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-list li .academy-review_container .academy-review-content .academy-review-meta__published-date',
			$this->get_date_time_css( $attributes, '' ),
			$this->get_date_time_css( $attributes, 'Tablet' ),
			$this->get_date_time_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-list li .academy-review_container .academy-review-content .academy-review-meta__published-date:hover',
			$this->get_date_time_hover_css( $attributes, '' ),
			$this->get_date_time_hover_css( $attributes, 'Tablet' ),
			$this->get_date_time_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-list li .academy-review_container .academy-review-content .academy-review-description p',
			$this->get_description_css( $attributes, '' ),
			$this->get_description_css( $attributes, 'Tablet' ),
			$this->get_description_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-list li .academy-review_container .academy-review-content .academy-review-description p:hover',
			$this->get_description_hover_css( $attributes, '' ),
			$this->get_description_hover_css( $attributes, 'Tablet' ),
			$this->get_description_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-list li .academy-review_container .academy-review-thumnail .academy-review__rating',
			$this->get_summary_css( $attributes, '' ),
			$this->get_summary_css( $attributes, 'Tablet' ),
			$this->get_summary_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-list li .academy-review_container .academy-review-thumnail .academy-review__rating:hover',
			$this->get_summary_hover_css( $attributes, '' ),
			$this->get_summary_hover_css( $attributes, 'Tablet' ),
			$this->get_summary_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-list li .academy-review_container .academy-review-thumnail .academy-group-star i',
			$this->get_summary_icon_css( $attributes, '' ),
			$this->get_summary_icon_css( $attributes, 'Tablet' ),
			$this->get_summary_icon_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-list li .academy-review_container .academy-review-thumnail .academy-group-star i:hover',
			$this->get_summary_icon_hover_css( $attributes, '' ),
			$this->get_summary_icon_hover_css( $attributes, 'Tablet' ),
			$this->get_summary_icon_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_avatar_css( $attributes, $device = '' ) {
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['avatarWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 50,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['avatarHeight'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 50,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			]),
		);
	}

	public function get_author_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['authorTypographyGlobal'] ) ? $attributes['authorTypographyGlobal'] : '';
			$typography_value = ! empty( $attributes['authorTypography'] ) ?
				Typography::get_css( $attributes['authorTypography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['authorColor'] ) ? $attributes['authorColor'] : '#111' ) ],
			$typography_value,
		);
	}

	public function get_author_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['authorHoverColor'] ) ? $attributes['authorHoverColor'] : '#111' ) ],
			Range::get_css([
				'attributeValue' => $attributes['authorTransition'],
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

	public function get_date_time_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['dateTypographyGlobal'] ) ? $attributes['dateTypographyGlobal'] : '';
			$typography_value = ! empty( $attributes['dateTypography'] ) ?
				Typography::get_css( $attributes['dateTypography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['dateColor'] ) ? $attributes['dateColor'] : '#111' ) ],
			$typography_value,
		);
	}

	public function get_date_time_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['dateHoverColor'] ) ? $attributes['dateHoverColor'] : '#111' ) ],
			Range::get_css([
				'attributeValue' => $attributes['dateTransition'],
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
		$typographyValueGlobal = ! empty( $attributes['desTypographyGlobal'] ) ? $attributes['desTypographyGlobal'] : '';
			$typography_value = ! empty( $attributes['desTypography'] ) ?
				Typography::get_css( $attributes['desTypography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['desColor'] ) ? $attributes['desColor'] : '#111' ) ],
			$typography_value,
		);
	}

	public function get_description_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['desHoverColor'] ) ? $attributes['desHoverColor'] : '#111' ) ],
			Range::get_css([
				'attributeValue' => $attributes['desTransition'],
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

	public function get_summary_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['sumTypographyGlobal'] ) ? $attributes['sumTypographyGlobal'] : '';
		$typography_value = ! empty( $attributes['sumTypography'] ) ?
			Typography::get_css( $attributes['sumTypography'], '', $device, $typographyValueGlobal )
			: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['sumColor'] ) ? $attributes['sumColor'] : '#111' ) ],
			$typography_value,
		);
	}

	public function get_summary_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['sumColorH'] ) ? $attributes['sumColorH'] : '#111' ) ],
			Range::get_css([
				'attributeValue' => $attributes['sumTransition'],
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

	public function get_summary_icon_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['iconColor'] ) ? $attributes['iconColor'] : '#f4c150' ) ],
			Range::get_css([
				'attributeValue' => $attributes['iconSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 50,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'font-size',
				'device' => $device,
			]),
		);

	}

	public function get_summary_icon_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['iconColorH'] ) ? $attributes['iconColorH'] : '#f4c150' ) ];
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$course_addition_info_id = method_exists( Helper::class, 'get_attribute_value' ) ? (int) Helper::get_attribute_value( $attributes, 'course_id' ) : (int) ( $attributes['course_id'] ?? 0 );
		$course_addition_info_attr = method_exists( Helper::class, 'attr_shortcode' ) ? Helper::attr_shortcode( [ 'course_id' => $course_addition_info_id ] ) : 'course_id="' . esc_attr( $course_addition_info_id ) . '"';
		$course_addition_info_shortcode = '[academy_course_reviews ' . trim( $course_addition_info_attr ) . ']';
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
