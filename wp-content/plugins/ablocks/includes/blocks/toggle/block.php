<?php
namespace ABlocks\Blocks\Toggle;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Range;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Controls\Color;


class Block extends BlockBaseAbstract {
	protected $block_name = 'toggle';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-toggle__topbar',
			$this->get_toggle_bar_css( $attributes ),
			$this->get_toggle_bar_css( $attributes, 'Tablet' ),
			$this->get_toggle_bar_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-toggle__topbar:hover',
			$this->get_toggle_bar_hover_css( $attributes ),
			$this->get_toggle_bar_hover_css( $attributes, 'Tablet' ),
			$this->get_toggle_bar_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-toggle__topbar-wrapper',
			$this->get_toggle_bar_wrapper_css( $attributes ),
			$this->get_toggle_bar_wrapper_css( $attributes, 'Tablet' ),
			$this->get_toggle_bar_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-toggle__label',
			$this->get_toggle_label_css( $attributes ),
			$this->get_toggle_label_css( $attributes, 'Tablet' ),
			$this->get_toggle_label_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-toggle__label--active',
			$this->get_toggle_label_active_css( $attributes )
		);

		if ( ! empty( $attributes['toggleNormalBgColor'] ) ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-toggle__slider',
				[ 'background-color' => $attributes['toggleNormalBgColor'] ]
			);
		}

		if ( ! empty( $attributes['toggleNormalColor'] ) ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-toggle__slider:before',
				[ 'background-color' => $attributes['toggleNormalColor'] ]
			);
		}

		if ( ! empty( $attributes['toggleActiveBgColor'] ) ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}} input.ablocks-toggle__checkbox:checked + .ablocks-toggle__slider',
				[ 'background-color' => $attributes['toggleActiveBgColor'] ]
			);
		}

		if ( ! empty( $attributes['toggleActiveColor'] ) ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}} input.ablocks-toggle__checkbox:checked + .ablocks-toggle__slider:before',
				[ 'background-color' => $attributes['toggleActiveColor'] ]
			);
		}

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-toggle__topbar',
			$this->get_toggle_bar_css( $attributes ),
			$this->get_toggle_bar_css( $attributes, 'Tablet' ),
			$this->get_toggle_bar_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-toggle__topbar:hover',
			$this->get_toggle_bar_hover_css( $attributes ),
			$this->get_toggle_bar_hover_css( $attributes, 'Tablet' ),
			$this->get_toggle_bar_hover_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-toggle__topbar-wrapper',
			$this->get_toggle_bar_wrapper_css( $attributes ),
			$this->get_toggle_bar_wrapper_css( $attributes, 'Tablet' ),
			$this->get_toggle_bar_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-toggle__label',
			$this->get_toggle_label_css( $attributes ),
			$this->get_toggle_label_css( $attributes, 'Tablet' ),
			$this->get_toggle_label_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-toggle__label--active',
			$this->get_toggle_label_active_css( $attributes )
		);

		if ( ! empty( $attributes['toggleNormalBgColor'] ) ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-toggle__slider',
				[ 'background-color' => Color::get_css( isset( $attributes['toggleNormalBgColor'] ) ? $attributes['toggleNormalBgColor'] : '' ) ]
			);
		}

		if ( ! empty( $attributes['toggleNormalColor'] ) ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-toggle__slider:before',
				[ 'background-color' => Color::get_css( isset( $attributes['toggleNormalColor'] ) ? $attributes['toggleNormalColor'] : '' ) ]
			);
		}

		if ( ! empty( $attributes['toggleActiveBgColor'] ) ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}} input.ablocks-toggle__checkbox:checked + .ablocks-toggle__slider',
				[ 'background-color' => Color::get_css( isset( $attributes['toggleActiveBgColor'] ) ? $attributes['toggleActiveBgColor'] : '' ) ]
			);
		}

		if ( ! empty( $attributes['toggleActiveColor'] ) ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}} input.ablocks-toggle__checkbox:checked + .ablocks-toggle__slider:before',
				[ 'background-color' => Color::get_css( isset( $attributes['toggleActiveColor'] ) ? $attributes['toggleActiveColor'] : '' ) ]
			);
		}

		return $css_generator->generate_css();
	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}

	public function get_toggle_bar_css( $attributes, $device = '' ) {

		return array_merge(
			[ 'background-color' => Color::get_css( isset( $attributes['toggleBarBgColor'] ) ? $attributes['toggleBarBgColor'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['space'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 10,
				'unitDefaultValue' => 'px',
				'property' => 'margin-bottom',
				'device' => $device,
			]),
			isset( $attributes['alignment'] ) ? Alignment::get_css( $attributes['alignment'], 'text-align', $device ) : [],
			isset( $attributes['toggleBarBorder'] ) ? Border::get_css( $attributes['toggleBarBorder'], '', $device ) : [],
			isset( $attributes['toggleBarPadding'] ) ? Dimensions::get_css( $attributes['toggleBarPadding'], 'padding', $device ) : [],
		);
	}

	public function get_toggle_bar_wrapper_css( $attributes, $device = '' ) {
		$toggleBarWrapperCSS = Range::get_css([
			'attributeValue' => $attributes['gap'],
			'attribute_object_key' => 'value',
			'isResponsive' => true,
			'defaultValue' => 10,
			'unitDefaultValue' => 'px',
			'property' => 'gap',
			'device' => $device,
		]);

		if ( ! empty( $attributes['toggleDirection'] ) ) {
			$toggleBarWrapperCSS['flex-direction'] = $attributes['toggleDirection'];
		}
		return $toggleBarWrapperCSS;
	}

	public function get_toggle_bar_hover_css( $attributes, $device = '' ) {
		return isset( $attributes['toggleBarBorder'] ) ? Border::get_hover_css( $attributes['toggleBarBorder'], $device ) : [];
	}

	public function get_toggle_label_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['labelTypographyGlobal'] ) ? $attributes['labelTypographyGlobal'] : [];
		$labelCSS = isset( $attributes['labelTypography'] ) ? Typography::get_css( $attributes['labelTypography'], '', $device, $typographyValueGlobal ) : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['labelNormalColor'] ) ? $attributes['labelNormalColor'] : '' ) ],
			$labelCSS
		);
	}

	public function get_toggle_label_active_css( $attributes ) {
		return [ 'color' => Color::get_css( isset( $attributes['labelActiveColor'] ) ? $attributes['labelActiveColor'] : '' ) ];
	}
}
