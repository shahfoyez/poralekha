<?php
namespace ABlocks\Blocks\lists;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Width;
use ABlocks\Controls\Border;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {

	protected $block_name = 'lists';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		// Wrapper CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes, '' ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' ),
		);
		// List CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-list',
			$this->get_list_css( $attributes, '' ),
			$this->get_list_css( $attributes, 'Tablet' ),
			$this->get_list_css( $attributes, 'Mobile' ),
		);
		// List Wrapper CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-list__item-content',
			$this->get_list_wrapper_css( $attributes, '' ),
			$this->get_list_wrapper_css( $attributes, 'Tablet' ),
			$this->get_list_wrapper_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-list_item-content-divider',
			$this->get_Divider_Wrapper_css( $attributes, '' ),
			$this->get_Divider_Wrapper_css( $attributes, 'Tablet' ),
			$this->get_Divider_Wrapper_css( $attributes, 'Mobile' ),
		);
		// Marker CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-list__item-content .ablocks-list__item-marker',
			$this->get_marker_css( $attributes, '' ),
			$this->get_marker_css( $attributes, 'Tablet' ),
			$this->get_marker_css( $attributes, 'Mobile' ),
		);
		// Icon CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-list__item-content .ablocks-svg-icon',
			$this->get_icon_css( $attributes, '' ),
			$this->get_icon_css( $attributes, 'Tablet' ),
			$this->get_icon_css( $attributes, 'Mobile' ),
		);
		// Icon hover css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-list__item-content .ablocks-svg-icon:hover',
			$this->get_icon_hover_css( $attributes, '' ),
			$this->get_icon_hover_css( $attributes, 'Tablet' ),
			$this->get_icon_hover_css( $attributes, 'Mobile' ),
		);

		// List text CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-list__item-content .ablocks-list__item-text',
			$this->get_list_text_css( $attributes ),
			$this->get_list_text_css( $attributes, 'Tablet' ),
			$this->get_list_text_css( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );
		// Wrapper CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes, '' ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' ),
		);
		// List CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-list',
			$this->get_list_css( $attributes, '' ),
			$this->get_list_css( $attributes, 'Tablet' ),
			$this->get_list_css( $attributes, 'Mobile' ),
		);
		// List Wrapper CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-list__item-content',
			$this->get_list_wrapper_css( $attributes, '' ),
			$this->get_list_wrapper_css( $attributes, 'Tablet' ),
			$this->get_list_wrapper_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-list_item-content-divider',
			$this->get_Divider_Wrapper_css( $attributes, '' ),
			$this->get_Divider_Wrapper_css( $attributes, 'Tablet' ),
			$this->get_Divider_Wrapper_css( $attributes, 'Mobile' ),
		);
		// Marker CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-list__item-content .ablocks-list__item-marker',
			$this->get_marker_css( $attributes, '' ),
			$this->get_marker_css( $attributes, 'Tablet' ),
			$this->get_marker_css( $attributes, 'Mobile' ),
		);
		// Icon CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-list__item-content .ablocks-svg-icon',
			$this->get_icon_css( $attributes, '' ),
			$this->get_icon_css( $attributes, 'Tablet' ),
			$this->get_icon_css( $attributes, 'Mobile' ),
		);
		// Icon hover css
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-list__item-content .ablocks-svg-icon:hover',
			$this->get_icon_hover_css( $attributes, '' ),
			$this->get_icon_hover_css( $attributes, 'Tablet' ),
			$this->get_icon_hover_css( $attributes, 'Mobile' ),
		);

		// List text CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-list__item-content .ablocks-list__item-text',
			$this->get_list_text_css( $attributes ),
			$this->get_list_text_css( $attributes, 'Tablet' ),
			$this->get_list_text_css( $attributes, 'Mobile' ),
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
		$typography = isset( $attributes['typography'] ) ? $attributes['typography'] : '';
		$alignment = isset( $attributes['alignment'] ) ? $attributes['alignment'] : '';
		$typographyglobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : array();
		return array_merge(
			Alignment::get_css( $alignment, 'text-align', $device ),
			Typography::get_css( $typography, '', $device, $typographyglobal ),
		);
	}

	public function get_list_css( $attributes, $device = '' ) {
		$css = [];

		if ( isset( $attributes['spaceBetween'][ 'value' . $device ] ) && ! empty( $attributes['spaceBetween'][ 'value' . $device ] ) ) {
			$css['gap'] = $attributes['spaceBetween'][ 'value' . $device ] . 'px';
		}

		$stack = $attributes[ 'stack' . $device ] ?? $attributes['stack'] ?? '';
		if ( $stack === 'vertical' ) {
			$css['flex-direction'] = 'column';
			$vertical_alignment = $attributes[ 'verticalAlignment' . $device ] ?? $attributes['verticalAlignment'] ?? '';
			if ( ! empty( $vertical_alignment ) ) {
				$css['align-items'] = $vertical_alignment;
			}
		} elseif ( $stack === 'horizontal' ) {
			$css['flex-direction'] = 'row';
			$horizontal_alignment = $attributes[ 'horizontalAlignment' . $device ] ?? $attributes['horizontalAlignment'] ?? '';
			if ( ! empty( $horizontal_alignment ) ) {
				$css['justify-content'] = $horizontal_alignment;
			}
		}

		$width_css = isset( $attributes[ 'width' . $device ] )
			? Width::get_css( $attributes[ 'width' . $device ], 'width', $device )
			: [];

		$css = array_merge(
			$css,
			$width_css,
			isset( $attributes['horizontalAlignment'] )
				? Alignment::get_css( $attributes['horizontalAlignment'], 'justify-content', $device )
				: []
		);
		foreach ( $css as $property => $value ) {
			if ( is_array( $value ) ) {
				unset( $css[ $property ] );
			}
		}
		return $css;
	}

	public function get_list_wrapper_css( $attributes, $device = '' ) {
		$css = [];
		$marker_type = $attributes['markerType'] ?? '';
		$text_indent = $attributes['textIndent'][ 'value' . $device ] ?? '';
		if ( $marker_type && 'icon' === $marker_type ) {
			if ( ! empty( $attributes['position'][ 'value' . $device ] ) ) {
				$css['align-items'] = $attributes['position'][ 'value' . $device ];
			}
		}
		if ( $text_indent && ! empty( $text_indent ) ) {
			$css['gap'] = $text_indent . 'px';
		}

		return $css;
	}

	public function get_divider_wrapper_css( $attributes, $device = '' ) {
		$css = [];
		$width_css = [];
		$weight_css = [];

		if ( isset( $attributes['divider'] ) && ! empty( $attributes['divider'] ) ) {
			$border_color = Color::get_css(
			isset( $attributes['borderColor'] ) ? $attributes['borderColor'] : 'black');
			$divider_pattern_url = isset( $attributes['dividerPatternUrl'] ) ? $attributes['dividerPatternUrl'] : '';
			$stack = isset( $attributes['stack'] ) ? $attributes['stack'] : '';

			if ( ! empty( $divider_pattern_url ) ) {
				if ( $stack === 'vertical' ) {
					$css['border-bottom-color'] = $border_color;
					$css['border-bottom-style'] = $divider_pattern_url;
					$weight_css = Range::get_css([
						'attributeValue' => $attributes['weight'],
						'attribute_object_key' => 'value',
						'isResponsive' => true,
						'defaultValue' => 5,
						'hasUnit' => true,
						'unitDefaultValue' => 'px',
						'property' => 'border-bottom-width',
						'device' => $device,
					]);
				} elseif ( $stack === 'horizontal' ) {
					$css['border-right-color'] = $border_color;
					$css['border-right-style'] = $divider_pattern_url;
					$weight_css = Range::get_css([
						'attributeValue' => $attributes['weight'],
						'attribute_object_key' => 'value',
						'isResponsive' => true,
						'defaultValue' => 5,
						'hasUnit' => true,
						'unitDefaultValue' => 'px',
						'property' => 'border-right-width',
						'device' => $device,
					]);
				}//end if
			}//end if

			if ( $stack === 'vertical' && ! empty( $attributes['width'][ 'value' . $device ] ) ) {
				$width_css = Range::get_css([
					'attributeValue' => $attributes['width'],
					'attribute_object_key' => 'value',
					'isResponsive' => true,
					'defaultValue' => 100,
					'hasUnit' => true,
					'unitDefaultValue' => 'px',
					'property' => 'width',
					'device' => $device,
				]);
			}
		}//end if

		return array_merge(
			$css,
			$weight_css,
			$width_css
		);
	}



	public function get_marker_css( $attributes, $device = '' ) {
		$css = [];
		$marker_size = isset( $attributes['markerSize'][ 'value' . $device ] ) ? $attributes['markerSize'][ 'value' . $device ] : '';
		if ( isset( $attributes['markerColor'] ) && ! empty( $attributes['markerColor'] ) ) {
			$css['background'] = Color::get_css(
			isset( $attributes['markerColor'] ) ? $attributes['markerColor'] : '');
		}

		if ( $marker_size && ! empty( $marker_size ) ) {
			$css['max-width'] = $marker_size . 'px';
			$css['max-height'] = $marker_size . 'px';
			$css['min-width'] = $marker_size . 'px';
			$css['min-height'] = $marker_size . 'px';
		}

		return $css;
	}

	public function get_icon_css( $attributes, $device = '' ) {
		$css = [];
		$border = isset( $attributes['border'] ) ? $attributes['border'] : '';
		$padding = isset( $attributes['padding'] ) ? $attributes['padding'] : '';
		$css['box-sizing'] = 'content-box';

		if ( isset( $attributes['markerType'] ) && $attributes['markerType'] === 'icon' ) {
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
		}//end if

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
		return [ 'color' => Color::get_css( isset( $attributes['textColor'] ) ? $attributes['textColor'] : '' ) ];
	}
}
