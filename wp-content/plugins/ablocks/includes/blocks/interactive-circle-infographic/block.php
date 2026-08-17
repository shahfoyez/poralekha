<?php
namespace ABlocks\Blocks\InteractiveCircleInfographic;

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Width;
use ABlocks\Controls\Border;
use ABlocks\Controls\Range;
use ABlocks\Controls\BoxShadow;

use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {

	protected $block_name = 'interactive-circle-infographic';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		// Wrapper CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->blockAlignment( $attributes, '' ),
			$this->blockAlignment( $attributes, 'Tablet' ),
			$this->blockAlignment( $attributes, 'Mobile' ),
		);
		// List CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-circle-wrapper',
			$this->get_circle_radius( $attributes, '' ),
			$this->get_circle_radius( $attributes, 'Tablet' ),
			$this->get_circle_radius( $attributes, 'Mobile' ),
		);
			$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-circle',
			$this->get_circle_style( $attributes, '' ),
			$this->get_circle_style( $attributes, 'Tablet' ),
			$this->get_circle_style( $attributes, 'Mobile' ),
		);	
			$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-segment-content.ablocks-interactive-item-content',
			$this->get_segment_adjustable_margin( $attributes, '' ),
			$this->get_segment_adjustable_margin( $attributes, 'Tablet' ),
			$this->get_segment_adjustable_margin( $attributes, 'Mobile' ),
		);	
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-circle:hover',
			$this->get_circle_style_hover( $attributes, '' ),
			$this->get_circle_style_hover( $attributes, 'Tablet' ),
			$this->get_circle_style_hover( $attributes, 'Mobile' ),
		);	
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-item-inner',
			$this->get_item_button_css( $attributes, '' ),
			$this->get_item_button_css( $attributes, 'Tablet' ),
			$this->get_item_button_css( $attributes, 'Mobile' ),
		);	

           $css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-segment',
			$this->get_item_button_css( $attributes, '' ),
			$this->get_item_button_css( $attributes, 'Tablet' ),
			$this->get_item_button_css( $attributes, 'Mobile' ),
		);	

		// Inner Circle CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-circle-inner',
			$this->get_inner_circle_css( $attributes, '' ),
			$this->get_inner_circle_css( $attributes, 'Tablet' ),
			$this->get_inner_circle_css( $attributes, 'Mobile' ),
		);
		// Inner hover Circle CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-circle-inner:hover',
			$this->get_inner_circle_css_hover( $attributes, '' ),
			$this->get_inner_circle_css_hover( $attributes, 'Tablet' ),
			$this->get_inner_circle_css_hover( $attributes, 'Mobile' ),
		);
		// Inner Circle Title CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-circle-inner h2',
			$this->get_inner_circle_title_css( $attributes, '' ),
			$this->get_inner_circle_title_css( $attributes, 'Tablet' ),
			$this->get_inner_circle_title_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-circle-inner .ablocks-divider__pattern-css',
			$this->get_inner_circle_divider_css( $attributes, '' ),
			$this->get_inner_circle_divider_css( $attributes, 'Tablet' ),
			$this->get_inner_circle_divider_css( $attributes, 'Mobile' ),
		);
		// Inner Circle Description CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-circle-inner p',
			$this->get_inner_circle_description_css( $attributes, '' ),
			$this->get_inner_circle_description_css( $attributes, 'Tablet' ),
			$this->get_inner_circle_description_css( $attributes, 'Mobile' ),
		);
		// Inner Circle Description CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-circle-inner img',
			$this->get_inner_circle_img_css( $attributes, '' ),
			$this->get_inner_circle_img_css( $attributes, 'Tablet' ),
			$this->get_inner_circle_img_css( $attributes, 'Mobile' ),
		);	

		// Inner Circle CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-circle-content',
			$this->get_inner_circle_css( $attributes, '' ),
			$this->get_inner_circle_css( $attributes, 'Tablet' ),
			$this->get_inner_circle_css( $attributes, 'Mobile' ),
		);

		// Inner Circle Title CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-circle-content h2',
			$this->get_inner_circle_title_css( $attributes, '' ),
			$this->get_inner_circle_title_css( $attributes, 'Tablet' ),
			$this->get_inner_circle_title_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-circle-content .ablocks-divider__pattern-css',
			$this->get_inner_circle_divider_css( $attributes, '' ),
			$this->get_inner_circle_divider_css( $attributes, 'Tablet' ),
			$this->get_inner_circle_divider_css( $attributes, 'Mobile' ),
		);
		// Inner Circle Description CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-circle-content p',
			$this->get_inner_circle_description_css( $attributes, '' ),
			$this->get_inner_circle_description_css( $attributes, 'Tablet' ),
			$this->get_inner_circle_description_css( $attributes, 'Mobile' ),
		);
		// Inner Circle Description CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-circle-content img',
			$this->get_inner_circle_img_css( $attributes, '' ),
			$this->get_inner_circle_img_css( $attributes, 'Tablet' ),
			$this->get_inner_circle_img_css( $attributes, 'Mobile' ),
		);	
		// List Wrapper CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-item-content',
			$this->get_list_wrapper_css( $attributes, '' ),
			$this->get_list_wrapper_css( $attributes, 'Tablet' ),
			$this->get_list_wrapper_css( $attributes, 'Mobile' ),
		);

		// Icon CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-item-content .ablocks-svg-icon',
			$this->get_icon_css( $attributes, '' ),
			$this->get_icon_css( $attributes, 'Tablet' ),
			$this->get_icon_css( $attributes, 'Mobile' ),
		);
		// Icon hover css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-item-content .ablocks-svg-icon:hover',
			$this->get_icon_hover_css( $attributes, '' ),
			$this->get_icon_hover_css( $attributes, 'Tablet' ),
			$this->get_icon_hover_css( $attributes, 'Mobile' ),
		);

		// List text CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-interactive-item-content .ablocks-interactive__item-text',
			$this->get_list_text_css( $attributes ),
			$this->get_list_text_css( $attributes, 'Tablet' ),
			$this->get_list_text_css( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}
	

	
