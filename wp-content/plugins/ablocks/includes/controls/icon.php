<?php
namespace ABlocks\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Helper;
use ABlocks\Controls\Color;
use ABlocks\Controls\Border;
use ABlocks\Controls\BoxShadow;

class Icon {
	public static function get_attribute_default_value( $is_responsive = false ) {
		return [];
	}

	public static function get_attribute( $attribute_prefix = 'icon', $default = [] ) {
		$default_args = wp_parse_args($default, [
			'path' => 'M248 8C111 8 0 119 0 256s111 248 248 248 248-111 248-248S385 8 248 8zm0 448c-110.3 0-200-89.7-200-200S137.7 56 248 56s200 89.7 200 200-89.7 200-200 200zm84-143.4c-20.8 25-51.5 39.4-84 39.4s-63.2-14.3-84-39.4c-8.5-10.2-23.6-11.5-33.8-3.1-10.2 8.5-11.5 23.6-3.1 33.8 30 36 74.1 56.6 120.9 56.6s90.9-20.6 120.9-56.6c8.5-10.2 7.1-25.3-3.1-33.8-10.2-8.4-25.3-7.1-33.8 3.1zM136.5 211c7.7-13.7 19.2-21.6 31.5-21.6s23.8 7.9 31.5 21.6l9.5 17c2.1 3.7 6.2 4.7 9.3 3.7 3.6-1.1 6-4.5 5.7-8.3-3.3-42.1-32.2-71.4-56-71.4s-52.7 29.3-56 71.4c-.3 3.7 2.1 7.2 5.7 8.3 3.4 1.1 7.4-.5 9.3-3.7l9.5-17zM328 152c-23.8 0-52.7 29.3-56 71.4-.3 3.7 2.1 7.2 5.7 8.3 3.5 1.1 7.4-.5 9.3-3.7l9.5-17c7.7-13.7 19.2-21.6 31.5-21.6s23.8 7.9 31.5 21.6l9.5 17c2.1 3.7 6.2 4.7 9.3 3.7 3.6-1.1 6-4.5 5.7-8.3-3.3-42.1-32.2-71.4-56-71.4z',
			'viewBox' => '0 0 496 512',
			'className' => 'far fa-smile-beam',
			'hasNoSelectorOrSource' => false,
			'size' => 55,
			'color' => '#69727d',
			'BgColor' => '',
			'hoverColor' => '',
			'iconType' => 'default',
			'iconShape' => 'circle',
		]);

		$svgPathKey = $attribute_prefix . 'SvgPath';
		$svgViewBoxKey = $attribute_prefix . 'SvgViewBox';
		$svgClassKey = $attribute_prefix . 'Class';
		$customImageUrl = $attribute_prefix . 'ImageUrl';
		$customImageID = $attribute_prefix . 'ImageID';
		$customImageSize = $attribute_prefix . 'ImageSize';

		$attribute = [
			$svgPathKey => [
				'type' => 'string',
				'default' => $default_args['path'],
			],
			$svgViewBoxKey => [
				'type' => 'string',
				'default' => $default_args['viewBox'],
			],
			$svgClassKey => [
				'type' => 'string',
				'default' => $default_args['className'],
			],
			$customImageUrl => [
				'type' => 'string',
				'default' => '',
			],
			$customImageID => [
				'type' => 'number',
				'default' => 0,
			],
			$customImageSize => [
				'type' => 'string',
				'default' => '',
			],
			$attribute_prefix . 'Color' => [
				'type' => 'string',
				'default' => $default_args['color'],
			],
			$attribute_prefix . 'BgColor' => [
				'type' => 'string',
				'default' => $default_args['BgColor'],
			],
			$attribute_prefix . 'HoverColor' => [
				'type' => 'string',
				'default' => $default_args['hoverColor'],
			],
			$attribute_prefix . 'HoverBgColor' => [
				'type' => 'string',
				'default' => $default_args['BgColor'],
			],
			$attribute_prefix . 'Type' => [
				'type' => 'string',
				'default' => $default_args['iconType'],
			],
			$attribute_prefix . 'Shape' => [
				'type' => 'string',
				'default' => $default_args['iconShape'],
			],
		];

		// Add additional attributes if 'hasNoSelectorOrSource' is false
		if ( ! $default_args['hasNoSelectorOrSource'] ) {
			$attribute[ $svgPathKey ]['source'] = 'attribute';
			$attribute[ $svgPathKey ]['selector'] = 'svg.ablocks-svg-icon path';
			$attribute[ $svgPathKey ]['attribute'] = 'd';

			$attribute[ $svgViewBoxKey ]['source'] = 'attribute';
			$attribute[ $svgViewBoxKey ]['selector'] = 'svg.ablocks-svg-icon';
			$attribute[ $svgViewBoxKey ]['attribute'] = 'viewBox';
		}

		$attribute = array_merge(
			$attribute,
			Dimensions::get_attribute( $attribute_prefix . 'Padding', false ),
			Border::get_attribute( $attribute_prefix . 'Border', false ),
			BoxShadow::get_attribute( $attribute_prefix . 'BoxShadow', true ),
			Range::get_attribute([
				'attributeName' => $attribute_prefix . 'Size',
				'isResponsive' => false,
				'defaultValue' => $default_args['size'],
			]),
			Range::get_attribute([
				'attributeName' => $attribute_prefix . 'Rotate',
				'isResponsive' => false,
				'defaultValue' => 0,
			]),
			Range::get_attribute([
				'attributeName' => $attribute_prefix . 'Sizing',
				'attributeObjectKey' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'defaultValue' => $default_args['size'],

			]),
			Range::get_attribute([
				'attributeName' => $attribute_prefix . 'Rotated',
				'attributeObjectKey' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => 'deg',
				'defaultValue' => 0,
			]),
			Range::get_attribute([
				'attributeName' => $attribute_prefix . 'RotateHover',
				'attributeObjectKey' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => 'deg',
				'defaultValue' => 0,
			] ),
			Range::get_attribute([
				'attributeName' => $attribute_prefix . 'transitionDuration',
				'isResponsive' => false,
				'defaultValue' => 0,
			] ),
			Range::get_attribute([
				'attributeName' => $attribute_prefix . 'ImageWidth',
				'isResponsive' => false,
				'defaultValue' => 0,
			])
		);

		return $attribute;
	}

