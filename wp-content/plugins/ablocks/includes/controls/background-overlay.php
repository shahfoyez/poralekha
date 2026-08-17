<?php
namespace ABlocks\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

use ABlocks\Classes\ControlBaseAbstract;
use ABlocks\Controls\Color;

class BackgroundOverlay extends ControlBaseAbstract {

	public static function get_attribute_default_value( $is_responsive = false ) {
		return [
			'backgroundOverlayType' => 'none',
			'backgroundOverlayTypeH' => 'none',
			// Normal background image attribute
			'imgId' => '',
			'imgIdTablet' => '',
			'imgIdMobile' => '',
			'imgUrl' => '',
			'imgUrlTablet' => '',
			'imgUrlMobile' => '',
			'imgSize' => '',
			'imgSizeTablet' => '',
			'imgSizeMobile' => '',
			'imgPosition' => '',
			'imgPositionTablet' => '',
			'imgPositionMobile' => '',
			'imgXPositionUnit' => 'px',
			'imgXPositionUnitTablet' => 'px',
			'imgXPositionUnitMobile' => 'px',
			'imgXPositionTabletUnit' => '',
			'imgXPositionMobileUnit' => '',
			'imgXPosition' => '',
			'imgXPositionTablet' => '',
			'imgXPositionMobile' => '',
			'imgYPositionUnit' => 'px',
			'imgYPositionUnitTablet' => '',
			'imgYPositionUnitMobile' => '',
			'imgYPosition' => '',
			'imgYPositionTablet' => '',
			'imgYPositionMobile' => '',
			'imgAttachment' => '',
			'imgRepeat' => '',
			'imgRepeatTablet' => '',
			'imgRepeatMobile' => '',
			'imgDisplaySize' => '',
			'imgDisplaySizeTablet' => '',
			'imgDisplaySizeMobile' => '',
			'imgDisplaySizeWidth' => '',
			'imgDisplaySizeWidthTablet' => '',
			'imgDisplaySizeWidthMobile' => '',
			'imgDisplaySizeWidthUnit' => 'px',
			'imgDisplaySizeWidthUnitTablet' => '',
			'imgDisplaySizeWidthUnitMobile' => '',
			// Hover background image attribute
			'imgIdH' => '',
			'imgIdTabletH' => '',
			'imgIdMobileH' => '',
			'imgUrlH' => '',
			'imgUrlHTablet' => '',
			'imgUrlHMobile' => '',
			'imgSizeH' => '',
			'imgSizeHTablet' => '',
			'imgSizeHMobile' => '',
			'imgPositionH' => '',
			'imgPositionHTablet' => '',
			'imgPositionHMobile' => '',
			'imgXPositionUnitH' => 'px',
			'imgXPositionUnitHTablet' => '',
			'imgXPositionUnitHMobile' => '',
			'imgXPositionH' => '',
			'imgXPositionHTablet' => '',
			'imgXPositionHMobile' => '',
			'imgYPositionUnitH' => 'px',
			'imgYPositionUnitHTablet' => '',
			'imgYPositionUnitHMobile' => '',
			'imgYPositionH' => '',
			'imgYPositionHTablet' => '',
			'imgYPositionHMobile' => '',
			'imgAttachmentH' => '',
			'imgRepeatH' => '',
			'imgRepeatHTablet' => '',
			'imgRepeatHMobile' => '',
			'imgDisplaySizeH' => '',
			'imgDisplaySizeHTablet' => '',
			'imgDisplaySizeHMobile' => '',
			'imgDisplaySizeWidthH' => '',
			'imgDisplaySizeWidthHTablet' => '',
			'imgDisplaySizeWidthHMobile' => '',
			'imgDisplaySizeWidthHUnit' => 'px',
			'imgDisplaySizeWidthHUnitTablet' => '',
			'imgDisplaySizeWidthHUnitMobile' => '',

			'blendMode' => '',
			'blur' => '',
			'brightness' => '',
			'contrast' => '',
			'saturate' => '',
			'hue' => '',
			'opacity' => '',
			'opacityTablet' => '',
			'opacityMobile' => '',
			// HOVER
			'blendModeH' => '',
			'blurH' => '',
			'brightnessH' => '',
			'contrastH' => '',
			'saturateH' => '',
			'hueH' => '',
			'opacityH' => '',
			'opacityHTablet' => '',
			'opacityHMobile' => '',

			'transitionDuration' => '',
		];
	}

