<?php

namespace ABlocks\Blocks\Modal;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Range;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Background;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Border;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Color;


class Block extends BlockBaseAbstract {

	protected $block_name = 'modal';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		if ( ! empty( $attributes['popupPosition'] ) && 'popup' === $attributes['popupPosition'] && ! empty( $attributes['popupTopOffset'] ) ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}}.ablocks-block-modal-position--popup .ablocks-block-modal---panel-wrap .ablocks-modal-popup-content-wrap',
				[ 'margin-top' => $attributes['popupTopOffset'] . 'px' ]
			);
		}
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-modal---panel-wrap.ablocks-block-modal---panel-wrap',
			$this->get_panel_main_wrapper_css( $attributes ),
			$this->get_panel_main_wrapper_css( $attributes, 'Tablet' ),
			$this->get_panel_main_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-modal---panel-wrap.ablocks-block-modal---panel-wrap .ablocks-modal-popup-content-wrap',
			$this->get_panel_content_wrap_css( $attributes ),
			$this->get_panel_content_wrap_css( $attributes, 'Tablet' ),
			$this->get_panel_content_wrap_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-modal---panel-wrap.ablocks-block-modal---panel-wrap .ablocks-modal-popup-content-wrap:hover',
			$this->get_panel_content_wrap_hover_css( $attributes ),
			$this->get_panel_content_wrap_hover_css( $attributes, 'Tablet' ),
			$this->get_panel_content_wrap_hover_css( $attributes, 'Mobile' )
		);
		if ( empty( $attributes['disableCloseButton'] ) ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-block-modal---panel-wrap.ablocks-block-modal---panel-wrap.ablocks-block-modal---panel-wrap.ablocks-block-modal---panel-wrap.ablocks-block-modal---panel-wrap .ablocks-modal-popup-close',
				$this->get_panel_close_button_css( $attributes ),
				$this->get_panel_close_button_css( $attributes, 'Tablet' ),
				$this->get_panel_close_button_css( $attributes, 'Mobile' )
			);

			if ( ! empty( $attributes['closeBtnColor'] ) ) {
				$css_generator->add_class_styles(
					'{{WRAPPER}} .ablocks-block-modal---panel-wrap.ablocks-block-modal---panel-wrap .ablocks-modal-popup-close svg',
					[ 'fill' => $attributes['closeBtnColor'] ]
				);
			}
		}
		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );
		if ( ! empty( $attributes['popupPosition'] ) && 'popup' === $attributes['popupPosition'] && ! empty( $attributes['popupTopOffset'] ) ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}}.ablocks-block-modal-position--popup .ablocks-block-modal---panel-wrap .ablocks-modal-popup-content-wrap',
				[ 'margin-top' => $attributes['popupTopOffset'] . 'px' ]
			);
		}
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-modal---panel-wrap.ablocks-block-modal---panel-wrap',
			$this->get_panel_main_wrapper_css( $attributes ),
			$this->get_panel_main_wrapper_css( $attributes, 'Tablet' ),
			$this->get_panel_main_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-modal---panel-wrap.ablocks-block-modal---panel-wrap .ablocks-modal-popup-content-wrap',
			$this->get_panel_content_wrap_css( $attributes ),
			$this->get_panel_content_wrap_css( $attributes, 'Tablet' ),
			$this->get_panel_content_wrap_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-modal---panel-wrap.ablocks-block-modal---panel-wrap .ablocks-modal-popup-content-wrap:hover',
			$this->get_panel_content_wrap_hover_css( $attributes ),
			$this->get_panel_content_wrap_hover_css( $attributes, 'Tablet' ),
			$this->get_panel_content_wrap_hover_css( $attributes, 'Mobile' )
		);
		if ( empty( $attributes['disableCloseButton'] ) ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-block-modal---panel-wrap.ablocks-block-modal---panel-wrap.ablocks-block-modal---panel-wrap.ablocks-block-modal---panel-wrap.ablocks-block-modal---panel-wrap .ablocks-modal-popup-close',
				$this->get_panel_close_button_css( $attributes ),
				$this->get_panel_close_button_css( $attributes, 'Tablet' ),
				$this->get_panel_close_button_css( $attributes, 'Mobile' )
			);

			if ( ! empty( $attributes['closeBtnColor'] ) ) {
				$css_generator->add_class_styles(
					'{{WRAPPER}} .ablocks-block-modal---panel-wrap.ablocks-block-modal---panel-wrap .ablocks-modal-popup-close svg',
					[ 'fill' => $attributes['closeBtnColor'] ]
				);
			}
		}
		return $css_generator->generate_css();
	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}

	public function get_panel_main_wrapper_css( $attributes, $device = '' ) {
		if ( ! empty( $device ) ) {
			return [];
		}
		return [ 'background-color' => Color::get_css( isset( $attributes['backdropColor'] ) ? $attributes['backdropColor'] : '' ) ];
	}

	public function get_panel_content_wrap_css( $attributes, $device = '' ) {
		$css = [];

		if ( empty( $device ) && ! empty( $attributes['panelContentPosition'] ) ) {
			$css['align-items'] = $attributes['panelContentPosition'];
		}

		$Background_css = Background::get_css( $attributes['panelBackground'], 'background', $device );
		$Border_css = Border::get_css( $attributes['panelBorder'], '', $device );
		$BoxShadow_css = BoxShadow::get_css( $attributes['panelShadow'], 'box-shadow' );

		$transition_css = [];
		if ( ! $device ) {
			$transition_value = 'all 1s';
			if ( ! empty( $Background_css['transition'] ) ) {
				$transition_value = $transition_value . ',' . $Background_css['transition'];
			}
			if ( ! empty( $Border_css['transition'] ) ) {
				$transition_value = $transition_value . ',' . $Border_css['transition'];
			}
			if ( ! empty( $BoxShadow_css['transition'] ) ) {
				$transition_value = $transition_value . ',' . $BoxShadow_css['transition'];
			}
			$transition_css['transition'] = $transition_value;
		}

		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['panelWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 50,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'width',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['panelHeight'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 50,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'min-height',
				'device' => $device,
			]),
			isset( $attributes['barBorder'] ) ? Border::get_css( $attributes['barBorder'], '', $device ) : [],
			$css,
			$css,
			Dimensions::get_css( $attributes['panelPadding'], 'padding', $device ),
			$Background_css,
			$Border_css,
			$BoxShadow_css,
			$transition_css,
		);
	}

	public function get_panel_content_wrap_hover_css( $attributes, $device = '' ) {
		$css = [];
		return array_merge(
			$css,
			Dimensions::get_css( $attributes['panelPadding'], 'padding', $device ),
			Background::get_hover_css( $attributes['panelBackground'], 'background', $device ),
			Border::get_hover_css( $attributes['panelBorder'], $device ),
			BoxShadow::get_hover_css( $attributes['panelShadow'] ),
		);
	}

	public function get_panel_close_button_css( $attributes, $device = '' ) {
		$css = [];
		if ( empty( $device ) ) {
			if ( ! empty( $attributes['closeBtnBackgroundColor'] ) ) {
				$css['background-color'] = Color::get_css(
				isset( $attributes['closeBtnBackgroundColor'] ) ? $attributes['closeBtnBackgroundColor'] : '');
			}
		}
		if ( ! empty( $attributes['closeBtnTop'] ) || 0 === $attributes['closeBtnTop'] ) {
			$css['top'] = $attributes['closeBtnTop'] . 'px';
		}
		if ( ! empty( $attributes['closeBtnSide'] ) || 0 === $attributes['closeBtnSide'] ) {
			if ( 'left' === $attributes['closePosition'] ) {
				$css['left'] = $attributes['closeBtnSide'] . 'px';
				$css['right'] = 'unset';
			} else {
				$css['right'] = $attributes['closeBtnSide'] . 'px';
				$css['left'] = 'unset';
			}
		}
		return $css;
	}
}