public function blockAlignment( $attributes, $device = '' ) {
	$css = [];
	if ( empty( $attributes['alignment'] ) ) {
		return $css;
	}

	$alignment = $attributes['alignment'];
	$key       = 'value' . $device;

	$css['display']         = 'flex';
	$css['justify-content'] = isset( $alignment[ $key ] )
		? $alignment[ $key ]
		: 'center';
	return $css;
}


	public function get_circle_radius( $attributes, $device = '' ) {
		$css = [];

		if ( isset( $attributes['circleSize'][ 'value' . $device ] ) && ! empty( $attributes['circleSize'][ 'value' . $device ] ) 
			&& ( ! isset( $attributes['enableSegments'] ) || $attributes['enableSegments'] === false )
			) {
			$css['height'] = $attributes['circleSize'][ 'value' . $device ] . 'px';
			$css['width'] = $attributes['circleSize'][ 'value' . $device ] . 'px';
		}

		$alignment = isset( $attributes['alignment'] ) ? $attributes['alignment'] : '';
		$css = array_merge(
			$css,
			Alignment::get_css( $alignment, 'justify-content', $device )
		);

		return $css;
	}

	public function get_circle_style( $attributes, $device = '' ) {
		$css = [];
		$circlePaddingUnit = isset($attributes['circlePadding']) ? $attributes['circlePadding'] : '';
	   $circleBorderUnit = isset($attributes['circleBorder']) ? $attributes['circleBorder'] : '';

	return array_merge(
			$css,
			isset( $attributes['circleBorder'] ) ? Border::get_css( $attributes['circleBorder'], '', $device ) : [],
			Dimensions::get_css( $circlePaddingUnit, 'padding', $device ) ,
			BoxShadow::get_css( $attributes['circleBoxShadow'], '', $device ),

		);	 
	 }
	public function get_segment_adjustable_margin( $attributes, $device = '' ) {
		$segMarginUnit = isset($attributes['segMargin']) ? $attributes['segMargin'] : '';
	return array_merge(
			Dimensions::get_css( $segMarginUnit, 'margin', $device ) ,

		);	 
	 }

public function get_circle_style_hover( $attributes, $device = '' ) {
	return array_merge(
			Border::get_hover_css( $attributes['circleBorder'], '', $device ),
			BoxShadow::get_hover_css( $attributes['circleBoxShadow'], '', $device )

		);	 
	 }	 
 public function get_item_button_css( $attributes, $device = '' ) {
	$css = [] ;
			$css['background'] = Color::get_css( $attributes['itemButtonColor'] ?? '' );

	return array_merge(
	    	$css 
		);	 
	 }	