	public static function get_attribute(
		$attribute_name,
		$is_responsive = false
	) {
		$default_value = self::get_attribute_default_value( $is_responsive );

		return [
			$attribute_name => [
				'type' => 'object',
				'default' => $default_value,
			],
		];
	}

	public static function get_css(
		$attribute_value,
		$property = '',
		$device = ''
	) {
	}

	public static function get_before_css(
		$attribute_value,
		$extra_border_value,
		$property = '',
		$device = ''
	) {
		// Merge default values with the provided attribute values
		$value = array_merge(
			self::get_attribute_default_value( $device ? true : false ),
			$attribute_value
		);

		// Get the units for X and Y position, and display size width
		$unitX_position_unit = self::get_unit(
			[
				'unit' => $value['imgXPositionUnit'],
				'unitTablet' => $value['imgXPositionUnitTablet'],
				'unitMobile' => $value['imgXPositionUnitMobile'],
			],
			$device
		);

		$unitY_position_unit = self::get_unit(
			[
				'unit' => $value['imgYPositionUnit'],
				'unitTablet' => $value['imgYPositionUnitTablet'],
				'unitMobile' => $value['imgYPositionUnitMobile'],
			],
			$device
		);

		$display_size_width_unit = self::get_unit(
			[
				'unit' => $value['imgDisplaySizeWidthUnit'],
				'unitTablet' => $value['imgDisplaySizeWidthUnitTablet'],
				'unitMobile' => $value['imgDisplaySizeWidthUnitMobile'],
			],
			$device
		);

		$css = [];

		$topDiff = ! empty( $extra_border_value['commonWidth'] ) ? intval( $extra_border_value['commonWidth'] ) : intval( $extra_border_value['topWidth'] ?? 0 );
		$bottomDiff = ! empty( $extra_border_value['commonWidth'] ) ? intval( $extra_border_value['commonWidth'] ) : intval( $extra_border_value['bottomWidth'] ?? 0 );
		$leftDiff = ! empty( $extra_border_value['commonWidth'] ) ? intval( $extra_border_value['commonWidth'] ) : intval( $extra_border_value['leftWidth'] ?? 0 );
		$rightDiff = ! empty( $extra_border_value['commonWidth'] ) ? intval( $extra_border_value['commonWidth'] ) : intval( $extra_border_value['rightWidth'] ?? 0 );

		// Radius calculations
		$topRadiusValue = ! empty( $extra_border_value['commonRadius'] ) ? intval( $extra_border_value['commonRadius'] ) : intval( $extra_border_value['topRadius'] ?? 0 );
		$leftRadiusValue = ! empty( $extra_border_value['commonRadius'] ) ? intval( $extra_border_value['commonRadius'] ) : intval( $extra_border_value['leftRadius'] ?? 0 );
		$rightRadiusValue = ! empty( $extra_border_value['commonRadius'] ) ? intval( $extra_border_value['commonRadius'] ) : intval( $extra_border_value['rightRadius'] ?? 0 );
		$bottomRadiusValue = ! empty( $extra_border_value['commonRadius'] ) ? intval( $extra_border_value['commonRadius'] ) : intval( $extra_border_value['bottomRadius'] ?? 0 );

		// Ensure no negative values
		$css['border-top-left-radius'] = max( $topRadiusValue - $topDiff, 0 ) . 'px';
		$css['border-top-right-radius'] = max( $rightRadiusValue - $rightDiff, 0 ) . 'px';
		$css['border-bottom-left-radius'] = max( $bottomRadiusValue - $bottomDiff, 0 ) . 'px';
		$css['border-bottom-right-radius'] = max( $leftRadiusValue - $leftDiff, 0 ) . 'px';

		// Handle transition duration
		if ( self::has_value( $value['transitionDuration'] ) ) {
			$css['transition'] = "border {$value['transitionDuration']}s, border-radius {$value['transitionDuration']}s";
		}

		// If background is set to image
		if ( $value['backgroundOverlayType'] === 'image' ) {
			$img_url_key = 'imgUrl' . $device;
			if ( ! empty( $value[ $img_url_key ] ) ) {
				$filter_values = [
					'brightness' =>
						'brightness(' . ( $value['brightness'] ?? 100 ) . '%)',
					'contrast' =>
						'contrast(' . ( $value['contrast'] ?? 100 ) . '%)',
					'saturate' =>
						'saturate(' . ( $value['saturate'] ?? 100 ) . '%)',
					'blur' => 'blur(' . ( $value['blur'] ?? 0 ) . 'px)',
					'hue' => 'hue-rotate(' . ( $value['hue'] ?? 0 ) . 'deg)',
				];

				// CSS for background image and ::before pseudo element
				$css[ "{$property}-image" ] =
					'url("' . $value[ $img_url_key ] . '")';
				$css['position'] = 'absolute';
				$css['content'] = "''";
				$css['top'] = '0';
				$css['left'] = '0';
				$css['width'] = '100%';
				$css['height'] = '100%';

				// Handle background-position
				$img_position_key = 'imgPosition' . $device;
				if ( ! empty( $value[ $img_position_key ] ) ) {
					if ( $value[ $img_position_key ] === 'custom' ) {
						$css[ "{$property}-position" ] =
							( $value[ 'imgXPosition' . $device ] ?? '0' ) .
							$unitX_position_unit .
							' ' .
							( $value[ 'imgYPosition' . $device ] ?? '0' ) .
							$unitY_position_unit;
					} else {
						$css[ "{$property}-position" ] =
							$value[ $img_position_key ];
					}
				}

				// Handle background attachment
				$img_attachment_key = 'imgAttachment' . $device;
				if ( ! empty( $value[ $img_attachment_key ] ) ) {
					$css[ "{$property}-attachment" ] =
						$value[ $img_attachment_key ];
				}

				// Handle background repeat
				$img_repeat_key = 'imgRepeat' . $device;
				if ( ! empty( $value[ $img_repeat_key ] ) ) {
					$css[ "{$property}-repeat" ] = $value[ $img_repeat_key ];
				}

				// Handle background size
				$img_display_size_key = 'imgDisplaySize' . $device;
				if ( ! empty( $value[ $img_display_size_key ] ) ) {
					if ( $value[ $img_display_size_key ] === 'custom' ) {
						$css[ "{$property}-size" ] =
							$value[ 'imgDisplaySizeWidth' . $device ] .
							$display_size_width_unit;
					} else {
						$css[ "{$property}-size" ] =
							$value[ $img_display_size_key ];
					}
				}

				// Handle opacity
				if ( $device === 'Tablet' && isset( $value['opacityTablet'] ) ) {
					$css['opacity'] = $value['opacityTablet'];
				} elseif ( $device === 'Mobile' && isset( $value['opacityMobile'] ) ) {
					$css['opacity'] = $value['opacityMobile'];
				} elseif ( isset( $value['opacity'] ) ) {
					$css['opacity'] = $value['opacity'];
				}

				// Handle blend mode
				if ( ! empty( $value['blendMode'] ) ) {
					$css['mix-blend-mode'] = $value['blendMode'];
				}

				// Add filter CSS
				$css['filter'] = implode( ' ', $filter_values );

				// Handle transition duration
				if ( ! empty( $value['transitionDuration'] ) ) {
					$css['transition'] =
						'all ' . $value['transitionDuration'] . 's';
				}
			}//end if
		} elseif ( $value['backgroundOverlayType'] === 'color' ) {
			$background_color_key = 'backgroundColor' . $device;
			if ( ! empty( $value[ $background_color_key ] ) ) {
				$css['position'] = 'absolute';
				$css['content'] = "''";
				$css['top'] = '0';
				$css['left'] = '0';
				$css['width'] = '100%';
				$css['height'] = '100%';
				$css[ $property ] = Color::get_css( esc_attr( $value[ $background_color_key ] ) );

				// Handle opacity

				if ( $device === 'Tablet' && isset( $value['opacityTablet'] ) ) {
					$css['opacity'] = $value['opacityTablet'];
				} elseif ( $device === 'Mobile' && isset( $value['opacityMobile'] ) ) {
					$css['opacity'] = $value['opacityMobile'];
				} elseif ( isset( $value['opacity'] ) ) {
					$css['opacity'] = $value['opacity'];
				}

				// Handle blend mode
				if ( ! empty( $value['blendMode'] ) ) {
					$css['mix-blend-mode'] = $value['blendMode'];
				}

				// Add filter CSS
				$filter_values = [
					'brightness' =>
						'brightness(' . ( $value['brightness'] ?? 100 ) . '%)',
					'contrast' =>
						'contrast(' . ( $value['contrast'] ?? 100 ) . '%)',
					'saturate' =>
						'saturate(' . ( $value['saturate'] ?? 100 ) . '%)',
					'blur' => 'blur(' . ( $value['blur'] ?? 0 ) . 'px)',
					'hue' => 'hue-rotate(' . ( $value['hue'] ?? 0 ) . 'deg)',
				];
				$css['filter'] = implode( ' ', $filter_values );

				// Handle transition duration
				if ( ! empty( $value['transitionDuration'] ) ) {
					$css['transition'] =
						'all ' . $value['transitionDuration'] . 's';
				}
			}//end if
		}//end if

		return $css;
	}

