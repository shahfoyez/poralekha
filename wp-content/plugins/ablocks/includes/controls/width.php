<?php
namespace ABlocks\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

use ABlocks\Classes\ControlBaseAbstract;
use ABlocks\Helper;

class Width extends ControlBaseAbstract {

	public static function get_attribute_default_value( $is_responsive = false ) {
		if ( $is_responsive ) {
			return [
				'widthType' => 'default',
				'widthTypeTablet' => '',
				'widthTypeMobile' => '',
				'customWidth' => '',
				'customWidthTablet' => '',
				'customWidthMobile' => '',
				'customWidthUnit' => '%',
				'customWidthUnitTablet' => '',
				'customWidthUnitMobile' => '',
			];
		}
		return [
			'widthType' => 'default',
			'customWidth' => '',
			'customWidthUnit' => '%',
		];
	}

	public static function get_attribute( $attributeName, $isResponsive = false ) {
		if ( $isResponsive ) {
			return [
				$attributeName => [
					'type' => 'object',
					'default' => self::get_attribute_default_value(
						$isResponsive
					),
				],
			];
		}
		return [
			$attributeName => [
				'type' => 'object',
				'default' => self::get_attribute_default_value( $isResponsive ),
			],
		];
	}
	public static function get_css(
		$attribute_value,
		$property = '',
		$device = ''
	) {
		$attribute_value = wp_parse_args(
			$attribute_value,
			self::get_attribute_default_value( (bool) $device )
		);

		$css = [];

		// Width type (uses responsive fallback)
		$width_type = Helper::get_responsive_value(
			$attribute_value,
			'widthType',
			$device,
			self::get_attribute_default_value()
		);

		if ( ! empty( $width_type ) ) {
			if ( $width_type === 'full' ) {
				$css[ $property ] = '100%';
			} elseif ( $width_type === 'auto' ) {
				$css[ $property ] = 'auto';
				$css['display']  = 'inline-block';
			} elseif ( $width_type === 'custom' ) {
				// Get custom width with responsive fallback
				$custom_width = Helper::get_responsive_value(
					$attribute_value,
					'customWidth',
					$device,
					self::get_attribute_default_value()
				);

				// Get responsive unit (delegates to your get_unit function)
				$unit = self::get_unit(
					[
						'unit'       => Helper::get_responsive_value( $attribute_value, 'customWidthUnit', 'Desktop' ),
						'unitTablet' => Helper::get_responsive_value( $attribute_value, 'customWidthUnit', 'Tablet' ),
						'unitMobile' => Helper::get_responsive_value( $attribute_value, 'customWidthUnit', 'Mobile' ),
					],
					$device
				);

				$css[ $property ] = $custom_width . $unit;
			}//end if
		}//end if

		return $css;
	}

}
