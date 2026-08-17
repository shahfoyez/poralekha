<?php

namespace ABlocks\Blocks\ContentTimeline;

use ABlocks\Controls\Range;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Border;
use ABlocks\Controls\Color;


class Block extends BlockBaseAbstract {

	protected $block_name = 'content-timeline';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes );

		// Generate and add CSS styles for different parts of the block
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline--outer-wrap .ablocks-icon-maker svg',
			$this->get_content_timeline_icon_css( $attributes ),
			$this->get_content_timeline_icon_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_icon_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline--outer-wrap .ablocks__in-view-icon .ablocks-icon-wrap',
			$this->get_content_timeline_icon_background_css( $attributes ),
			$this->get_content_timeline_icon_background_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_icon_background_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline .ablocks-block-content-timeline__line',
			$this->get_content_timeline_connector_css( $attributes ),
			$this->get_content_timeline_connector_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_connector_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline--outer-wrap .ablocks-block-content-timeline-child--field',
			$this->get_content_timeline_item_gap_css( $attributes ),
			$this->get_content_timeline_item_gap_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_item_gap_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline--outer-wrap .ablocks-block-content-timeline-child--field .ablocks-block-content-timeline-child__content-part',
			$this->get_content_timeline_content_css( $attributes ),
			$this->get_content_timeline_content_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_content_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline-child__content-part .ablocks-block-content-timeline-child__arrow::after',
			$this->get_content_timeline_content_background_css( $attributes ),
			$this->get_content_timeline_content_background_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_content_background_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline--outer-wrap .ablocks__in-view-icon',
			$this->get_content_timeline_connector_alignment_css( $attributes ),
			$this->get_content_timeline_connector_alignment_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_connector_alignment_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline-child__date',
			$this->get_content_timeline_date_alignment_css( $attributes ),
			$this->get_content_timeline_date_alignment_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_date_alignment_css( $attributes, 'Mobile' )
		);
		// Conditional CSS generation based on arrowAlignment

		$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-block-content-timeline-child--line-center .ablocks-block-content-timeline-child__arrow',
				$this->get_content_timeline_arrow_css( $attributes ),
				$this->get_content_timeline_arrow_css( $attributes, 'Tablet' ),
				$this->get_content_timeline_arrow_css( $attributes, 'Mobile' )
			);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline-child__date,.ablocks-block-content-timeline-child__inner-content-date',
			$this->get_content_timeline_date_css( $attributes ),
			$this->get_content_timeline_date_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_date_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline-child__inner-content-date',
			$this->get_content_timeline_date_mobile_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline-child__inner-content-date:hover',
			$this->get_content_timeline_date_hover_mobile_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline-child__date-inner',
			$this->get_content_timeline_show_date_center_css( $attributes, '' ),
			$this->get_content_timeline_show_date_center_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_show_date_center_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline-child__date',
			$this->get_content_timeline_show_date_left_right_css( $attributes, '' ),
			$this->get_content_timeline_show_date_left_right_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_show_date_left_right_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline--left .ablocks-block-content-timeline__line,.ablocks-block-content-timeline--right .ablocks-block-content-timeline__line',
			$this->get_content_timeline_show_date_left_right_line_css( $attributes, '' ),
			$this->get_content_timeline_show_date_left_right_line_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_show_date_left_right_line_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline-child__inner-content-date',
			$this->get_content_timeline_show_date_mobile_css( $attributes, '' ),
			$this->get_content_timeline_show_date_mobile_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_show_date_mobile_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		// Generate and add CSS styles for different parts of the block
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-icon-maker svg',
			$this->get_content_timeline_icon_css( $attributes ),
			$this->get_content_timeline_icon_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_icon_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline--outer-wrap .ablocks__in-view-icon',
			$this->get_content_timeline_icon_background_css( $attributes ),
			$this->get_content_timeline_icon_background_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_icon_background_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline .ablocks-block-content-timeline__line',
			$this->get_content_timeline_connector_css( $attributes ),
			$this->get_content_timeline_connector_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_connector_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline--outer-wrap .ablocks-block-content-timeline-child--field',
			$this->get_content_timeline_item_gap_css( $attributes ),
			$this->get_content_timeline_item_gap_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_item_gap_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline--outer-wrap .ablocks-block-content-timeline-child--field .ablocks-block-content-timeline-child__content-part',
			$this->get_content_timeline_content_css( $attributes ),
			$this->get_content_timeline_content_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_content_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline-child__content-part .ablocks-block-content-timeline-child__arrow::after',
			$this->get_content_timeline_content_background_css( $attributes ),
			$this->get_content_timeline_content_background_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_content_background_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline--outer-wrap .ablocks__in-view-icon',
			$this->get_content_timeline_connector_alignment_css( $attributes ),
			$this->get_content_timeline_connector_alignment_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_connector_alignment_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline-child__date',
			$this->get_content_timeline_date_alignment_css( $attributes ),
			$this->get_content_timeline_date_alignment_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_date_alignment_css( $attributes, 'Mobile' )
		);
		// Conditional CSS generation based on arrowAlignment

			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-block-content-timeline-child--line-center .ablocks-block-content-timeline-child__arrow',
				$this->get_content_timeline_arrow_css( $attributes ),
				$this->get_content_timeline_arrow_css( $attributes, 'Tablet' ),
				$this->get_content_timeline_arrow_css( $attributes, 'Mobile' )
			);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline-child__date,.ablocks-block-content-timeline-child__inner-content-date',
			$this->get_content_timeline_date_css( $attributes ),
			$this->get_content_timeline_date_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_date_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline-child__inner-content-date',
			$this->get_content_timeline_date_mobile_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline-child__inner-content-date:hover',
			$this->get_content_timeline_date_hover_mobile_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline-child__date-inner',
			$this->get_content_timeline_show_date_center_css( $attributes, '' ),
			$this->get_content_timeline_show_date_center_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_show_date_center_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline-child__date',
			$this->get_content_timeline_show_date_left_right_css( $attributes, '' ),
			$this->get_content_timeline_show_date_left_right_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_show_date_left_right_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline--left .ablocks-block-content-timeline__line,.ablocks-block-content-timeline--right .ablocks-block-content-timeline__line',
			$this->get_content_timeline_show_date_left_right_line_css( $attributes, '' ),
			$this->get_content_timeline_show_date_left_right_line_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_show_date_left_right_line_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-content-timeline-child__inner-content-date',
			$this->get_content_timeline_show_date_mobile_css( $attributes, '' ),
			$this->get_content_timeline_show_date_mobile_css( $attributes, 'Tablet' ),
			$this->get_content_timeline_show_date_mobile_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}
	public function get_content_timeline_show_date_mobile_css( $attributes, $device = '' ) {
		$css = [];
		if ( $device === '' || $device === 'Tablet' ) {
			$css['display'] = 'none';
		}
		if ( $device === 'Mobile' ) {
			if ( isset( $attributes[ "showDate{$device}" ] ) && $attributes[ "showDate{$device}" ] === false ) {
				$css['display'] = 'none';
			} elseif ( isset( $attributes[ "showDate{$device}" ] ) && $attributes[ "showDate{$device}" ] === true ) {
				$css['display'] = 'block';
			}
		}

		return $css;
	}
	public function get_content_timeline_show_date_center_css( $attribute, $device ) {
		$css = [];
		$showDateKey = $device ? "showDate{$device}" : 'showDate';
		$showDate = $attribute[ $showDateKey ] ?? null;
		$isCentered = $attribute['contentPosition'] === 'center';

		if ( $isCentered ) {
			$css['display'] = $showDate ? 'block' : 'none';
		}

		return $css;
	}

	public function get_content_timeline_show_date_left_right_css( $attribute, $device ) {
		$css = [];
		$showDateKey = $device ? "showDate{$device}" : 'showDate';
		$showDate = $attribute[ $showDateKey ] ?? null;
		$isLeftOrRight = in_array( $attribute['contentPosition'], [ 'left', 'right' ], true );

		if ( $isLeftOrRight ) {
			if ( $device !== 'Mobile' ) {
				$css['display'] = $showDate ? 'block !important' : 'none !important';
			} elseif ( $device === 'Mobile' ) {
				$css['display'] = 'none !important';

			}
		}
		if ( $attribute['contentPosition'] === 'center' ) {
			$css['display'] = 'block';
		}

		return $css;
	}
	public function get_content_timeline_show_date_left_right_line_css( $attribute, $device ) {
		$css = [];
		$showDate = $attribute[ "showDate{$device}" ] ?? null;
		if ( $device === 'Mobile' ) {
			// Specific case for mobile content position left/right without date
			if ( $attribute['contentPosition'] === 'left' ) {
				$css['left'] = 'calc(35px / 2) !important'; // fallback for left without date
			} elseif ( $attribute['contentPosition'] === 'right' ) {
				$css['right'] = 'calc(35px / 2) !important'; // fallback for right without date
			}
		} elseif ( $device ) {
			// Non-mobile styles (if needed)
			if ( $attribute['contentPosition'] === 'left' ) {
				$css['left'] = $showDate ? 'calc(30% / 2) !important' : 'calc(61px / 2) !important';
				$css['right'] = 'auto !important';
			} elseif ( $attribute['contentPosition'] === 'right' ) {
				$css['right'] = $showDate ? 'calc(30% / 2) !important' : 'calc(61px / 2) !important';
				$css['left'] = 'auto !important';
			}
		} else {
			if ( $attribute['contentPosition'] === 'left' ) {
				$css['left'] = $showDate ? 'calc(30% / 2) !important' : 'calc(61px / 2) !important';
				$css['right'] = 'auto !important';
			} elseif ( $attribute['contentPosition'] === 'right' ) {
				$css['right'] = $showDate ? 'calc(30% / 2) !important' : 'calc(61px / 2) !important';
				$css['left'] = 'auto !important';
			}
		}//end if

		return $css;
	}
	public function get_content_timeline_icon_css( $attributes, $device = '' ) {
		$css = [];
		return array_merge(
			[ 'fill' => Color::get_css( isset( $attributes['iconColor'] ) ? $attributes['iconColor'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['iconSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 18,
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['iconSize'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 18,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			]),
			$css,
		);
	}

	public function get_content_timeline_icon_background_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['iconBackgroundColor'] ) ? $attributes['iconBackgroundColor'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['iconBackgroundSize'],
				'attribute_object_key' => 'value',
				'defaultValue' => 48,
				'property' => 'width',
			]),
			Range::get_css([
				'attributeValue' => $attributes['iconBackgroundSize'],
				'attribute_object_key' => 'value',
				'defaultValue' => 48,
				'property' => 'height',
			]),
		);
	}

	public function get_content_timeline_connector_css( $attributes, $device = '' ) {
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['thicknessColor'] ) ? $attributes['thicknessColor'] : '' ) ],
			Range::get_css([
				'attributeValue' => $attributes['thickness'],
				'attribute_object_key' => 'value',
				'defaultValue' => 3,
				'property' => 'width',
			]),
			Range::get_css([
				'attributeValue' => $attributes['lineLeft'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 0,
				'unitDefaultValue' => 'px',
				'property' => 'margin-left',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['lineRight'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 0,
				'unitDefaultValue' => 'px',
				'property' => 'margin-right',
				'device' => $device,
			]),
		);
	}

	public function get_content_timeline_item_gap_css( $attributes, $device = '' ) {
		$css = [];
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['itemGap'],
				'attribute_object_key' => 'value',
				'defaultValue' => 10,
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'margin-top',
				'device' => $device,
			]),
			$css,
		);
	}

	public function get_content_timeline_content_css( $attributes, $device = '' ) {
		$css = [];
		$css['padding'] = '15px';
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['contentBackgroundColor'] ) ? $attributes['contentBackgroundColor'] : '' ) ],
			$css,
			Dimensions::get_css( $attributes['contentPadding'] ?? [], 'padding', $device )
		);
	}

	public function get_content_timeline_content_background_css( $attributes, $device = '' ) {
		$css = [];
		if ( ! empty( $attributes['contentBackgroundColor'] ) ) {
			$css['border-left-color'] = Color::get_css(
			isset( $attributes['contentBackgroundColor'] ) ? $attributes['contentBackgroundColor'] : '');
			$css['border-right-color'] = Color::get_css(
			isset( $attributes['contentBackgroundColor'] ) ? $attributes['contentBackgroundColor'] : '');
		}
		return $css;
	}
	// public function get_content_timeline_arrow_css( $attributes, $device = '' ) {
	// 	$css = [];
	// 	$arrowAlignment = $attributes['arrowAlignment'] ?? '';
	// 	$iconBackgroundSize = $attributes['iconBackgroundSize'] ?? '';

	// 	if ( $arrowAlignment === 'top' ) {
	// 		$css['height'] = '0px';
	// 		$css['top'] = $iconBackgroundSize ? ( $iconBackgroundSize / 2 ) . 'px' : '0';
	// 	} elseif ( $arrowAlignment === 'bottom' ) {
	// 		$css['height'] = $iconBackgroundSize ? ( $iconBackgroundSize / 2 ) . 'px' : '0';
	// 		$css['bottom'] = '0';
	// 	}

	// 	return $css;
	// }

	public function get_content_timeline_arrow_css( $attributes, $device = '' ) {
			$css = [];
			$arrowAlignment = $attributes['arrowAlignment'] ?? '';
			$iconBackgroundSize = $attributes['iconBackgroundSize'] ?? '';

			// Existing logic + updated styles
			if ( $arrowAlignment === 'top' ) {
				$css['height'] = '35px';
				$css['top'] = '10%';
				$css['transform'] = 'translateY(-50%)';
				$css['-webkit-transform'] = 'translateY(-50%)';

			} elseif ( $arrowAlignment === 'bottom' ) {
				$css['height'] = '35px';
				$css['top'] = '90%';
				$css['transform'] = 'translateY(-50%)';
				$css['-webkit-transform'] = 'translateY(-50%)';

			} elseif ( $arrowAlignment === 'centre' ) {
				$css['height'] = '35px';
				$css['top'] = '50%';
				$css['transform'] = 'translateY(-50%)';
				$css['-webkit-transform'] = 'translateY(-50%)';
			}

			return $css;
	}
	public function get_content_timeline_connector_alignment_css( $attributes, $device = '' ) {
		$css = [];
		$arrowAlignment = $attributes['arrowAlignment'] ?? '';

		if ( $arrowAlignment === 'top' ) {
			$css['align-self'] = 'flex-start';
		} elseif ( $arrowAlignment === 'bottom' ) {
			$css['align-self'] = 'flex-end';
		} else {
			$css['align-self'] = 'center';
		}

		return $css;
	}
	public function get_content_timeline_date_alignment_css( $attributes, $device = '' ) {
		$css = [];

		$arrowAlignment = $attributes['arrowAlignment'] ?? '';
		$iconBackgroundSize = $attributes['iconBackgroundSize'] ?? '';

		if ( $arrowAlignment === 'top' ) {
			$css['align-self'] = 'flex-start';
		} elseif ( $arrowAlignment === 'bottom' ) {
			$css['align-self'] = 'flex-end';
		} else {
			$css['align-self'] = 'center';
		}

		if ( $arrowAlignment === 'top' ) {
			$css['margin-top'] = $iconBackgroundSize ? ( $iconBackgroundSize / 4 ) . 'px' : '0';
		} elseif ( $arrowAlignment === 'bottom' ) {
			$css['margin-bottom'] = $iconBackgroundSize ? ( $iconBackgroundSize / 4 ) . 'px' : '0';
		}

		return $css;
	}

	public function get_content_timeline_date_css( $attributes, $device = '' ) {
		$typographyValueGlobal = $attributes['dateTypographyGlobal'] ? $attributes['dateTypographyGlobal'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['dateColor'] ) ? $attributes['dateColor'] : '' ) ],
			Typography::get_css( $attributes['dateTypography'] ?? [], '', $device, $typographyValueGlobal )
		);
	}
	public function get_content_timeline_date_mobile_css( $attributes, $device = '' ) {
		$css = [];
		if ( ! empty( $attributes['dateAlign'] ) ) {
			$css['text-align'] = $attributes['dateAlign'];
		}
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['dateBackground'] ) ? $attributes['dateBackground'] : '' ) ],
			$css,
			Dimensions::get_css( $attributes['datePadding'] ?? [], 'padding', $device ),
			Border::get_css( $attributes['dateBorder'] ?? [], '', $device )
		);
	}
	public function get_content_timeline_date_hover_mobile_css( $attributes, $device = '' ) {
		return array_merge(
			Border::get_hover_css( $attributes['dateBorder'] ?? [], '', $device )
		);
	}
}
