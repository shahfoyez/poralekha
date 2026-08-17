<?php

namespace ABlocks\Blocks\Tabs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Controls\Icon;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;


class Block extends BlockBaseAbstract {

	protected $block_name = 'tabs';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs',
			$this->get_tabs_css( $attributes ),
			$this->get_tabs_css( $attributes, 'Tablet' ),
			$this->get_tabs_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab-panel',
			$this->get_tabs_panel_css( $attributes ),
			$this->get_tabs_panel_css( $attributes, 'Tablet' ),
			$this->get_tabs_panel_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab-panel:hover',
			$this->get_tabs_panel_hover_css( $attributes ),
			$this->get_tabs_panel_hover_css( $attributes, 'Tablet' ),
			$this->get_tabs_panel_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab',
			$this->get_tabs_menu_content_css( $attributes ),
			$this->get_tabs_menu_content_css( $attributes, 'Tablet' ),
			$this->get_tabs_menu_content_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-block-tabs__tab--active',
			$this->get_tabs_menu_content_active_css( $attributes ),
			$this->get_tabs_menu_content_active_css( $attributes, 'Tablet' ),
			$this->get_tabs_menu_content_active_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-block-tabs__tab:hover',
			$this->get_tabs_menu_content_hover_css( $attributes ),
			$this->get_tabs_menu_content_hover_css( $attributes, 'Tablet' ),
			$this->get_tabs_menu_content_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab-menu-title',
			$this->get_tabs_title_css( $attributes ),
			$this->get_tabs_title_css( $attributes, 'Tablet' ),
			$this->get_tabs_title_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab--active .ablocks-block-tabs__tab-menu-title',
			$this->get_tabs_active_title_css( $attributes ),
			$this->get_tabs_active_title_css( $attributes, 'Tablet' ),
			$this->get_tabs_active_title_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab-menu-subtitle',
			$this->get_tabs_subtitle_css( $attributes ),
			$this->get_tabs_subtitle_css( $attributes, 'Tablet' ),
			$this->get_tabs_subtitle_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-block-tabs__tab--active .ablocks-block-tabs__tab-menu-subtitle',
			$this->get_tabs_active_subtitle_text_css( $attributes ),
			$this->get_tabs_active_subtitle_text_css( $attributes, 'Tablet' ),
			$this->get_tabs_active_subtitle_text_css( $attributes, 'Mobile' )
		);
		if ( isset( $attributes['showActiveSubTitle'] ) && $attributes['showActiveSubTitle'] === false ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-block-tabs__icon',
				$this->get_icon_position_css( $attributes ),
				$this->get_icon_position_css( $attributes, 'Tablet' ),
				$this->get_icon_position_css( $attributes, 'Mobile' )
			);
		} else {
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-block-tabs__tab--active .ablocks-block-tabs__icon',
				$this->get_icon_position_css( $attributes ),
				$this->get_icon_position_css( $attributes, 'Tablet' ),
				$this->get_icon_position_css( $attributes, 'Mobile' )
			);
		}
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab--active .ablocks-block-tabs__progressbar',
			$this->progress_bar_style_css( $attributes ),
			$this->progress_bar_style_css( $attributes, 'Tablet' ),
			$this->progress_bar_style_css( $attributes, 'Mobile' )
		);
		$tabs_element_icon_wrapper_styles = Icon::get_wrapper_css( $attributes );

			$spacing_value = [];

		switch ( $attributes['iconPosition'] ) {
			case 'left':
				$spacing_value = Range::get_css([
					'attributeValue' => $attributes['spacing'] ?? null,
					'attributeObjectKey' => 'value',
					'defaultValue' => 0,
					'isResponsive' => true,
					'hasUnit' => true,
					'property' => 'margin-right',
					'unitDefaultValue' => 'px',
				]);
				break;
			case 'right':
				$spacing_value = Range::get_css([
					'attributeValue' => $attributes['spacing'] ?? null,
					'attributeObjectKey' => 'value',
					'defaultValue' => 0,
					'isResponsive' => true,
					'hasUnit' => true,
					'property' => 'margin-left',
					'unitDefaultValue' => 'px',
				]);
				break;
			case 'bottom':
				$spacing_value = Range::get_css([
					'attributeValue' => $attributes['spacing'] ?? null,
					'attributeObjectKey' => 'value',
					'defaultValue' => 0,
					'isResponsive' => true,
					'hasUnit' => true,
					'property' => 'margin-top',
					'unitDefaultValue' => 'px',
				]);
				break;
			case 'top':
				$spacing_value = Range::get_css([
					'attributeValue' => $attributes['spacing'] ?? null,
					'attributeObjectKey' => 'value',
					'defaultValue' => 0,
					'isResponsive' => true,
					'hasUnit' => true,
					'property' => 'margin-bottom',
					'unitDefaultValue' => 'px',
				]);
				break;
		}//end switch

		// Merge $spacing_value into icon wrapper styles
		$tabs_element_icon_wrapper_styles = array_merge(
			Icon::get_wrapper_css( $attributes ),
			$spacing_value
		);

		// Generate tablet and mobile styles for the icon wrapper
		$tablet_styles = array_merge(
			Icon::get_wrapper_css( $attributes, 'Tablet' ),
			$spacing_value
		);
		$mobile_styles = array_merge(
			Icon::get_wrapper_css( $attributes, 'Mobile' ),
			$spacing_value
		);

		// Add icon wrapper styles to the CSS generator
		if ( ! empty( $tabs_element_icon_wrapper_styles ) || ! empty( $tablet_styles ) || ! empty( $mobile_styles ) ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-block-tabs__icon .ablocks-icon-wrap',
				$tabs_element_icon_wrapper_styles,
				$tablet_styles,
				$mobile_styles
			);
		}
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__icon .ablocks-icon-wrap:hover',
			Icon::get_wrapper_hover_css( $attributes ),
			Icon::get_wrapper_hover_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_hover_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__icon .ablocks-icon-wrap img.ablocks-image-icon',
			Icon::get_element_image_css( $attributes ),
			Icon::get_element_image_css( $attributes, 'Tablet' ),
			Icon::get_element_image_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__icon .ablocks-icon-wrap img.ablocks-image-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__icon .ablocks-icon-wrap svg.ablocks-svg-icon',
			Icon::get_element_css( $attributes ),
			Icon::get_element_css( $attributes, 'Tablet' ),
			Icon::get_element_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__icon .ablocks-icon-wrap svg.ablocks-svg-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-block-tabs__body',
			$this->get_tabs_content_css( $attributes ),
			$this->get_tabs_content_css( $attributes, 'Tablet' ),
			$this->get_tabs_content_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__body:hover',
			$this->get_tabs_content_hover_css( $attributes ),
			$this->get_tabs_content_hover_css( $attributes, 'Tablet' ),
			$this->get_tabs_content_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab-menu-content',
			$this->get_content_css( $attributes ),
			$this->get_content_css( $attributes, 'Tablet' ),
			$this->get_content_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab-panel',
			$this->get_tabs_width_css( $attributes ),
			$this->get_tabs_width_css( $attributes, 'Tablet' ),
			$this->get_tabs_width_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__body',
			$this->get_content_width_css( $attributes ),
			$this->get_content_width_css( $attributes, 'Tablet' ),
			$this->get_content_width_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs',
			$this->get_tabs_css( $attributes ),
			$this->get_tabs_css( $attributes, 'Tablet' ),
			$this->get_tabs_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab-panel',
			$this->get_tabs_panel_css( $attributes ),
			$this->get_tabs_panel_css( $attributes, 'Tablet' ),
			$this->get_tabs_panel_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab-panel:hover',
			$this->get_tabs_panel_hover_css( $attributes ),
			$this->get_tabs_panel_hover_css( $attributes, 'Tablet' ),
			$this->get_tabs_panel_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab',
			$this->get_tabs_menu_content_css( $attributes ),
			$this->get_tabs_menu_content_css( $attributes, 'Tablet' ),
			$this->get_tabs_menu_content_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-block-tabs__tab--active',
			$this->get_tabs_menu_content_active_css( $attributes ),
			$this->get_tabs_menu_content_active_css( $attributes, 'Tablet' ),
			$this->get_tabs_menu_content_active_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-block-tabs__tab:hover',
			$this->get_tabs_menu_content_hover_css( $attributes ),
			$this->get_tabs_menu_content_hover_css( $attributes, 'Tablet' ),
			$this->get_tabs_menu_content_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab-menu-title',
			$this->get_tabs_title_css( $attributes ),
			$this->get_tabs_title_css( $attributes, 'Tablet' ),
			$this->get_tabs_title_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab--active .ablocks-block-tabs__tab-menu-title',
			$this->get_tabs_active_title_css( $attributes ),
			$this->get_tabs_active_title_css( $attributes, 'Tablet' ),
			$this->get_tabs_active_title_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab-menu-subtitle',
			$this->get_tabs_subtitle_css( $attributes ),
			$this->get_tabs_subtitle_css( $attributes, 'Tablet' ),
			$this->get_tabs_subtitle_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-block-tabs__tab--active .ablocks-block-tabs__tab-menu-subtitle',
			$this->get_tabs_active_subtitle_text_css( $attributes ),
			$this->get_tabs_active_subtitle_text_css( $attributes, 'Tablet' ),
			$this->get_tabs_active_subtitle_text_css( $attributes, 'Mobile' )
		);
		if ( isset( $attributes['showActiveSubTitle'] ) && $attributes['showActiveSubTitle'] === false ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-block-tabs__icon',
				$this->get_icon_position_css( $attributes ),
				$this->get_icon_position_css( $attributes, 'Tablet' ),
				$this->get_icon_position_css( $attributes, 'Mobile' )
			);
		} else {
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-block-tabs__tab--active .ablocks-block-tabs__icon',
				$this->get_icon_position_css( $attributes ),
				$this->get_icon_position_css( $attributes, 'Tablet' ),
				$this->get_icon_position_css( $attributes, 'Mobile' )
			);
		}
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab--active .ablocks-block-tabs__progressbar',
			$this->progress_bar_style_css( $attributes ),
			$this->progress_bar_style_css( $attributes, 'Tablet' ),
			$this->progress_bar_style_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__icon .ablocks-icon-wrap',
			Icon::get_wrapper_css( $attributes ),
			Icon::get_wrapper_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__icon .ablocks-icon-wrap:hover',
			Icon::get_wrapper_hover_css( $attributes ),
			Icon::get_wrapper_hover_css( $attributes, 'Tablet' ),
			Icon::get_wrapper_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__icon .ablocks-icon-wrap',
			$this->get_icon_spacing_css( $attributes ),
			$this->get_icon_spacing_css( $attributes, 'Tablet' ),
			$this->get_icon_spacing_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__icon .ablocks-icon-wrap img.ablocks-image-icon',
			Icon::get_element_image_css( $attributes ),
			Icon::get_element_image_css( $attributes, 'Tablet' ),
			Icon::get_element_image_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__icon .ablocks-icon-wrap img.ablocks-image-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__icon .ablocks-icon-wrap svg.ablocks-svg-icon',
			Icon::get_element_css( $attributes ),
			Icon::get_element_css( $attributes, 'Tablet' ),
			Icon::get_element_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__icon .ablocks-icon-wrap svg.ablocks-svg-icon:hover',
			Icon::get_element_image_hover_css( $attributes ),
			Icon::get_element_image_hover_css( $attributes, 'Tablet' ),
			Icon::get_element_image_hover_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}}  .ablocks-block-tabs__body',
			$this->get_tabs_content_css( $attributes ),
			$this->get_tabs_content_css( $attributes, 'Tablet' ),
			$this->get_tabs_content_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__body:hover',
			$this->get_tabs_content_hover_css( $attributes ),
			$this->get_tabs_content_hover_css( $attributes, 'Tablet' ),
			$this->get_tabs_content_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab-menu-content',
			$this->get_content_css( $attributes ),
			$this->get_content_css( $attributes, 'Tablet' ),
			$this->get_content_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__tab-panel',
			$this->get_tabs_width_css( $attributes ),
			$this->get_tabs_width_css( $attributes, 'Tablet' ),
			$this->get_tabs_width_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-tabs__body',
			$this->get_content_width_css( $attributes ),
			$this->get_content_width_css( $attributes, 'Tablet' ),
			$this->get_content_width_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}

	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}


	public function get_tabs_css( $attributes, $device = '' ) {
		$tabs_css = [];
		$menuPosition = $attributes['tabsMenuPositioning'][ 'value' . $device ] ?? '';

		if ( $menuPosition === 'top' ) {
			$tabs_css['flex-direction'] = 'column';
		} elseif ( $menuPosition === 'bottom' ) {
			$tabs_css['flex-direction'] = 'column-reverse';
		} elseif ( $menuPosition === 'left' ) {
			$tabs_css['flex-direction'] = 'row';
		} elseif ( $menuPosition === 'right' ) {
			$tabs_css['flex-direction'] = 'row-reverse';
		}
		return $tabs_css;
	}

	public function get_tabs_panel_css( $attributes, $device = '' ) {
		$tabs_panel_css = [];

		$tabMenuAlignment = ! empty( $attributes['tabMenuAlignment'][ 'value' . $device ] ) ? $attributes['tabMenuAlignment'][ 'value' . $device ] : '';
		$tabsMenuPositioning = $this->get_responsive_attribute_value( $attributes['tabsMenuPositioning'], $device );
		$tabsMenuDirection = $this->get_responsive_attribute_value( $attributes['tabsMenuDirection'], $device );

		// Handle tabMenuAlign for the given device
		if ( $tabsMenuPositioning === 'top' || $tabsMenuPositioning === 'bottom' ) {
			if ( $tabsMenuDirection === 'row' ) {
				$tabs_panel_css['justify-content'] = $tabMenuAlignment;
			} elseif ( $tabsMenuDirection === 'column' ) {
				$tabs_panel_css['align-items'] = $tabMenuAlignment;
			}
			if ( $tabMenuAlignment && $attributes['tabsWidthType'][ 'value' . $device ] === 'auto' ) {
				if ( $tabMenuAlignment === 'flex-start' ) {
					$tabs_panel_css['margin-right'] = 'auto';
				} elseif ( $tabMenuAlignment === 'center' ) {
					$tabs_panel_css['margin-inline'] = 'auto';
				}if ( $tabMenuAlignment === 'flex-end' ) {
					$tabs_panel_css['margin-left'] = 'auto';
				}
			}
		}

		if ( $tabsMenuPositioning === 'left' || $tabsMenuPositioning === 'right' ) {
			$tabs_panel_css['flex-direction'] = 'column';
		} elseif ( $tabsMenuPositioning === 'top' || $tabsMenuPositioning === 'bottom' ) {
			$tabs_panel_css['flex-direction'] = $tabsMenuDirection;
			if ( ! empty( $attributes['tabWrap'][ 'value' . $device ] ) ) {
				$tabs_panel_css['flex-wrap'] = $attributes['tabWrap'][ 'value' . $device ];
			}
		}

		return array_merge(
			$tabs_panel_css,
			[ 'background' => Color::get_css( isset( $attributes['tabMenusBackgroundColor'] ) ? $attributes['tabMenusBackgroundColor'] : '' ) ],
			Dimensions::get_css( $attributes['tabMenusMargin'] ?? [], 'margin', $device ),
			Dimensions::get_css( $attributes['tabMenusPadding'] ?? [], 'padding', $device ),
			Border::get_css( $attributes['tabMenusBorder'] ?? [], '', $device ),
			Range::get_css([
				'attributeValue' => $attributes['tabsGap'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 10,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'gap',
				'device' => $device,
			]),
		);
	}

	public function get_tabs_panel_hover_css( $attributes, $device = '' ) {

		return array_merge(
			Border::get_hover_css( $attributes['tabMenusBorder'] ?? [], '', $device ),
		);
	}

	public function get_tabs_menu_content_css( $attributes, $device = '' ) {
		$css = [];
		$css['display'] = 'flex';

		$iconPosition = $this->get_responsive_attribute_value( $attributes['iconPosition'], $device );
		$tabsMenuPositioning = $this->get_responsive_attribute_value( $attributes['tabsMenuPositioning'], $device );
		$tabsContentWidthType = ! empty( $attributes['tabsContentWidthType'][ 'value' . $device ] ) ? $attributes['tabsContentWidthType'][ 'value' . $device ] : '';
		$tabsMenuDirection = ! empty( $attributes['tabsMenuDirection'][ 'value' . $device ] ) ? $attributes['tabsMenuDirection'][ 'value' . $device ] : '';

		if ( ( $tabsMenuPositioning === 'top' || $tabsMenuPositioning === 'bottom' ) && $tabsContentWidthType === '100%' ) {
			$css['width'] = '100%';
		}

		// Handling icon position
		if ( $iconPosition === 'top' ) {
			$css['flex-direction'] = 'column';
			if ( isset( $attributes['menuContentAlignment'][ 'value' . $device ] ) ) {
				$css['align-items'] = $attributes['menuContentAlignment'][ 'value' . $device ];
			}
		}
		if ( $iconPosition === 'bottom' ) {
			$css['flex-direction'] = 'column-reverse';
			if ( isset( $attributes['menuContentAlignment'][ 'value' . $device ] ) ) {
				$css['align-items'] = $attributes['menuContentAlignment'][ 'value' . $device ];
			}
		}
		if ( $iconPosition === 'left' ) {
			$css['flex-direction'] = 'row';
			$css['align-items'] = 'center';
			if ( isset( $attributes['menuContentAlignment'][ 'value' . $device ] ) ) {
				$css['justify-content'] = $attributes['menuContentAlignment'][ 'value' . $device ];
			}
		}
		if ( $iconPosition === 'right' ) {
			$css['flex-direction'] = 'row-reverse';
			$css['align-items'] = 'center';
			if ( isset( $attributes['menuContentAlignment'][ 'value' . $device ] ) ) {
				$css['justify-content'] = $attributes['menuContentAlignment'][ 'value' . $device ];
			}
		}

		// Merging with margin, padding, and border styles
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['tabBackgroundColor'] ) ? $attributes['tabBackgroundColor'] : '' ) ],
			$css,
			Dimensions::get_css( $attributes['menuContentPadding'] ?? [], 'padding', $device ),
			Border::get_css( $attributes['menuContentBorder'] ?? [], '', $device ),
			BoxShadow::get_css( $attributes['boxShadow'], '', $device ),
		);
	}


	public function get_tabs_menu_content_active_css( $attributes, $device = '' ) {
		$tabs_menu_content_active_css = [];

		if ( isset( $attributes['activeColorOptions'] ) && $attributes['activeColorOptions'] === 'background' ) {
			if ( isset( $attributes['tabActiveBackgroundColor'] ) ) {
				$tabs_menu_content_active_css['background-color'] = Color::get_css( isset( $attributes['tabActiveBackgroundColor'] ) ? $attributes['tabActiveBackgroundColor'] : '' ) . ' !important';
			}
		} elseif ( isset( $attributes['activeColorOptions'] ) && $attributes['activeColorOptions'] === 'border' ) {
			if ( isset( $attributes['activeBorderColor'] ) ) {
				$tabs_menu_content_active_css['border-color'] = Color::get_css( isset( $attributes['activeBorderColor'] ) ? $attributes['activeBorderColor'] : '' ) . ' !important';
			}
		}

		return $tabs_menu_content_active_css;
	}


	public function get_tabs_menu_content_hover_css( $attributes, $device = '' ) {

		return array_merge(
			Border::get_hover_css( $attributes['menuContentBorder'] ?? [], '', $device ),
			BoxShadow::get_hover_css( $attributes['boxShadow'], '', $device )
		);
	}
	public function get_tabs_title_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['titleTypographyGlobal'] ) ? $attributes['titleTypographyGlobal'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['titleTextColor'] ) ? $attributes['titleTextColor'] : '' ) ],
		Typography::get_css( $attributes['titleTypography'] ?? [], '', $device, $typographyValueGlobal ));
	}

	public function get_tabs_active_title_css( $attributes, $device = '' ) {

		return [ 'color' => Color::get_css( isset( $attributes['titleTextActiveColor'] ) ? $attributes['titleTextActiveColor'] : '' ) ];
	}

	public function get_tabs_subtitle_css( $attributes, $device = '' ) {
		$tabsSubtitleCSS = [];
		if ( isset( $attributes['showActiveSubTitle'] ) && $attributes['showActiveSubTitle'] === true ) {
			$tabsSubtitleCSS['display'] = 'none';
		}
		// Get typography styles
		$typographyValueGlobal = ! empty( $attributes['subTitleTypographyGlobal'] ) ? $attributes['subTitleTypographyGlobal'] : [];
		$typographyStyles = Typography::get_css( $attributes['subTitleTypography'] ?? [], '', $device, $typographyValueGlobal );
		$tabsSubtitleCSS = array_merge( $tabsSubtitleCSS, $typographyStyles );

		// Determine width based on menu position
		$tabsContentWidthType = $this->get_responsive_attribute_value( $attributes['tabsContentWidthType'], $device );
		$position = $this->get_responsive_attribute_value( $attributes['tabsMenuPositioning'], $device );
		if ( $device === 'Mobile' ) {
			$tabsSubtitleCSS['width'] = '100%';
		} elseif ( ( $position === 'top' || $position === 'bottom' ) && $tabsContentWidthType === 'auto' ) {
			$tabsSubtitleCSS['max-width'] = '160px';
		}

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['subTitleTextColor'] ) ? $attributes['subTitleTextColor'] : '' ) ],
			$tabsSubtitleCSS
		);
	}


	public function get_tabs_active_subtitle_text_css( $attributes, $device = '' ) {
		$css = [];
		if ( isset( $attributes['showActiveSubTitle'] ) && $attributes['showActiveSubTitle'] === true ) {
			$css['display'] = 'block';
		}
		if ( isset( $attributes['subTitleTextActiveColor'] ) ) {
			$css['color'] = Color::get_css( isset( $attributes['subTitleTextActiveColor'] ) ? $attributes['subTitleTextActiveColor'] : '' ) . '!important';
		}
		return $css;
	}
	public function get_icon_position_css( $attributes, $device = '' ) {
		return array_merge(
			Dimensions::get_css( $attributes['iconPositionMargin'] ?? [], 'margin', $device )
		);
	}
	public function progress_bar_style_css( $attributes, $device = '' ) {
		return [ 'background' => Color::get_css( isset( $attributes['progressBarColor'] ) ? $attributes['progressBarColor'] : '' ) ];
	}

	public function get_tabs_icon_css( $attributes, $device = '' ) {
		$css = [];
		$iconType = $attributes['iconType'] ?? 'default';
		$iconShape = $attributes['iconShape'] ?? 'square';
		$iconColor = $attributes['iconColor'] ?? '#69727d';
		$iconBackground = $attributes['iconBackground'] ?? 'transparent';
		$size = $attributes['size'][ 'value' . $device ] ?? null;
		$spacing = $attributes['spacing'][ 'value' . $device ] ?? null;
		$iconPosition = $attributes['iconPosition'] ?? '';

		// Determine icon view CSS based on type and shape
		$iconViewCSS = [];

		if ( $iconType !== 'default' ) {
			if ( $iconType === 'stacked' ) {
				$iconViewCSS['background'] = $iconBackground;
				$iconViewCSS['padding'] = '.5em';

				if ( $iconShape === 'circle' ) {
					$iconViewCSS['border-radius'] = '50px';
				}
			} elseif ( $iconType === 'framed' ) {
				$iconViewCSS['background'] = 'transparent';
				$iconViewCSS['padding'] = '.5em';
				$iconViewCSS['border'] = '2px solid ' . ( $iconColor ? $iconColor : '#69727d' );

				if ( $iconShape === 'circle' ) {
					$iconViewCSS['border-radius'] = '50px';
				}
			}
		}

		// Apply size CSS if available
		if ( $size ) {
			$css['width'] = $size . 'px !important';
			$css['height'] = $size . 'px !important';
		}
		// Apply spacing based on position
		if ( $spacing ) {
			switch ( $iconPosition ) {
				case 'left':
					$css['margin-right'] = $spacing . 'px';
					break;
				case 'right':
					$css['margin-left'] = $spacing . 'px';
					break;
				case 'bottom':
					$css['margin-top'] = $spacing . 'px';
					break;
				case 'top':
					$css['margin-bottom'] = $spacing . 'px';
					break;
			}
		}
		// Merge the icon view CSS with the main CSS
		return array_merge(
			[ 'fill' => Color::get_css( isset( $attributes['iconColor'] ) ? $attributes['iconColor'] : '' ) ],
			$css,
		$iconViewCSS );
	}

	public function get_tabs_active_icon_css( $attributes ) {
		$css = [];

		// Check for active icon color
		if ( isset( $attributes['iconActiveColor'] ) ) {
			$css['fill'] = Color::get_css( isset( $attributes['iconActiveColor'] ) ? $attributes['iconActiveColor'] : '' );
		}

		// Check for icon type and active background color
		if ( isset( $attributes['iconType'] ) && $attributes['iconType'] !== 'default' && $attributes['iconType'] !== 'framed' ) {
				$css['background-color'] = Color::get_css( isset( $attributes['iconActiveBackground'] ) ? $attributes['iconActiveBackground'] : '' );
		}

		return $css;
	}

	public function get_tabs_content_css( $attributes, $device = '' ) {
		$tabs_content_css = [];
		// Merge margin, padding, and border CSS
		$tabs_content_css = array_merge(
			[ 'background' => Color::get_css( isset( $attributes['contentBackgroundColor'] ) ? $attributes['contentBackgroundColor'] : '' ) ],
			$tabs_content_css,
			Dimensions::get_css( $attributes['contentMargin'] ?? [], 'margin', $device ),
			Dimensions::get_css( $attributes['contentPadding'] ?? [], 'padding', $device ),
			Border::get_css( $attributes['contentBorder'] ?? [], '', $device )
		);
		// Apply max-width based on device and tabsMenuPosition
		if ( $device === 'Mobile' ) {
			$tabs_content_css['max-width'] = '100%';
		} else {
			$menuPosition = $attributes['tabsMenuPositioning'][ 'value' . $device ] ?? '';
			if ( $menuPosition === 'top' || $menuPosition === 'bottom' ) {
				$tabs_content_css['max-width'] = '100% !important';
			} elseif ( $menuPosition === 'left' || $menuPosition === 'right' ) {
				$tabs_content_css['max-width'] = '70%';
				$tabs_content_css['flex-grow'] = 3;
			}
		}

		return $tabs_content_css;
	}


	public function get_tabs_content_hover_css( $attributes, $device = '' ) {
		return array_merge(
			Border::get_hover_css( $attributes['contentBorder'] ?? [], '', $device ),
		);
	}

	public function get_content_css( $attributes, $device = '' ) {
		$css = [];
		if ( isset( $attributes['menuContentAlignment'][ 'value' . $device ] ) ) {
			$css['text-align'] = $attributes['menuContentAlignment'][ 'value' . $device ];
		}
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['contentGap'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 10,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'gap',
				'device' => $device,
			]),
			$css,
		);
	}
	public function get_tabs_width_css( $attributes, $device = '' ) {
		$css = [];

		$tabsWidthType = $this->get_responsive_attribute_value( $attributes['tabsWidthType'], $device );
		$tabsMenuPositioning = $this->get_responsive_attribute_value( $attributes['tabsMenuPositioning'], $device );

		if (
			$tabsMenuPositioning === 'top' ||
			$tabsMenuPositioning === 'bottom'
		) {
			$css['width'] = $tabsWidthType;
			return $css;
		}

		if (
			$tabsMenuPositioning === 'left' ||
			$tabsMenuPositioning === 'right'
		) {
			return array_merge(
				Range::get_css([
					'attributeValue' => $attributes['tabsWidth'],
					'attribute_object_key' => 'value',
					'isResponsive' => true,
					'defaultValue' => 30,
					'hasUnit' => false,
					'unitDefaultValue' => '%',
					'property' => 'width',
					'device' => $device,
				]),
			);
		}
	}

	public function get_content_width_css( $attributes, $device = '' ) {
		$css = [];
		if (
			isset( $attributes['tabsMenuPositioning'][ 'value' . $device ] ) &&
			(
				$attributes['tabsMenuPositioning'][ 'value' . $device ] === 'top' ||
				$attributes['tabsMenuPositioning'][ 'value' . $device ] === 'bottom'
			)
		) {
			$css['max-width'] = '100% !important';
		}
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['contentWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 70,
				'hasUnit' => false,
				'unitDefaultValue' => '%',
				'property' => 'max-width',
				'device' => $device,
			]),
			$css,
		);
	}
	public function get_icon_spacing_css( $attributes, $device = '' ) {
		$spacing_css = [];

		if ( isset( $attributes['iconPosition'][ 'value' . $device ] ) ) {
			$position = $attributes['iconPosition'][ 'value' . $device ];

			if ( $position === 'left' ) {
				$spacing_css = Range::get_css([
					'attributeValue'     => $attributes['spacing'] ?? null,
					'attributeObjectKey' => 'value',
					'defaultValue'       => 0,
					'isResponsive'       => true,
					'hasUnit'            => true,
					'property'           => 'margin-right',
					'unitDefaultValue'   => 'px',
				]);
			} elseif ( $position === 'right' ) {
				$spacing_css = Range::get_css([
					'attributeValue'     => $attributes['spacing'] ?? null,
					'attributeObjectKey' => 'value',
					'defaultValue'       => 0,
					'isResponsive'       => true,
					'hasUnit'            => true,
					'property'           => 'margin-left',
					'unitDefaultValue'   => 'px',
				]);
			} elseif ( $position === 'bottom' ) {
				$spacing_css = Range::get_css([
					'attributeValue'     => $attributes['spacing'] ?? null,
					'attributeObjectKey' => 'value',
					'defaultValue'       => 0,
					'isResponsive'       => true,
					'hasUnit'            => true,
					'property'           => 'margin-top',
					'unitDefaultValue'   => 'px',
				]);
			} elseif ( $position === 'top' ) {
				$spacing_css = Range::get_css([
					'attributeValue'     => $attributes['spacing'] ?? null,
					'attributeObjectKey' => 'value',
					'defaultValue'       => 0,
					'isResponsive'       => true,
					'hasUnit'            => true,
					'property'           => 'margin-bottom',
					'unitDefaultValue'   => 'px',
				]);
			}//end if
		}//end if

		return $spacing_css;
	}

	// don't delete this function, it's used in the render method to get responsive attribute values
	public function get_responsive_attribute_value( $attribute, $device ) {

		if ( $device === 'Mobile' ) {
			return ! empty( $attribute['valueMobile'] ) ? $attribute['valueMobile']
				: ( ! empty( $attribute['valueTablet'] ) ? $attribute['valueTablet']
				: ( $attribute['value'] ?? '' ) );
		}

		if ( $device === 'Tablet' ) {
			return ! empty( $attribute['valueTablet'] ) ? $attribute['valueTablet']
				: ( $attribute['value'] ?? '' );
		}

		// Default (Desktop or others)
		return $attribute['value'] ?? '';
	}



}