	public static function get_css( $attribute_value, $property = '', $device = '' ) {
		return [];
	}



	public static function get_wrapper_css( $attributes, $device = '', $attributePrefix = 'icon' ) {
		$iconType = $attributes[ $attributePrefix . 'Type' ];
		$iconShape = $attributes[ $attributePrefix . 'Shape' ];
		$backgroundColor = Color::get_css( $attributes[ $attributePrefix . 'BgColor' ] );
		$primaryColor = Color::get_css( $attributes[ $attributePrefix . 'Color' ] );
		$iconViewCSS = [];

		if ( $iconType !== 'default' ) {
			if ( $iconType === 'stacked' ) {
				if ( $iconShape === 'circle' ) {
					$iconViewCSS = [
						'background' => $backgroundColor ? $backgroundColor : '#ddd',
						'border-radius' => '50%',
						'padding' => '.5em',
					];
				} elseif ( $iconShape === 'square' ) {
					$iconViewCSS = [
						'background' => $backgroundColor ? $backgroundColor : '#ddd',
						'padding' => '.5em',
					];
				}
			} elseif ( $iconType === 'framed' ) {
				if ( $iconShape === 'circle' ) {
					$iconViewCSS = [
						'background' => $backgroundColor ? $backgroundColor : 'transparent',
						'padding' => '.5em',
						'border-radius' => '50%',
						'border' => '2px solid ' . ( Color::get_css( $primaryColor ? $primaryColor : '#69727d' ) ),
					];
				} elseif ( $iconShape === 'square' ) {
					$iconViewCSS = [
						'background' => $backgroundColor ? $backgroundColor : 'transparent',
						'padding' => '.5em',
						'border' => '2px solid ' . ( Color::get_css( $primaryColor ? $primaryColor : '#69727d' ) ),
					];
				}
			}//end if
		}//end if

		if ( ! empty( $attributes[ $attributePrefix . 'Sizing' ] ) && empty( $attributes[ $attributePrefix . 'ImageUrl' ] ) ) {
			$sizeValue = isset( $attributes[ $attributePrefix . 'Sizing' ][ 'value' . $device ] ) ? $attributes[ $attributePrefix . 'Sizing' ][ 'value' . $device ] : $attributes[ $attributePrefix . 'Sizing' ]['value'];

			$sizeUnit = self::get_unit( [
				'attributeValue'      => $attributes[ $attributePrefix . 'Sizing' ],
				'attributeObjectKey'  => 'value',
				'unitDefaultValue'    => 'px',
				'device'              => $device,
			] );

			$iconViewCSS['font-size'] = $sizeValue . $sizeUnit;
		}
		// Remove font size for fixing
		if ( isset( $attributes[ $attributePrefix . 'ImageUrl' ] ) && ! empty( $attributes[ $attributePrefix . 'ImageUrl' ] ) ) {
			$iconViewCSS['font-size'] = 'unset';
		}

		return array_merge(
			[ 'background' => $backgroundColor ],
			$iconViewCSS,
			'default' !== $iconType ? array_merge(
				Dimensions::get_css( $attributes[ $attributePrefix . 'Padding' ], 'padding', $device ),
				Border::get_css( $attributes[ $attributePrefix . 'Border' ], 'border', $device ),
				BoxShadow::get_css( $attributes[ $attributePrefix . 'BoxShadow' ], '', $device )
			) : []
		);
	}

