<?php
namespace ABlocks\Blocks\MenuChildSub;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Typography;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Range;
use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Color;
class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'menu';
	protected $block_name = 'menu-child-sub';

	public function build_css( $attributes ) {
		// Generate CSS
		$css_generator = new CssGenerator( $attributes );
		// Wrapper CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--menu-child-sub',
			$this->get_wrapper_css( $attributes, '' ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--menu-child-sub:hover',
			$this->get_wrapper_hover_css( $attributes, '' ),
			$this->get_wrapper_hover_css( $attributes, 'Tablet' ),
			$this->get_wrapper_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--menu-child-sub .ablocks-block--menu-item',
			$this->get_menu_item_css( $attributes, '' ),
			$this->get_menu_item_css( $attributes, 'Tablet' ),
			$this->get_menu_item_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--menu-child-sub .ablocks-block--menu-item:hover',
			$this->get_menu_item_hover_css( $attributes, '' ),
			$this->get_menu_item_hover_css( $attributes, 'Tablet' ),
			$this->get_menu_item_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} > .ablocks-menu-item > .ablocks-menu-item__link',
			$this->get_menu_item_link_css( $attributes, '' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} > .ablocks-menu-item:hover > .ablocks-menu-item__link',
			$this->get_menu_item_link_hover_css( $attributes, '' ),
		);
		$css_generator->add_class_styles( '{{WRAPPER}} > .ablocks-menu-item .ablocks-menu-item__dropdown-icon svg', $this->get_menu_item_dropdown_icon_css( $attributes ) );
		$css_generator->add_class_styles( '{{WRAPPER}} > .ablocks-menu-item:hover .ablocks-menu-item__dropdown-icon svg', $this->get_menu_item_dropdown_icon_hover_css( $attributes ) );
		return $css_generator->generate_css();
	}

	private function get_wrapper_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['background'] ) ? $attributes['background'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['width'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			]),
			BoxShadow::get_css( ! empty( $attributes['boxShadow'] ) ? $attributes['boxShadow'] : '', $device ),
			Dimensions::get_css( $attributes['padding'], 'padding', $device ),
			Border::get_css( $attributes['border'], '', $device ),
		);
	}
	private function get_wrapper_hover_css( $attributes, $device = '' ) {
		return array_merge(
			Border::get_hover_css( $attributes['border'], '', $device ),
			BoxShadow::get_hover_css( $attributes['boxShadow'], '', $device )
		);

	}
	private function get_menu_item_css( $attributes, $device = '' ) {
		$typographyglobal = isset( $attributes['menuItemTypographyGlobal'] ) ? $attributes['menuItemTypographyGlobal'] : '';
		$css = array_merge(
			[ 'color' => Color::get_css( isset( $attributes['menuItemTextColor'] ) ? $attributes['menuItemTextColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['menuItemBackground'] ) ? $attributes['menuItemBackground'] : '' ) ],
			Border::get_css( isset( $attributes['menuItemBorder'] ) ? $attributes['menuItemBorder'] : [], '', $device ),
			Typography::get_css( isset( $attributes['menuItemTypography'] ) ? $attributes['menuItemTypography'] : [], '', $device, $typographyglobal ),
			Dimensions::get_css( isset( $attributes['menuItemPadding'] ) ? $attributes['menuItemPadding'] : [], 'padding', $device ),
			Dimensions::get_css( isset( $attributes['menuItemMargin'] ) ? $attributes['menuItemMargin'] : [], 'margin', $device )
		);

		if ( isset( $attributes[ 'menuItemDirection' . $device ] ) && ! empty( $attributes[ 'menuItemDirection' . $device ] ) ) {
			$css['flex-direction'] = $attributes[ 'menuItemDirection' . $device ];
		}

		// Justify content
		if ( isset( $attributes['menuItemJustification'][ 'value' . $device ] ) && ! empty( $attributes['menuItemJustification'][ 'value' . $device ] ) ) {
			$css['justify-content'] = $attributes['menuItemJustification'][ 'value' . $device ];
		}

		// Align items
		if ( isset( $attributes[ 'menuItemAlign' . $device ] ) && ! empty( $attributes[ 'menuItemAlign' . $device ] ) ) {
			$css['align-items'] = $attributes[ 'menuItemAlign' . $device ];
		}
		if ( ! empty( $attributes['menuItemTransition'] ) ) {
			$css['transition-duration'] = $attributes['menuItemTransition'] . 's';
		}
		return $css;
	}
	private function get_menu_item_link_css( $attributes ) {
		return [ 'color' => Color::get_css( isset( $attributes['menuItemTextColor'] ) ? $attributes['menuItemTextColor'] : '' ) . '!important' ];
	}
	private function get_menu_item_hover_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['menuItemBackgroundH'] ) ? $attributes['menuItemBackgroundH'] : '' ) ],
			Border::get_hover_css( isset( $attributes['menuItemBorder'] ) ? $attributes['menuItemBorder'] : [], '', $device )
		);
	}
	private function get_menu_item_link_hover_css( $attributes ) {
		return [ 'color' => Color::get_css( isset( $attributes['menuItemTextColorH'] ) ? $attributes['menuItemTextColorH'] : '' ) . '!important' ];
	}
	private function get_menu_item_dropdown_icon_css( $attributes ) {

		return [ 'fill' => Color::get_css( isset( $attributes['menuItemTextColor'] ) ? $attributes['menuItemTextColor'] : '' ) ];
	}
	private function get_menu_item_dropdown_icon_hover_css( $attributes ) {
		return [ 'fill' => Color::get_css( isset( $attributes['menuItemTextColorH'] ) ? $attributes['menuItemTextColorH'] : '' ) ];
	}
	private function get_hamburger_menu_css( $attributes ) {
			$css = [];
		if ( isset( $attributes['hamburgerAlignment'] ) ) {
			$css['justify-content'] = $attributes['hamburgerAlignment'];
		}
		return $css;
	}
}