	public static function get_before_hover_css(
		$attribute_value,
		$extra_border_value,
		$property = '',
		$device = ''
	) {
		// Merge default values with the provided attribute values
		$value = array_merge(
			self::get_attribute_default_value( $device ? true : false ),
			$attribute_value
		);
		// Get the units for X and Y position hover states and display size hover width
		$unit_x_position_unit_h = self::get_unit(
			[
				'unit' => $value['imgXPositionUnitH'],
				'unitTablet' => $value['imgXPositionUnitHTablet'],
				'unitMobile' => $value['imgXPositionUnitHMobile'],
			],
			$device
		);

		$unit_y_position_unit_h = self::get_unit(
			[
				'unit' => $value['imgYPositionUnitH'],
				'unitTablet' => $value['imgYPositionUnitHTablet'],
				'unitMobile' => $value['imgYPositionUnitHMobile'],
			],
			$device
		);

		$img_display_size_width_h_unit = self::get_unit(
			[
				'unit' => $value['imgDisplaySizeWidthHUnit'],
				'unitTablet' => $value['imgDisplaySizeWidthHUnitTablet'],
				'unitMobile' => $value['imgDisplaySizeWidthHMobile'],
			],
			$device
		);

		$css = [];
		$topDiffH = ! empty( $extra_border_value['commonWidthH'] ) ? intval( $extra_border_value['commonWidthH'] ) : intval( $extra_border_value['topWidth'] ?? 0 );
		$bottomDiffH = ! empty( $extra_border_value['commonWidthH'] ) ? intval( $extra_border_value['commonWidthH'] ) : intval( $extra_border_value['bottomWidth'] ?? 0 );
		$leftDiffH = ! empty( $extra_border_value['commonWidthH'] ) ? intval( $extra_border_value['commonWidthH'] ) : intval( $extra_border_value['leftWidth'] ?? 0 );
		$rightDiffH = ! empty( $extra_border_value['commonWidthH'] ) ? intval( $extra_border_value['commonWidthH'] ) : intval( $extra_border_value['rightWidth'] ?? 0 );

		// Radius calculations
		$topRadiusValueH = ! empty( $extra_border_value['commonRadiusH'] ) ? intval( $extra_border_value['commonRadiusH'] ) : intval( $extra_border_value['topRadius'] ?? 0 );
		$leftRadiusValueH = ! empty( $extra_border_value['commonRadiusH'] ) ? intval( $extra_border_value['commonRadiusH'] ) : intval( $extra_border_value['leftRadius'] ?? 0 );
		$rightRadiusValueH = ! empty( $extra_border_value['commonRadiusH'] ) ? intval( $extra_border_value['commonRadiusH'] ) : intval( $extra_border_value['rightRadius'] ?? 0 );
		$bottomRadiusValueH = ! empty( $extra_border_value['commonRadiusH'] ) ? intval( $extra_border_value['commonRadiusH'] ) : intval( $extra_border_value['bottomRadius'] ?? 0 );

		// Ensure no negative values
		$css['border-top-left-radius'] = max( $topRadiusValueH - $topDiffH, 0 ) . 'px';
		$css['border-top-right-radius'] = max( $rightRadiusValueH - $rightDiffH, 0 ) . 'px';
		$css['border-bottom-left-radius'] = max( $bottomRadiusValueH - $bottomDiffH, 0 ) . 'px';
		$css['border-bottom-right-radius'] = max( $leftRadiusValueH - $leftDiffH, 0 ) . 'px';

		// If hover background is set to image
		if ( $value['backgroundOverlayTypeH'] === 'image' ) {
			$img_url_h_key = 'imgUrlH' . $device;

			if ( ! empty( $value[ $img_url_h_key ] ) ) {
				$filter_values_h = [
					'brightness' =>
						'brightness(' . ( $value['brightnessH'] ?? 100 ) . '%)',
					'contrast' =>
						'contrast(' . ( $value['contrastH'] ?? 100 ) . '%)',
					'saturate' =>
						'saturate(' . ( $value['saturateH'] ?? 100 ) . '%)',
					'blur' => 'blur(' . ( $value['blurH'] ?? 0 ) . 'px)',
					'hue' => 'hue-rotate(' . ( $value['hueH'] ?? 0 ) . 'deg)',
				];

				// CSS for background image and ::before pseudo element on hover
				$css[ "{$property}-image" ] =
					'url("' . esc_url( $value[ $img_url_h_key ] ) . '")';
				$css['position'] = 'absolute';
				$css['content'] = "''";
				$css['top'] = '0';
				$css['left'] = '0';
				$css['width'] = '100%';
				$css['height'] = '100%';

				// Handle background-position on hover
				$img_position_h_key = 'imgPositionH' . $device;
				if ( ! empty( $value[ $img_position_h_key ] ) ) {
					if ( $value[ $img_position_h_key ] === 'custom' ) {
						$css[ "{$property}-position" ] =
							( $value[ 'imgXPositionH' . $device ] ?? '0' ) .
							$unit_x_position_unit_h .
							' ' .
							( $value[ 'imgYPositionH' . $device ] ?? '0' ) .
							$unit_y_position_unit_h;
					} else {
						$css[ "{$property}-position" ] = esc_attr(
							$value[ $img_position_h_key ]
						);
					}
				}

				// Handle background attachment on hover
				if ( ! empty( $value['imgAttachmentH'] ) ) {
					$css[ "{$property}-attachment" ] = esc_attr(
						$value['imgAttachmentH']
					);
				}

				// Handle background repeat on hover
				if ( ! empty( $value[ 'imgRepeatH' . $device ] ) ) {
					$css[ "{$property}-repeat" ] = esc_attr(
						$value[ 'imgRepeatH' . $device ]
					);
				}

				// Handle background size on hover
				$img_display_size_h_key = 'imgDisplaySizeH' . $device;
				if ( ! empty( $value[ $img_display_size_h_key ] ) ) {
					if ( $value[ $img_display_size_h_key ] === 'custom' ) {
						$css[ "{$property}-size" ] =
							esc_attr( $value[ 'imgDisplaySizeWidthH' . $device ] ) .
							esc_attr( $img_display_size_width_h_unit );
					} else {
						$css[ "{$property}-size" ] = esc_attr(
							$value[ $img_display_size_h_key ]
						);
					}
				}
				// Handle opacity on hover
				if ( $device === 'Tablet' && isset( $value['opacityHTablet'] ) ) {
					$css['opacity'] = $value['opacityHTablet'];
				} elseif ( $device === 'Mobile' && isset( $value['opacityHMobile'] ) ) {
					$css['opacity'] = $value['opacityHMobile'];
				} elseif ( isset( $value['opacityH'] ) ) {
					$css['opacity'] = $value['opacityH'];
				}
				// Handle blend mode on hover
				if ( ! empty( $value['blendModeH'] ) ) {
					$css['mix-blend-mode'] = esc_attr( $value['blendModeH'] );
				}

				// Add filter CSS on hover
				$css['filter'] = implode( ' ', $filter_values_h );
			}//end if
		} elseif ( $value['backgroundOverlayTypeH'] === 'color' ) {
			// If hover background is set to color
			$background_color_h_key = 'backgroundColorH' . $device;
			if ( ! empty( $value[ $background_color_h_key ] ) ) {
				$css['position'] = 'absolute';
				$css['content'] = "''";
				$css['top'] = '0';
				$css['left'] = '0';
				$css['width'] = '100%';
				$css['height'] = '100%';
				$css[ $property ] = Color::get_css( esc_attr( $value[ $background_color_h_key ] ) );

				if ( $device === 'Tablet' && isset( $value['opacityHTablet'] ) ) {
					$css['opacity'] = $value['opacityHTablet'];
				} elseif ( $device === 'Mobile' && isset( $value['opacityHMobile'] ) ) {
					$css['opacity'] = $value['opacityHMobile'];
				} elseif ( isset( $value['opacityH'] ) ) {
					$css['opacity'] = $value['opacityH'];
				}

				// Handle blend mode on hover
				if ( ! empty( $value['blendModeH'] ) ) {
					$css['mix-blend-mode'] = esc_attr( $value['blendModeH'] );
				}

				// Add filter CSS on hover
				$filter_values_h = [
					'brightness' =>
						'brightness(' . ( $value['brightnessH'] ?? 100 ) . '%)',
					'contrast' =>
						'contrast(' . ( $value['contrastH'] ?? 100 ) . '%)',
					'saturate' =>
						'saturate(' . ( $value['saturateH'] ?? 100 ) . '%)',
					'blur' => 'blur(' . ( $value['blurH'] ?? 0 ) . 'px)',
					'hue' => 'hue-rotate(' . ( $value['hueH'] ?? 0 ) . 'deg)',
				];
				$css['filter'] = implode( ' ', $filter_values_h );
			}//end if
		}//end if

		return $css;
	}
}
