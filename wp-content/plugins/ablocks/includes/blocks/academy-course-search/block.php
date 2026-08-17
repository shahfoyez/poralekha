<?php
namespace ABlocks\Blocks\AcademyCourseSearch;

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
	protected $block_name = 'academy-course-search';

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-search-form-wrap .academy-search-form__field-input',
			$this->get_search_form_css( $attributes ),
			$this->get_search_form_css( $attributes, 'Tablet' ),
			$this->get_search_form_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-search-form-wrap .academy-search-form__field-input:hover',
			$this->get_search_form_hover_css( $attributes ),
			$this->get_search_form_hover_css( $attributes, 'Tablet' ),
			$this->get_search_form_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-search-form-wrap .academy-search-form__field-icon span',
			$this->get_search_box_icon_css( $attributes ),
			$this->get_search_box_icon_css( $attributes, 'Tablet' ),
			$this->get_search_box_icon_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-search-form-wrap .academy-search-form__field-input::placeholder',
			$this->get_search_box_placeholder_css( $attributes ),
			$this->get_search_box_placeholder_css( $attributes, 'Tablet' ),
			$this->get_search_box_placeholder_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}


	public function get_search_form_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['search_typographyGlobal'] ) ? $attributes['search_typographyGlobal'] : '';
		$typography_value = isset( $attributes['search_typography'] ) ? $attributes['search_typography'] : [];
		$search_border_css = ! empty( $attributes['search_border'] ) ? Border::get_css( $attributes['search_border'], '', $device ) : array();

		return array_merge(
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
			$search_border_css,
			[ 'color' => Color::get_css( isset( $attributes['search_box_color'] ) ? $attributes['search_box_color'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['search_background_color'] ) ? $attributes['search_background_color'] : '' ) ],
		);
	}
	public function get_search_form_hover_css( $attributes, $device = '' ) {
		$css = array();

		$search_border_hover_css = ! empty( $attributes['search_border'] ) ? Border::get_hover_css( $attributes['search_border'], '', $device ) : array();

		return array_merge(
			$search_border_hover_css,
			$css,
		);
	}
	public function get_search_box_icon_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['search_icon_color'] ) ? $attributes['search_icon_color'] : '' ) ];
	}
	public function get_search_box_placeholder_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['search_placeholder_color'] ) ? $attributes['search_placeholder_color'] : '' ) ];
	}


	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'placeholder' => sanitize_text_field( Helper::get_attribute_value( $attributes, 'placeholder' ) ),
		];
		$shortcode = '[academy_course_search ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
