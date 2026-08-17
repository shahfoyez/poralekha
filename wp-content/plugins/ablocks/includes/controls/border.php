<?php
namespace ABlocks\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\ControlBaseAbstract;
use ABlocks\Controls\Color;

class Border extends ControlBaseAbstract {
	public static function get_attribute_default_value( $is_responsive = false ) {

		if ( $is_responsive ) {
			return array(
				'borderStyle' => 'default',
				'borderStyleH' => 'default',
				// border color
				'borderColor' => '',
				// border color - Hover
				'borderColorH' => '',
				// width
				'isLinkedWidth' => true,
				'isLinkedWidthTablet' => true,
				'isLinkedWidthMobile' => true,
				'commonWidth' => '',
				'commonWidthTablet' => '',
				'commonWidthMobile' => '',
				'topWidth' => '',
				'rightWidth' => '',
				'bottomWidth' => '',
				'leftWidth' => '',
				'topWidthTablet' => '',
				'rightWidthTablet' => '',
				'bottomWidthTablet' => '',
				'leftWidthTablet' => '',
				'topWidthMobile' => '',
				'rightWidthMobile' => '',
				'bottomWidthMobile' => '',
				'leftWidthMobile' => '',
				'unitWidth' => 'px',
				'unitWidthTablet' => '',
				'unitWidthMobile' => '',
				'isLinkedWidthH' => true,
				'isLinkedWidthHTablet' => true,
				'isLinkedWidthHMobile' => true,
				// Width Hover
				'commonWidthH' => '',
				'commonWidthHTablet' => '',
				'commonWidthHMobile' => '',
				'topWidthH' => '',
				'rightWidthH' => '',
				'bottomWidthH' => '',
				'leftWidthH' => '',
				'topWidthHTablet' => '',
				'rightWidthHTablet' => '',
				'bottomWidthHTablet' => '',
				'leftWidthHTablet' => '',
				'topWidthHMobile' => '',
				'rightWidthHMobile' => '',
				'bottomWidthHMobile' => '',
				'leftWidthHMobile' => '',
				'unitWidthH' => 'px',
				'unitWidthHTablet' => '',
				'unitWidthHMobile' => '',
				// Radius
				'isLinkedRadius' => true,
				'isLinkedRadiusTablet' => true,
				'isLinkedRadiusMobile' => true,
				'commonRadius' => '',
				'commonRadiusTablet' => '',
				'commonRadiusMobile' => '',
				'topRadius' => '',
				'rightRadius' => '',
				'bottomRadius' => '',
				'leftRadius' => '',
				'topRadiusTablet' => '',
				'rightRadiusTablet' => '',
				'bottomRadiusTablet' => '',
				'leftRadiusTablet' => '',
				'topRadiusMobile' => '',
				'rightRadiusMobile' => '',
				'bottomRadiusMobile' => '',
				'leftRadiusMobile' => '',
				'unitRadius' => 'px',
				'unitRadiusTablet' => '',
				'unitRadiusMobile' => '',
				// Radius Hover
				'isLinkedRadiusH' => true,
				'isLinkedRadiusHTablet' => true,
				'isLinkedRadiusHMobile' => true,
				'commonRadiusH' => '',
				'commonRadiusHTablet' => '',
				'commonRadiusHMobile' => '',
				'topRadiusH' => '',
				'rightRadiusH' => '',
				'bottomRadiusH' => '',
				'leftRadiusH' => '',
				'topRadiusHTablet' => '',
				'rightRadiusHTablet' => '',
				'bottomRadiusHTablet' => '',
				'leftRadiusHTablet' => '',
				'topRadiusHMobile' => '',
				'rightRadiusHMobile' => '',
				'bottomRadiusHMobile' => '',
				'leftRadiusHMobile' => '',
				'unitRadiusH' => 'px',
				'unitRadiusHTablet' => '',
				'unitRadiusHMobile' => '',
				'transitionDuration' => ''
			);
		}//end if
		return [
			'borderStyle' => 'default',
			'borderStyleH' => 'default',
			// border color
			'borderColor' => '',
			// border color - Hover
			'borderColorH' => '',
			// border width - Normal
			'isLinkedWidth' => true,
			'isLinkedWidthTablet' => true,
			'isLinkedWidthMobile' => true,
			'commonWidth' => '',
			'topWidth' => '',
			'rightWidth' => '',
			'bottomWidth' => '',
			'leftWidth' => '',
			'unitWidth' => 'px',

			// border width - Hover
			'isLinkedWidthH' => true,
			'isLinkedWidthHTablet' => true,
			'isLinkedWidthHMobile' => true,
			'commonWidthH' => '',
			'topWidthH' => '',
			'rightWidthH' => '',
			'bottomWidthH' => '',
			'leftWidthH' => '',
			'unitWidthH' => 'px',

			// border Radius
			'isLinkedRadius' => true,
			'commonRadius' => '',
			'topRadius' => '',
			'rightRadius' => '',
			'bottomRadius' => '',
			'leftRadius' => '',
			'unitRadius' => 'px',

			// border RadiusH - Hover
			'isLinkedRadiusH' => true,
			'isLinkedRadiusHTablet' => true,
			'isLinkedRadiusHMobile' => true,
			'commonRadiusH' => '',
			'topRadiusH' => '',
			'leftRadiusH' => '',
			'unitRadiusH' => 'px',
			'transitionDuration' => '',
		];
	}

