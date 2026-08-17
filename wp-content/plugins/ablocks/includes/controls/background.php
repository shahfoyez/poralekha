<?php
namespace ABlocks\Controls;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\ControlBaseAbstract;
use ABlocks\Controls\Color;

class Background extends ControlBaseAbstract {
	public static function get_attribute_default_value( $is_responsive = false ) {
		if ( $is_responsive ) {
			return [
				'backgroundType' => 'none',
				'backgroundTypeH' => 'none',
				'backgroundColor' => '',
				'backgroundColorH' => '',
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
				'imgXPositionUnitTablet' => '',
				'imgXPositionUnitMobile' => '',
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
				// Background image - Hover
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
				// Background image transition
				'transitionDuration' => '',
				// Background video
				'videoUrl' => '',
				'youtubeURL' => '',
				'vimeoURL' => '',
				'selfHostedURL' => '',
				'externalLink' => false,
				'videoSource' => 'none',
				'videoPlayOnce' => '',
				'videoStartTime' => '',
				'videoEndTime' => '',
				'playOnMobile' => '',
				'videoPrivacyMode' => '',
				'fallbackImageUrl' => '',
			];
		}//end if
		return [
			'backgroundType' => 'none',
			'backgroundTypeH' => 'none',
			'backgroundColor' => '',
			'backgroundColorH' => '',
			// Background image - Normal
			'imgId' => '',
			'imgUrl' => '',
			'imgSize' => '',
			'imgPosition' => '',
			'imgAttachment' => '',
			'imgRepeat' => '',
			'imgDisplaySize' => '',
			'imgdisplaySizeWidth' => '',
			'imgDisplaySizeWidthUnit' => 'px',
			'imgXPositionUnit' => 'px',
			'imgXPosition' => '',
			'imgYPositionUnit' => 'px',
			'imgYPosition' => '',
			// Background image - Hover
			'imgIdH' => '',
			'imgUrlH' => '',
			'imgSizeH' => '',
			'imgPositionH' => '',
			'imgXPositionUnitH' => 'px',
			'imgXPositionH' => '',
			'imgYPositionUnitH' => 'px',
			'imgYPositionH' => '',
			'imgAttachmentH' => '',
			'imgRepeatH' => '',
			'imgDisplaySizeH' => '',
			'imgDisplaySizeWidthH' => '',
			'imgDisplaySizeWidthHUnit' => 'px',
			// Background image transition
			'transitionDuration' => '',
			// Background video
			'videoUrl' => '',
			'youtubeURL' => '',
			'vimeoURL' => '',
			'selfHostedURL' => '',
			'externalLink' => false,
			'videoSource' => 'none',
			'videoPlayOnce' => '',
			'videoStartTime' => '',
			'videoEndTime' => '',
			'playOnMobile' => '',
			'videoPrivacyMode' => '',
			'fallbackImageUrl' => '',
		];
	}

	public static function get_attribute( $attribute_name, $is_responsive = false ) {
		return [
			$attribute_name => [
				'type' => 'object',
				'default' => self::get_attribute_default_value( $is_responsive ),
			]
		];
	}

