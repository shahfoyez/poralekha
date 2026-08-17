<?php
namespace ABlocks\Blocks\AcademyReviewForm;

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
	protected $block_name = 'academy-review-form';


	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-form,
			{{WRAPPER}} .academy-review-form--open-form .comment-respond',
			$this->get_review_form_css( $attributes, '' ),
			$this->get_review_form_css( $attributes, 'Tablet' ),
			$this->get_review_form_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-form:hover,
		{{WRAPPER}} .academy-review-form--open-form .comment-respond:hover',
			$this->get_review_form_hover_css( $attributes, '' ),
			$this->get_review_form_hover_css( $attributes, 'Tablet' ),
			$this->get_review_form_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-form__add-review .academy-btn-add-review',
			$this->get_review_button_css( $attributes, '' ),
			$this->get_review_button_css( $attributes, 'Tablet' ),
			$this->get_review_button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-form__add-review .academy-btn-add-review:hover',
			$this->get_review_button_hover_css( $attributes, '' ),
			$this->get_review_button_hover_css( $attributes, 'Tablet' ),
			$this->get_review_button_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-form .academy-review-form-rating p.stars a,
			{{WRAPPER}} .academy-review-form .academy-review-form-rating p.stars a::before',
			$this->get_start_review_css( $attributes, '' ),
			$this->get_start_review_css( $attributes, 'Tablet' ),
			$this->get_start_review_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-form .academy-review-form-rating p.stars a:hover,
		{{WRAPPER}} .academy-review-form .academy-review-form-rating p.stars a:hover::before',
			$this->get_start_review_hover_css( $attributes, '' ),
			$this->get_start_review_hover_css( $attributes, 'Tablet' ),
			$this->get_start_review_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-form .comment-respond form.comment-form .academy-review-form-review textarea',
			$this->get_form_css( $attributes, '' ),
			$this->get_form_css( $attributes, 'Tablet' ),
			$this->get_form_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-form .comment-respond form.comment-form .academy-review-form-review textarea:hover',
			$this->get_form_hover_css( $attributes, '' ),
			$this->get_form_hover_css( $attributes, 'Tablet' ),
			$this->get_form_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-form input[type=submit]',
			$this->get_submit_css( $attributes, '' ),
			$this->get_submit_css( $attributes, 'Tablet' ),
			$this->get_submit_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-review-form input[type=submit]:hover',
			$this->get_submit_hover_css( $attributes, '' ),
			$this->get_submit_hover_css( $attributes, 'Tablet' ),
			$this->get_submit_hover_css( $attributes, 'Mobile' )
		);
		return $css_generator->generate_css();
	}

	public function get_review_form_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['review_bg'] ) ? $attributes['review_bg'] : '' ) ],
			Border::get_css( $attributes['border'], '', $device ),
			Dimensions::get_css( $attributes['padding'], 'padding', $device ),
			BoxShadow::get_css( $attributes['boxShadow'], '', $device ),
			Range::get_css([
				'attributeValue' => $attributes['box_width'],
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

	public function get_review_form_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['review_bg_hover'] ) ? $attributes['review_bg_hover'] : '' ) ],
			Border::get_hover_css( $attributes['border'], '', $device ),
			BoxShadow::get_hover_css( $attributes['boxShadow'], '', $device ),
		);
	}

	public function get_review_button_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['review_btn_typographyGlobal'] ) ? $attributes['review_btn_typographyGlobal'] : '';
		$typography_value = ! empty( $attributes['review_btn_typography'] ) ?
				Typography::get_css( $attributes['review_btn_typography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['review_btn'] ) ? $attributes['review_btn'] : '#fff' ) ],
			[ 'background' => Color::get_css( isset( $attributes['review_btn_bg'] ) ? $attributes['review_btn_bg'] : '#7b68ee' ) ],
			Dimensions::get_css( $attributes['button_padding'], 'padding', $device ),
			$typography_value,
		);
	}

	public function get_review_button_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['review_btn_bg_hover'] ) ? $attributes['review_btn_bg_hover'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['review_btn_hover'] ) ? $attributes['review_btn_hover'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['btn_transition'],
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
	public function get_start_review_css( $attributes, $device = '' ) {
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

	public function get_start_review_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['starColorH'] ) ? $attributes['starColorH'] : '#f4c150' ) ],
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

	public function get_form_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['formTypographyGlobal'] ) ? $attributes['formTypographyGlobal'] : '';
		$typography_value = ! empty( $attributes['formTypography'] ) ?
				Typography::get_css( $attributes['formTypography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['formColor'] ) ? $attributes['formColor'] : '#444' ) ],
			[ 'background' => Color::get_css( isset( $attributes['formBg'] ) ? $attributes['formBg'] : '#E5E4E6' ) ],
			$typography_value,
			Border::get_css( $attributes['formBorder'], '', $device ),
		);
	}

	public function get_form_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['formColorH'] ) ? $attributes['formColorH'] : '#444' ) ],
			[ 'background' => Color::get_css( isset( $attributes['formBgH'] ) ? $attributes['formBgH'] : '#E5E4E6' ) ],
			Range::get_css([
				'attributeValue' => $attributes['formTransition'],
				'attribute_object_key' => 'value',
				'isResponsive' => false,
				'defaultValue' => 0,
				'hasUnit' => false,
				'unitDefaultValue' => 's',
				'property' => 'transition-duration',
				'device' => $device,
			]),
			Border::get_hover_css( $attributes['formBorder'], '', $device ),
		);
	}

	public function get_submit_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['formBtnTypographyGlobal'] ) ? $attributes['formBtnTypographyGlobal'] : '';
		$typography_value = ! empty( $attributes['formBtnTypography'] ) ?
				Typography::get_css( $attributes['formBtnTypography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['formBtnColor'] ) ? $attributes['formBtnColor'] : '#fff' ) ],
			[ 'background' => Color::get_css( isset( $attributes['formBtnBg'] ) ? $attributes['formBtnBg'] : '#7b68ee' ) ],
			Dimensions::get_css( $attributes['formBtnPadding'], 'padding', $device ),
			$typography_value,
		);
	}

	public function get_submit_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['formBtnColorH'] ) ? $attributes['formBtnColorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['formBtnBgH'] ) ? $attributes['formBtnBgH'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['formBtnT'],
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
		$course_addition_info_shortcode = '[academy_single_course_review_form ' . trim( $course_addition_info_attr ) . ']';
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
