<?php
namespace ABlocks\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\ControlBaseAbstract;
use ABlocks\Controls\Color;

class TextStroke extends ControlBaseAbstract {
	public static function get_attribute_default_value( $is_responsive = false ) {
		if ( $is_responsive ) {
			return array(
				'strokeWidth' => '',
				'strokeWidthTablet' => '',
				'strokeWidthMobile' => '',
				'strokeWidthUnit' => 'px',
				'strokeWidthUnitTablet' => 'px',
				'strokeWidthUnitMobile' => 'px',
				'stroke' => '',
			);
		}
		return array(
			'strokeWidth' => '',
			'strokeWidthUnit' => 'px',
			'stroke' => '',
		);
	}

	public static function get_attribute( $attributeName, $isResponsive = false ) {
		return [
			$attributeName => [
				'type' => 'object',
				'default' => self::get_attribute_default_value( $isResponsive ),
			]
		];
	}

	public static function get_css( $attribute_value, $property = '', $device = '' ) {
		if ( empty( $attribute_value ) ) {
			return [];
		}
		$default_attar_value = self::get_attribute_default_value( (bool) $device );
		$value = wp_parse_args( $attribute_value, $default_attar_value );
		$css = [];

		if ( '' !== $value[ 'strokeWidth' . $device ] ) {
			$css['-webkit-text-stroke-width'] = $value[ 'strokeWidth' . $device ] . $value[ 'strokeWidthUnit' . $device ];
			$css['-webkit-text-stroke-color'] = Color::get_css( '' !== $value['stroke'] ? $value['stroke'] : '#000' );
		}

		return $css;
	}


}