	public static function get_attribute( $attributeName, $isResponsive = false ) {
		$attribute_value = self::get_attribute_default_value( $isResponsive );
		if ( $isResponsive ) {
			return [
				$attributeName => [
					'type' => 'object',
					'default' => $attribute_value
				]
			];
		}
		return [
			$attributeName => [
				'type' => 'object',
				'default' => $attribute_value
			]
		];
	}
	public static function get_css( $attribute_value, $property = '', $device = '' ) {
		$value = wp_parse_args( $attribute_value, self::get_attribute_default_value( true ) );
		$css = [];

		// Separate handling of width units
		if ( $device ) {
			$widthUnit = self::get_unit([
				'unit' => $value['unitWidth'],
				'unitTablet' => $value['unitWidthTablet'],
				'unitMobile' => $value['unitWidthMobile'],
			], $device);
		} else {
			$widthUnit = $value['unitWidth'];
		}

		// Handle width
		if ( $value[ 'isLinkedWidth' . $device ] ) {
			if ( '' !== $value[ 'commonWidth' . $device ] ) {
				$css['border-width'] = $value[ 'commonWidth' . $device ] . $widthUnit;
			}
		} else {
			// Only emit border-width when at least one side was actually set —
			// otherwise every unlinked border produced "border-width:0px 0px 0px 0px".
			$has_width = '' !== $value[ 'topWidth' . $device ] || '' !== $value[ 'rightWidth' . $device ]
				|| '' !== $value[ 'bottomWidth' . $device ] || '' !== $value[ 'leftWidth' . $device ];
			if ( $has_width ) {
				$topWidth = ! empty( $value[ 'topWidth' . $device ] ) ? $value[ 'topWidth' . $device ] : 0;
				$rightWidth = ! empty( $value[ 'rightWidth' . $device ] ) ? $value[ 'rightWidth' . $device ] : 0;
				$bottomWidth = ! empty( $value[ 'bottomWidth' . $device ] ) ? $value[ 'bottomWidth' . $device ] : 0;
				$leftWidth = ! empty( $value[ 'leftWidth' . $device ] ) ? $value[ 'leftWidth' . $device ] : 0;

				$borderWidth = $topWidth . $widthUnit . ' ' . $rightWidth . $widthUnit . ' ' . $bottomWidth . $widthUnit . ' ' . $leftWidth . $widthUnit;

				$css['border-width'] = $borderWidth;
			}
		}

		// Handle border style and color
		if ( '' !== $value['borderStyle'] && 'default' !== $value['borderStyle'] ) {
			$css['border-style'] = $value['borderStyle'];
		}
		if ( '' !== $value['borderColor'] ) {
			$css['border-color'] = Color::get_css( $value['borderColor'] );
		}

		// Separate handling of radius units
		if ( $device ) {
			$radiusUnit = self::get_unit([
				'unit' => $value['unitRadius'],
				'unitTablet' => $value['unitRadiusTablet'],
				'unitMobile' => $value['unitRadiusMobile'],
			], $device);
		} else {
			$radiusUnit = $value['unitRadius'];
		}

		// Handle radius
		if ( $value[ 'isLinkedRadius' . $device ] ) {
			if ( '' !== $value[ 'commonRadius' . $device ] ) {
				$css['border-radius'] = $value[ 'commonRadius' . $device ] . $radiusUnit;
			}
		} else {
			if ( '' !== $value[ 'topRadius' . $device ] ) {
				$css['border-top-left-radius'] = $value[ 'topRadius' . $device ] . $radiusUnit;
			}
			if ( '' !== $value[ 'rightRadius' . $device ] ) {
				$css['border-top-right-radius'] = $value[ 'rightRadius' . $device ] . $radiusUnit;
			}
			if ( '' !== $value[ 'bottomRadius' . $device ] ) {
				$css['border-bottom-right-radius'] = $value[ 'bottomRadius' . $device ] . $radiusUnit;
			}
			if ( '' !== $value[ 'leftRadius' . $device ] ) {
				$css['border-bottom-left-radius'] = $value[ 'leftRadius' . $device ] . $radiusUnit;
			}
		}

		// Handle transition duration
		if ( self::has_value( $value['transitionDuration'] ) ) {
			$css['transition'] = "border {$value['transitionDuration']}s, border-radius {$value['transitionDuration']}s";
		}

		return $css;
	}

