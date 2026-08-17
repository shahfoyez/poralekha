<?php
namespace ABlocks\Blocks\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\BackgroundOverlay;
use ABlocks\Controls\Range;
use ABlocks\Helper;

class Block extends BlockBaseAbstract {
	protected $block_name = 'container';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		$css_generator->add_class_styles(
			'.ablocks-is-fse-theme {{WRAPPER}}:not(.block-editor-block-list__block).ablocks-block--container--is-root, body:not(.ablocks-is-fse-theme) {{WRAPPER}}:not(.block-editor-block-list__block).ablocks-block--container--is-root',
			$this->reset_static_css( $attributes ),
			$this->reset_static_css( $attributes, 'Tablet' ),
			$this->reset_static_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--container',
			$this->get_main_wrapper_css( $attributes ),
			$this->get_main_wrapper_css( $attributes, 'Tablet' ),
			$this->get_main_wrapper_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--container-has-link-overlay',
			$this->get_link_overlay_wrapper_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--container-has-link-overlay > .ablocks-block-container__link-overlay',
			$this->get_link_overlay_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--container-has-link-overlay > *:not(.ablocks-block-container__link-overlay):not(.ablocks-background--video):not(.ablocks-background-video-wrapper)',
			$this->get_link_overlay_content_css( $attributes )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} > .ablocks-block-container',
			$this->get_block_container_css( $attributes ),
			$this->get_block_container_css( $attributes, 'Tablet' ),
			$this->get_block_container_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} > .ablocks-block-container',
			$this->get_inner_blocks_closest_parent_css( $attributes ),
			$this->get_inner_blocks_closest_parent_css( $attributes, 'Tablet' ),
			$this->get_inner_blocks_closest_parent_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} > .ablocks-block-container > *:not(style,.ablocks-block--container)',
			$this->get_container_inner_blocks_row_column_display_css( $attributes ),
			$this->get_container_inner_blocks_row_column_display_css( $attributes, 'Tablet' ),
			$this->get_container_inner_blocks_row_column_display_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}::before',
			$this->get_container_before_css( $attributes ),
			$this->get_container_before_css( $attributes, 'Tablet' ),
			$this->get_container_before_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}:hover::before',
			$this->get_container_before_hover_css( $attributes ),
			$this->get_container_before_hover_css( $attributes, 'Tablet' ),
			$this->get_container_before_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container >.ablocks-container-shape-bring-to-front',
			$this->getContainerShapeTopCSS( $attributes ),
			$this->getContainerShapeTopCSS( $attributes, 'Tablet' ),
			$this->getContainerShapeTopCSS( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container >.ablocks-container-shape-bring-to-front',
			$this->getContainerShapeBottomCSS( $attributes ),
			$this->getContainerShapeBottomCSS( $attributes, 'Tablet' ),
			$this->getContainerShapeBottomCSS( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-shape-top > svg',
			$this->getContainerShapeTopSvgCSS( $attributes ),
			$this->getContainerShapeTopSvgCSS( $attributes, 'Tablet' ),
			$this->getContainerShapeTopSvgCSS( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-shape-bottom > svg',
			$this->getContainerShapeBottomSvgCSS( $attributes ),
			$this->getContainerShapeBottomSvgCSS( $attributes, 'Tablet' ),
			$this->getContainerShapeBottomSvgCSS( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}

	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );
		if ( isset( $attributes['isRootContainer'] ) && (bool) $attributes['isRootContainer'] ) {
			$css_generator->add_class_styles(
				'.ablocks-is-fse-theme {{WRAPPER}}.ablocks-block--container--is-root, 
				body:not(.ablocks-is-fse-theme) {{WRAPPER}}.ablocks-block--container--is-root',
				$this->reset_static_css( $attributes ),
				$this->reset_static_css( $attributes, 'Tablet' ),
				$this->reset_static_css( $attributes, 'Mobile' ),
			);
		}
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--container',
			$this->get_main_wrapper_css( $attributes ),
			$this->get_main_wrapper_css( $attributes, 'Tablet' ),
			$this->get_main_wrapper_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--container-has-link-overlay',
			$this->get_link_overlay_wrapper_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--container-has-link-overlay > .ablocks-block-container__link-overlay',
			$this->get_link_overlay_css( $attributes )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--container-has-link-overlay > *:not(.ablocks-block-container__link-overlay):not(.ablocks-background--video):not(.ablocks-background-video-wrapper)',
			$this->get_link_overlay_content_css( $attributes )
		);

		// Block Container only available root block
		$css_generator->add_class_styles(
			$attributes['isRootContainer'] ? '{{WRAPPER}} > .ablocks-block-container' : '{{WRAPPER}}',
			$this->get_block_container_css( $attributes ),
			$this->get_block_container_css( $attributes, 'Tablet' ),
			$this->get_block_container_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			$attributes['isRootContainer'] ? '{{WRAPPER}} > .ablocks-block-container' : '{{WRAPPER}}',
			$this->get_inner_blocks_closest_parent_css( $attributes ),
			$this->get_inner_blocks_closest_parent_css( $attributes, 'Tablet' ),
			$this->get_inner_blocks_closest_parent_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-has-block-container > .ablocks-block-container',
			$this->get_inner_blocks_closest_parent_css( $attributes ),
			$this->get_inner_blocks_closest_parent_css( $attributes, 'Tablet' ),
			$this->get_inner_blocks_closest_parent_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			$attributes['isRootContainer'] ? '{{WRAPPER}} > .ablocks-block-container > *:not(style,.ablocks-block--container)' : '{{WRAPPER}}',
			$this->get_container_inner_blocks_row_column_display_css_2( $attributes ),
			$this->get_container_inner_blocks_row_column_display_css_2( $attributes, 'Tablet' ),
			$this->get_container_inner_blocks_row_column_display_css_2( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}::before',
			$this->get_container_before_css( $attributes ),
			$this->get_container_before_css( $attributes, 'Tablet' ),
			$this->get_container_before_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}:hover::before',
			$this->get_container_before_hover_css( $attributes ),
			$this->get_container_before_hover_css( $attributes, 'Tablet' ),
			$this->get_container_before_hover_css( $attributes, 'Mobile' ),
		);
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-container-shape-top > svg',
				$this->getContainerShapeTopSvgCSS( $attributes ),
				$this->getContainerShapeTopSvgCSS( $attributes, 'Tablet' ),
				$this->getContainerShapeTopSvgCSS( $attributes, 'Mobile' ),
			);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-container-shape-bottom > svg',
			$this->getContainerShapeBottomSvgCSS( $attributes ),
			$this->getContainerShapeBottomSvgCSS( $attributes, 'Tablet' ),
			$this->getContainerShapeBottomSvgCSS( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-container-shape-top',
			$this->getContainerShapeTopCSS( $attributes ),
			$this->getContainerShapeTopCSS( $attributes, 'Tablet' ),
			$this->getContainerShapeTopCSS( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-container-shape-bottom',
			$this->getContainerShapeBottomCSS( $attributes ),
			$this->getContainerShapeBottomCSS( $attributes, 'Tablet' ),
			$this->getContainerShapeBottomCSS( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}

	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}

	public function get_inner_blocks_closest_parent_css( $attributes, $device = '' ) {
		$css = [
			'display' => 'flex',
		];

		$gridColumn = Range::get_css([
			'attributeValue' => $attributes['gridColumn'],
			'attributeObjectKey' => 'value',
			'isResponsive' => true,
			'hasUnit' => true,
			'defaultValue' => 0,
			'defaultValueTablet' => 2,
			'defaultValueMobile' => 1,
			'property' => 'value',
			'unitDefaultValue' => '',
			'device' => $device,
		]);

		$gridRow = Range::get_css([
			'attributeValue' => $attributes['gridRow'],
			'attributeObjectKey' => 'value',
			'isResponsive' => true,
			'hasUnit' => true,
			'defaultValue' => 0,
			'property' => 'value',
			'unitDefaultValue' => '',
			'device' => $device,
		]);
		if ( ( $attributes['layout'] ?? '' ) !== 'grid' ) {
			$css['display'] = 'flex';
			if ( ! empty( $attributes[ 'direction' . $device ] ) ) {
				$css['flex-direction'] = $attributes[ 'direction' . $device ];
			}
			if ( ! empty( $attributes[ 'wrap' . $device ] ) ) {
				$css['flex-wrap'] = $attributes[ 'wrap' . $device ];
			}
		} else {
			$css['display'] = 'grid';
			if ( ! empty( $gridColumn['value'] ) ) {
				$css['grid-template-columns'] = 'repeat(' . $gridColumn['value'] . ', 1fr)';
			}
			if ( ! empty( $gridRow['value'] ) ) {
				$css['grid-template-rows'] = 'repeat(' . $gridRow['value'] . ', auto)';
			}
		}

		// Set alignment properties
		if ( ! empty( $attributes[ 'justify' . $device ] ) ) {
			$css['justify-content'] = $attributes[ 'justify' . $device ];
		}
		if ( ! empty( $attributes[ 'align' . $device ] ) ) {
			$css['align-items'] = $attributes[ 'align' . $device ];
		}

		// Need to add below code support
		if ( ! empty( $attributes['dir'][ 'value' . $device ] ) ) {
			$css['flex-direction'] = $attributes['dir'][ 'value' . $device ];
		}
		if ( ! empty( $attributes['justification'][ 'value' . $device ] ) ) {
			$css['justify-content'] = $attributes['justification'][ 'value' . $device ];
		}
		if ( ! empty( $attributes['justification'][ 'value' . $device ] ) ) {
			$css['justify-items'] = $attributes['justification'][ 'value' . $device ];
		}
		if ( ! empty( $attributes['alignment'][ 'value' . $device ] ) ) {
			$css['align-items'] = $attributes['alignment'][ 'value' . $device ];
		}
		if ( ! empty( $attributes['wrapping'][ 'value' . $device ] ) ) {
			$css['flex-wrap'] = $attributes['wrapping'][ 'value' . $device ];}

		// Merge additional CSS properties safely
		return array_merge(
			$css,
			Range::get_css([
				'attributeValue' => $attributes['minimumHeight'],
				'attributeObjectKey' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 0,
				'property' => 'min-height',
				'unitDefaultValue' => 'px',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['gap'],
				'attributeObjectKey' => 'rowGap',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 0,
				'property' => 'row-gap',
				'unitDefaultValue' => 'px',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['gap'],
				'attributeObjectKey' => 'columnGap',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 0,
				'property' => 'column-gap',
				'unitDefaultValue' => 'px',
				'device' => $device,
			])
		);
	}



	public function get_container_inner_blocks_row_column_display_css( $attributes, $device = '' ) {
		$css = [];
		if ( ! empty( $attributes['dir'][ 'value' . $device ] ) && ( 'row' === $attributes['dir'][ 'value' . $device ] || 'row-reverse' === $attributes['dir'][ 'value' . $device ] ) ) {
			$css['display'] = 'inline-block';
			$css['width'] = 'auto';
		}
		return $css;
	}

	public function get_container_inner_blocks_row_column_display_css_2( $attributes, $device = '' ) {
		$css = [];
		if ( ! empty( $attributes['dir'][ 'value' . $device ] ) && ( 'row' === $attributes['dir'][ 'value' . $device ] || 'row-reverse' === $attributes['dir'][ 'value' . $device ] ) ) {
			$css['display'] = 'inline-flex';
			// $css['width'] = 'auto';
		}
		return $css;
	}

	public function get_block_container_css( $attributes, $device = '' ) {
		$css = [];
		$preparedContentWidth = Range::get_css([
			'attributeValue' => $attributes['containerContentWidth'],
			'attributeObjectKey' => 'value',
			'isResponsive' => true,
			'hasUnit' => true,
			'defaultValue' => 1140,
			'property' => 'value',
			'unitDefaultValue' => 'px',
			'device' => $device,
		]);
		$content_box_width_value = $preparedContentWidth['value'] ?? '';
		if ( empty( $content_box_width_value ) && $device === '' ) {
			$content_box_width_value = Helper::get_settings( 'default_container_width' ) ?? 1140;
		}
		$content_box_width_unit = $preparedContentWidth['valueUnit'] ?? 'px';
		$is_root_container = isset( $attributes['isRootContainer'] ) ? $attributes['isRootContainer'] : false;

		if ( $is_root_container && $attributes['containerWidthType'] === 'boxed' && ! empty( $content_box_width_value ) ) {
			$css['max-width'] = "min(100%, {$content_box_width_value}{$content_box_width_unit})";
			$css['margin-right'] = 'auto !important';
			$css['margin-left'] = 'auto !important';
		}

		return $css;
	}

	public function reset_static_css( $attributes, $device = '' ) {
		$preparedContainer = Range::get_css([
			'attributeValue' => $attributes['containerWidth'],
			'attributeObjectKey' => 'value',
			'isResponsive' => true,
			'hasUnit' => true,
			'defaultValue' => 100,
			'property' => 'value',
			'unitDefaultValue' => '%',
			'device' => '',
		]);
		$css = [];
		if ( 'custom' === $attributes['containerWidthType'] && ! empty( $preparedContainer['value'] ) ) {
			$css['margin-left'] = 'auto !important';
			$css['margin-right'] = 'auto !important';
		}
		return $css;
	}
	public function get_main_wrapper_css( $attributes, $device = '' ) {
		$css = [];
		$prepared_container = Range::get_css([
			'attributeValue' => $attributes['containerWidth'],
			'attributeObjectKey' => 'value',
			'isResponsive' => true,
			'hasUnit' => true,
			'defaultValue' => 100,
			'property' => 'value',
			'unitDefaultValue' => '%',
			'device' => $device,
		]);

		$is_root_container = isset( $attributes['isRootContainer'] ) ? $attributes['isRootContainer'] : false;
		if ( ( ! $is_root_container || 'custom' === $attributes['containerWidthType'] ) && ! empty( $prepared_container['value'] ) ) {
			$css['max-width'] = "min(100%, {$prepared_container['value']}{$prepared_container['valueUnit']}) !important";
		}

		if ( 'custom' === $attributes['containerWidthType'] && ! empty( $prepared_container['value'] ) && $is_root_container ) {
			$css['margin-left'] = 'auto !important';
			$css['margin-right'] = 'auto !important';
		}

		if ( $is_root_container && 'custom' !== $attributes['containerWidthType'] ) {
			unset( $css['max-width'] );
			unset( $css['margin-left'] );
			unset( $css['margin-right'] );
		}

		$css['overflow'] = ! empty( $attributes['overflow'] ) ? $attributes['overflow'] : 'visible';

		return array_merge(
			Dimensions::get_css( $attributes['_padding'], 'padding', $device ),
			$css
		);
	}

	// The container's link (HTML Tag = a) is rendered as an overlay <a>, not as
	// the wrapping tag, so nested blocks can never end up with an <a> nested
	// inside another <a>. These three methods position that overlay and lift
	// the container's real content above it so nested interactive elements
	// (e.g. an InfoBox button) stay independently clickable.
	public function get_link_overlay_wrapper_css( $attributes, $device = '' ) {
		if ( empty( $attributes['htmlTag'] ) || 'a' !== $attributes['htmlTag'] ) {
			return [];
		}
		return [ 'position' => 'relative' ];
	}

	public function get_link_overlay_css( $attributes, $device = '' ) {
		if ( empty( $attributes['htmlTag'] ) || 'a' !== $attributes['htmlTag'] ) {
			return [];
		}
		return [
			'position' => 'absolute',
			'top' => '0',
			'left' => '0',
			'right' => '0',
			'bottom' => '0',
			'width' => '100%',
			'height' => '100%',
			'z-index' => '0',
		];
	}

	public function get_link_overlay_content_css( $attributes, $device = '' ) {
		if ( empty( $attributes['htmlTag'] ) || 'a' !== $attributes['htmlTag'] ) {
			return [];
		}
		return [
			'position' => 'relative',
			'z-index' => '1',
		];
	}

	public static function get_container_before_css( $attributes, $device = '' ) {
		if ( ! isset( $attributes['_backgroundOverlay']['backgroundOverlayType'] ) || $attributes['_backgroundOverlay']['backgroundOverlayType'] === 'none' ) {
			return [];
		}
		return array_merge(
			BackgroundOverlay::get_before_css( $attributes['_backgroundOverlay'], $attributes['_border'], 'background', $device ),
		);
	}
	public static function get_container_before_hover_css( $attributes, $device = '' ) {
		if ( ! isset( $attributes['_backgroundOverlay']['backgroundOverlayTypeH'] ) || $attributes['_backgroundOverlay']['backgroundOverlayTypeH'] === 'none' ) {
			return [];
		}
		return array_merge(
			BackgroundOverlay::get_before_hover_css( $attributes['_backgroundOverlay'], $attributes['_border'], 'background', $device ),
		);
	}

	public function getContainerShapeTopCSS( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['topShapeBringToFront'] ) &&
		$attributes['shapeTop'] !== '' ) {
			$css['z-index'] = 1;
			$css['position'] = 'absolute';
		}
		return $css;
	}

	public function getContainerShapeBottomCSS( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['bottomShapeBringToFront'] ) &&
		$attributes['shapeBottom'] !== '' ) {
			$css['z-index'] = 1;
			$css['position'] = 'absolute';
		}
		return $css;
	}

	public function getContainerShapeTopSvgCSS( $attributes, $device = '' ) {
		$css = [];

		if ( empty( $attributes['shapeTop'] ) ) {
			return [];
		}

		if ( ! empty( $attributes['shapeTopColor'] ) ) {
			$css['fill'] = $attributes['shapeTopColor'];
		}
		$topShapeFlip = isset( $attributes['topShapeFlip'] ) ? $attributes['topShapeFlip'] : false;
		$shapeTop = isset( $attributes['shapeTop'] ) ? $attributes['shapeTop'] : '';

		if ( $topShapeFlip && $shapeTop !== '' ) {
			$css['transform'] = 'translateX(0%) rotateY(180deg)';
		} else {
			$css['transform'] = 'translateX(0%)';
		}

		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['shapeTopHeight'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['shapeTopWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'width',
				'device' => $device,
			]),
			$css,
		);
	}

	public function getContainerShapeBottomSvgCSS( $attributes, $device = '' ) {
		$css = [];

		if ( empty( $attributes['shapeBottom'] ) ) {
			return [];
		}

		if ( ! empty( $attributes['shapeBottomColor'] ) ) {
			$css['fill'] = $attributes['shapeBottomColor'];
		}

		$bottomShapeFlip = isset( $attributes['bottomShapeFlip'] ) ? $attributes['bottomShapeFlip'] : false;
		$shapeBottom = isset( $attributes['shapeBottom'] ) ? $attributes['shapeBottom'] : '';

		if ( $bottomShapeFlip && $shapeBottom !== '' ) {
			$css['transform'] = 'translateX(0%) rotateY(180deg)';
		} else {
			$css['transform'] = 'translateX(0%)';
		}
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['shapeBottomHeight'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['shapeBottomWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 100,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'width',
				'device' => $device,
			]),
			$css,
		);
	}
}