	public static function get_wrapper_hover_css( $attributes, $device = '', $attributePrefix = 'icon' ) {
		$primaryHoverColor = Color::get_css( $attributes[ $attributePrefix . 'HoverBgColor' ] ?? '' );
		$iconViewHoverCSS = [];

		if ( $primaryHoverColor ) {
			$iconViewHoverCSS['background'] = $primaryHoverColor;
		}

		return array_merge(
			Border::get_hover_css( $attributes[ $attributePrefix . 'Border' ], 'border', $device ),
			BoxShadow::get_hover_css( $attributes[ $attributePrefix . 'BoxShadow' ], '', $device ),
			$iconViewHoverCSS
		);
	}

	public static function get_element_css( $attributes, $device = '', $attributePrefix = 'icon' ) {
		$iconType = $attributes[ $attributePrefix . 'Type' ] ?? 'default';
		$iconShape = $attributes[ $attributePrefix . 'Shape' ] ?? '';
		$primaryColor = Color::get_css( $attributes[ $attributePrefix . 'Color' ] ?? '#69727d' );
		$rotate = ! empty( $attributes[ $attributePrefix . 'Rotated' ][ 'value' . $device ] ) ?? 0;
		$iconViewCSS = [];

		if ( ! empty( $attributes[ $attributePrefix . 'Rotated' ][ 'value' . $device ] ) ) {
			$rotate = $attributes[ $attributePrefix . 'Rotated' ][ 'value' . $device ];
		}

		if ( $iconType !== 'default' ) {
			$iconViewCSS['fill'] = $primaryColor;
		}

		if ( $rotate ) {
			$iconViewCSS['transform'] = 'rotate(' . $rotate . 'deg)';
		}

		return array_merge(
			[ 'fill' => $primaryColor ],
			$iconViewCSS
		);
	}

