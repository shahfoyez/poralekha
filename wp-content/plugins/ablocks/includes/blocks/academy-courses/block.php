<?php
namespace ABlocks\Blocks\AcademyCourses;

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
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'academy-courses';

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-courses--grid .academy-course .academy-course__meta--categroy a',
			$this->get_course_card_category_css( $attributes ),
			$this->get_course_card_category_css( $attributes, 'Tablet' ),
			$this->get_course_card_category_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-courses--grid .academy-course .academy-course__meta--categroy:hover a',
			$this->course_category_desktop_hover_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-courses--grid .academy-course .academy-course__title, 
            {{WRAPPER}} .academy-courses--grid .academy-course .academy-course__title a',
			$this->get_course_card_title_css( $attributes ),
			$this->get_course_card_title_css( $attributes, 'Tablet' ),
			$this->get_course_card_title_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-courses--grid .academy-course .academy-course__title:hover, 
            {{WRAPPER}} .academy-courses--grid .academy-course .academy-course__title:hover a',
			$this->get_course_title__hover_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-courses--grid .academy-course .academy-course__author,
            {{WRAPPER}} .academy-courses--grid .academy-course .academy-course__author .author,
            {{WRAPPER}} .academy-courses--grid .academy-course .academy-course__author .author a',
			$this->get_course_card_author_css( $attributes ),
			$this->get_course_card_author_css( $attributes, 'Tablet' ),
			$this->get_course_card_author_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-courses--grid .academy-course .academy-course__author:hover,
            {{WRAPPER}} .academy-courses--grid .academy-course .academy-course__author:hover .author,
            {{WRAPPER}} .academy-courses--grid .academy-course .academy-course__author:hover .author a',
			$this->course_author_desktop_hover_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-courses--grid .academy-course .academy-course__rating,
            {{WRAPPER}} .academy-courses--grid .academy-course .academy-course__rating .academy-group-star, 
            {{WRAPPER}} .academy-courses--grid .academy-course .academy-course__rating .academy-group-star .academy-icon::before, 
            {{WRAPPER}} .academy-courses--grid .academy-course .academy-course__rating .academy-course__rating-count',
			$this->get_course_card_rating_css( $attributes ),
			$this->get_course_card_rating_css( $attributes, 'Tablet' ),
			$this->get_course_card_rating_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-courses--grid .academy-course .academy-course__rating:hover,
            {{WRAPPER}} .academy-courses--grid .academy-course .academy-course__rating:hover .academy-group-star, 
            {{WRAPPER}} .academy-courses--grid .academy-course .academy-course__rating:hover .academy-group-star .academy-icon::before, 
            {{WRAPPER}} .academy-courses--grid .academy-course .academy-course__rating:hover .academy-course__rating-count',
			$this->course_rating_desktop_hover_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-courses--grid .academy-course .academy-course__price',
			$this->get_course_card_price_css( $attributes ),
			$this->get_course_card_price_css( $attributes, 'Tablet' ),
			$this->get_course_card_price_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-courses--grid .academy-course .academy-course__price:hover',
			$this->course_price_hover_css( $attributes )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-courses--grid .academy-row .academy-course,
            {{WRAPPER}} .academy-courses--grid .academy-row .academy-course, 
            {{WRAPPER}} .academy-courses--grid .academy-row .academy-course, 
            {{WRAPPER}} .academy-courses--grid .academy-row .academy-course',
			$this->get_course_card_css( $attributes ),
			$this->get_course_card_css( $attributes, 'Tablet' ),
			$this->get_course_card_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-courses--grid .academy-row .academy-course:hover,
            {{WRAPPER}} .academy-courses--grid .academy-row .academy-course:hover, 
            {{WRAPPER}} .academy-courses--grid .academy-row .academy-course:hover, 
            {{WRAPPER}} .academy-courses--grid .academy-row .academy-course:hover',
			$this->get_course_card_hover_css( $attributes ),
			$this->get_course_card_hover_css( $attributes, 'Tablet' ),
			$this->get_course_card_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-courses .academy-course__header .academy-course-header-meta .academy-course__wishlist',
			$this->get_wish_icon_css( $attributes ),
			$this->get_wish_icon_css( $attributes, 'Tablet' ),
			$this->get_wish_icon_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-courses .academy-course__header .academy-course-header-meta .academy-course__wishlist:hover',
			$this->get_wish_icon_hover_css( $attributes ),
			$this->get_wish_icon_hover_css( $attributes, 'Tablet' ),
			$this->get_wish_icon_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_course_card_category_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['cat_typographyGlobal'] ) ? $attributes['cat_typographyGlobal'] : '';
		$typography_value = isset( $attributes['typography'] ) ? $attributes['typography'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['category_color'] ) ? $attributes['category_color'] : '' ) ],
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal )
		);
	}
	public function course_category_desktop_hover_css( $attributes ) {

		return [ 'color' => Color::get_css( isset( $attributes['category_hover_color'] ) ? $attributes['category_hover_color'] : '' ) ];
	}
	public function course_author_desktop_hover_css( $attributes ) {

		return [ 'color' => Color::get_css( isset( $attributes['author_hover_color'] ) ? $attributes['author_hover_color'] : '' ) ];
	}
	public function course_rating_desktop_hover_css( $attributes ) {

		return [ 'color' => Color::get_css( isset( $attributes['rating_hover_color'] ) ? $attributes['rating_hover_color'] : '' ) ];
	}
	public function course_price_hover_css( $attributes ) {
		return [ 'color' => Color::get_css( isset( $attributes['price_hover_color'] ) ? $attributes['price_hover_color'] : '' ) ];
	}
	public function get_course_title__hover_css( $attributes ) {
		return [ 'color' => Color::get_css( isset( $attributes['title_hover_color'] ) ? $attributes['title_hover_color'] : '' ) ];
	}

	public function get_course_card_title_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['title_typographyGlobal'] ) ? $attributes['title_typographyGlobal'] : '';
		$course_title_typography_css = isset( $attributes['title_typography'] ) ? $attributes['title_typography'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['title_color'] ) ? $attributes['title_color'] : '' ) ],
			Typography::get_css( $course_title_typography_css, '', $device, $typographyValueGlobal ),
		);
	}

	public function get_course_card_author_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['author_typographyGlobal'] ) ? $attributes['author_typographyGlobal'] : '';
		$course_author_typography_css = isset( $attributes['author_typography'] ) ? $attributes['author_typography'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['author_color'] ) ? $attributes['author_color'] : '' ) ],
			Typography::get_css( $course_author_typography_css, '', $device, $typographyValueGlobal ),
		);
	}

	public function get_course_card_rating_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['rating_typographyGlobal'] ) ? $attributes['rating_typographyGlobal'] : '';
		$typography_value = isset( $attributes['rating_typography'] ) ? $attributes['rating_typography'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['rating_color'] ) ? $attributes['rating_color'] : '' ) ],
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
		);
	}

	public function get_course_card_price_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['price_typographyGlobal'] ) ? $attributes['price_typographyGlobal'] : '';

		$course_price_typography_css = ! empty( $attributes['price_typography'] ) ? $attributes['price_typography'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['price_color'] ) ? $attributes['price_color'] : '' ) ],
			Typography::get_css( $course_price_typography_css, '', $device, $typographyValueGlobal ),
		);
	}

	public function get_wish_icon_css( $attributes, $device = '' ) {
		$wish_icon_background_css = ! empty( $attributes['wish_icon_background'] ) ? Background::get_css( $attributes['wish_icon_background'], 'background', $device ) : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['wish_icon_color'] ) ? $attributes['wish_icon_color'] : '' ) ],
			$wish_icon_background_css
		);
	}

	public function get_wish_icon_hover_css( $attributes, $device = '' ) {
		$wish_icon_background_css = ! empty( $attributes['wish_icon_background'] ) ? Background::get_hover_css( $attributes['wish_icon_background'], 'background', $device ) : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['wish_icon_hover_color'] ) ? $attributes['wish_icon_hover_color'] : '' ) ],
			$wish_icon_background_css
		);
	}

	public function get_course_card_css( $attributes, $device = '' ) {
		$course_background_css = ! empty( $attributes['card_background'] ) ? Background::get_css( $attributes['card_background'], 'background', $device ) : array();
		$course_border_css = ! empty( $attributes['card_border'] ) ? Border::get_css( $attributes['card_border'], '', $device ) : array();
		$course_margin_css = ! empty( $attributes['card_margin'] ) ? Dimensions::get_css( $attributes['card_margin'], 'margin', $device ) : array();
		$course_padding_css = ! empty( $attributes['card_padding'] ) ? Dimensions::get_css( $attributes['card_padding'], 'padding', $device ) : array();

		return array_merge(
			$course_background_css,
			$course_border_css,
			$course_margin_css,
			$course_padding_css
		);
	}

	public function get_course_card_hover_css( $attributes, $device = '' ) {
		$course_background_hover_css = ! empty( $attributes['card_background'] ) ? Background::get_hover_css( $attributes['card_background'], 'background', $device ) : array();
		$course_border_hover_css = ! empty( $attributes['card_border'] ) ? Border::get_hover_css( $attributes['card_border'], '', $device ) : array();
		$course_margin_hover_css = ! empty( $attributes['card_hover_margin'] ) ? Dimensions::get_css( $attributes['card_hover_margin'], 'margin', $device ) : array();
		$course_padding_hover_css = ! empty( $attributes['card_hover_padding'] ) ? Dimensions::get_css( $attributes['card_hover_padding'], 'padding', $device ) : array();

		return array_merge(
			$course_background_hover_css,
			$course_border_hover_css,
			$course_margin_hover_css,
			$course_padding_hover_css
		);
	}



	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'count'                 => absint( Helper::get_attribute_value( $attributes, 'course_count' ) ),
			'column_per_row'        => absint( Helper::get_attribute_value( $attributes, 'course_columns' ) ),
			'has_pagination'        => filter_var( Helper::get_attribute_value( $attributes, 'show_pagination' ), FILTER_VALIDATE_BOOLEAN ),
			'course_level'          => sanitize_text_field( Helper::get_attribute_value( $attributes, 'difficulty_levels' ) ),
			'price_type'            => sanitize_text_field( Helper::get_attribute_value( $attributes, 'price_types' ) ),
			'orderby'               => sanitize_key( Helper::get_attribute_value( $attributes, 'order_by' ) ),
			'order'                 => sanitize_text_field( Helper::get_attribute_value( $attributes, 'course_order' ) ),
			'ids'                   => sanitize_text_field( Helper::get_attribute_value( $attributes, 'course_ids' ) ),
			'exclude_ids'           => sanitize_text_field( Helper::get_attribute_value( $attributes, 'course_exclude_ids' ) ),
			'category'              => sanitize_text_field( Helper::get_attribute_value( $attributes, 'course_categories' ) ),
			'cat_not_in'            => sanitize_text_field( Helper::get_attribute_value( $attributes, 'course_exclude_categories' ) ),
			'tag'                   => sanitize_text_field( Helper::get_attribute_value( $attributes, 'course_tags' ) ),
			'tag_not_in'            => sanitize_text_field( Helper::get_attribute_value( $attributes, 'course_exclude_tags' ) ),
		];
		$shortcode = '[academy_courses ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
