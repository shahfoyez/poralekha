<?php

namespace ABlocks\Blocks\EcmReaction;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Helper;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Color;
use ABlocks\Controls\Alignment;


class Block extends BlockBaseAbstract {
	protected $block_name = 'ecm-reaction';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--reaction',
			$this->get_wrapper_alignment_css( $attributes ),
			$this->get_wrapper_alignment_css( $attributes, 'Tablet' ),
			$this->get_wrapper_alignment_css( $attributes, 'Mobile' ),

		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} span.ecm-shortcode--reaction__label.ecm--hidden',
			$this->get_show_title_css( $attributes ),

		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} div.ecm-shortcode--reaction__icon svg',
			$this->get_icon_css( $attributes ),
			$this->get_icon_css( $attributes, 'Tablet' ),
			$this->get_icon_css( $attributes, 'Mobile' ),

		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} div.ecm-shortcode--reaction__icon svg path',
			$this->get_icon_color_css( $attributes ),
			$this->get_icon_color_css( $attributes, 'Tablet' ),
			$this->get_icon_color_css( $attributes, 'Mobile' ),

		);	
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ecm-shortcode--reaction__button--active div.ecm-shortcode--reaction__icon svg path',
			$this->get_icon_active_color_css( $attributes ),
			$this->get_icon_active_color_css( $attributes, 'Tablet' ),
			$this->get_icon_active_color_css( $attributes, 'Mobile' ),

		);	
		$css_generator->add_class_styles(
			'{{WRAPPER}} button.ecm-shortcode--reaction__button',
			$this->get_title_style_css( $attributes ),
			$this->get_title_style_css( $attributes, 'Tablet' ),
			$this->get_title_style_css( $attributes, 'Mobile' ),

		);		
		$css_generator->add_class_styles(
			'{{WRAPPER}} button.ecm-shortcode--reaction__button:hover',
			$this->get_title_hover_style_css( $attributes ),
			$this->get_title_hover_style_css( $attributes, 'Tablet' ),
			$this->get_title_hover_style_css( $attributes, 'Mobile' ),

		);		
		$css_generator->add_class_styles(
			'{{WRAPPER}} button.ecm-shortcode--reaction__button.ecm-shortcode--reaction__button--active',
			$this->get_title_active_style_css( $attributes ),
			$this->get_title_active_style_css( $attributes, 'Tablet' ),
			$this->get_title_active_style_css( $attributes, 'Mobile' ),

		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} button.ecm-shortcode--reaction__button.ecm-shortcode--reaction__button--active:hover',
			$this->get_title_active_hover_style_css( $attributes ),
			$this->get_title_active_hover_style_css( $attributes, 'Tablet' ),
			$this->get_title_active_hover_style_css( $attributes, 'Mobile' ),

		);				
		return $css_generator->generate_css();
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		$attr_array = [
			'user_id'     				  => Helper::get_attribute_value( $attributes, 'user_id' ),
			'not_found_text'     		  => Helper::get_attribute_value( $attributes, 'not_found_text' ),
		];

		$shortcode = '[ecm_reaction ' . Helper::attr_shortcode( $attr_array ) . ']';
		echo do_shortcode( $shortcode );
		
	}

	public function get_wrapper_alignment_css( $attributes, $device = '' ) {
		$alignment = ! empty( $attributes['justificationAlign'] ) ? $attributes['justificationAlign'] : '';
		return array_merge(
		Range::get_css( [
				'attributeValue'      => $attributes['itemGap'],
				'attribute_object_key' => 'value',
				'isResponsive'        => true,
				'defaultValue'        => 50,
				'hasUnit'             => true,
				'unitDefaultValue'    => 'px',
				'property'            => 'gap',
				'device'              => $device,
			] ),
		Alignment::get_css( $alignment, 'justify-content', $device )
	); }
	public function get_show_title_css( $attributes, $device = '' ) {
       $css = [];
	   if(!empty($attributes['showTitle']) && $attributes['showTitle'] === true  ){
		$css['display'] = 'block';
	   }
		return $css;
	}
	public function get_icon_css( $attributes, $device = '' ) {
		return array_merge(
		Range::get_css( [
				'attributeValue'      => $attributes['iconSize'],
				'attribute_object_key' => 'value',
				'isResponsive'        => true,
				'defaultValue'        => 50,
				'hasUnit'             => true,
				'unitDefaultValue'    => 'px',
				'property'            => 'width',
				'device'              => $device,
			] ),
			Range::get_css( [
				'attributeValue'      => $attributes['iconSize'],
				'attribute_object_key' => 'value',
				'isResponsive'        => true,
				'defaultValue'        => 50,
				'hasUnit'             => true,
				'unitDefaultValue'    => 'px',
				'property'            => 'height',
				'device'              => $device,
			] ),
	); }	
	public function get_icon_color_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'fill' => Color::get_css( isset( $attributes['fillColor'] ) ? $attributes['fillColor'] : '' ) . '!important' ],
			[ 'stroke' => Color::get_css( isset( $attributes['iconColor'] ) ? $attributes['iconColor'] : '' ) . '!important' ],
	); }

	public function get_icon_active_color_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'fill' => Color::get_css( isset( $attributes['activeFillColor'] ) ? $attributes['activeFillColor'] : '' ) . '!important' ],
			[ 'stroke' => Color::get_css( isset( $attributes['activeIconColor'] ) ? $attributes['activeIconColor'] : '' ) . '!important' ],
	); }	
	
	public function get_title_style_css( $attributes, $device = '' ) {
		$typographyGlobal = ( isset( $attributes['titleTypographyGlobal'] ) ? $attributes['titleTypographyGlobal'] : '' );

		return array_merge(
			! empty( $attributes['titleTypography'] ) ? Typography::get_css( $attributes['titleTypography'], '', $device, $typographyGlobal ) : array(),
			Border::get_css( ! empty( $attributes['border'] ) ? $attributes['border'] : [], '', $device ),
			[ 'background' => Color::get_css( isset( $attributes['bgColor'] ) ? $attributes['bgColor'] : '' ) . '!important' ],
			[ 'color' => Color::get_css( isset( $attributes['titleColor'] ) ? $attributes['titleColor'] : '' ) . '!important' ],
	); }	
	public function get_title_hover_style_css( $attributes, $device = '' ) {

		return array_merge(
			Range::get_css( [
				'attributeValue'      => $attributes['transition'],
				'defaultValue'        => 10,
				'unitDefaultValue'    => 's',
				'property'            => 'transition-duration',
			] ),
			Border::get_hover_css( ! empty( $attributes['border'] ) ? $attributes['border'] : [], '', $device ),

	); }

	public function get_title_active_style_css( $attributes, $device = '' ) {
		$typographyGlobal = ( isset( $attributes['activeTitleTypographyGlobal'] ) ? $attributes['activeTitleTypographyGlobal'] : '' );

		return array_merge(
			! empty( $attributes['activeTitleTypography'] ) ? Typography::get_css( $attributes['activeTitleTypography'], '', $device, $typographyGlobal ) : array(),
			Border::get_css( ! empty( $attributes['activeBorder'] ) ? $attributes['activeBorder'] : [], '', $device ),
			[ 'background' => Color::get_css( isset( $attributes['activeBGColor'] ) ? $attributes['activeBGColor'] : '' ) . '!important' ],
			[ 'color' => Color::get_css( isset( $attributes['activeTitleColor'] ) ? $attributes['activeTitleColor'] : '' ) . '!important' ],
	); }	

	public function get_title_active_hover_style_css( $attributes, $device = '' ) {
	$css = [];
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['transition'],
				'defaultValue' => 10,
				'unitDefaultValue' => 's',
				'property' => 'transition-duration',
				'device' => $device,
			]),
			$css,
			Border::get_hover_css( $attributes['activeBorder'], '', $device ),
		);
	 }	
	
	
}
