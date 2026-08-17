<?php
namespace ABlocks\Blocks\Image;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Typography;
use ABlocks\Controls\CssFilter;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'image';

	public function build_css_v1( $attributes ) {

		// Generate CSS
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		// Image Wrapper CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' ),
		);

		// Image container css
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_image_container_css( $attributes ),
			$this->get_image_container_css( $attributes, 'Tablet' ),
			$this->get_image_container_css( $attributes, 'Mobile' ),
		);
		// Image css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--featured-image img',
			$this->get_image_css( $attributes ),
			$this->get_image_css( $attributes, 'Tablet' ),
			$this->get_image_css( $attributes, 'Mobile' ),
		);

		// Image hover css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block--featured-image img:hover',
			$this->get_image_hover_css( $attributes ),
			$this->get_image_hover_css( $attributes, 'Tablet' ),
			$this->get_image_hover_css( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {

		// Generate CSS
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );
		// Image Wrapper CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' ),
		);

		// Image container css
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_image_container_css( $attributes ),
			$this->get_image_container_css( $attributes, 'Tablet' ),
			$this->get_image_container_css( $attributes, 'Mobile' ),
		);
		// Image css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-image-figure img',
			$this->get_image_css( $attributes ),
			$this->get_image_css( $attributes, 'Tablet' ),
			$this->get_image_css( $attributes, 'Mobile' ),
		);

		// Image hover css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-image-figure img:hover',
			$this->get_image_hover_css( $attributes ),
			$this->get_image_hover_css( $attributes, 'Tablet' ),
			$this->get_image_hover_css( $attributes, 'Mobile' ),
		);
		// Image caption css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-image-figure .ablocks-image-caption',
			$this->get_image_caption_css( $attributes ),
			$this->get_image_caption_css( $attributes, 'Tablet' ),
			$this->get_image_caption_css( $attributes, 'Mobile' ),
		);

		// Image caption hover css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-image-figure .ablocks-image-caption:hover',
			$this->get_image_caption_hover_css( $attributes ),
			$this->get_image_caption_hover_css( $attributes, 'Tablet' ),
			$this->get_image_caption_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}

	public function get_wrapper_css( $attributes, $device = '' ) {
		return array_merge(
			isset( $attributes['padding'] ) ? Dimensions::get_css( $attributes['padding'], 'padding', $device ) : [],
		);
	}

	public function get_image_css( $attributes, $device = '' ) {
		$css = [];
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
		if ( isset( $attributes['onHoverImg'] ) && 'slide' === $attributes['onHoverImg'] ) {
			$css['transform'] = 'translate3d(-40px, 0, 0)';
			$css['transition'] = 'transform 0.3s';
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
			isset( $attributes['border'] ) ? Border::get_css( $attributes['border'], '', $device ) : [],
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

		if ( isset( $attributes['onHoverImg'] ) ) {
			$on_Hover_Img = $attributes['onHoverImg'];
			if ( 'zoomin' === $on_Hover_Img ) {
				$css['transform'] = 'scale(1.1)';
				$css['transition'] = 'transform 0.3s';
			} elseif ( 'grayscale' === $on_Hover_Img ) {
				$css['filter'] = 'grayscale(100%)';
				$css['transition'] = 'transform 0.3s';
			} elseif ( 'blur' === $on_Hover_Img ) {
				$css['filter'] = 'blur(3px)';
				$css['transition'] = 'transform 0.3s';
			} elseif ( 'slide' === $on_Hover_Img ) {
				$css['transform'] = 'translate3d(0, 0, 0)';
				$css['transition'] = 'transform 0.3s';
			}
		}
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

		$border_hover_css = Border::get_hover_css( isset( $attributes['border'] ) ? $attributes['border'] : null, $device );

		return array_merge(
			$css,
			$range_css,
			$border_hover_css,
			( isset( $attributes['boxShadow'] ) ) ? BoxShadow::get_hover_css( $attributes['boxShadow'], $device ) : [],
			( isset( $attributes['cssHoverFilter'] ) ) ? CssFilter::get_css( $attributes['cssHoverFilter'], '', $device ) : [],
		);
	}

	public function get_image_caption_css( $attributes, $device = '' ) {
		$css = [];

		if ( isset( $attributes['captionColor'] ) && ! empty( $attributes['captionColor'] ) ) {
			$css['color'] = Color::get_css( isset( $attributes['captionColor'] ) ? $attributes['captionColor'] : '' );

		}
		if ( isset( $attributes['captionBackground'] ) && ! empty( $attributes['captionBackground'] ) ) {
			$css['background'] = Color::get_css( isset( $attributes['captionBackground'] ) ? $attributes['captionBackground'] : '' );
		}
		if ( isset( $attributes['captionPosition'] ) && ! empty( $attributes['captionPosition'] && 'overlap' === $attributes['captionPosition'] ) ) {
			$css['width'] = '100%';
			$css['position'] = 'absolute';
			$css['bottom'] = 0;
			$css['left'] = 0;
		}
		$typographyGlobal = isset( $attributes['captionTypographyGlobal'] ) ? $attributes['captionTypographyGlobal'] : [];
		return array_merge(
			$css,
			( isset( $attributes['captionPadding'] ) ) ? Dimensions::get_css( $attributes['captionPadding'], 'padding', $device ) : [],
			( isset( $attributes['captionAlignment'] ) ) ? Alignment::get_css( $attributes['captionAlignment'], 'text-align', $device ) : [],
			( isset( $attributes['captionTypography'] ) ) ? Typography::get_css( $attributes['captionTypography'], '', $device, $typographyGlobal ) : [],
			( isset( $attributes['captionBorder'] ) ) ? Border::get_css( $attributes['captionBorder'], '', $device ) : [],
		);
	}

	public function get_image_container_css( $attributes, $device = '' ) {
		$alignment_value = $attributes['alignment'][ 'value' . $device ] ?? '';

		$css = [];
		if ( ! empty( $alignment_value ) ) {
			$css['display'] = 'flex';
			$css['justify-content'] = $alignment_value;
		}
		return $css;
	}

	public function get_image_caption_hover_css( $attributes, $device = '' ) {
		if ( isset( $attributes['captionBorder'] ) ) {
			return Border::get_hover_css( $attributes['captionBorder'], '', $device );
		}
		return [];
	}
}
