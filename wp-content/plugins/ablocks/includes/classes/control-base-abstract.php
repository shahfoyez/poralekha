<?php
namespace ABlocks\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Helper;

abstract class ControlBaseAbstract {
	abstract public static function get_attribute_default_value( $is_responsive = false);
	abstract public static function get_attribute( $attributeName, $is_responsive = false);
	abstract public static function get_css( $attribute_value, $property = '', $device = '');
	public static function has_value( $value ) {
		return isset( $value ) && ! empty( $value );
	}
	public static function get_unit( $attribute_value, $device = '' ) {
		return Helper::get_responsive_value(
			$attribute_value,
			'unit',
			$device,
			[]
		);
	}
}