	public static function get_hover_css( $attribute_value, $property = '', $device = '' ) {
		$value = wp_parse_args( $attribute_value, self::get_attribute_default_value( true ) ); // avoid Undefined error
		$css = [];

		// Handle width units based on device
		$widthUnit = self::get_unit( [
			'unit' => $value['unitWidthH'],
			'unitTablet' => $value['unitWidthHTablet'],
			'unitMobile' => $value['unitWidthHMobile'],
		], $device );

		if ( ! empty( $value['borderStyleH'] ) && 'default' !== $value['borderStyleH'] ) {
			// Handle hover width
			if ( ! empty( $value[ 'isLinkedWidthH' . $device ] ) ) {
				if ( '' !== $value[ 'commonWidthH' . $device ] ) {
					$css['border-width'] = $value[ 'commonWidthH' . $device ] . $widthUnit;
				}
			} else {
				$topWidth = ! empty( $value[ 'topWidthH' . $device ] ) ? $value[ 'topWidthH' . $device ] : 0;
				$rightWidth = ! empty( $value[ 'rightWidthH' . $device ] ) ? $value[ 'rightWidthH' . $device ] : 0;
				$bottomWidth = ! empty( $value[ 'bottomWidthH' . $device ] ) ? $value[ 'bottomWidthH' . $device ] : 0;
				$leftWidth = ! empty( $value[ 'leftWidthH' . $device ] ) ? $value[ 'leftWidthH' . $device ] : 0;

				$css['border-width'] = $topWidth . $widthUnit . ' ' . $rightWidth . $widthUnit . ' ' . $bottomWidth . $widthUnit . ' ' . $leftWidth . $widthUnit;
			}

			// Handle hover border color
			if ( ! empty( $value['borderColorH'] ) ) {
				$css['border-color'] = Color::get_css( $value['borderColorH'] );
			}
		}

		// Handle hover border style
		if ( ! empty( $value['borderStyleH'] ) && 'default' !== $value['borderStyleH'] ) {
			$css['border-style'] = $value['borderStyleH'];
		}

		// Handle radius units based on device
		$radiusUnit = self::get_unit( [
			'unit' => $value['unitRadiusH'],
			'unitTablet' => $value['unitRadiusHTablet'],
			'unitMobile' => $value['unitRadiusHMobile'],
		], $device );

		// Handle hover border radius
		if ( ! empty( $value[ 'isLinkedRadiusH' . $device ] ) ) {
			if ( '' !== ( $value[ 'commonRadiusH' . $device ] ?? '' ) ) {
				$css['border-radius'] = $value[ 'commonRadiusH' . $device ] . $radiusUnit;
			}
		} else {
			if ( isset( $value[ 'topRadiusH' . $device ] ) && '' !== $value[ 'topRadiusH' . $device ] ) {
				$css['border-top-left-radius'] = $value[ 'topRadiusH' . $device ] . $radiusUnit;
			}
			if ( isset( $value[ 'rightRadiusH' . $device ] ) && '' !== $value[ 'rightRadiusH' . $device ] ) {
				$css['border-top-right-radius'] = $value[ 'rightRadiusH' . $device ] . $radiusUnit;
			}
			if ( isset( $value[ 'bottomRadiusH' . $device ] ) && '' !== $value[ 'bottomRadiusH' . $device ] ) {
				$css['border-bottom-right-radius'] = $value[ 'bottomRadiusH' . $device ] . $radiusUnit;
			}
			if ( isset( $value[ 'leftRadiusH' . $device ] ) && '' !== $value[ 'leftRadiusH' . $device ] ) {
				$css['border-bottom-left-radius'] = $value[ 'leftRadiusH' . $device ] . $radiusUnit;
			}
		}

		return $css;

	}
}
