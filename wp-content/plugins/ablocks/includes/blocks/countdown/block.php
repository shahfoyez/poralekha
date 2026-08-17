<?php
namespace ABlocks\Blocks\Countdown;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Border;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Range;
use ABlocks\Controls\Color;
use ABlocks\Controls\Dimensions;

class Block extends BlockBaseAbstract {
	protected $block_name = 'countdown';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container',
			$this->get_countdown_items_css( $attributes ),
			$this->get_countdown_items_css( $attributes, 'Tablet' ),
			$this->get_countdown_items_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-countdown__item',
			$this->get_countdown_item_css( $attributes ),
			$this->get_countdown_item_css( $attributes, 'Tablet' ),
			$this->get_countdown_item_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-countdown__item:hover',
			$this->get_countdown_item_hover_css( $attributes ),
			$this->get_countdown_item_hover_css( $attributes, 'Tablet' ),
			$this->get_countdown_item_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-countdown__item .ablocks-countdown-label',
			$this->get_label_css( $attributes ),
			$this->get_label_css( $attributes, 'Tablet' ),
			$this->get_label_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-countdown__item .ablocks-countdown-value',
			$this->get_number_css( $attributes ),
			$this->get_number_css( $attributes, 'Tablet' ),
			$this->get_number_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-countdown__separator',
			$this->get_separator_css( $attributes ),
			$this->get_separator_css( $attributes, 'Tablet' ),
			$this->get_separator_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--countdown:not(.ablocks-has-container),
			{{WRAPPER}}.ablocks-block--countdown .ablocks-block-container',
			$this->get_countdown_items_css( $attributes, '' ),
			$this->get_countdown_items_css( $attributes, 'Tablet' ),
			$this->get_countdown_items_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-countdown__item',
			$this->get_countdown_item_css( $attributes ),
			$this->get_countdown_item_css( $attributes, 'Tablet' ),
			$this->get_countdown_item_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-countdown__item:hover',
			$this->get_countdown_item_hover_css( $attributes ),
			$this->get_countdown_item_hover_css( $attributes, 'Tablet' ),
			$this->get_countdown_item_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-countdown__item .ablocks-countdown-label',
			$this->get_label_css( $attributes ),
			$this->get_label_css( $attributes, 'Tablet' ),
			$this->get_label_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-countdown__item .ablocks-countdown-value',
			$this->get_number_css( $attributes ),
			$this->get_number_css( $attributes, 'Tablet' ),
			$this->get_number_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-countdown__separator',
			$this->get_separator_css( $attributes ),
			$this->get_separator_css( $attributes, 'Tablet' ),
			$this->get_separator_css( $attributes, 'Mobile' )
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
		$alignment = ! empty( $attributes['alignment'] ) ? $attributes['alignment'] : '';
		return Alignment::get_css( $alignment, 'text-align', $device );
	}

	public function get_countdown_items_css( $attributes, $device = '' ) {
		$css = [];

		if ( ! empty( $attributes['orient'][ 'value' . $device ] ) ) {
			$css['flex-direction'] = $attributes['orient'][ 'value' . $device ];
		}
		if ( ! empty( $attributes['justificationAlign'][ 'value' . $device ] ) ) {
			$css['justify-content'] = $attributes['justificationAlign'][ 'value' . $device ];
		}
		if ( ! empty( $attributes['alignment'][ 'value' . $device ] ) ) {
			$css['align-items'] = $attributes['alignment'][ 'value' . $device ];
		}
		if ( ! empty( $attributes['wrapping'][ 'value' . $device ] ) ) {
			$css['flex-wrap'] = $attributes['wrapping'][ 'value' . $device ];
		}

		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['boxSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 130,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'min-height',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['boxRowGap'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 0,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'row-gap',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['boxColumnGap'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 0,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'column-gap',
				'device' => $device,
			]),
			$css,
		);
	}

	public function get_countdown_item_css( $attributes, $device = '' ) {
		$css = [];
		$width_css = [];
		// $css['padding'] = '0';
		$label_position       = ! empty( $attributes['labelPosition'] ) ? $attributes['labelPosition'] : '';
		$orient               = ! empty( $attributes['orient'] ) ? $attributes['orient'] : [];

		$device_key = $device ? 'value' . ucfirst( $device ) : 'value';
		$direction  = isset( $orient[ $device_key ] ) ? $orient[ $device_key ] : ( $orient['value'] ?? '' );

		if ( in_array( $direction, [ 'column', 'column-reverse' ], true ) ) {
			$width_css = Range::get_css( [
				'attributeValue'      => $attributes['boxSize'],
				'attribute_object_key' => 'value',
				'isResponsive'        => true,
				'defaultValue'        => 130,
				'hasUnit'             => true,
				'unitDefaultValue'    => 'px',
				'property'            => 'width',
				'device'              => $device,
			] );
		} else {
			$width_css = [ 'width' => '130px' ];
		}

		if ( $label_position ) {
			$css['flex-direction'] = $label_position;
		}

		$border_css = [];
		if ( isset( $attributes['boxBorder'] ) ) {
			$border_css = Border::get_css( $attributes['boxBorder'], '', $device );
		}

		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['boxBackgroundColor'] ) ? $attributes['boxBackgroundColor'] : '' ) ],
			$width_css,
			Range::get_css( [
				'attributeValue'      => $attributes['numberAndLabelGap'],
				'attribute_object_key' => 'value',
				'isResponsive'        => true,
				'defaultValue'        => 5,
				'hasUnit'             => true,
				'unitDefaultValue'    => 'px',
				'property'            => 'gap',
				'device'              => $device,
			] ),
			$border_css,
			$css,
			BoxShadow::get_css( ! empty( $attributes['boxShadow'] ) ? $attributes['boxShadow'] : '', $device ),
			Dimensions::get_css( $attributes['padding'], 'padding', $device ),
		);
	}




	public function get_countdown_item_hover_css( $attributes, $device = '' ) {
		return array_merge(
			Border::get_hover_css( ! empty( $attributes['boxBorder'] ) ? $attributes['boxBorder'] : [], '', $device ),
			BoxShadow::get_hover_css( $attributes['boxShadow'], '', $device )
		);
	}

	public function get_label_css( $attributes, $device = '' ) {
		$css = [];
		if ( $attributes['labelPosition'] === 'column' || $attributes['labelPosition'] === 'column-reverse' ) {
			$css['width'] = '100%';
			$css['flex'] = '1 1 30%';
			$css['display'] = 'flex';
			$css['justify-content'] = 'center';
			$css['align-items'] = 'center';
		}
		$typographyGlobal = ( isset( $attributes['labelTypographyGlobal'] ) ? $attributes['labelTypographyGlobal'] : '' );
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['labelColor'] ) ? $attributes['labelColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['labelBgColor'] ) ? $attributes['labelBgColor'] : '' ) ],
			! empty( $attributes['labelTypography'] ) ? Typography::get_css( $attributes['labelTypography'], '', $device, $typographyGlobal ) : array(),
			$css
		);
	}

	public function get_number_css( $attributes, $device = '' ) {
		$css = [];
		if ( $attributes['labelPosition'] === 'column' || $attributes['labelPosition'] === 'column-reverse' ) {
			$css['width'] = '100%';
			$css['flex'] = '1 1 70%';
			$css['display'] = 'flex';
			$css['justify-content'] = 'center';
			$css['align-items'] = 'center';
		}
		$typographyGlobal = ( isset( $attributes['numberTypographyGlobal'] ) ? $attributes['numberTypographyGlobal'] : '' );
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['numberColor'] ) ? $attributes['numberColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['numberBgColor'] ) ? $attributes['numberBgColor'] : '' ) ],
			! empty( $attributes['numberTypography'] ) ? Typography::get_css( $attributes['numberTypography'], '', $device, $typographyGlobal ) : array(),
			$css
		);
	}

	public function get_separator_css( $attributes, $device = '' ) {
		$typographyGlobal = ( isset( $attributes['separatorTypographyGlobal'] ) ? $attributes['separatorTypographyGlobal'] : '' );
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['separatorColor'] ) ? $attributes['separatorColor'] : '' ) ],
			! empty( $attributes['separatorTypography'] ) ? Typography::get_css( $attributes['separatorTypography'], '', $device, $typographyGlobal ) : array(),
		);
	}
}

