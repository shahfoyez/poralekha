<?php
namespace ABlocks\Blocks\ImageHotspot;

use ABlocks\Controls\Range;
use ABlocks\Controls\Color;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\BoxShadow;
use ABlocks\Classes\CssGeneratorV2;


class Block extends BlockBaseAbstract {
	protected $block_name = 'image-hotspot';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-image-hotspot__pin:after',
			[
				'animation' => $attributes['animationType'],
			]
		);

		// Loop through lists and apply styles for each pin
		if ( ! empty( $attributes['lists'] ) ) {
			foreach ( $attributes['lists'] as $list ) {
				$css_generator->add_class_styles(
					"{{WRAPPER}} .ablocks-image-hotspot-list-{$list['id']}",
					$this->get_pin_css( $attributes, $list )
				);

				$css_generator->add_class_styles(
					"{{WRAPPER}} .ablocks-image-hotspot-list-{$list['id']}:after",
					$this->get_pin_effect_css( $attributes, $list )
				);

				$css_generator->add_class_styles(
					"{{WRAPPER}} .ablocks-image-hotspot-list-{$list['id']}:hover",
					$this->get_pin_hover_css( $attributes, $list )
				);

				$css_generator->add_class_styles(
					"{{WRAPPER}} .ablocks-image-hotspot-list-{$list['id']}:hover:after",
					$this->get_pin_hover_effect_css( $attributes, $list )
				);
			}//end foreach
		}//end if

		// Tooltip content animation
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-image-hotspot__tooltip-content',
			$this->get_tooltip_content_css( $attributes )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-image-hotspot__tooltip--active',
			$this->get_active_content_css( $attributes ),
			$this->get_active_content_css( $attributes, 'Tablet' ),
			$this->get_active_content_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-image-hotspot__tooltip--active:hover',
			$this->get_active_content_hover_css( $attributes ),
			$this->get_active_content_hover_css( $attributes, 'Tablet' ),
			$this->get_active_content_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-image-hotspot__pin:after',
			[
				'animation' => $attributes['animationType'],
			]
		);

		// Loop through lists and apply styles for each pin
		if ( ! empty( $attributes['lists'] ) ) {
			foreach ( $attributes['lists'] as $list ) {
				$css_generator->add_class_styles(
					"{{WRAPPER}} .ablocks-image-hotspot-list-{$list['id']}",
					$this->get_pin_css( $attributes, $list )
				);

				$css_generator->add_class_styles(
					"{{WRAPPER}} .ablocks-image-hotspot-list-{$list['id']}:after",
					$this->get_pin_effect_css( $attributes, $list )
				);

				$css_generator->add_class_styles(
					"{{WRAPPER}} .ablocks-image-hotspot-list-{$list['id']}:hover",
					$this->get_pin_hover_css( $attributes, $list )
				);

				$css_generator->add_class_styles(
					"{{WRAPPER}} .ablocks-image-hotspot-list-{$list['id']}:hover:after",
					$this->get_pin_hover_effect_css( $attributes, $list )
				);
			}//end foreach
		}//end if

		// Tooltip content animation
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-image-hotspot__tooltip-content',
			$this->get_tooltip_content_css( $attributes )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-image-hotspot__tooltip--active',
			$this->get_active_content_css( $attributes ),
			$this->get_active_content_css( $attributes, 'Tablet' ),
			$this->get_active_content_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-image-hotspot__tooltip--active:hover',
			$this->get_active_content_hover_css( $attributes ),
			$this->get_active_content_hover_css( $attributes, 'Tablet' ),
			$this->get_active_content_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}
	public function get_pin_css( $attributes, $list ) {
		return [
			'background-color' => Color::get_css( ! empty( $list['pinColorEffect'] ) ? $list['pinColorEffect'] : $attributes['pinColorEffect'] ),
			'width' => ! empty( $list['pinSize'] ) ? $list['pinSize'] . 'px' : $attributes['pinSize'] . 'px',
			'height' => ! empty( $list['pinSize'] ) ? $list['pinSize'] . 'px' : $attributes['pinSize'] . 'px',
			'transform' => 'translate(-50%, -50%)',
		];
	}

	public function get_pin_effect_css( $attributes, $list ) {
		return [
			'background-color' => Color::get_css( ! empty( $list['pinColor'] ) ? $list['pinColor'] : $attributes['pinColor'] ),
			'width' => ! empty( $list['pinSize'] ) ? $list['pinSize'] . 'px' : $attributes['pinSize'] . 'px',
			'height' => ! empty( $list['pinSize'] ) ? $list['pinSize'] . 'px' : $attributes['pinSize'] . 'px',
		];
	}

	public function get_pin_hover_css( $attributes, $list ) {
		return [
			'background-color' => Color::get_css( ! empty( $list['pinHoverColor'] ) ? $list['pinHoverColor'] : $attributes['pinHoverColor'] ),
			'transform' => 'translate(-50%, -50%)',
			'--ablocks-hotspot-effect-max-scale' => ! empty( $list['pinHoverSize'] ) ? $list['pinHoverSize'] : $attributes['pinHoverSize'],
		];
	}

	public function get_pin_hover_effect_css( $attributes, $list ) {
		return [
			'background-color' => Color::get_css( ! empty( $list['pinHoverColor'] ) ? $list['pinHoverColor'] : $attributes['pinHoverColor'] ),
			'--ablocks-hotspot-effect-max-scale' => ! empty( $list['pinHoverSize'] ) ? $list['pinHoverSize'] : $attributes['pinHoverSize'],
		];
	}

	public function get_tooltip_content_css( $attributes ) {
		// Extract the attributes
		$content_position = $attributes['contentPosition'];
		$content_animation = $attributes['contentAnimation'];

		// Initialize transform values for sliding animations
		$translateX_start = '0px';
		$translateY_start = '0px';
		$translateX_end = '-50%';
		$translateY_end = '0px';

		// Default transform for bottom position
		$content_transform = 'translate(-50%, 40px)';

		if ( $content_position === 'top' ) {
			$content_transform = 'translate(-50%, calc(-100% - 40px))';
		} elseif ( $content_position === 'left' ) {
			$content_transform = 'translate(calc(-100% - 40px), -50%)';
		} elseif ( $content_position === 'right' ) {
			$content_transform = 'translate(40px, -50%)';
		}

		// Set default animation duration and ease if not provided
		$animation_duration = '0.5s';
		$animation_ease = 'ease';

		// Set animation style dynamically based on contentAnimation
		$animation_style = '';
		if ( $content_animation === 'ablocks-hotspot-fadeIn' ) {
			$animation_style = 'ablocks-hotspot-fadeIn 1.0s ease forwards';
		} elseif ( $content_animation === 'ablocks-hotspot-fadeGrow' ) {
			$animation_style = 'ablocks-hotspot-fadeGrow 0.5s ease';
			switch ( $content_position ) {
				case 'top':
					$translateX_end = '-50%';
					$translateY_end = 'calc(-100% - 40px)';
					$translateX_start = $translateX_end;
					$translateY_start = $translateY_end;
					break;
				case 'bottom':
					$translateX_end = '-50%';
					$translateY_end = '40px';
					$translateX_start = $translateX_end;
					$translateY_start = $translateY_end;
					break;
				case 'left':
					$translateX_end = 'calc(-100% - 40px)';
					$translateY_end = '-50%';
					$translateX_start = $translateX_end;
					$translateY_start = $translateY_end;
					break;
				case 'right':
					$translateX_end = '40px';
					$translateY_end = '-50%';
					$translateX_start = $translateX_end;
					$translateY_start = $translateY_end;
					break;
			}//end switch
			// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
		} elseif ( in_array( $content_animation, [ 'ablocks-hotspot-slideInTop', 'ablocks-hotspot-slideInBottom', 'ablocks-hotspot-slideInLeft', 'ablocks-hotspot-slideInRight' ] ) ) {
			$animation_style = "ablocks-hotspot-slideIn {$animation_duration} {$animation_ease} forwards";
		}//end if

		// Handle specific sliding animations per position
		switch ( $content_position ) {
			case 'top':
				$translateX_end = '-50%';
				$translateY_end = 'calc(-100% - 40px)';
				$translateX_start = $translateX_end;
				$translateY_start = $translateY_end;

				switch ( $content_animation ) {
					case 'ablocks-hotspot-slideInLeft':
						$translateX_start = 'calc(-50% - 40px)';
						break;
					case 'ablocks-hotspot-slideInRight':
						$translateX_start = 'calc(-50% + 40px)';
						break;
					case 'ablocks-hotspot-slideInTop':
						$translateY_start = 'calc(-100% - 60px)';
						break;
					case 'ablocks-hotspot-slideInBottom':
						$translateY_start = 'calc(-100% + 40px)';
						break;
				}
				break;

			case 'bottom':
				$translateX_end = '-50%';
				$translateY_end = '40px';
				$translateX_start = $translateX_end;
				$translateY_start = $translateY_end;

				switch ( $content_animation ) {
					case 'ablocks-hotspot-slideInLeft':
						$translateX_start = 'calc(-50% - 40px)';
						break;
					case 'ablocks-hotspot-slideInRight':
						$translateX_start = 'calc(-50% + 40px)';
						break;
					case 'ablocks-hotspot-slideInTop':
						$translateY_start = 'calc(40px - 40px)';
						break;
					case 'ablocks-hotspot-slideInBottom':
						$translateY_start = 'calc(40px + 40px)';
						break;
				}
				break;

			case 'left':
				$translateX_end = 'calc(-100% - 40px)';
				$translateY_end = '-50%';
				$translateX_start = $translateX_end;
				$translateY_start = $translateY_end;

				switch ( $content_animation ) {
					case 'ablocks-hotspot-slideInLeft':
						$translateX_start = 'calc(-100% - 40px - 40px)';
						break;
					case 'ablocks-hotspot-slideInRight':
						$translateX_start = 'calc(-100% - 40px + 40px)';
						break;
					case 'ablocks-hotspot-slideInTop':
						$translateY_start = 'calc(-50% - 40px)';
						break;
					case 'ablocks-hotspot-slideInBottom':
						$translateY_start = 'calc(-50% + 40px)';
						break;
				}
				break;

			case 'right':
				$translateX_end = '40px';
				$translateY_end = '-50%';
				$translateX_start = $translateX_end;
				$translateY_start = $translateY_end;

				switch ( $content_animation ) {
					case 'ablocks-hotspot-slideInLeft':
						$translateX_start = 'calc(40px - 40px)';
						break;
					case 'ablocks-hotspot-slideInRight':
						$translateX_start = 'calc(40px + 40px)';
						break;
					case 'ablocks-hotspot-slideInTop':
						$translateY_start = 'calc(-50% - 40px)';
						break;
					case 'ablocks-hotspot-slideInBottom':
						$translateY_start = 'calc(-50% + 40px)';
						break;
				}
				break;
		}//end switch

		// Return the final styles as an array
		return [
			'animation'          => $animation_style,
			'transform'          => $content_transform,
			'--ablocks-hotspot-translateX-start' => $translateX_start,
			'--ablocks-hotspot-translateY-start' => $translateY_start,
			'--ablocks-hotspot-translateX-end'   => $translateX_end,
			'--ablocks-hotspot-translateY-end'   => $translateY_end,
		];
	}

	public function get_active_content_css( $attributes, $device = '' ) {
		$css = array();
		if ( isset( $width[ 'value' . $device ] ) && ! empty( $width[ 'value' . $device ] ) ) {

			if ( ! empty( $width[ 'valueUnit' . $device ] ) ) {
				$css['width'] = $width[ 'value' . $device ] . $width[ 'valueUnit' . $device ];
			} else {
				$css['width'] = $width[ 'value' . $device ] . 'px';
			}
		}

		$css = array_merge(
			[ 'background' => Color::get_css( isset( $attributes['backgroundColor'] ) ? $attributes['backgroundColor'] : '' ) ],
			$css,
			Range::get_css([
				'attributeValue' => $attributes['childWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 200,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			]),
		isset( $attributes['commonBoxShadow'] ) ? BoxShadow::get_css( $attributes['commonBoxShadow'], '', $device ) : [] );

		return $css;
	}

	public function get_active_content_hover_css( $attributes, $device = '' ) {

		$css = isset( $attributes['commonBoxShadow'] ) ? BoxShadow::get_hover_css( $attributes['commonBoxShadow'], '', $device ) : [];

		return $css;
	}

}
