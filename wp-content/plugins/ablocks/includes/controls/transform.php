<?php
namespace ABlocks\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\ControlBaseAbstract;

class Transform extends ControlBaseAbstract {
	public static function get_attribute_default_value( $is_responsive = false ) {
		if ( $is_responsive ) {
			return [
				'rotate' => '',
				'rotateTablet' => '',
				'rotateMobile' => '',
				'rotate3D' => false,
				'rotate3DTablet' => false,
				'rotate3DMobile' => false,
				'rotateX' => '',
				'rotateXTablet' => '',
				'rotateXMobile' => '',
				'rotateY' => '',
				'rotateYTablet' => '',
				'rotateYMobile' => '',
				'rotateP' => '',
				'rotatePTablet' => '',
				'rotatePMobile' => '',
				// Offset
				'offsetX' => '',
				'offsetXTablet' => '',
				'offsetXMobile' => '',
				'offsetXUnit' => 'px',
				'offsetXUnitTablet' => 'px',
				'offsetXUnitMobile' => 'px',
				'offsetY' => '',
				'offsetYTablet' => '',
				'offsetYMobile' => '',
				'offsetYUnit' => 'px',
				'offsetYUnitTablet' => 'px',
				'offsetYUnitMobile' => 'px',
				// Scale
				'scaleProportions' => false,
				'scale' => '',
				'scaleTablet' => '',
				'scaleMobile' => '',
				'scaleX' => '',
				'scaleXTablet' => '',
				'scaleXMobile' => '',
				'scaleY' => '',
				'scaleYTablet' => '',
				'scaleYMobile' => '',
				// Skew
				'skewX' => '',
				'skewXTablet' => '',
				'skewXMobile' => '',
				'skewY' => '',
				'skewYTablet' => '',
				'skewYMobile' => '',
				// Flip
				'flipHorizontal' => false,
				'flipVertical' => false,
				// X Anchor
				'xAnchorPoint' => '',
				'xAnchorPointTablet' => '',
				'xAnchorPointMobile' => '',
				// Y Anchor
				'yAnchorPoint' => '',
				'yAnchorPointTablet' => '',
				'yAnchorPointMobile' => '',

				// Rotate - Hover
				'rotateH' => '',
				'rotateHTablet' => '',
				'rotateHMobile' => '',
				'rotate3DH' => false,
				'rotate3DHTablet' => false,
				'rotate3DHMobile' => false,
				'rotateXH' => '',
				'rotateXHTablet' => '',
				'rotateXHMobile' => '',
				'rotateYH' => '',
				'rotateYHTablet' => '',
				'rotateYHMobile' => '',
				'rotatePH' => '',
				'rotatePHTablet' => '',
				'rotatePHMobile' => '',
				// Offset - Hover
				'offsetXH' => '',
				'offsetXHTablet' => '',
				'offsetXHMobile' => '',
				'offsetXHUnit' => 'px',
				'offsetXHUnitTablet' => 'px',
				'offsetXHUnitMobile' => 'px',
				'offsetYH' => '',
				'offsetYHTablet' => '',
				'offsetYHMobile' => '',
				'offsetYHUnit' => 'px',
				'offsetYHUnitTablet' => 'px',
				'offsetYHUnitMobile' => 'px',
				// Scale - Hover
				'scaleProportionsH' => false,
				'scaleH' => '',
				'scaleHTablet' => '',
				'scaleHMobile' => '',
				'scaleXH' => '',
				'scaleXHTablet' => '',
				'scaleXHMobile' => '',
				'scaleYH' => '',
				'scaleYHTablet' => '',
				'scaleYHMobile' => '',
				// Skew - Hover
				'skewXH' => '',
				'skewXHTablet' => '',
				'skewXHMobile' => '',
				'skewYH' => '',
				'skewYHTablet' => '',
				'skewYHMobile' => '',
				// Flip - Hover
				'flipHorizontalH' => false,
				'flipVerticalH' => false,
				// X Anchor - Hover
				'xAnchorPointH' => '',
				'xAnchorPointHTablet' => '',
				'xAnchorPointHMobile' => '',
				// Y Anchor - Hover
				'yAnchorPointH' => '',
				'yAnchorPointHTablet' => '',
				'yAnchorPointHMobile' => '',

				// Transition
				'transitionH' => '',
			];
		}//end if
		return [
			'rotate' => '',
			'rotate3D' => '',
			'rotateX' => '',
			'rotateY' => '',
			'rotateP' => '',
			'offsetX' => '',
			'offsetXUnit' => 'px',
			'offsetY' => '',
			'offsetYUnit' => 'px',
			'scaleProportions' => false,
			'scale' => '',
			'scaleX' => '',
			'scaleY' => '',
			'skewX' => '',
			'skewY' => '',
			'flipHorizontal' => false,
			'flipVertical' => false,
			'xAnchorPoint' => '',
			'yAnchorPoint' => '',
			'rotateH' => '',
			'rotate3DH' => false,
			'rotateXH' => '',
			'rotateYH' => '',
			'rotatePH' => '',
			'offsetXH' => '',
			'offsetXHUnit' => 'px',
			'offsetYH' => '',
			'offsetYHUnit' => 'px',
			'scaleH' => '',
			'scaleXH' => '',
			'scaleYH' => '',
			'skewXH' => '',
			'skewYH' => '',
			'flipHorizontalH' => false,
			'flipVerticalH' => false,
			'xAnchorPointH' => '',
			'yAnchorPointH' => '',
			'transitionH' => '',
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

	public static function get_css( $attributeValue, $property = '', $device = '' ) {
		$value = wp_parse_args(
			$attributeValue,
			self::get_attribute_default_value( (bool) $device )
		);

		$css = [];
		$transformations = [];
		$transformOrigin = '';

		$x_offset_unit = self::get_unit(
			[
				'unit' => ! empty( $value['offsetXUnit'] ) ? $value['offsetXUnit'] : 'px',
				'unitTablet' => ! empty( $value['offsetXUnitTablet'] ) ? $value['offsetXUnitTablet'] : 'px',
				'unitMobile' => ! empty( $value['offsetXUnitMobile'] ) ? $value['offsetXUnitMobile'] : 'px',
			],
			$device
		);
		$y_offset_unit = self::get_unit(
			[
				'unit' => ! empty( $value['offsetYUnit'] ) ? $value['offsetYUnit'] : 'px',
				'unitTablet' => ! empty( $value['offsetYUnitTablet'] ) ? $value['offsetYUnitTablet'] : 'px',
				'unitMobile' => ! empty( $value['offsetYUnitMobile'] ) ? $value['offsetYUnitMobile'] : 'px',
			],
			$device
		);

		if ( ! empty( $value[ 'rotate' . $device ] ) ) {
			$transformations[] = 'rotate(' . $value[ 'rotate' . $device ] . 'deg)';
		}

		if ( ! empty( $value[ 'rotateX' . $device ] ) ) {
			$transformations[] = 'rotateX(' . $value[ 'rotateX' . $device ] . 'deg)';
		}

		if ( ! empty( $value[ 'rotateY' . $device ] ) ) {
			$transformations[] = 'rotateY(' . $value[ 'rotateY' . $device ] . 'deg)';
		}

		if ( $value[ 'rotate3D' . $device ] !== false ) {
			$rotation = ! empty( $value[ 'rotate' . $device ] ) ? $value[ 'rotate' . $device ] : 0;
			$transformations[] = 'rotateZ(' . $rotation . 'deg)';
		}

		if ( ! empty( $value[ 'rotateP' . $device ] ) ) {
			$perspective = ! empty( $value[ 'rotateP' . $device ] ) ? $value[ 'rotateP' . $device ] : 0;
			$transformations[] = 'perspective(' . $perspective . 'px)';
		}

		if ( ! empty( $value[ 'scale' . $device ] ) ) {
			$transformations[] = 'scale(' . $value[ 'scale' . $device ] . ')';
		}

		if ( ! empty( $value[ 'scaleX' . $device ] ) ) {
			$transformations[] = 'scaleX(' . $value[ 'scaleX' . $device ] . ')';
		}

		if ( ! empty( $value[ 'scaleY' . $device ] ) ) {
			$transformations[] = 'scaleY(' . $value[ 'scaleY' . $device ] . ')';
		}

		if ( isset( $value['flipHorizontal'] ) && $value['flipHorizontal'] !== false ) {
			$transformations[] = 'scaleX(-1)';
		}

		if ( isset( $value['flipVertical'] ) && $value['flipVertical'] !== false ) {
			$transformations[] = 'scaleY(-1)';
		}

		if ( ! empty( $value[ 'offsetX' . $device ] ) ) {
			$transformations[] = 'translateX(' . $value[ 'offsetX' . $device ] . $x_offset_unit . ')';
		}

		if ( ! empty( $value[ 'offsetY' . $device ] ) ) {
			$transformations[] = 'translateY(' . $value[ 'offsetY' . $device ] . $y_offset_unit . ')';
		}

		if ( ! empty( $value[ 'skewX' . $device ] ) ) {
			$transformations[] = 'skewX(' . $value[ 'skewX' . $device ] . 'deg)';
		}

		if ( ! empty( $value[ 'skewY' . $device ] ) ) {
			$transformations[] = 'skewY(' . $value[ 'skewY' . $device ] . 'deg)';
		}

		if ( ! empty( $value[ 'xAnchorPoint' . $device ] ) ) {
			$transformOrigin = $value[ 'yAnchorPoint' . $device ] . ' ' . $value[ 'xAnchorPoint' . $device ];
		}

		if ( ! empty( $value[ 'yAnchorPoint' . $device ] ) ) {
			$transformOrigin = $value[ 'yAnchorPoint' . $device ] . ' ' . $value[ 'xAnchorPoint' . $device ];
		}

		if ( $transformOrigin ) {
			$css['transform-origin'] = $transformOrigin;
		}

		if ( ! empty( $transformations ) ) {
			$css[ $property ] = implode( ' ', $transformations );
		}

		return $css;
	}

	public static function get_hover_css( $attribute_value, $property = '', $device = '' ) {
		$value = wp_parse_args( $attribute_value, self::get_attribute_default_value( (bool) $device ) );
		$transformations = [];
		$transformOrigin = '';
		$x_offset_hover_unit = self::get_unit(
			[
				'unit' => ! empty( $value['offsetXHUnit'] ) ? $value['offsetXHUnit'] : 'px',
				'unitTablet' => ! empty( $value['offsetXHUnitTablet'] ) ? $value['offsetXHUnitTablet'] : 'px',
				'unitMobile' => ! empty( $value['offsetXHUnitMobile'] ) ? $value['offsetXHUnitMobile'] : 'px',
			],
			$device
		);
		$y_offset_hover_unit = self::get_unit(
			[
				'unit' => ! empty( $value['offsetYHUnit'] ) ? $value['offsetYHUnit'] : 'px',
				'unitTablet' => ! empty( $value['offsetYHUnitTablet'] ) ? $value['offsetYHUnitTablet'] : 'px',
				'unitMobile' => ! empty( $value['offsetYHUnitMobile'] ) ? $value['offsetYHUnitMobile'] : 'px',
			],
			$device
		);

		if ( '' !== $value[ 'rotateH' . $device ] ) {
			$rotateH = isset( $value[ 'rotateH' . $device ] ) ? $value[ 'rotateH' . $device ] : 0;
			$transformations[] = 'rotate(' . $rotateH . 'deg)';
		}

		if ( '' !== $value[ 'rotateXH' . $device ] ) {
			$transformations[] = 'rotateX(' . $value[ 'rotateXH' . $device ] . 'deg)';
		}

		if ( '' !== $value[ 'rotateYH' . $device ] ) {
			$transformations[] = 'rotateY(' . $value[ 'rotateYH' . $device ] . 'deg)';
		}

		if ( $value[ 'rotate3DH' . $device ] !== false && isset( $value[ 'rotateH' . $device ] ) && $value[ 'rotateH' . $device ] >= 0 ) {
			$rotateH = isset( $value[ 'rotateH' . $device ] ) ? $value[ 'rotateH' . $device ] : 0;
			$transformations[] = 'rotateZ(' . $rotateH . 'deg)';
		}

		if ( '' !== $value[ 'rotatePH' . $device ] ) {
			$rotatePH = isset( $value[ 'rotatePH' . $device ] ) ? $value[ 'rotatePH' . $device ] : 0;
			$transformations[] = 'perspective(' . $rotatePH . 'px)';
		}

		if ( '' !== $value[ 'scaleH' . $device ] ) {
			$transformations[] = 'scale(' . $value[ 'scaleH' . $device ] . ')';
		}

		if ( '' !== $value[ 'scaleXH' . $device ] ) {
			$transformations[] = 'scaleX(' . $value[ 'scaleXH' . $device ] . ')';
		}

		if ( '' !== $value[ 'scaleYH' . $device ] ) {
			$transformations[] = 'scaleY(' . $value[ 'scaleYH' . $device ] . ')';
		}

		if ( '' !== $value[ 'offsetXH' . $device ] ) {
			$transformations[] = 'translateX(' . $value[ 'offsetXH' . $device ] . $x_offset_hover_unit . ')';
		}

		if ( '' !== $value[ 'offsetYH' . $device ] ) {
			$transformations[] = 'translateY(' . $value[ 'offsetYH' . $device ] . $y_offset_hover_unit . ')';
		}

		if ( '' !== $value[ 'skewXH' . $device ] ) {
			$transformations[] = 'skewX(' . $value[ 'skewXH' . $device ] . 'deg)';
		}

		if ( '' !== $value[ 'skewYH' . $device ] ) {
			$transformations[] = 'skewY(' . $value[ 'skewYH' . $device ] . 'deg)';
		}

		if ( $value[ 'xAnchorPointH' . $device ] || $value[ 'yAnchorPointH' . $device ] ) {
			$transformOrigin = $value[ 'yAnchorPointH' . $device ] . ' ' . $value[ 'xAnchorPointH' . $device ];
		}

		if ( count( $transformations ) ) {
			if ( false !== $value['flipHorizontalH'] ) {
				$transformations[] = 'scaleX(-1)';
			}

			if ( false !== $value['flipVerticalH'] ) {
				$transformations[] = 'scaleY(-1)';
			}
		}

		$css = [];

		if ( 0 !== count( $transformations ) ) {
			$css[ $property ] = implode( ' ', $transformations );
		}

		if ( $transformOrigin ) {
			$css['transform-origin'] = $transformOrigin;
		}

		if ( ! empty( $transformations ) ) {
			$css[ $property ] = implode( ' ', $transformations );
		}

		if ( isset( $value[ 'transitionH' . $device ] ) && $value[ 'transitionH' . $device ] !== '' ) {
			$css['transition-duration'] = "{$value['transitionH' . $device]}s";
		}

		return $css;
	}
}
