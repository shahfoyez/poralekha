<?php
namespace ABlocks\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\ControlBaseAbstract;
use ABlocks\Controls\Color;

class TextShadow extends ControlBaseAbstract {
	public static function get_attribute_default_value( $is_responsive = false ) {
		return array(
			'color' => '',
			'blur' => '',
			'horizontal' => '',
			'vertical' => '',
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

		if (
			'' !== $value['color'] ||
			'' !== $value['horizontal'] ||
			'' !== $value['vertical'] ||
			'' !== $value['blur']
		) {
			$horizontal = '' !== $value['horizontal'] ? $value['horizontal'] : '0';
			$vertical = '' !== $value['vertical'] ? $value['vertical'] : '0';
			$blur = '' !== $value['blur'] ? $value['blur'] : '10';
			$color = '' !== $value['color'] ? $value['color'] : 'rgba(0, 0, 0, 0.3)';
			$css['text-shadow'] = $horizontal . 'px ' . $vertical . 'px ' . $blur . 'px ' . Color::get_css( $color );
		}

		return $css;
	}


}
