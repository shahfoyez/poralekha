<?php
namespace ABlocks\Blocks\AcademyEnrollContent;

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
	protected $block_name = 'academy-enroll-content';

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-widget-enroll__content-lists',
			$this->get_content_css( $attributes, '' ),
			$this->get_content_css( $attributes, 'Tablet' ),
			$this->get_content_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-widget-enroll__content-lists:hover',
			$this->get_content_hover_css( $attributes, '' ),
			$this->get_content_hover_css( $attributes, 'Tablet' ),
			$this->get_content_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-widget-enroll__content-lists li .academy-icon,
			{{WRAPPER}}	.academy-icon--level:before,
			{{WRAPPER}} .academy-icon--video-lesson:before',
			$this->get_content_icon_css( $attributes, '' ),
			$this->get_content_icon_css( $attributes, 'Tablet' ),
			$this->get_content_icon_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-widget-enroll__content-lists li .academy-icon:hover,
			{{WRAPPER}} .academy-icon--level:hover::before,
			{{WRAPPER}} .academy-icon--video-lesson:hover::before',
			$this->get_content_icon_hover_css( $attributes, '' ),
			$this->get_content_icon_hover_css( $attributes, 'Tablet' ),
			$this->get_content_icon_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-widget-enroll__content-lists li',
			$this->get_content_list_css( $attributes, '' ),
			$this->get_content_list_css( $attributes, 'Tablet' ),
			$this->get_content_list_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-widget-enroll__content-lists li:hover',
			$this->get_content_list_hover_css( $attributes, '' ),
			$this->get_content_list_hover_css( $attributes, 'Tablet' ),
			$this->get_content_list_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-widget-enroll__wishlist-and-share .academy-btn',
			$this->get_share_button_css( $attributes, '' ),
			$this->get_share_button_css( $attributes, 'Tablet' ),
			$this->get_share_button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-widget-enroll__wishlist-and-share .academy-btn:hover',
			$this->get_share_button_hover_css( $attributes, '' ),
			$this->get_share_button_hover_css( $attributes, 'Tablet' ),
			$this->get_share_button_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-widget-enroll__wishlist-and-share .academy-course__wishlist',
			$this->get_wishlist_button_css( $attributes, '' ),
			$this->get_wishlist_button_css( $attributes, 'Tablet' ),
			$this->get_wishlist_button_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-widget-enroll__wishlist-and-share .academy-course__wishlist:hover',
			$this->get_wishlist_button_hover_css( $attributes, '' ),
			$this->get_wishlist_button_hover_css( $attributes, 'Tablet' ),
			$this->get_wishlist_button_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-widget-enroll__wishlist-and-share .academy-btn i.academy-icon',
			$this->get_button_icon_css( $attributes, '' ),
			$this->get_button_icon_css( $attributes, 'Tablet' ),
			$this->get_button_icon_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function get_content_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['contentBg'] ) ? $attributes['contentBg'] : '#fff' ) ];
	}

	public function get_content_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['contentBgH'] ) ? $attributes['contentBgH'] : '#fff' ) ],
			Range::get_css([
				'attributeValue' => $attributes['contentTransition'],
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

	public function get_content_icon_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['iconColor'] ) ? $attributes['iconColor'] : '#595959' ) ],
			Range::get_css([
				'attributeValue' => $attributes['iconSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 14,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'font-size',
				'device' => $device,
			]),
		);
	}

	public function get_content_icon_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['iconColorH'] ) ? $attributes['iconColorH'] : '#595959' ) ];
	}

	public function get_content_list_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['listTypographyGlobal'] ) ? $attributes['listTypographyGlobal'] : '';
		$typography_value = ! empty( $attributes['listTypography'] ) ?
				Typography::get_css( $attributes['listTypography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['listColor'] ) ? $attributes['listColor'] : '#111' ) ],
			$typography_value
		);

	}

	public function get_content_list_hover_css( $attributes, $device = '' ) {
		return [ 'color' => Color::get_css( isset( $attributes['listColorH'] ) ? $attributes['listColorH'] : '#111' ) ];
	}

	public function get_share_button_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['shareTypographyGlobal'] ) ? $attributes['shareTypographyGlobal'] : '';
		$typography_value = ! empty( $attributes['shareTypography'] ) ?
				Typography::get_css( $attributes['shareTypography'], '', $device, $typographyValueGlobal )
				: array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['shareColor'] ) ? $attributes['shareColor'] : '#444' ) ],
			[ 'background' => Color::get_css( isset( $attributes['shareBg'] ) ? $attributes['shareBg'] : '#fff' ) ],
			$typography_value,
			Dimensions::get_css( $attributes['sharePadding'], 'padding', $device ),
			Border::get_css( $attributes['shareBorder'], '', $device ),
		);
	}

	public function get_share_button_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['shareColorH'] ) ? $attributes['shareColorH'] : '#444' ) ],
			[ 'background' => Color::get_css( isset( $attributes['shareBgH'] ) ? $attributes['shareBgH'] : '#fff' ) ],
			Border::get_hover_css( $attributes['shareBorder'], '', $device )
		);
	}

	public function get_wishlist_button_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['wishlistColor'] ) ? $attributes['wishlistColor'] : '#fff' ) ],
			[ 'background' => Color::get_css( isset( $attributes['wishlistBg'] ) ? $attributes['wishlistBg'] : '#7b68ee' ) ],
		);
	}

	public function get_wishlist_button_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['wishlistColorH'] ) ? $attributes['wishlistColorH'] : '#fff' ) ],
			[ 'background' => Color::get_css( isset( $attributes['wishlistBgH'] ) ? $attributes['wishlistBgH'] : '#7b68ee' ) ],
		);

	}

	public function get_button_icon_css( $attributes, $device = '' ) {
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['buttonIconSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 14,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'font-size',
				'device' => $device,
			]),
		);
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [];

		$shortcode = '[academy_course_enroll_widget_content ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
	}
}
