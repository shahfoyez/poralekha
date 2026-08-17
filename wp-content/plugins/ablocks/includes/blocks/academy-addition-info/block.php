<?php
namespace ABlocks\Blocks\AcademyAdditionInfo;

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
	protected $block_name = 'academy-addition-info';

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--benefits .benefits-title',
			$this->get_heading_css( $attributes, '' ),
			$this->get_heading_css( $attributes, 'Tablet' ),
			$this->get_heading_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--benefits .benefits-title:hover',
			$this->get_heading_hover_css( $attributes, '' ),
			$this->get_heading_hover_css( $attributes, 'Tablet' ),
			$this->get_heading_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--benefits .benefits-content ul li span,
			{{WRAPPER}} .academy-tabs-content .academy-lists li span',
			$this->get_list_css( $attributes, '' ),
			$this->get_list_css( $attributes, 'Tablet' ),
			$this->get_list_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--benefits .benefits-content ul li span:hover,
			{{WRAPPER}} .academy-tabs-content .academy-lists li span:hover',
			$this->get_list_hover_css( $attributes, '' ),
			$this->get_list_hover_css( $attributes, 'Tablet' ),
			$this->get_list_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--additional-info .academy-tabs-nav li a',
			$this->get_tab_css( $attributes, '' ),
			$this->get_tab_css( $attributes, 'Tablet' ),
			$this->get_tab_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-single-course__content-item--additional-info .academy-tabs-nav li a:hover',
			$this->get_tab_hover_css( $attributes, '' ),
			$this->get_tab_hover_css( $attributes, 'Tablet' ),
			$this->get_tab_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-tabs-nav li.active',
			$this->get_active_list_css( $attributes, '' ),
			$this->get_active_list_css( $attributes, 'Tablet' ),
			$this->get_active_list_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-tabs-nav li.active',
			$this->get_active_list_hover_css( $attributes, '' ),
			$this->get_active_list_hover_css( $attributes, 'Tablet' ),
			$this->get_active_list_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_heading_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['headingTypographyGlobal'] ) ? $attributes['headingTypographyGlobal'] : '';
			$typography_value = ! empty( $attributes['headingTypography'] ) ?
				Typography::get_css( $attributes['headingTypography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['headingColor'] ) ? $attributes['headingColor'] : '#111' ) ],
			$typography_value,
		);
	}

	public function get_heading_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['headingColorH'] ) ? $attributes['headingColorH'] : '#111' ) ],
			Range::get_css([
				'attributeValue' => $attributes['headingTransition'],
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
	public function get_list_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['listTypographyGlobal'] ) ? $attributes['listTypographyGlobal'] : '';
			$typography_value = ! empty( $attributes['listTypography'] ) ?
				Typography::get_css( $attributes['listTypography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['listColor'] ) ? $attributes['listColor'] : '#111' ) ],
			$typography_value,
		);
	}

	public function get_list_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['listColorH'] ) ? $attributes['listColorH'] : '#111' ) ],
			Range::get_css([
				'attributeValue' => $attributes['listTransition'],
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
	public function get_tab_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['tabTypographyGlobal'] ) ? $attributes['tabTypographyGlobal'] : '';
			$typography_value = ! empty( $attributes['tabTypography'] ) ?
				Typography::get_css( $attributes['tabTypography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['tabColor'] ) ? $attributes['tabColor'] : '#111' ) ],
			$typography_value,
		);
	}

	public function get_tab_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['tabColorH'] ) ? $attributes['tabColorH'] : '#111' ) ],
			Range::get_css([
				'attributeValue' => $attributes['tabTransition'],
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

	public function get_active_list_css( $attributes, $device = '' ) {
		$border_value = Border::get_css( $attributes['listBorder'], '', $device ) ?? [];
		$margin_value = Dimensions::get_css( $attributes['listMargin'], 'margin', $device ) ?? [];

		return array_merge(
			Border::get_css( $attributes['listBorder'], '', $device ),
			Dimensions::get_css( $attributes['listMargin'], 'margin', $device ),
		);
	}

	public function get_active_list_hover_css( $attributes, $device = '' ) {
		$border_value = Border::get_hover_css( $attributes['listBorder'], '', $device ) ?? [];

		return $border_value;
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$course_addition_info_id = method_exists( Helper::class, 'get_attribute_value' ) ? (int) Helper::get_attribute_value( $attributes, 'course_id' ) : (int) ( $attributes['course_id'] ?? 0 );
		$course_addition_info_attr = method_exists( Helper::class, 'attr_shortcode' ) ? Helper::attr_shortcode( [ 'course_id' => $course_addition_info_id ] ) : 'course_id="' . esc_attr( $course_addition_info_id ) . '"';
		$course_addition_info_shortcode = '[academy_single_course_addition_info ' . trim( $course_addition_info_attr ) . ']';
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
