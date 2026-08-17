<?php
namespace ABlocks\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\ControlBaseAbstract;

class Typography extends ControlBaseAbstract {
	public static function get_attribute_default_value( $is_responsive = false, $default = [] ) {
		$base_values = [
			'fontFamily' => '',
			'weight' => '',
			'transform' => '',
			'style' => '',
			'decoration' => '',
			'fontSize' => '',
			'fontSizeUnit' => 'px',
			'lineHeight' => '',
			'lineHeightUnit' => 'px',
			'letterSpacing' => '',
			'letterSpacingUnit' => 'px',
			'wordSpacing' => '',
			'wordSpacingUnit' => 'px',
		];

		if ( $is_responsive ) {
			$responsive_values = [
				'fontSizeTablet' => '',
				'fontSizeMobile' => '',
				'fontSizeUnitTablet' => 'px',
				'fontSizeUnitMobile' => 'px',
				'lineHeightTablet' => '',
				'lineHeightMobile' => '',
				'lineHeightUnitTablet' => 'px',
				'lineHeightUnitMobile' => 'px',
				'letterSpacingTablet' => '',
				'letterSpacingMobile' => '',
				'letterSpacingUnitTablet' => 'px',
				'letterSpacingUnitMobile' => 'px',
				'wordSpacingTablet' => '',
				'wordSpacingMobile' => '',
				'wordSpacingUnitTablet' => 'px',
				'wordSpacingUnitMobile' => 'px',
			];
			return array_merge( $base_values, $responsive_values, $default );
		}//end if

		return array_merge( $base_values, $default );
	}


	public static function get_attribute( $attributeName, $isResponsive = false, $default = [] ) {
		return [
			$attributeName . 'Global'  => [
				'type' => 'string',
				'default' => '',
			],
			$attributeName => [
				'type' => 'object',
				'default' => self::get_attribute_default_value( $isResponsive, $default ),
			]
		];
	}

	public static function get_css( $attribute_value, $property = '', $device = '', $attribute_global_name = '' ) {
		$global_value = [];
		if ( $attribute_global_name ) {
			$global_typography = wp_list_pluck( \Ablocks\Helper::get_settings( 'global_typography', [] ), 'value', 'id' );
			$global_value = ( isset( $global_typography[ $attribute_global_name ] ) ? (array) $global_typography[ $attribute_global_name ] : [] );
		}

		if ( empty( $attribute_value ) && ! count( $global_value ) ) {
			return [];
		}

		$default_attr_value = self::get_attribute_default_value( (bool) $device );
		$value = wp_parse_args( $attribute_value, $default_attr_value );
		$css = [];

		// Helper: add CSS property with global fallback
		$add_prop = function( $prop, $local_val, $local_unit, $global_key, $css_var_name ) use ( $attribute_global_name, $global_value, &$css ) {
			if ( ! empty( $global_value[ $global_key ] ) ) {
				$css[ $prop ] = "var(--ablocks-{$attribute_global_name}-{$css_var_name})";
			} elseif ( ! empty( $local_val ) ) {
				$css[ $prop ] = $local_unit ? $local_val . $local_unit : $local_val;
			}
		};

		// Font family. Output the CSS value only — font *discovery* is no longer
		// done at render time. The set of fonts a page uses is collected from
		// saved block attributes (FontCollector) and emitted by core via
		// theme.json (CoreFontRegistry). See docs/FONT-MANAGEMENT-PLAN.md.
		//
		// The stored attribute is a bare family name (that name doubles as the
		// download key), so FontStack turns it into a real stack — quoted, with a
		// metric-adjusted fallback face and a generic tail derived from the font's
		// own category. There is no per-block fallback field: `fontFallback` is
		// read only so a value set through a filter still wins.
		if ( ! empty( $global_value['fontFamily'] ) ) {
			$css['font-family'] = "var(--ablocks-{$attribute_global_name}-font-family)";
		} elseif ( ! empty( $value['fontFamily'] ) ) {
			$stack = \ABlocks\Classes\FontStack::build(
				$value['fontFamily'],
				isset( $value['fontFallback'] ) ? $value['fontFallback'] : ''
			);
			if ( '' !== $stack ) {
				$css['font-family'] = $stack;
			}
		}

		// Desktop-only styles
		if ( empty( $device ) ) {
			$add_prop( 'font-weight', $value['weight'] ?? '', null, 'weight', 'weight' );
			$add_prop( 'text-transform', $value['transform'] ?? '', null, 'transform', 'transform' );
			$add_prop( 'font-style', $value['style'] ?? '', null, 'style', 'style' );
			$add_prop( 'text-decoration', $value['decoration'] ?? '', null, 'decoration', 'decoration' );
		}

		// Device-specific typography
		$map = [
			'fontSize'      => 'font-size',
			'lineHeight'    => 'line-height',
			'letterSpacing' => 'letter-spacing',
			'wordSpacing'   => 'word-spacing',
		];

		foreach ( $map as $prop => $css_prop ) {
			$local_val  = $value[ $prop . $device ] ?? '';
			$local_unit = $value[ $prop . 'Unit' . $device ] ?? '';
			$global_key = $prop . $device;

			$css_var_name = $css_prop . ( $device ? '-' . strtolower( $device ) : '' );

			$add_prop( $css_prop, $local_val, $local_unit, $global_key, $css_var_name );
		}

		return $css;
	}
}
