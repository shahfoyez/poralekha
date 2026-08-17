<?php
namespace ABlocks\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\ControlBaseAbstract;

class Position extends ControlBaseAbstract {
	public static function get_attribute_default_value( $is_responsive = false ) {
		if ( $is_responsive ) {
			return [
				'positionType' => 'relative',
				'positionTypeTablet' => '',
				'positionTypeMobile' => '',
				'hOrientation' => '',
				'hOffset' => '',
				'hOffsetTablet' => '',
				'hOffsetMobile' => '',
				'hOffsetUnit' => 'px',
				'hOffsetUnitTablet' => 'px',
				'hOffsetUnitMobile' => 'px',
				'vOrientation' => '',
				'vOffset' => '',
				'vOffsetTablet' => '',
				'vOffsetMobile' => '',
				'vOffsetUnit' => 'px',
				'vOffsetUnitTablet' => 'px',
				'vOffsetUnitMobile' => 'px',
			];
		}//end if
		return [
			'positionType' => 'relative',
			'hOrientation' => '',
			'hOffset' => '0',
			'hOffsetUnit' => 'px',
			'vOrientation' => '',
			'vOffset' => '0',
			'vOffsetUnit' => 'px',
		];
	}

	public static function get_attribute( $attributeName, $is_responsive = false ) {
		return [
			$attributeName => [
				'type' => 'object',
				'default' => self::get_attribute_default_value( $is_responsive ),
			]
		];
	}

	public static function get_css( $attribute_value, $property = '', $device = '' ) {
		$default_attr_value = self::get_attribute_default_value( (bool) $device );
		$value = wp_parse_args( $attribute_value, $default_attr_value );
		$css = [];

		$h_offset_unit = self::get_unit(
			[
				'unit' => ! empty( $value['hOffsetUnit'] ) ? $value['hOffsetUnit'] : '',
				'unitTablet' => ! empty( $value['hOffsetUnitTablet'] ) ? $value['hOffsetUnitTablet'] : '',
				'unitMobile' => ! empty( $value['hOffsetUnitMobile'] ) ? $value['hOffsetUnitMobile'] : '',
			],
			$device
		);
		$v_offset_unit = self::get_unit(
			[
				'unit' => ! empty( $value['vOffsetUnit'] ) ? $value['vOffsetUnit'] : '',
				'unitTablet' => ! empty( $value['vOffsetUnitTablet'] ) ? $value['vOffsetUnitTablet'] : '',
				'unitMobile' => ! empty( $value['vOffsetUnitMobile'] ) ? $value['vOffsetUnitMobile'] : '',
			],
			$device
		);

		if ( ! empty( $value['positionType'] ) ) {
			$css[ $property ] = $value['positionType'];
		}

		$is_absolute_or_fixed = $value['positionType'] === 'absolute' || $value['positionType'] === 'fixed';

		if ( $is_absolute_or_fixed ) {
			if ( ! empty( $value[ 'hOrientation' . $device ] ) && $value[ 'hOrientation' . $device ] === 'right' ) {
				$css['right'] = $value[ 'hOffset' . $device ] . $h_offset_unit;
				unset( $css['left'] );
			} elseif ( ! empty( $value[ 'hOffset' . $device ] ) ) {
				$css['left'] = $value[ 'hOffset' . $device ] . $h_offset_unit;
				unset( $css['right'] );
			}

			if ( ! empty( $value[ 'vOrientation' . $device ] ) && $value[ 'vOrientation' . $device ] === 'bottom' ) {
				$css['bottom'] = $value[ 'vOffset' . $device ] . $v_offset_unit;
				unset( $css['top'] );
			} elseif ( ! empty( $value[ 'vOffset' . $device ] ) ) {
				$css['top'] = $value[ 'vOffset' . $device ] . $v_offset_unit;
				unset( $css['bottom'] );
			}
		}
		return $css;
	}
}
