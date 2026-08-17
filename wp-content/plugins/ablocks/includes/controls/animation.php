<?php
namespace ABlocks\Controls;

use ABlocks\Classes\ControlBaseAbstract;

class Animation extends ControlBaseAbstract {
	public static function get_attribute_default_value( $is_responsive = false ) {
		if ( $is_responsive ) {
			return [
				'animationType' => '',
				'animationTypeTablet' => '',
				'animationTypeMobile' => '',
				'animationDuration' => '',
				'animationDelay' => '',
			];
		}
		return [
			'animationType' => '',
			'animationDuration' => '',
			'animationDelay' => '',
		];
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
		return [];
	}


}
