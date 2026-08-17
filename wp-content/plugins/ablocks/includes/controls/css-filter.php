<?php
namespace ABlocks\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ABlocks\Classes\ControlBaseAbstract;
class CssFilter extends ControlBaseAbstract {
	public static function get_attribute_default_value( $is_responsive = false ) {
		if ( $is_responsive ) {
			return array(
				'blur' => '',
				'blurTablet' => '',
				'blurMobile' => '',
				'brightness' => '',
				'brightnessTablet' => '',
				'brightnessMobile' => '',
				'contrast' => '',
				'contrastTablet' => '',
				'contrastMobile' => '',
				'saturate' => '',
				'saturateTablet' => '',
				'saturateMobile' => '',
				'hue' => '',
				'hueTablet' => '',
				'hueMobile' => '',

				'blurH' => '',
				'blurHTablet' => '',
				'blurHMobile' => '',
				'brightnessH' => '',
				'brightnessHTablet' => '',
				'brightnessHMobile' => '',
				'contrastH' => '',
				'contrastHTablet' => '',
				'contrastHMobile' => '',
				'saturateH' => '',
				'saturateHTablet' => '',
				'saturateHMobile' => '',
				'hueH' => '',
				'hueHTablet' => '',
				'hueHMobile' => '',
			);
		}//end if
		return [
			'blur' => '',
			'brightness' => '',
			'contrast' => '',
			'saturate' => '',
			'hue' => '',
			// css filter hover attributes
			'blurH' => '',
			'brightnessH' => '',
			'contrastH' => '',
			'saturateH' => '',
			'hueH' => '',
		];
	}
	public static function get_attribute( $attributeName, $isResponsive = false ) {
		return [
			$attributeName => [
				'type' => 'object',
				'default' => self::get_attribute_default_value( $isResponsive ),
			],
		];
	}
	public static function get_css( $attribute_value, $property = '', $device = '' ) {
		$value = array_merge(
			self::get_attribute_default_value( (bool) $device ),
			$attribute_value
		);
		$css = [];
		$filterValues = [];
		if ( isset( $value[ 'brightness' . $device ] ) && $value[ 'brightness' . $device ] !== '' ) {
			$filterValues[] = 'brightness(' . ( $value[ 'brightness' . $device ] ?? 100 ) . '%)';
		}
		if ( isset( $value[ 'contrast' . $device ] ) && $value[ 'contrast' . $device ] !== '' ) {
			$filterValues[] = 'contrast(' . ( $value[ 'contrast' . $device ] ?? 100 ) . '%)';
		}
		if ( isset( $value[ 'saturate' . $device ] ) && $value[ 'saturate' . $device ] !== '' ) {
			$filterValues[] = 'saturate(' . ( $value[ 'saturate' . $device ] ?? 100 ) . '%)';
		}
		if ( isset( $value[ 'blur' . $device ] ) && $value[ 'blur' . $device ] !== '' ) {
			$filterValues[] = 'blur(' . ( $value[ 'blur' . $device ] ?? 0 ) . 'px)';
		}
		if ( isset( $value[ 'hue' . $device ] ) && $value[ 'hue' . $device ] !== '' ) {
			$filterValues[] = 'hue-rotate(' . ( $value[ 'hue' . $device ] ?? 0 ) . 'deg)';
		}
		// Only add the filter property if there are valid filter values
		if ( ! empty( $filterValues ) ) {
			$css['filter'] = implode( ' ', $filterValues );
		}
		return $css;
	}
}
