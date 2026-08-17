<?php
namespace ABlocks\Blocks\FilterableCards;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\BoxShadow;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Color;


class Block extends BlockBaseAbstract {
	protected $block_name = 'filterable-cards';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards_filter , {{WRAPPER}} .filterable-cards-filter-wrap',
			$this->get_filter_wrap_CSS( $attributes ),
			$this->get_filter_wrap_CSS( $attributes, 'Tablet' ),
			$this->get_filter_wrap_CSS( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards_filter .filterable-filter-button',
			$this->get_filter_CSS( $attributes ),
			$this->get_filter_CSS( $attributes, 'Tablet' ),
			$this->get_filter_CSS( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards_filter .filterable-filter-button:hover',
			$this->get_filter_hover_CSS( $attributes ),
			$this->get_filter_hover_CSS( $attributes, 'Tablet' ),
			$this->get_filter_hover_CSS( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards-filter-wrap .filterable-cards_filter .filterable-search-toggle-btn , {{WRAPPER}} .filterable-cards-filter-wrap .filterable-searchInput',
			$this->get_search_menu_CSS( $attributes ),
			$this->get_search_menu_CSS( $attributes, 'Tablet' ),
			$this->get_search_menu_CSS( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards_filter .filterable-search-toggle-btn:hover , {{WRAPPER}} .filterable-cards-filter-wrap .filterable-searchInput:hover',
			$this->get_search_menu_hover_CSS( $attributes ),
			$this->get_search_menu_hover_CSS( $attributes, 'Tablet' ),
			$this->get_search_menu_hover_CSS( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards-filter-wrap .filterable-searchInput::placeholder ',
			$this->get_search_input_placeholder_CSS( $attributes ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards_filter .filterable-filter-button.filterable-filter-button-active',
			$this->get_Filter_active_class_CSS( $attributes ),
			$this->get_Filter_active_class_CSS( $attributes, 'Tablet' ),
			$this->get_Filter_active_class_CSS( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards_filter .filterable-filter-button.filterable-filter-button-active:hover',
			$this->get_Filter_active_class_hover_CSS( $attributes ),
			$this->get_Filter_active_class_hover_CSS( $attributes, 'Tablet' ),
			$this->get_Filter_active_class_hover_CSS( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards-wrap',
			$this->filterable_cards_WrapCSS( $attributes ),
			$this->filterable_cards_WrapCSS( $attributes, 'Tablet' ),
			$this->filterable_cards_WrapCSS( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards-wrap > .ablocks-block--filterable-cards-item',
			$this->filterable_cards_items( $attributes ),
			$this->filterable_cards_items( $attributes, 'Tablet' ),
			$this->filterable_cards_items( $attributes, 'Mobile' ),
		);
		// button style
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards-load-more-wrapper',
			$this->filterable_Cards_More_Wrapper( $attributes ),
			$this->filterable_Cards_More_Wrapper( $attributes, 'Tablet' ),
			$this->filterable_Cards_More_Wrapper( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards-showMore-button',
			$this->filterable_loadMore_button( $attributes ),
			$this->filterable_loadMore_button( $attributes, 'Tablet' ),
			$this->filterable_loadMore_button( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards-showMore-button:hover',
			$this->filterable_loadMore_button_hover( $attributes ),
			$this->filterable_loadMore_button_hover( $attributes, 'Tablet' ),
			$this->filterable_loadMore_button_hover( $attributes, 'Mobile' )
		);
		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards_filter , {{WRAPPER}} .filterable-cards-filter-wrap',
			$this->get_filter_wrap_CSS( $attributes ),
			$this->get_filter_wrap_CSS( $attributes, 'Tablet' ),
			$this->get_filter_wrap_CSS( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards_filter .filterable-filter-button',
			$this->get_filter_CSS( $attributes ),
			$this->get_filter_CSS( $attributes, 'Tablet' ),
			$this->get_filter_CSS( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards_filter .filterable-filter-button:hover',
			$this->get_filter_hover_CSS( $attributes ),
			$this->get_filter_hover_CSS( $attributes, 'Tablet' ),
			$this->get_filter_hover_CSS( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards-filter-wrap .filterable-cards_filter .filterable-search-toggle-btn , {{WRAPPER}} .filterable-cards-filter-wrap .filterable-searchInput',
			$this->get_search_menu_CSS( $attributes ),
			$this->get_search_menu_CSS( $attributes, 'Tablet' ),
			$this->get_search_menu_CSS( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards_filter .filterable-search-toggle-btn:hover , {{WRAPPER}} .filterable-cards-filter-wrap .filterable-searchInput:hover',
			$this->get_search_menu_hover_CSS( $attributes ),
			$this->get_search_menu_hover_CSS( $attributes, 'Tablet' ),
			$this->get_search_menu_hover_CSS( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards-filter-wrap .filterable-searchInput::placeholder ',
			$this->get_search_input_placeholder_CSS( $attributes ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards_filter .filterable-filter-button.filterable-filter-button-active',
			$this->get_Filter_active_class_CSS( $attributes ),
			$this->get_Filter_active_class_CSS( $attributes, 'Tablet' ),
			$this->get_Filter_active_class_CSS( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards_filter .filterable-filter-button.filterable-filter-button-active:hover',
			$this->get_Filter_active_class_hover_CSS( $attributes ),
			$this->get_Filter_active_class_hover_CSS( $attributes, 'Tablet' ),
			$this->get_Filter_active_class_hover_CSS( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards-wrap',
			$this->filterable_cards_WrapCSS( $attributes ),
			$this->filterable_cards_WrapCSS( $attributes, 'Tablet' ),
			$this->filterable_cards_WrapCSS( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards-wrap > .ablocks-block--filterable-cards-item',
			$this->filterable_cards_items( $attributes ),
			$this->filterable_cards_items( $attributes, 'Tablet' ),
			$this->filterable_cards_items( $attributes, 'Mobile' ),
		);
		// button style
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards-load-more-wrapper',
			$this->filterable_Cards_More_Wrapper( $attributes ),
			$this->filterable_Cards_More_Wrapper( $attributes, 'Tablet' ),
			$this->filterable_Cards_More_Wrapper( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards-showMore-button',
			$this->filterable_loadMore_button( $attributes ),
			$this->filterable_loadMore_button( $attributes, 'Tablet' ),
			$this->filterable_loadMore_button( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .filterable-cards-showMore-button:hover',
			$this->filterable_loadMore_button_hover( $attributes ),
			$this->filterable_loadMore_button_hover( $attributes, 'Tablet' ),
			$this->filterable_loadMore_button_hover( $attributes, 'Mobile' )
		);
		return $css_generator->generate_css();
	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}
	private function get_filter_wrap_CSS( $attributes, $device = '' ) {
		$css = [];
		if ( ! empty( $horizontal_alignment ) ) {
			$css['justify-content'] = $horizontal_alignment;
		}
		$alignment_css = isset( $attributes['filterAlignment'] ) ? Alignment::get_css( $attributes['filterAlignment'], 'justify-content', $device )
		: [];
		$css = array_merge( $css, $alignment_css );
		return array_merge(
			$css,
			Range::get_css([
				'attributeValue' => $attributes['filterButtonGap'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => false,
				'defaultValue' => 8,
				'unitDefaultValue' => 'px',
				'property' => 'gap',
				'device' => $device,
			])
		);
	}
	private function get_filter_CSS( $attributes, $device = '' ) {
		$filter_border_css = ! empty( $attributes['filterButtonBorder'] )
			? Border::get_css( $attributes['filterButtonBorder'], '', $device )
			: array();
		$typographyGlobal = ( isset( $attributes['filterButtonTypographyGlobal'] ) ? $attributes['filterButtonTypographyGlobal'] : '' );
		$css = array_merge(
			[ 'color' => Color::get_css( isset( $attributes['filterButtonColor'] ) ? $attributes['filterButtonColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['filterButtonBackground'] ) ? $attributes['filterButtonBackground'] : '' ) ],
			$filter_border_css,
			Typography::get_css( isset( $attributes['filterButtonTypography'] ) ? $attributes['filterButtonTypography'] : [], '', $device, $typographyGlobal ),
			Dimensions::get_css( isset( $attributes['filterButtonPadding'] ) ? $attributes['filterButtonPadding'] : [], 'padding', $device ),
			Dimensions::get_css( isset( $attributes['filterButtonMargin'] ) ? $attributes['filterButtonMargin'] : [], 'margin', $device ),
		);
		return $css;
	}
	private function get_filter_hover_CSS( $attributes, $device = '' ) {
		$css = array_merge(
			[ 'color' => Color::get_css( isset( $attributes['filterButtonColorH'] ) ? $attributes['filterButtonColorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['filterButtonBackgroundH'] ) ? $attributes['filterButtonBackgroundH'] : '' ) ],
			Border::get_hover_css( isset( $attributes['filterButtonBorder'] ) ? $attributes['filterButtonBorder'] : [], $device )
		);
		if ( ! empty( $attributes['filterButtonTransition'] ) ) {
			$css['transition'] = $attributes['filterButtonTransition'];
		}

		return $css;
	}
	private function get_search_menu_CSS( $attributes, $device = '' ) {
		$searchMenu_menu_border_css = ! empty( $attributes['searchMenuBorder'] )
			? Border::get_css( $attributes['searchMenuBorder'], '', $device )
			: array();
		$css = array_merge(
			[ 'color' => Color::get_css( isset( $attributes['searchMenuColor'] ) ? $attributes['searchMenuColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['searchMenuBackground'] ) ? $attributes['searchMenuBackground'] : '' ) ],
			$searchMenu_menu_border_css,
			Dimensions::get_css( isset( $attributes['searchMenuPadding'] ) ? $attributes['searchMenuPadding'] : [], 'padding', $device ),
			Dimensions::get_css( isset( $attributes['searchMenuMargin'] ) ? $attributes['searchMenuMargin'] : [], 'margin', $device )
		);
		return $css;
	}

	private function get_search_menu_hover_CSS( $attributes, $device = '' ) {
		$css = array_merge(
			[ 'color' => Color::get_css( isset( $attributes['searchMenuColorH'] ) ? $attributes['searchMenuColorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['searchMenuBackgroundH'] ) ? $attributes['searchMenuBackgroundH'] : '' ) ],
			Border::get_hover_css( isset( $attributes['searchMenuBorder'] ) ? $attributes['searchMenuBorder'] : [], $device )
		);
		if ( ! empty( $attributes['searchMenuTransition'] ) ) {
			$css['transition'] = $attributes['searchMenuTransition'];
		}

		return $css;
	}
	private function get_search_input_placeholder_CSS( $attributes ) {
		return [ 'color' => Color::get_css( isset( $attributes['searchMenuColor'] ) ? $attributes['searchMenuColor'] : '' ) ];
	}
	private function get_filter_active_class_CSS( $attributes, $device = '' ) {
		$css = array_merge(
			[ 'color' => Color::get_css( isset( $attributes['activeClassColor'] ) ? $attributes['activeClassColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['activeClassBackground'] ) ? $attributes['activeClassBackground'] : '' ) ],
			Border::get_css( isset( $attributes['activeClassBorder'] ) ? $attributes['activeClassBorder'] : [], $device )
		);
		return $css;
	}
	private function get_Filter_active_class_hover_CSS( $attributes, $device = '' ) {
		$hover_border = Border::get_hover_css( isset( $attributes['activeClassBorder'] ) ? $attributes['activeClassBorder'] : [], $device );
		return $hover_border;
	}
	private function filterable_cards_wrapCSS( $attributes, $device = '' ) {
		$css = [];
		if ( $attributes['gridStyle'] === 'grid' ) {
			$css['display'] = 'grid';
			if ( $device === 'Tablet' ) {
				$css['grid-template-columns'] = 'repeat(2, 1fr)';
			} elseif ( $device === 'Mobile' ) {
				$css['grid-template-columns'] = 'repeat(1, 1fr)';
			} elseif ( ! empty( $attributes['gridColumns'] ) ) {
				$css['grid-template-columns'] = 'repeat(' . $attributes['gridColumns'] . ', 1fr)';
			}
		}
		if ( $attributes['gridStyle'] === 'masonry' ) {
			if ( $device === 'Tablet' ) {
				$css['column-count'] = '2';
			} elseif ( $device === 'Mobile' ) {
				$css['column-count'] = '1';
			} elseif ( ! empty( $attributes['gridColumns'] ) ) {
				$css['column-count'] = $attributes['gridColumns'];
			}
		}

		return array_merge(
			$css,
			Range::get_css([
				'attributeValue' => $attributes['itemGap'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => false,
				'unitDefaultValue' => 'px',
				'defaultValue' => 10,
				'property' => 'gap',
				'device' => $device,
			])
		);
	}

	private function filterable_cards_items( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['cardHeight'] ) && $attributes['gridStyle'] === 'grid' ) {
			$css['height'] = $attributes['cardHeight'] . 'px';
		}
		if ( $attributes['gridStyle'] === 'masonry' ) {
			$css['break-inside'] = 'avoid';
			$css['width'] = '100%';
			$css = array_merge($css, Range::get_css([
				'attributeValue' => $attributes['itemGap'] ?? null,
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => false,
				'unitDefaultValue' => 'px',
				'defaultValue' => 10,
				'property' => 'margin-bottom',
				'device' => $device,
			]));
		}
		return $css;
	}
	private function filterable_Cards_More_Wrapper( $attributes, $device = '' ) {
		$css = [];
		if ( isset( $attributes['moreButtonAlignment'] ) ) {
			$css['justify-content'] = $attributes['moreButtonAlignment'];
		}
		$css = array_merge($css, Range::get_css([
			'attributeValue' => $attributes['loadMoreButtonGap'] ?? null,
			'attribute_object_key' => 'value',
			'isResponsive' => true,
			'hasUnit' => false,
			'unitDefaultValue' => 'px',
			'defaultValue' => '',
			'property' => 'margin-top',
			'device' => $device,
		]));
		return $css;
	}
	private function filterable_loadMore_button( $attributes, $device = '' ) {
		$typographyGlobal = ( isset( $attributes['moreButtonTypographyGlobal'] ) ? $attributes['moreButtonTypographyGlobal'] : '' );
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['loadMoreButtonTextColor'] ) ? $attributes['loadMoreButtonTextColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['loadMoreButtonBackground'] ) ? $attributes['loadMoreButtonBackground'] : '' ) ],
			Border::get_css( $attributes['moreButtonBorder'], '', $device ),
			BoxShadow::get_css( $attributes['moreButtonboxShadow'], '', $device ),
			Typography::get_css( $attributes['moreButtonTypography'], '', $device, $typographyGlobal ),
			Dimensions::get_css( $attributes['moreButtonPadding'], 'padding', $device ),
		);
	}
	private function filterable_loadMore_button_hover( $attributes, $device = '' ) {
		$css = [];
		if ( ! empty( $attributes['loadMoreButtonTransition'] ) ) {
			$css['transition-duration'] = $attributes['loadMoreButtonTransition'] . 's';
		}
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['loadMoreButtonTextColorH'] ) ? $attributes['loadMoreButtonTextColorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['loadMoreButtonBackgroundH'] ) ? $attributes['loadMoreButtonBackgroundH'] : '' ) ],
			$css,
			Border::get_hover_css( $attributes['moreButtonBorder'], '', $device ),
			BoxShadow::get_hover_css( $attributes['moreButtonboxShadow'], '', $device )
		);
	}
}
