<?php
namespace ABlocks\Blocks\AcademyEnrollForm;

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

class Block extends BlockBaseAbstract {
	protected $block_name = 'academy-enroll-form';

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-enroll-form .academy-widget-enroll__continue a,
			{{WRAPPER}} .academy-widget-enroll__continue .academy-btn,
			{{WRAPPER}} .academy-enroll-form-shortcode__continue a',
			$this->getStartButtonCss( $attributes ),
			$this->getStartButtonCss( $attributes, 'Tablet' ),
			$this->getStartButtonCss( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-enroll-form .academy-widget-enroll__continue a:hover,
			{{WRAPPER}} .academy-widget-enroll__continue .academy-btn:hover,
			{{WRAPPER}} .academy-enroll-form-shortcode__continue a:hover',
			$this->getStartButtonHoverCss( $attributes ),
			$this->getStartButtonHoverCss( $attributes, 'Tablet' ),
			$this->getStartButtonHoverCss( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-enroll-form .academy-widget-enroll__enroll-form button,
			{{WRAPPER}} .academy-enroll-form-shortcode__prerequisite-button,
			{{WRAPPER}} .academy-enroll-form-shortcode__button,
			{{WRAPPER}} .academy-enroll-form-shortcode .academy-course-enroll-form .academy-btn--bg-purple,
			{{WRAPPER}} .academy-widget-enroll__enroll-form .academy-btn,
			{{WRAPPER}} .academy-add-to-cart-button button,
			{{WRAPPER}} .academy-enroll-form-shortcode .academy-course-enroll-form .academy-btn--preset-purple,
			{{WRAPPER}} .academy-widget-enroll__complete-form .academy-btn,
			{{WRAPPER}} .academy-widget-enroll__add-to-cart form button',
			$this->getEnrollButtonCss( $attributes ),
			$this->getEnrollButtonCss( $attributes, 'Tablet' ),
			$this->getEnrollButtonCss( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-enroll-form .academy-widget-enroll__enroll-form button:hover,
			{{WRAPPER}} .academy-enroll-form-shortcode__prerequisite-button:hover,
			{{WRAPPER}} .academy-enroll-form-shortcode__button:hover,
			{{WRAPPER}} .academy-enroll-form-shortcode .academy-course-enroll-form .academy-btn--bg-purple:hover,
			{{WRAPPER}} .academy-widget-enroll__enr oll-form .academy-btn:hover,
			{{WRAPPER}} .academy-add-to-cart-button button:hover,
			{{WRAPPER}} .academy-enroll-form-shortcode .academy-course-enroll-form .academy-btn--preset-purple:hover,
			{{WRAPPER}} .academy-widget-enroll__complete-form .academy-btn:hover',
			$this->getEnrollButtonHoverCss( $attributes ),
			$this->getEnrollButtonHoverCss( $attributes, 'Tablet' ),
			$this->getEnrollButtonHoverCss( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-enroll-form-shortcode__prerequisite .academy-shortcode-prerequisites-message',
			$this->get_massage_title_css( $attributes, '' ),
			$this->get_massage_title_css( $attributes, 'Tablet' ),
			$this->get_massage_title_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-enroll-form-shortcode__prerequisite .academy-shortcode-prerequisites-message:hover',
			$this->get_massage_title_hover_css( $attributes, '' ),
			$this->get_massage_title_hover_css( $attributes, 'Tablet' ),
			$this->get_massage_title_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-enroll-form-shortcode__prerequisite',
			$this->get_modal_css( $attributes, '' ),
			$this->get_modal_css( $attributes, 'Tablet' ),
			$this->get_modal_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-enroll-form-shortcode__prerequisite .academy-shortcode-prerequisites-lists li a',
			$this->get_modal_list_css( $attributes, '' ),
			$this->get_modal_list_css( $attributes, 'Tablet' ),
			$this->get_modal_list_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-enroll-form-shortcode__prerequisite .academy-shortcode-prerequisites-lists li a:hover',
			$this->get_modal_list_hover_css( $attributes, '' ),
			$this->get_modal_list_hover_css( $attributes, 'Tablet' ),
			$this->get_modal_list_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-enroll-form-shortcode__price,
			{{WRAPPER}} .academy-widget-enroll__head .academy-course-type,
			{{WRAPPER}} .academy-widget-enroll__head .academy-course-price del,
			{{WRAPPER}} .academy-widget-enroll__head .academy-course-price ins',
			$this->get_price_css( $attributes, '' ),
			$this->get_price_css( $attributes, 'Tablet' ),
			$this->get_price_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-enroll-form-shortcode__price:hover,
			{{WRAPPER}} .academy-widget-enroll__head .academy-course-type:hover,
			{{WRAPPER}} .academy-widget-enroll__head .academy-course-price del:hover,
			{{WRAPPER}} .academy-widget-enroll__head .academy-course-price ins:hover',
			$this->get_price_hover_css( $attributes, '' ),
			$this->get_price_hover_css( $attributes, 'Tablet' ),
			$this->get_price_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-widget-enroll__head .title',
			$this->get_price_title_css( $attributes, '' ),
			$this->get_price_title_css( $attributes, 'Tablet' ),
			$this->get_price_title_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-widget-enroll__head .title',
			$this->get_price_title_hover_css( $attributes, '' ),
			$this->get_price_title_hover_css( $attributes, 'Tablet' ),
			$this->get_price_title_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-widget-enroll__enrolled-info',
			$this->get_enroll_info_css( $attributes, '' ),
			$this->get_enroll_info_css( $attributes, 'Tablet' ),
			$this->get_enroll_info_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-widget-enroll__enrolled-info:hover',
			$this->get_enroll_info_hover_css( $attributes, '' ),
			$this->get_enroll_info_hover_css( $attributes, 'Tablet' ),
			$this->get_enroll_info_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}



	public function getStartButtonCss( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['start_btn_typographyGlobal'] ) ? $attributes['start_btn_typographyGlobal'] : '';
		$start_btn_typography = isset( $attributes['start_btn_typography'] ) ? $attributes['start_btn_typography'] : [];
		$start_btn_padding = ! empty( $attributes['start_btn_padding'] ) ? Dimensions::get_css( $attributes['start_btn_padding'], 'padding', $device ) : array();
		$start_btn_border = ! empty( $attributes['start_btn_border'] ) ? Border::get_css( $attributes['start_btn_border'], '', $device ) : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['start_btn_color'] ) ? $attributes['start_btn_color'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['start_btn_bg_color'] ) ? $attributes['start_btn_bg_color'] : '' ) ],
			Typography::get_css( $start_btn_typography, '', $device, $typographyValueGlobal ),
			$start_btn_padding,
			$start_btn_border,
		);
	}
	public function getStartButtonHoverCss( $attributes, $device = '' ) {
		$start_btn_border = ! empty( $attributes['start_btn_border'] ) ? Border::get_hover_css( $attributes['start_btn_border'], '', $device ) : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['start_btn_color_hover'] ) ? $attributes['start_btn_color_hover'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['start_btn_bg_hover_color'] ) ? $attributes['start_btn_bg_hover_color'] : '' ) ],
			$start_btn_border
		);
	}

	public function getEnrollButtonCss( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['enroll_btn_typographyGlobal'] ) ? $attributes['enroll_btn_typographyGlobal'] : '';
		$enroll_btn_typography = isset( $attributes['enroll_btn_typography'] ) ? $attributes['enroll_btn_typography'] : [];
		$enroll_btn_padding = ! empty( $attributes['enroll_btn_padding'] ) ? Dimensions::get_css( $attributes['enroll_btn_padding'], 'padding', $device ) : array();
		$enroll_btn_border = ! empty( $attributes['enroll_btn_border'] ) ? Border::get_css( $attributes['enroll_btn_border'], '', $device ) : array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['enroll_btn_color'] ) ? $attributes['enroll_btn_color'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['enroll_btn_bg_color'] ) ? $attributes['enroll_btn_bg_color'] : '' ) ],
			Typography::get_css( $enroll_btn_typography, '', $device, $typographyValueGlobal ),
			$enroll_btn_padding,
			$enroll_btn_border,
		);
	}
	public function getEnrollButtonHoverCss( $attributes, $device = '' ) {
		$enroll_btn_border = ! empty( $attributes['enroll_btn_border'] ) ? Border::get_hover_css( $attributes['enroll_btn_border'], '', $device ) : array();
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['enroll_btn_color_hover'] ) ? $attributes['enroll_btn_color_hover'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['enroll_btn_bg_hover_color'] ) ? $attributes['enroll_btn_bg_hover_color'] : '' ) ],
			$enroll_btn_border
		);
	}

	public function get_massage_title_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['massage_title_typographyGlobal'] ) ? $attributes['massage_title_typographyGlobal'] : '';
		$typography_value = isset( $attributes['massage_title_typography'] ) ? $attributes['massage_title_typography'] : [];

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['massage_title_color'] ) ? $attributes['massage_title_color'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['massage_title_bg'] ) ? $attributes['massage_title_bg'] : '' ) ],
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
		);
	}
	public function get_massage_title_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['massage_title_hover_color'] ) ? $attributes['massage_title_hover_color'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['massage_title_hover_bg'] ) ? $attributes['massage_title_hover_bg'] : '' ) ]
		);

	}
	public function get_modal_css( $attributes, $device = '' ) {
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['modalWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 150,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			]),
		);

	}

	public function get_modal_list_css( $attributes, $device = '' ) {
		$css = [];
		$css['text-decoration'] = 'none';
		$typographyValueGlobal = ! empty( $attributes['list_typographyGlobal'] ) ? $attributes['list_typographyGlobal'] : '';
		$typography_value = isset( $attributes['list_typography'] ) ? $attributes['list_typography'] : [];

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['list_color'] ) ? $attributes['list_color'] : '' ) ],
			$css,
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
		);
	}

	public function get_modal_list_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['list_hover_color'] ) ? $attributes['list_hover_color'] : '' ) ];

	}

	public function get_price_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['price_typographyGlobal'] ) ? $attributes['price_typographyGlobal'] : '';
		$typography_value = isset( $attributes['price_typography'] ) ? $attributes['price_typography'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['price_color'] ) ? $attributes['price_color'] : '' ) ],
		Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ), );
	}

	public function get_price_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['price_hover_color'] ) ? $attributes['price_hover_color'] : '' ) ];
	}

	public function get_price_title_css( $attributes, $device = '' ) {
		$css = [];
		$css['color'] = $attributes['price_title_color'] ?? '';
		$typography_value = ! empty( $attributes['price_title_typography'] ) ? Typography::get_css( $attributes['price_title_typography'], '', $device ) : array();

		return array_merge(
			$css,
			$typography_value,
		);

	}
	public function get_price_title_hover_css( $attributes, $device = '' ) {
		$css = [];
		$css['color'] = $attributes['price_title_hover_color'] ?? '';
		return $css;
	}

	public function get_enroll_info_css( $attributes, $device = '' ) {
		$css = [];
		$css['color'] = $attributes['info_color'] ?? '#7b68ee';
		$css['background'] = $attributes['info_bg'] ?? '#eae8fa';

		$typography_value = $attributes['info_typography'] ? Typography::get_css( $attributes['info_typography'], '', $device ) : array();

		return array_merge(
			$css,
			$typography_value
		);
	}

	public function get_enroll_info_hover_css( $attributes, $device = '' ) {
		$css = [];
		$css['color'] = $attributes['info_color_hover'] ?? '#7b68ee';
		$css['background'] = $attributes['info_bg_hover'] ?? '#eae8fa';

		return array_merge(
			$css,
			Range::get_css([
				'attributeValue' => $attributes['bg_transition'],
				'attribute_object_key' => 'value',
				'defaultValue' => 0,
				'unitDefaultValue' => 's',
				'property' => 'transition-duration',
				'device' => $device,
			]),
		);
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'course_id' => absint( Helper::get_attribute_value( $attributes, 'course_id' ) ),
			'layout'  => (string) ( Helper::get_attribute_value( $attributes, 'layout' ) )
		];

		if ( isset( $block_instance->context['postId'] ) && ! empty( $block_instance->context['postId'] ) && empty( $attr_array['course_id'] ) ) {
			$attr_array['course_id'] = $block_instance->context['postId'];
		}

		$shortcode = '[academy_enroll_form ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}

}
