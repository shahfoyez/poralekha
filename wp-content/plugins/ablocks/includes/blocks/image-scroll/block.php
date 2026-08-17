<?php
namespace ABlocks\Blocks\ImageScroll;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Typography;
use ABlocks\Controls\CssFilter;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;
class Block extends BlockBaseAbstract {
	protected $block_name = 'image-scroll';

	public function build_css( $attributes ) {

		// Generate CSS
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		// Image Wrapper CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}:hover',
			$this->get_wrapper_hover_css( $attributes ),
			$this->get_wrapper_hover_css( $attributes, 'Tablet' ),
			$this->get_wrapper_hover_css( $attributes, 'Mobile' ),
		);
		// Image container css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container',
			$this->get_scroll_css( $attributes ),
			$this->get_scroll_css( $attributes, 'Tablet' ),
			$this->get_scroll_css( $attributes, 'Mobile' ),
		);
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-block-container',
				$this->get_scroll_option_css( $attributes ),
				$this->get_scroll_option_css( $attributes, 'Tablet' ),
				$this->get_scroll_option_css( $attributes, 'Mobile' ),
			);

			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-block-container .ablocks-image-scroll__figure',
				$this->get_image_figure_css( $attributes ),
				$this->get_image_figure_css( $attributes, 'Tablet' ),
				$this->get_image_figure_css( $attributes, 'Mobile' ),
			);
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-block-image-overlay',
				$this->get_image_overlay_css( $attributes ),
			);
			$css_generator->add_class_styles(
				'{{WRAPPER}}.ablocks-block--image-scroll:hover .ablocks-block-image-overlay',
				$this->get_image_overlay_hover_css( $attributes ),
			);

		// Image container css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container',
			$this->get_image_container_css( $attributes ),
			$this->get_image_container_css( $attributes, 'Tablet' ),
			$this->get_image_container_css( $attributes, 'Mobile' ),
		);
		// Image css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container .ablocks-image-scroll__figure img',
			$this->get_image_css( $attributes ),
			$this->get_image_css( $attributes, 'Tablet' ),
			$this->get_image_css( $attributes, 'Mobile' ),
		);

		// Image hover css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container .ablocks-image-scroll__figure img:hover',
			$this->get_image_hover_css( $attributes ),
			$this->get_image_hover_css( $attributes, 'Tablet' ),
			$this->get_image_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-wrap',
			$this->get_icon_wrapper_css( $attributes ),
			$this->get_icon_wrapper_css( $attributes, 'Tablet' ),
			$this->get_icon_wrapper_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--image-scroll:hover .ablocks-icon-wrap',
			$this->get_icon_wrapper_hover_css( $attributes ),
		);
		return $css_generator->generate_css();
	}
	public function get_scroll_css( $attributes, $device = '' ) {
		$css = [];
		$css['width'] = '100%';
		return array_merge(
			$css,
			Range::get_css([
				'attributeValue' => $attributes['scrollHeight'],
				'isResponsive' => true,
				'defaultValue' => 200,
				'property' => 'height',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['scrollWidth'],
				'attributeObjectKey' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'width',
				'device' => $device,
			]),
		);
	}
	public function get_scroll_option_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['imageScrollOption']['value'] ) ) {
			if ( $attributes['imageScrollOption']['value'] === 'mouse-scroll' ) {
				$css['position'] = 'static';
				$css['overflow-y'] = 'scroll';
				$css['overflow-x'] = 'hidden';
			} elseif ( $attributes['imageScrollOption']['value'] === 'top-to-bottom' ) {
				$css['position'] = 'static';
				$css['overflow'] = 'hidden';
			} elseif ( $attributes['imageScrollOption']['value'] === 'bottom-to-top' ) {
				$css['position'] = 'static';
				$css['overflow'] = 'hidden';
			} elseif ( $attributes['imageScrollOption']['value'] === 'left-to-right' ) {
				$css['position'] = 'static';
				$css['overflow'] = 'hidden';
			} elseif ( $attributes['imageScrollOption']['value'] === 'right-to-left' ) {
				$css['position'] = 'static';
				$css['overflow'] = 'hidden';
			} elseif ( $attributes['imageScrollOption']['value'] === 'horizontal-scroll' ) {
				$css['position'] = 'static';
				$css['overflow-y'] = 'hidden';
				$css['overflow-x'] = 'scroll';
			}//end if
		}//end if

		return $css;
	}
	public function get_image_overlay_css( $attributes ) {
		$css = [];
		$css['background-color'] = Color::get_css(
		isset( $attributes['overlayColor'] ) ? $attributes['overlayColor'] : '');

		// Set common positioning and display properties
		$css['position'] = 'absolute';
		$css['top'] = '0';
		$css['left'] = '0';
		$css['right'] = '0';
		$css['bottom'] = '0';
		$css['z-index'] = 4;
		$css['display'] = 'block';

		// Border calculations
		$border = $attributes['border'] ?? [];
		$topDiff = ! empty( $border['commonWidth'] ) ? (int) $border['commonWidth'] : (int) ( $border['topWidth'] ?? 0 );
		$bottomDiff = ! empty( $border['commonWidth'] ) ? (int) $border['commonWidth'] : (int) ( $border['bottomWidth'] ?? 0 );
		$leftDiff = ! empty( $border['commonWidth'] ) ? (int) $border['commonWidth'] : (int) ( $border['leftWidth'] ?? 0 );
		$rightDiff = ! empty( $border['commonWidth'] ) ? (int) $border['commonWidth'] : (int) ( $border['rightWidth'] ?? 0 );

		// Border radius calculations
		$topRadiusValue = ! empty( $border['commonRadius'] ) ? (int) $border['commonRadius'] : (int) ( $border['topRadius'] ?? 0 );
		$leftRadiusValue = ! empty( $border['commonRadius'] ) ? (int) $border['commonRadius'] : (int) ( $border['leftRadius'] ?? 0 );
		$rightRadiusValue = ! empty( $border['commonRadius'] ) ? (int) $border['commonRadius'] : (int) ( $border['rightRadius'] ?? 0 );
		$bottomRadiusValue = ! empty( $border['commonRadius'] ) ? (int) $border['commonRadius'] : (int) ( $border['bottomRadius'] ?? 0 );

		// Apply border radius with differences taken into account
		$css['border-top-left-radius'] = ( $topRadiusValue - $topDiff ) . 'px';
		$css['border-top-right-radius'] = ( $rightRadiusValue - $rightDiff ) . 'px';
		$css['border-bottom-left-radius'] = ( $bottomRadiusValue - $bottomDiff ) . 'px';
		$css['border-bottom-right-radius'] = ( $leftRadiusValue - $leftDiff ) . 'px';

		return $css;
	}

	public function get_image_overlay_hover_css( $attributes ) {
		$css = [];
		$css['display'] = 'none';
		return $css;
	}
	public function get_image_figure_css( $attributes, $device = '' ) {
		$css = [];
		$css['width'] = '100% !important';
		$css['position'] = 'static !important';
		$css['display'] = 'flex';
		$css['flex-direction'] = 'column';

		return $css;
	}
	public function get_wrapper_css( $attributes, $device = '' ) {
		return array_merge(
			isset( $attributes['padding'] ) ? Dimensions::get_css( $attributes['padding'], 'padding', $device ) : [],
			isset( $attributes['border'] ) ? Border::get_css( $attributes['border'], '', $device ) : [],
		);
	}
	public function get_wrapper_hover_css( $attributes, $device = '' ) {
		$border_hover_css = Border::get_hover_css( isset( $attributes['border'] ) ? $attributes['border'] : null, $device );

		return array_merge(
			$border_hover_css,
		);
	}

	public function get_image_css( $attributes, $device = '' ) {
		$css = [];
		$css['width'] = '100%';
		if ( ! empty( $attributes['imageScrollOption']['value'] ) ) {
			$checkHorizontalOption = $attributes['imageScrollOption']['value'] === 'horizontal-scroll' || $attributes['imageScrollOption']['value'] === 'left-to-right' || $attributes['imageScrollOption']['value'] === 'right-to-left';

			if ( $checkHorizontalOption ) {
				$css['width'] = 'none';
				$css['max-width'] = 'none';
				$css['object-fit'] = 'fill';
			}
		}
		$css['height'] = '100%';
		if ( ! empty( $attributes[ 'imgUrl' . $device ] ) ) {
			$css['max-width'] = '100%';
			$css['transition'] = '0.3s ease';
		}

		if ( isset( $attributes['widthHeightWidget'][ 'width' . $device ] ) ) {
			$css['width'] = $attributes['widthHeightWidget'][ 'width' . $device ] . 'px';
		} elseif ( isset( $attributes['widthHeightWidget']['imgNaturalWidth'] ) ) {
			$css['width'] = $attributes['widthHeightWidget']['imgNaturalWidth'] . 'px';
		}

		$aspectRatioValue = empty( $attributes['aspectRatio'][ 'value' . $device ] ) ? false : $attributes['aspectRatio'][ 'value' . $device ];
		$heightValue = empty( $attributes['widthHeightWidget'][ 'height' . $device ] ) ? false : $attributes['widthHeightWidget'][ 'height' . $device ];
		$showHeight = ! empty( $attributes['widthHeightWidget'][ 'showHeight' . $device ] );

		if ( $showHeight || ( ! $aspectRatioValue && $heightValue ) ) {
			$css['height'] = $heightValue . 'px';
		} else {
			$css['height'] = 'auto';
		}

		if ( isset( $attributes['objectFit'][ 'value' . $device ] ) && '' !== $attributes['objectFit'][ 'value' . $device ] && 'default' !== $attributes['objectFit'][ 'value' . $device ] ) {
			$css['object-fit'] = $attributes['objectFit'][ 'value' . $device ];
		}

		if ( $aspectRatioValue && $aspectRatioValue !== 'original' ) {
			$css['aspect-ratio'] = $aspectRatioValue;
		}

		return array_merge(
			$css,
			Range::get_css([
				'attributeValue' => $attributes['opacity'],
				'defaultValue' => 1,
				'unitDefaultValue' => '',
				'property' => 'opacity',
				'device' => $device,
			]),
			isset( $attributes['cssFilter'] ) ? CssFilter::get_css( $attributes['cssFilter'], '', $device ) : [],
			isset( $attributes['boxShadow'] ) ? BoxShadow::get_css( $attributes['boxShadow'], $device ) : []
		);
	}

	public function get_image_hover_css( $attributes, $device = '' ) {
		$css = [];
		$range_css = Range::get_css([
			'attributeValue' => $attributes['opacityH'],
			'defaultValue' => 0,
			'unitDefaultValue' => '',
			'property' => 'opacity',
			'device' => $device,
		]);
		$transitionDurationRange = Range::get_css([
			'attributeValue' => $attributes['transitionDuration'],
			'defaultValue' => 0.5,
			'unitDefaultValue' => '',
			'property' => 'transition',
			'device' => $device,
		]);
		$filterTransitionDurationRange = Range::get_css([
			'attributeValue' => $attributes['filterTransitionDuration'],
			'defaultValue' => 0.5,
			'unitDefaultValue' => '',
			'property' => 'filter',
			'device' => $device,
		]);

		if (
			isset( $attributes['border']['transitionDuration'] ) ||
			isset( $attributes['boxShadow']['transitionDuration'] ) ||
			isset( $filterTransitionDurationRange['filter'] ) ||
			isset( $transitionDurationRange['transition'] )
		) {
			$css['transition'] = sprintf(
				'border %ss, box-shadow %ss, opacity %ss, filter %ss, transform 0.3s',
				! empty( $attributes['border']['transitionDuration'] ) ? $attributes['border']['transitionDuration'] : $transitionDurationRange['transition'],
				isset( $attributes['boxShadow']['transitionDuration'] ) ? $attributes['boxShadow']['transitionDuration'] : $transitionDurationRange['transition'],
				$filterTransitionDurationRange['filter'],
				$filterTransitionDurationRange['filter']
			);
		}

		return array_merge(
			$css,
			$range_css,
			( isset( $attributes['boxShadow'] ) ) ? BoxShadow::get_hover_css( $attributes['boxShadow'], $device ) : [],
			( isset( $attributes['cssHoverFilter'] ) ) ? CssFilter::get_css( $attributes['cssHoverFilter'], '', $device ) : [],
		);
	}



	public function get_image_container_css( $attributes, $device = '' ) {
		$alignment_value = $attributes['alignment'][ 'value' . $device ] ?? '';

		$css = [];
		$border = $attributes['border'] ?? [];
		$topDiff = ! empty( $border['commonWidth'] ) ? (int) $border['commonWidth'] : (int) ( $border['topWidth'] ?? 0 );
		$bottomDiff = ! empty( $border['commonWidth'] ) ? (int) $border['commonWidth'] : (int) ( $border['bottomWidth'] ?? 0 );
		$leftDiff = ! empty( $border['commonWidth'] ) ? (int) $border['commonWidth'] : (int) ( $border['leftWidth'] ?? 0 );
		$rightDiff = ! empty( $border['commonWidth'] ) ? (int) $border['commonWidth'] : (int) ( $border['rightWidth'] ?? 0 );

		// Border radius calculations
		$topRadiusValue = ! empty( $border['commonRadius'] ) ? (int) $border['commonRadius'] : (int) ( $border['topRadius'] ?? 0 );
		$leftRadiusValue = ! empty( $border['commonRadius'] ) ? (int) $border['commonRadius'] : (int) ( $border['leftRadius'] ?? 0 );
		$rightRadiusValue = ! empty( $border['commonRadius'] ) ? (int) $border['commonRadius'] : (int) ( $border['rightRadius'] ?? 0 );
		$bottomRadiusValue = ! empty( $border['commonRadius'] ) ? (int) $border['commonRadius'] : (int) ( $border['bottomRadius'] ?? 0 );

		// Apply border radius with differences taken into account
		$css['border-top-left-radius'] = ( $topRadiusValue - $topDiff ) . 'px';
		$css['border-top-right-radius'] = ( $rightRadiusValue - $rightDiff ) . 'px';
		$css['border-bottom-left-radius'] = ( $bottomRadiusValue - $bottomDiff ) . 'px';
		$css['border-bottom-right-radius'] = ( $leftRadiusValue - $leftDiff ) . 'px';
		if ( ! empty( $alignment_value ) ) {
			$css['display'] = 'flex';
			$css['justify-content'] = $alignment_value;
		}
		return $css;
	}
	public function get_icon_wrapper_css( $attributes, $device = '' ) {
		$css = [
			'position' => 'absolute',
			'top' => '45%',
			'left' => '50%',
			'z-index' => '5',
			'opacity' => '1',
		];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['iconColor'] ) ? $attributes['iconColor'] : 'black' ) ],
			$css,
			Range::get_css([
				'attributeValue' => $attributes['iconFontSize'],
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 36,
				'property' => 'font-size',
			])
		);
	}
	public function get_icon_wrapper_hover_css( $attributes, $device = '' ) {
		$css = [
			'opacity' => '0'
		];
		return array_merge(
			$css,
		);
	}

}
