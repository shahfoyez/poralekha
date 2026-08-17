<?php
namespace ABlocks\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\ControlBaseAbstract;

class Color {

	private static function parse_color_variable_to_object( $value, $type ) {
		if ( is_string( $value ) && strpos( $value, "var:{$type}" ) === 0 ) {
			$parts = explode( '|', $value );
			if ( count( $parts ) === 3 ) {
				$result = [
					'source' => $parts[0],
					'color'  => $parts[1] ?: '',
				];
				$result[ $type === 'preset' ? 'slug' : 'id' ] = $parts[2];
				return $result;
			}
		}
		return false;
	}

	public static function parse_preset_color_to_object( $value ) {
		return self::parse_color_variable_to_object( $value, 'preset' );
	}

	public static function parse_global_color_to_object( $value ) {
		return self::parse_color_variable_to_object( $value, 'global' );
	}

	public static function get_css( $attribute_value ) {
		if ( ! empty( $attribute_value ) ) {
			$preset_color = self::parse_preset_color_to_object( $attribute_value );
			$global_color = self::parse_global_color_to_object( $attribute_value );

			if ( $preset_color ) {
				return sprintf( 'var(--wp--preset--color--%s)', esc_attr( $preset_color['slug'] ) );
			}

			if ( $global_color ) {
				return sprintf( 'var(--ablocks-%s)', esc_attr( $global_color['id'] ) );
			}

			return $attribute_value;
		}
		return '';
	}
}
