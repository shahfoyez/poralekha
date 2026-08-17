<?php
namespace ABlocks\Controls;

use ABlocks\Classes\ControlBaseAbstract;

class GsapAnimation extends ControlBaseAbstract {
	public static function get_attribute_default_value( $is_responsive = false ) {
		if ( $is_responsive ) {
			return [
				'animationType' => 'none',
				'duration' => 1,
				'delay' => 0,
				'triggerType' => 'scroll',
				// from method values
				'ease' => 'none',
				'x' => 0,
				'y' => 0,
				'rotation' => 0,
				'rotationY' => 0,
				'opacity' => 1,
				// to method values
				'xTo' => 0,
				'yTo' => 0,
				'rotationTo' => 0,
				'rotationYTo' => 0,
				'opacityTo' => 1,
				'easeTo' => 'none',
				// repeat/loops
				'loops' => false,
				'numberOfRepeat' => 0, // if -1 then infinite loops
				'isSmooth' => true, // yoyo
			// if animation type 'scroll' then these will work
				'startPoint' => 80, // top ${this.attribute?.startPoint ?? 80}%
				'endPoint' => 10
			];

		}//end if
		return [
			'animationType' => 'none',
			'duration' => 1,
			'delay' => 0,
			'triggerType' => 'scroll',
			// from method values
			'ease' => 'none',
			'x' => 0,
			'y' => 0,
			'rotation' => 0,
			'rotationY' => 0,
			'opacity' => 1,
			// to method values
			'xTo' => 0,
			'yTo' => 0,
			'rotationTo' => 0,
			'rotationYTo' => 0,
			'opacityTo' => 1,
			'easeTo' => 'none',
			// repeat/loops
			'loops' => false,
			'numberOfRepeat' => 0, // if -1 then infinite loops
			'isSmooth' => true, // yoyo
		// if animation type 'scroll' then these will work
			'startPoint' => 80, // top ${this.attribute?.startPoint ?? 80}%
			'endPoint' => 10
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
		$default_attar_value = self::get_attribute_default_value( (bool) $device );
		$value = wp_parse_args( $attribute_value, $default_attar_value );

		$css = [];

		/**
		 * Generated CSS
		 * css property
		 * mask-image
		 * mask-size
		 * mask-position
		 * mask-repeat
		 */

		if ( $value['mask'] ) {
			if ( 'custom' === $value['maskShape'] ) {
				$css['mask-image'] = 'url(' . $value['customMaskShape'] . ')';
			} else {
				$css['mask-image'] = 'url(' . ABLOCKS_ROOT_URL . '/assets/images/mask-shapes/' . $value['maskShape'] . '.svg)';
			}
			$css['mask-size'] = 'contain';
			$css['mask-position'] = 'center center';
			$css['mask-repeat'] = 'no-repeat';
		}

		if ( $value[ 'maskSize' . $device ] && 'custom' !== $value[ 'maskSize' . $device ] ) {
			$css['mask-size'] = $value[ 'maskSize' . $device ];
		}

		if (
			$value[ 'maskSize' . $device ] &&
			'custom' === $value[ 'maskSize' . $device ] &&
			$value[ 'scaleUnit' . $device ]
		) {
			$css['mask-size'] = $value[ 'scale' . $device ] . $value[ 'scaleUnit' . $device ];
		}

		if (
			'custom' === $value[ 'maskPosition' . $device ] &&
			$value[ 'xPosition' . $device ] &&
			$value[ 'xPositionUnit' . $device ]
		) {
			$css['-webkit-mask-position-x'] = $value[ 'xPosition' . $device ] . $value[ 'xPositionUnit' . $device ];
			$css['-webkit-mask-position-y'] = $value[ 'yPosition' . $device ] . $value[ 'yPositionUnit' . $device ];
		}

		if (
			'custom' === $value[ 'maskPosition' . $device ] &&
			$value[ 'yPosition' . $device ] &&
			$value[ 'yPositionUnit' . $device ]
		) {
			$css['-webkit-mask-position-y'] = $value[ 'yPosition' . $device ] . $value[ 'yPositionUnit' . $device ];
		}

		if ( $value[ 'maskRepeat' . $device ] ) {
			$css['mask-repeat'] = $value[ 'maskRepeat' . $device ];
		}

		return [];
	}


}
