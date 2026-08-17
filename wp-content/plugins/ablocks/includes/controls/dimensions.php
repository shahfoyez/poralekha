<?php
namespace ABlocks\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\ControlBaseAbstract;

class Dimensions extends ControlBaseAbstract {
	public static function get_attribute_default_value( $is_responsive = false ) {
		if ( $is_responsive ) {
			return [
				'isLinked' => true,
				'isLinkedTablet' => true,
				'isLinkedMobile' => true,
				'common' => '',
				'top' => '',
				'right' => '',
				'bottom' => '',
				'left' => '',
				'unit' => 'px',
				'commonTablet' => '',
				'topTablet' => '',
				'rightTablet' => '',
				'bottomTablet' => '',
				'leftTablet' => '',
				'unitTablet' => '',
				'commonMobile' => '',
				'topMobile' => '',
				'rightMobile' => '',
				'bottomMobile' => '',
				'leftMobile' => '',
				'unitMobile' => '',
			];
		}//end if
		return [
			'isLinked' => true,
			'common' => '',
			'top' => '',
			'right' => '',
			'bottom' => '',
			'left' => '',
			'unit' => 'px',
		];
	}

	public static function get_attribute( $attributeName, $isResponsive = false ) {
		$attribute_value = self::get_attribute_default_value( $isResponsive );
		if ( $isResponsive ) {
			return [
				$attributeName => [
					'type' => 'object',
					'default' => $attribute_value
				]
			];
		}
		return [
			$attributeName => [
				'type' => 'object',
				'default' => $attribute_value
			]
		];
	}
	public static function get_css( $attribute_value, $property = '', $device = '' ) {
		$attribute_value = wp_parse_args( $attribute_value, self::get_attribute_default_value( (bool) $device ) );
		$css = [];
		$unit = self::get_unit( $attribute_value, $device );

		if ( (bool) $attribute_value[ 'isLinked' . $device ] ) {
			if ( '' !== $attribute_value[ 'common' . $device ] ) {
				$css[ $property ] = $attribute_value[ 'common' . $device ] . $unit;
			}
		} else {
			if ( '' !== $attribute_value[ 'top' . $device ] ) {
				$css[ $property . '-top' ] = $attribute_value[ 'top' . $device ] . $unit;
			}
			if ( '' !== $attribute_value[ 'right' . $device ] ) {
				$css[ $property . '-right' ] = $attribute_value[ 'right' . $device ] . $unit;
			}
			if ( '' !== $attribute_value[ 'bottom' . $device ] ) {
				$css[ $property . '-bottom' ] = $attribute_value[ 'bottom' . $device ] . $unit;
			}
			if ( '' !== $attribute_value[ 'left' . $device ] ) {
				$css[ $property . '-left' ] = $attribute_value[ 'left' . $device ] . $unit;
			}
		}
		return $css;
	}

}