	public static function get_css( $attribute_value, $property = '', $device = '' ) {
		$value = wp_parse_args( $attribute_value, self::get_attribute_default_value( (bool) $device ) );
		$css = [];

		if ( $device ) {
			$unitXPositionUnit = self::get_unit(
				[
					'unit' => isset( $value['imgXPositionUnit'] ) ? $value['imgXPositionUnit'] : '',
					'unitTablet' => isset( $value['imgXPositionUnitTablet'] ) ? $value['imgXPositionUnitTablet'] : '',
					'unitMobile' => isset( $value['imgXPositionUnitMobile'] ) ? $value['imgXPositionUnitMobile'] : '',
				],
				$device
			);
			$unitYPositionUnit = self::get_unit(
				[
					'unit' => isset( $value['imgYPositionUnit'] ) ? $value['imgYPositionUnit'] : '',
					'unitTablet' => isset( $value['imgYPositionUnitTablet'] ) ? $value['imgYPositionUnitTablet'] : '',
					'unitMobile' => isset( $value['imgYPositionUnitMobile'] ) ? $value['imgYPositionUnitMobile'] : '',
				],
				$device
			);
			$imgDisplaySizeWidthUnit = self::get_unit(
				[
					'unit' => isset( $value['imgDisplaySizeWidthUnit'] ) ? $value['imgDisplaySizeWidthUnit'] : '',
					'unitTablet' => isset( $value['imgDisplaySizeWidthUnitTablet'] ) ? $value['imgDisplaySizeWidthUnitTablet'] : '',
					'unitMobile' => isset( $value['imgDisplaySizeWidthUnitMobile'] ) ? $value['imgDisplaySizeWidthUnitMobile'] : '',
				],
				$device
			);
		} else {
			$unitXPositionUnit = isset( $value['imgXPositionUnit'] ) ? $value['imgXPositionUnit'] : '';
			$unitYPositionUnit = isset( $value['imgYPositionUnit'] ) ? $value['imgYPositionUnit'] : '';
			$imgDisplaySizeWidthUnit = isset( $value['imgDisplaySizeWidthUnit'] ) ? $value['imgDisplaySizeWidthUnit'] : '';
		}//end if

		if ( 'image' === $value['backgroundType'] && '' !== $value[ 'imgUrl' . $device ] ) {
			// background image
			$css[ $property . '-image' ] = 'url("' . $value[ 'imgUrl' . $device ] . '")';
			// background-position css
			if ( '' !== $value[ 'imgPosition' . $device ] ) {
				if ( 'custom' === $value[ 'imgPosition' . $device ] ) {
					$imgXPosition = isset( $value[ 'imgXPosition' . $device ] ) ? $value[ 'imgXPosition' . $device ] : '0';
					$imgYPosition = isset( $value[ 'imgYPosition' . $device ] ) ? $value[ 'imgYPosition' . $device ] : '0';
					$css[ $property . '-position' ] = $imgXPosition . $unitXPositionUnit . $imgYPosition . $unitYPositionUnit;
				} else {
					$css[ $property . '-position' ] = $value[ 'imgPosition' . $device ];
				}
			}
			// background-attachments css
			if ( isset( $value[ 'imgAttachment' . $device ] ) && '' !== $value[ 'imgAttachment' . $device ] ) {
				$css[ $property . '-attachment' ] = $value[ 'imgAttachment' . $device ];
			}
			// background-repeat css
			if ( '' !== $value[ 'imgRepeat' . $device ] ) {
				$css[ $property . '-repeat' ] = $value[ 'imgRepeat' . $device ];
			}
			// background-size css
			if ( '' !== $value[ 'imgDisplaySize' . $device ] ) {
				if ( 'custom' === $value[ 'imgDisplaySize' . $device ] ) {
					$css[ $property . '-size' ] = isset( $value[ 'imgDisplaySizeWidth' . $device ] ) ? $value[ 'imgDisplaySizeWidth' . $device ] . $imgDisplaySizeWidthUnit . ' auto' : '';
				} else {
					$css[ $property . '-size' ] = $value[ 'imgDisplaySize' . $device ];
				}
			}
		} elseif ( 'video' === $value['backgroundType'] && '' !== $value['fallbackImageUrl'] && ( $value['videoSource'] == 'none' || ( $value['videoSource'] == 'selfHosted' && empty( $value['videoUrl'] ) ) || ( $value['videoSource'] == 'youtube' && empty( $value['youtubeURL'] ) ) || ( $value['videoSource'] == 'vimeo' && empty( $value['vimeoURL'] ) ) ) ) {
			$css[ $property . '-image' ] = 'url("' . $value['fallbackImageUrl'] . '")';
			$css[ $property . '-size' ] = 'cover';
		} elseif ( 'color' === $value['backgroundType'] && $value['backgroundColor'] ) {
			$css[ $property ] = Color::get_css( $value['backgroundColor'] );
		}//end if

		if ( '' !== $value['transitionDuration'] ) {
			$css['transition'] = $property . ' ' . $value['transitionDuration'] . 's ease';
		}

		return $css;
	}