public function innerCircleStyle( $attributes, $device = '' ) {
		$css = [];
	   $innerCirclePaddingUnit = isset($attributes['layoutPadding']) ? $attributes['layoutPadding'] : '';
	   $innerCircleBorderUnit = isset($attributes['layoutBorder']) ? $attributes['layoutBorder'] : '';

	return array_merge(
			$css,
			isset( $attributes['layoutBorder'] ) ? Border::get_css( $attributes['layoutBorder'], '', $device ) : [],
			Dimensions::get_css( $innerCirclePaddingUnit, 'padding', $device ) ,
			BoxShadow::get_css( $attributes['circleBoxShadow'], '', $device ),

		);	 
	 }


	public function get_list_wrapper_css( $attributes, $device = '' ) {
		$css = [];
		$item_button = $attributes['itemButtonSize'][ 'value' . $device ] ?? '';
		if ( ! empty( $item_button ) ) {
			$css['height'] = $item_button . 'px';
			$css['width'] = $item_button . 'px';
			$css['text-align'] = 'center';
		}
		return $css;
	}

	public function get_inner_circle_css( $attributes, $device = '' ) {
		$css = [];
	$innerCirclePaddingUnit = isset($attributes['layoutPadding']) ? $attributes['layoutPadding'] : '';
	 $innerCircleBorderUnit = isset($attributes['layoutBorder']) ? $attributes['layoutBorder'] : '';

		$css['background'] = Color::get_css( $attributes['layoutColor'] ?? '' );
		$css['display'] = 'flex';
		$css['flex-direction'] = 'column';
		$css['border-radius'] = '50%';
		$css['align-items'] = 'center';
		$css['justify-content'] = 'center';
		$css['text-align'] = 'center';
		$css['overflow'] = 'hidden';

		$inner_circle_css = Range::get_css([
			'attributeValue' => $attributes['innerCircleSize'] ?? [],
			'attribute_object_key' => 'value',
			'isResponsive' => true,
			'defaultValue' => 200,
			'hasUnit' => true,
			'unitDefaultValue' => 'px',
			'property' => 'width',
			'device' => $device,
		]);

		$inner_circle_height_css = Range::get_css([
			'attributeValue' => $attributes['innerCircleSize'] ?? [],
			'attribute_object_key' => 'value',
			'isResponsive' => true,
			'defaultValue' => 200,
			'hasUnit' => true,
			'unitDefaultValue' => 'px',
			'property' => 'height',
			'device' => $device,
		]);
			$inner_circle_item_gap_css = Range::get_css([
			'attributeValue' => $attributes['itemSpacing'] ?? [],
			'attribute_object_key' => 'value',
			'isResponsive' => true,
			'defaultValue' => 1,
			'hasUnit' => true,
			'unitDefaultValue' => 'px',
			'property' => 'row-gap',
			'device' => $device,
		]);	

		return array_merge( $css, $inner_circle_css, $inner_circle_height_css , $inner_circle_item_gap_css ,
		    Border::get_css( $innerCircleBorderUnit, 'border', $device ) ,
			Dimensions::get_css( $innerCirclePaddingUnit, 'padding', $device ) ,
			BoxShadow::get_css( $attributes['layoutBoxShadow'], '', $device ),

	);
	}

