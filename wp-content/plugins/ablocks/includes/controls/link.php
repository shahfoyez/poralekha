<?php
namespace ABlocks\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\ControlBaseAbstract;

class Link extends ControlBaseAbstract {

	public static function get_attribute_default_value( $is_responsive = false ) {
		return [
			'linkDestination' => '',
			'href' => '',
			'lightbox' => '',
			'linkTarget' => '',
			'rel' => '',
			'noFollow' => '',
			'keyValue' => '',
			'linkClass' => '',
		];
	}

	public static function get_attribute( $attributeName, $isResponsive = false ) {
		return [
			$attributeName => [
				'type' => 'object',
				'default' => self::get_attribute_default_value( $isResponsive )
			]
		];
	}
	public static function get_css( $attribute_value, $property = '', $device = '' ) {

	}
}