	public static function get_hover_css( $attribute_value, $property = '', $device = '' ) {
		$value = wp_parse_args( $attribute_value, self::get_attribute_default_value( (bool) $device ) );
		$css = [];

		if ( $device ) {
			$unitXPositionUnitH = self::get_unit(
				[
					'unit' => isset( $value['imgXPositionUnitH'] ) ? $value['imgXPositionUnitH'] : '',
					'unitTablet' => isset( $value['imgXPositionUnitHTablet'] ) ? $value['imgXPositionUnitHTablet'] : '',
					'unitMobile' => isset( $value['imgXPositionUnitHMobile'] ) ? $value['imgXPositionUnitHMobile'] : '',
				],
				$device
			);
			$unitYPositionUnitH = self::get_unit(
				[
					'unit' => isset( $value['imgYPositionUnitH'] ) ? $value['imgYPositionUnitH'] : '',
					'unitTablet' => isset( $value['imgYPositionUnitHTablet'] ) ? $value['imgYPositionUnitHTablet'] : '',
					'unitMobile' => isset( $value['imgYPositionUnitHMobile'] ) ? $value['imgYPositionUnitHMobile'] : '',
				],
				$device
			);
			$imgDisplaySizeWidthUnitH = self::get_unit(
				[
					'unit' => isset( $value['imgDisplaySizeWidthHUnit'] ) ? $value['imgDisplaySizeWidthHUnit'] : '',
					'unitTablet' => isset( $value['imgDisplaySizeWidthHUnitTablet'] ) ? $value['imgDisplaySizeWidthHUnitTablet'] : '',
					'unitMobile' => isset( $value['imgDisplaySizeWidthHUnitMobile'] ) ? $value['imgDisplaySizeWidthHUnitMobile'] : '',
				],
				$device
			);
		} else {
			$unitXPositionUnitH = isset( $value['imgXPositionUnitH'] ) ? $value['imgXPositionUnitH'] : '';
			$unitYPositionUnitH = isset( $value['imgYPositionUnitH'] ) ? $value['imgYPositionUnitH'] : '';
			$imgDisplaySizeWidthUnitH = isset( $value['imgDisplaySizeWidthHUnit'] ) ? $value['imgDisplaySizeWidthHUnit'] : '';
		}//end if

		if ( 'image' === $value['backgroundTypeH'] && '' !== $value[ 'imgUrlH' . $device ] ) {
			// background image
			$css[ $property . '-image' ] = 'url("' . $value[ 'imgUrlH' . $device ] . '")';
			// background-position css
			if ( '' !== $value[ 'imgPositionH' . $device ] ) {
				if ( 'custom' === $value[ 'imgPositionH' . $device ] ) {
					$imgXPosition = isset( $value[ 'imgXPositionH' . $device ] ) ? $value[ 'imgXPositionH' . $device ] : '0';
					$imgYPosition = isset( $value[ 'imgYPositionH' . $device ] ) ? $value[ 'imgYPositionH' . $device ] : '0';
					$css[ $property . '-position' ] = $imgXPosition . $unitXPositionUnitH . $imgYPosition . $unitYPositionUnitH;
				} else {
					$css[ $property . '-position' ] = $value[ 'imgPositionH' . $device ];
				}
			}
			// background-attachments css
			if ( '' !== isset( $value[ 'imgAttachmentH' . $device ] ) ) {
				$css[ $property . '-attachment' ] = $value[ 'imgAttachmentH' . $device ];
			}
			// background-repeat css
			if ( '' !== $value[ 'imgRepeatH' . $device ] ) {
				$css[ $property . '-repeat' ] = $value[ 'imgRepeatH' . $device ];
			}
			// background-size css
			if ( '' !== $value[ 'imgDisplaySizeH' . $device ] ) {
				if ( 'custom' === $value[ 'imgDisplaySizeH' . $device ] ) {
					$css[ $property . '-size' ] = isset( $value[ 'imgDisplaySizeWidthH' . $device ] ) ? $value[ 'imgDisplaySizeWidthH' . $device ] . $imgDisplaySizeWidthUnitH . ' auto' : '';
				} else {
					$css[ $property . '-size' ] = $value[ 'imgDisplaySizeH' . $device ];
				}
			}
		} elseif ( 'video' === $value['backgroundTypeH'] ) {

		} elseif ( 'color' === $value['backgroundTypeH'] && $value['backgroundColorH'] ) {
			$css[ $property ] = Color::get_css( $value['backgroundColorH'] );
		}//end if

		return $css;
	}

}