public function get_inner_circle_css_hover( $attributes, $device = '' ) {
			return array_merge(
			Border::get_hover_css( $attributes['layoutBorder'], '', $device ),
			BoxShadow::get_hover_css( $attributes['layoutBoxShadow'], '', $device )

		);	 
		
}
	public function get_inner_circle_title_css( $attributes, $device = '' ) {
		$css = [];
		$css['color'] = Color::get_css( $attributes['titleColor'] ?? '' );
		$typography = $attributes['titleTypography'] ?? [];
		$typographyGlobal = ( isset( $attributes['titleTypographyGlobal'] ) ? $attributes['titleTypographyGlobal'] : '' );
		return array_merge( $css, Typography::get_css( $typography, '', $device, $typographyGlobal ) );
	}
	public function get_inner_circle_divider_css( $attributes, $device = '' ) {
		$css = [];
		$css['border-bottom-color'] = Color::get_css( $attributes['borderColor'] ?? '' );
		$css['border-bottom-style'] = ( $attributes['dividerPatternUrl'] ?? '' );
			$inner_circle_divider_width_css = Range::get_css([
			'attributeValue' => $attributes['dividerWidth'] ?? [],
			'attribute_object_key' => 'value',
			'isResponsive' => true,
			'defaultValue' => 20,
			'hasUnit' => true,
			'unitDefaultValue' => 'px',
			'property' => 'width',
			'device' => $device,
		]);

		$inner_circle_divider_height_css = Range::get_css([
			'attributeValue' => $attributes['dividerWeight'] ?? [],
			'attribute_object_key' => 'value',
			'isResponsive' => true,
			'defaultValue' => 20,
			'hasUnit' => true,
			'unitDefaultValue' => 'px',
			'property' => 'border-bottom-width',
			'device' => $device,
		]);
		return array_merge( $css , $inner_circle_divider_width_css , $inner_circle_divider_height_css );
	}
	public function get_inner_circle_description_css( $attributes, $device = '' ) {
		$css = [];
		$css['color'] = Color::get_css( $attributes['textColor'] ?? '' );
		$typography = $attributes['textTypography'] ?? [];
		$typographyGlobal = ( isset( $attributes['textTypographyGlobal'] ) ? $attributes['textTypographyGlobal'] : '' );
		return array_merge( $css, Typography::get_css( $typography, '', $device, $typographyGlobal ) );
	}

	public function get_inner_circle_img_css( $attributes, $device = '' ) {
		$css = [];
		$css['object-fit'] = ( $attributes['layoutImageType'] ?? '' );
		$inner_circle_img_radius_css = Range::get_css([
			'attributeValue' => $attributes['imgRadius'] ?? [],
			'attribute_object_key' => 'value',
			'isResponsive' => true,
			'defaultValue' => 20,
			'hasUnit' => true,
			'unitDefaultValue' => 'px',
			'property' => 'border-radius',
			'device' => $device,
		]);		
			$inner_circle_img_width_css = Range::get_css([
			'attributeValue' => $attributes['imgWidth'] ?? [],
			'attribute_object_key' => 'value',
			'isResponsive' => true,
			'defaultValue' => 20,
			'hasUnit' => true,
			'unitDefaultValue' => 'px',
			'property' => 'width',
			'device' => $device,
		]);

		$inner_circle_img_height_css = Range::get_css([
			'attributeValue' => $attributes['imgHeight'] ?? [],
			'attribute_object_key' => 'value',
			'isResponsive' => true,
			'defaultValue' => 20,
			'hasUnit' => true,
			'unitDefaultValue' => 'px',
			'property' => 'height',
			'device' => $device,
		]);
		return array_merge( $css , $inner_circle_img_radius_css ?? [] , $inner_circle_img_width_css ?? [] , $inner_circle_img_height_css ?? [] );
	}

	public function get_icon_css( $attributes, $device = '' ) {
		$css = [];
		$border = isset( $attributes['border'] ) ? $attributes['border'] : '';
		$padding = isset( $attributes['padding'] ) ? $attributes['padding'] : '';
		$css['box-sizing'] = 'content-box';

			if ( $attributes['iconType'] === 'stacked' ) {
				$css['background'] = ! empty( $attributes['iconBackgroundColor'] ) ? $attributes['iconBackgroundColor'] : '#ddd';
				$css['padding'] = '.2em';
				$css['color'] = Color::get_css(
				isset( $attributes['iconColor'] ) ? $attributes['iconColor'] : '#000000');

				if ( $attributes['iconShape'] === 'circle' ) {
					$css['border-radius'] = '50px';
				}
			} elseif ( $attributes['iconType'] === 'framed' ) {
				$css['background'] = ! empty( $attributes['iconBackgroundColor'] ) ? $attributes['iconBackgroundColor'] : 'transparent';
				$css['padding'] = '.2em';
				$css['color'] = Color::get_css(
				isset( $attributes['iconColor'] ) ? $attributes['iconColor'] : '#69727d');
				$css['border'] = '2px solid ' . ( ! empty( $attributes['iconColor'] ) ? $attributes['iconColor'] : '#69727d' );

				if ( $attributes['iconShape'] === 'circle' ) {
					$css['border-radius'] = '50px';
				}
			}

			if ( ! empty( $attributes[ 'iconColor' . $device ] ) && isset( $attributes[ 'iconColor' . $device ] ) ) {
				$css['color'] = Color::get_css(
				isset( $attributes['iconColor'] ) ? $attributes['iconColor'] : '');
				$css['fill'] = Color::get_css(
				isset( $attributes['iconColor'] ) ? $attributes['iconColor'] : '');

			}

			if ( ! empty( $attributes['iconBackgroundColor'] ) ) {
				$css['background'] = Color::get_css(
				isset( $attributes['iconBackgroundColor'] ) ? $attributes['iconBackgroundColor'] : '');

			}
	

		return array_merge(
			$css,
			Range::get_css([
				'attributeValue' => $attributes['iconSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 20,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'font-size',
				'device' => $device,
			]),
			isset( $attributes['border'] ) ? Border::get_css( $attributes['border'], '', $device ) : [],
			Dimensions::get_css( $padding, 'padding', $device )
		);
	}


	public function get_icon_hover_css( $attributes, $device = '' ) {
		$border = isset( $attributes['border'] ) ? $attributes['border'] : '';
		return array_merge(
			isset( $attributes['border'] ) ? Border::get_hover_css( $attributes['border'], '', $device ) : []
		);
	}

	public function get_list_text_css( $attributes, $device = '' ) {
		$css = [];
		$css['color'] = Color::get_css( $attributes['textColor'] ?? '' );
		$typography = $attributes['itemTypography'] ?? [];
		$typographyGlobal = ( isset( $attributes['itemTypographyGlobal'] ) ? $attributes['itemTypographyGlobal'] : '' );
		return array_merge( $css, Typography::get_css( $typography, '', $device, $typographyGlobal ) );
	}
}