	public static function get_element_image_css( $attributes, $device = '', $attributePrefix = 'icon' ) {
		$rotate = ! empty( $attributes[ $attributePrefix . 'Rotated' ][ 'value' . $device ] ) ?? 0;
		$iconViewCSS = [];

		if ( isset( $attributes[ $attributePrefix . 'Rotated' ][ 'value' . $device ] ) ) {
			$rotate = $attributes[ $attributePrefix . 'Rotated' ][ 'value' . $device ];
		}

		if ( $rotate ) {
			$iconViewCSS['transform'] = 'rotate(' . $rotate . 'deg)';
		}
		if ( isset( $attributes[ $attributePrefix . 'ImageUrl' ] ) ) {
			$image_size_value = isset( $attributes[ $attributePrefix . 'Sizing' ][ 'value' . $device ] )
				? $attributes[ $attributePrefix . 'Sizing' ][ 'value' . $device ]
				: $attributes[ $attributePrefix . 'Sizing' ]['value'];

			$image_size_unit = isset( $attributes[ $attributePrefix . 'Sizing' ][ 'valueUnit' . $device ] )
				? $attributes[ $attributePrefix . 'Sizing' ][ 'valueUnit' . $device ]
				: ( isset( $attributes[ $attributePrefix . 'Sizing' ]['valueUnit'] )
					? $attributes[ $attributePrefix . 'Sizing' ]['valueUnit']
					: '' );

			if ( empty( $image_size_unit ) ) {
				$image_size_unit = 'px';
			}

			$iconViewCSS['width'] = "{$image_size_value}{$image_size_unit}";
		}

		return array_merge(
			[
				'height' => 'auto'
			],
			$iconViewCSS
		);
	}

	public static function get_element_image_hover_css( $attributes, $device = '', $attributePrefix = 'icon' ) {
		$rotateHover = $attributes[ $attributePrefix . 'RotateHover' ][ 'value' . $device ] ?? 0;
		$transitionHover = $attributes[ $attributePrefix . 'transitionDuration' ] ?? 0;
		$HoverColor = Color::get_css( $attributes[ $attributePrefix . 'HoverColor' ] ?? '' );
		$iconViewCSS = [];
		if ( isset( $attributes[ $attributePrefix . 'RotateHover' ][ 'value' . $device ] ) ) {
			$rotateHover = $attributes[ $attributePrefix . 'RotateHover' ][ 'value' . $device ];
		}
		if ( $rotateHover ) {
			$iconViewCSS['transform'] = 'rotate(' . $rotateHover . 'deg)';
		}
		if ( $transitionHover ) {
			$iconViewCSS['transition'] = $transitionHover . 's';
		}

		if ( $HoverColor ) {
			$iconViewCSS['fill'] = $HoverColor;
		}
		return array_merge(
			$iconViewCSS
		);
	}

	// copy from control-base-abstract-two.php
	public static function get_unit( $args ) {
		$defaultUnit = $args['unitDefaultValue'];
		$value = $args['attributeValue'];
		$device = $args['device'];
		$keyPrefix = $args['attributeObjectKey'] . 'Unit';

		// Retrieve units with fallback to default
		$unitDesktop = Helper::get_array_value( $value, $keyPrefix, $defaultUnit ); // Desktop
		$unitTablet = Helper::get_array_value( $value, $keyPrefix . 'Tablet', $unitDesktop ); // Tablet inherits from Desktop
		$unitMobile = Helper::get_array_value( $value, $keyPrefix . 'Mobile', $unitTablet ); // Mobile inherits from Tablet

		// Return the appropriate unit based on the device
		if ( '' === $device ) {
			return $unitDesktop; // Desktop
		}

		if ( 'Tablet' === $device ) {
			return $unitTablet; // Tablet
		}

		if ( 'Mobile' === $device ) {
			return $unitMobile; // Mobile
		}

		return $defaultUnit; // Default fallback
	}
}
