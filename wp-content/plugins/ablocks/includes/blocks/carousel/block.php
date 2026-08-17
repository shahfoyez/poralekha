<?php
namespace ABlocks\Blocks\Carousel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Range;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Controls\Color;
use ABlocks\Controls\BoxShadow;



class Block extends BlockBaseAbstract {
	protected $block_name = 'carousel';
	protected $style_depends = [ 'ablocks-swiper-style' ];
	protected $script_depends = [ 'ablocks-swiper-script' ];

	public function build_css_v1( $attributes ) {

		$css_generator = new CssGenerator( $attributes );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-carousel-swiper .swiper-wrapper',
			$this->get_carousel_css( $attributes ),
			$this->get_carousel_css( $attributes, 'Tablet' ),
			$this->get_carousel_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-carousel-navigation__button',
			$this->get_navigation_button_css( $attributes ),
			$this->get_navigation_button_css( $attributes, 'Tablet' ),
			$this->get_navigation_button_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-carousel-navigation__button--next',
			$this->get_navigation_next_button_css( $attributes ),
			$this->get_navigation_next_button_css( $attributes, 'Tablet' ),
			$this->get_navigation_next_button_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-carousel-navigation__button--prev',
			$this->get_navigation_prev_button_css( $attributes ),
			$this->get_navigation_prev_button_css( $attributes, 'Tablet' ),
			$this->get_navigation_prev_button_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-carousel-navigation__button .ablocks-icon-wrap',
			$this->get_navigation_icon_css( $attributes ),
			$this->get_navigation_icon_css( $attributes, 'Tablet' ),
			$this->get_navigation_icon_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-carousel-pagination',
			$this->get_pagination_parent_css( $attributes ),
			$this->get_pagination_parent_css( $attributes, 'Tablet' ),
			$this->get_pagination_parent_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .swiper-pagination-bullet',
			$this->get_pagination_color_css( $attributes ),
			$this->get_pagination_color_css( $attributes, 'Tablet' ),
			$this->get_pagination_color_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .swiper-pagination-bullet:hover',
			$this->get_pagination_color_hover_css( $attributes ),
			$this->get_pagination_color_hover_css( $attributes, 'Tablet' ),
			$this->get_pagination_color_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .swiper-pagination-bullet.swiper-pagination-bullet-active',
			$this->get_pagination_active_color_css( $attributes ),
			$this->get_pagination_active_color_css( $attributes, 'Tablet' ),
			$this->get_pagination_active_color_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .swiper-pagination-bullet.swiper-pagination-bullet-active:hover',
			$this->get_pagination_active_color_hover_css( $attributes ),
			$this->get_pagination_active_color_hover_css( $attributes, 'Tablet' ),
			$this->get_pagination_active_color_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-carousel-navigation__button .ablocks-svg-icon',
			$this->get_navigation_icon_svg_css( $attributes ),
			$this->get_navigation_icon_svg_css( $attributes, 'Tablet' ),
			$this->get_navigation_icon_svg_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-carousel-navigation__button .ablocks-svg-icon:hover',
			$this->get_navigation_icon_svg_hover_css( $attributes ),
			$this->get_navigation_icon_svg_hover_css( $attributes, 'Tablet' ),
			$this->get_navigation_icon_svg_hover_css( $attributes, 'Mobile' )
		);
		return $css_generator->generate_css();
	}

	public function build_css_v2( $attributes ) {

		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-carousel-swiper .swiper-wrapper',
			$this->get_carousel_css( $attributes ),
			$this->get_carousel_css( $attributes, 'Tablet' ),
			$this->get_carousel_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-carousel-navigation__button',
			$this->get_navigation_button_css( $attributes ),
			$this->get_navigation_button_css( $attributes, 'Tablet' ),
			$this->get_navigation_button_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-carousel-navigation__button--next',
			$this->get_navigation_next_button_css( $attributes ),
			$this->get_navigation_next_button_css( $attributes, 'Tablet' ),
			$this->get_navigation_next_button_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-carousel-navigation__button--prev',
			$this->get_navigation_prev_button_css( $attributes ),
			$this->get_navigation_prev_button_css( $attributes, 'Tablet' ),
			$this->get_navigation_prev_button_css( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-carousel-navigation__button .ablocks-icon-wrap',
			$this->get_navigation_icon_css( $attributes ),
			$this->get_navigation_icon_css( $attributes, 'Tablet' ),
			$this->get_navigation_icon_css( $attributes, 'Mobile' ),
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-carousel-pagination',
			$this->get_pagination_parent_css( $attributes ),
			$this->get_pagination_parent_css( $attributes, 'Tablet' ),
			$this->get_pagination_parent_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .swiper-pagination-bullet',
			$this->get_pagination_color_css( $attributes ),
			$this->get_pagination_color_css( $attributes, 'Tablet' ),
			$this->get_pagination_color_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .swiper-pagination-bullet:hover',
			$this->get_pagination_color_hover_css( $attributes ),
			$this->get_pagination_color_hover_css( $attributes, 'Tablet' ),
			$this->get_pagination_color_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .swiper-pagination-bullet.swiper-pagination-bullet-active',
			$this->get_pagination_active_color_css( $attributes ),
			$this->get_pagination_active_color_css( $attributes, 'Tablet' ),
			$this->get_pagination_active_color_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .swiper-pagination-bullet.swiper-pagination-bullet-active:hover',
			$this->get_pagination_active_color_hover_css( $attributes ),
			$this->get_pagination_active_color_hover_css( $attributes, 'Tablet' ),
			$this->get_pagination_active_color_hover_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-carousel-navigation__button .ablocks-svg-icon',
			$this->get_navigation_icon_svg_css( $attributes ),
			$this->get_navigation_icon_svg_css( $attributes, 'Tablet' ),
			$this->get_navigation_icon_svg_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-carousel-navigation__button .ablocks-svg-icon:hover',
			$this->get_navigation_icon_svg_hover_css( $attributes ),
			$this->get_navigation_icon_svg_hover_css( $attributes, 'Tablet' ),
			$this->get_navigation_icon_svg_hover_css( $attributes, 'Mobile' )
		);
		return $css_generator->generate_css();
	}
	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}

	public function get_carousel_css( $attributes, $device = '' ) {
		$carousel_css = [];
		// Temporary approch instead of attribute migration. Might remove it in future
		$verticalAlign = isset( $attributes['verticalAlign'][ 'value' . $device ] ) ? $attributes['verticalAlign'][ 'value' . $device ] : '';
		if (
			! isset( $attributes['verticalAlign'] )
			|| (
				$attributes['verticalAlignment'] !== '' && $attributes['verticalAlignment'] !== 'center'
			)
		) {
			$attributes['verticalAlign'][ 'value' . $device ] = $attributes['verticalAlignment'];
			$attributes['verticalAlignment'] = '';
		} elseif (
			$attributes['verticalAlignment'] !== '' && $attributes['verticalAlignment'] === 'center'
		) {
			$attributes['verticalAlignment'] = '';
		}
		// Temporary approch instead of attribute migration. Might remove it in future

		if ( ! empty( $attributes['verticalAlign'][ 'value' . $device ] ) ) {
			$carousel_css['align-items'] = $attributes['verticalAlign'][ 'value' . $device ];
		}

		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['carouselHeight'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 300,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'min-height',
				'device' => $device,
			]),
			$carousel_css,
		);
	}


	public function get_navigation_button_css( $attributes, $device = '' ) {
		$navigation_button_css = [];

		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['navigationIconPositionY'],
				'attribute_object_key' => 'value',
				'defaultValue' => 50,
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'top',
				'device' => $device,
			]),
			$navigation_button_css,
		);
	}


	public function get_navigation_prev_button_css( $attributes, $device = '' ) {
		$navigation_prev_button_css = [];

		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['navigationIconPositionPrevX'],
				'attribute_object_key' => 'value',
				'defaultValue' => -3,
				'hasUnit' => true,
				'isResponsive' => true,
				'unitDefaultValue' => '%',
				'property' => 'left',
				'device' => $device,
			]),
			$navigation_prev_button_css,
		);
	}

	public function get_navigation_next_button_css( $attributes, $device = '' ) {
		$navigation_next_button_css = [];

		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['navigationIconPositionNextX'],
				'attribute_object_key' => 'value',
				'defaultValue' => -3,
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'right',
				'device' => $device,
			]),
			$navigation_next_button_css,
		);
	}


	public function get_navigation_icon_css( $attributes, $device = '' ) {
		$navigation_icon_css = [];

		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['navigationIconSize'],
				'attribute_object_key' => 'value',
				'defaultValue' => 35,
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'font-size',
				'device' => $device,
			]),
			$navigation_icon_css,
		);
	}


	public function get_navigation_icon_svg_css( $attributes, $device = '' ) {
		$navigation_icon_svg_css = [];
		if ( isset( $attributes['navigationIconColor'] ) ) {
			$navigation_icon_svg_css['fill'] = Color::get_css( isset( $attributes['navigationIconColor'] ) ? $attributes['navigationIconColor'] : '' );
		};
		if ( isset( $attributes['navigationIconBgColor'] ) ) {
			$navigation_icon_svg_css['background-color'] = Color::get_css( isset( $attributes['navigationIconBgColor'] ) ? $attributes['navigationIconBgColor'] : '' );
		};
		return array_merge(
			$navigation_icon_svg_css,
			Dimensions::get_css( $attributes['navigationIconPadding'], 'padding', $device ),
			Border::get_css( $attributes['navigationIconBorder'], '', $device ),
			BoxShadow::get_css( $attributes['navigationIconBoxShadow'], $device ),
		);
	}
	public function get_navigation_icon_svg_hover_css( $attributes, $device = '' ) {
		$navigation_icon_svg_hover_css = [];
		if ( isset( $attributes['navigationIconColorH'] ) ) {
			$navigation_icon_svg_hover_css['fill'] = Color::get_css( isset( $attributes['navigationIconColorH'] ) ? $attributes['navigationIconColorH'] : '' );
		};
		if ( isset( $attributes['navigationIconBgColorH'] ) ) {
			$navigation_icon_svg_hover_css['background-color'] = Color::get_css( isset( $attributes['navigationIconBgColorH'] ) ? $attributes['navigationIconBgColorH'] : '' );
		};
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['navigationIconTransition'],
				'attribute_object_key' => 'value',
				'defaultValue' => 10,
				'unitDefaultValue' => 's',
				'property' => 'transition-duration',
				'device' => $device,
			]),
			$navigation_icon_svg_hover_css,
			Border::get_hover_css( $attributes['navigationIconBorder'], '', $device ),
			BoxShadow::get_hover_css( $attributes['navigationIconBoxShadow'], '', $device ),
		);
	}

	public function get_pagination_parent_css( $attributes, $device = '' ) {
		return array_merge(
			Range::get_css([
				'attributeValue' => $attributes['paginationPositionX'],
				'attribute_object_key' => 'value',
				'defaultValue' => 47,
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'left',
				'device' => $device,
			]),
			Range::get_css([
				'attributeValue' => $attributes['paginationPositionY'],
				'attribute_object_key' => 'value',
				'defaultValue' => 100,
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => '%',
				'property' => 'top',
				'device' => $device,
			]),
		);
	}
	public function get_pagination_color_css( $attributes, $device = '' ) {
		$pagination_color_css = [];
		$type = isset( $attributes['paginationType'] ) ? $attributes['paginationType'] : 'default';
		$pagination_color_css = [];
		if ( isset( $attributes['paginationColor'] ) ) {
			$color = Color::get_css( $attributes['paginationColor'] );
			if ( $type === 'default' ) {
				$pagination_color_css['background-color'] = $color;
			} elseif ( $type === 'border1' || $type === 'border3' ) {
				$pagination_color_css['border-color']     = $color;
				$pagination_color_css['background-color'] = 'transparent';
			} elseif ( $type === 'border2' ) {
				$pagination_color_css['background-color'] = $color;
				$pagination_color_css['border-color']     = $color;
			}
		}
		if ( $type === 'border3' ) {
			$dims = $this->get_ellipse_dims( $attributes['paginationSize'], $device );
			$pagination_color_css['width']         = $dims['width'];
			$pagination_color_css['height']        = $dims['height'];
			$pagination_color_css['border-radius'] = '9999px';
		}
		return array_merge(
			$pagination_color_css,
			( $type === 'border3' ? [] : Range::get_css([
				'attributeValue' => $attributes['paginationSize'],
				'attribute_object_key' => 'value',
				'defaultValue' => 8,
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			]) ),
			( $type === 'border3' ? [] : Range::get_css([
				'attributeValue' => $attributes['paginationSize'],
				'attribute_object_key' => 'value',
				'defaultValue' => 8,
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			]) ),
			Border::get_css( $attributes['paginationBorder'], '', $device ),
		);
	}
	public function get_pagination_color_hover_css( $attributes, $device = '' ) {
		$pagination_color_css = [];
		if ( isset( $attributes['paginationHoverColor'] ) ) {
			$pagination_color_css['background-color'] = $attributes['paginationHoverColor'];
		};
		$type = isset( $attributes['paginationType'] ) ? $attributes['paginationType'] : 'default';
		$pagination_color_css = [];
		if ( isset( $attributes['paginationHoverColor'] ) ) {
			$color = Color::get_css( $attributes['paginationHoverColor'] );
			if ( $type === 'default' ) {
				$pagination_color_css['background-color'] = $color;
			} elseif ( $type === 'border1' || $type === 'border3' ) {
				$pagination_color_css['border-color']     = $color;
				$pagination_color_css['background-color'] = $color;
			} elseif ( $type === 'border2' ) {
				$pagination_color_css['background-color'] = 'transparent';
				$pagination_color_css['border-color']     = $color;
			}
		}
		if ( $type === 'border3' ) {
			$dims = $this->get_ellipse_dims( $attributes['paginationHoverSize'], $device );
			$pagination_color_css['width']         = $dims['width'];
			$pagination_color_css['height']        = $dims['height'];
			$pagination_color_css['border-radius'] = '9999px';
		}
		return array_merge(
			$pagination_color_css,
			( $type === 'border3' ? [] : Range::get_css([
				'attributeValue' => $attributes['paginationHoverSize'],
				'attribute_object_key' => 'value',
				'defaultValue' => 8,
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			]) ),
			( $type === 'border3' ? [] : Range::get_css([
				'attributeValue' => $attributes['paginationHoverSize'],
				'attribute_object_key' => 'value',
				'defaultValue' => 8,
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			]) ),
			Border::get_hover_css( $attributes['paginationBorder'], '', $device ),
		);
	}

	public function get_pagination_active_color_css( $attributes, $device = '' ) {
		$pagination_active_color_css = [];
		$type = isset( $attributes['paginationType'] ) ? $attributes['paginationType'] : 'default';
		$pagination_active_color_css = [];
		if ( isset( $attributes['paginationActiveColor'] ) ) {
			$color = Color::get_css( $attributes['paginationActiveColor'] );
			if ( $type === 'default' ) {
				$pagination_active_color_css['background-color'] = $color;
			} elseif ( $type === 'border1' || $type === 'border3' ) {
				$pagination_active_color_css['background-color'] = $color;
				$pagination_active_color_css['border-color']     = $color;
			} elseif ( $type === 'border2' ) {
				$pagination_active_color_css['border-color']     = $color;
				$pagination_active_color_css['background-color'] = 'transparent';
			}
		}
		if ( $type === 'border3' ) {
			$dims = $this->get_ellipse_dims( $attributes['paginationActiveSize'], $device );
			$pagination_active_color_css['width']         = $dims['width'];
			$pagination_active_color_css['height']        = $dims['height'];
			$pagination_active_color_css['border-radius'] = '9999px';
		}
		return array_merge(
			$pagination_active_color_css,
			( $type === 'border3' ? [] : Range::get_css([
				'attributeValue' => $attributes['paginationActiveSize'],
				'attribute_object_key' => 'value',
				'defaultValue' => 8,
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			]) ),
			( $type === 'border3' ? [] : Range::get_css([
				'attributeValue' => $attributes['paginationActiveSize'],
				'attribute_object_key' => 'value',
				'defaultValue' => 8,
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			]) ),
			Border::get_css( $attributes['activePaginationBorder'], '', $device ),
		);
	}
	public function get_pagination_active_color_hover_css( $attributes, $device = '' ) {
		$pagination_active_color_css = [];
		$type = isset( $attributes['paginationType'] ) ? $attributes['paginationType'] : 'default';
		$pagination_active_color_css = [];
		if ( isset( $attributes['paginationActiveHoverColor'] ) ) {
			$color = Color::get_css( $attributes['paginationActiveHoverColor'] );
			if ( $type === 'default' ) {
				$pagination_active_color_css['background-color'] = $color;
			} elseif ( $type === 'border1' || $type === 'border3' ) {
				$pagination_active_color_css['border-color']     = $color;
				$pagination_active_color_css['background-color'] = 'transparent';
			} elseif ( $type === 'border2' ) {
				$pagination_active_color_css['background-color'] = $color;
				$pagination_active_color_css['border-color']     = $color;
			}
		}
		if ( $type === 'border3' ) {
			$dims = $this->get_ellipse_dims( $attributes['paginationActiveHoverSize'], $device );
			$pagination_active_color_css['width']         = $dims['width'];
			$pagination_active_color_css['height']        = $dims['height'];
			$pagination_active_color_css['border-radius'] = '9999px';
		}

		return array_merge(
			$pagination_active_color_css,
			( $type === 'border3' ? [] : Range::get_css([
				'attributeValue' => $attributes['paginationActiveHoverSize'],
				'attribute_object_key' => 'value',
				'defaultValue' => 8,
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			]) ),
			( $type === 'border3' ? [] : Range::get_css([
				'attributeValue' => $attributes['paginationActiveHoverSize'],
				'attribute_object_key' => 'value',
				'defaultValue' => 8,
				'isResponsive' => true,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'height',
				'device' => $device,
			]) ),
			Border::get_hover_css( $attributes['activePaginationBorder'], '', $device ),
		);
	}

	private function get_ellipse_dims( $sizeAttr, $device = '', $default = 8, $ratio = 1.8 ) {
		$vKey = 'value' . $device;
		$uKey = 'valueUnit' . $device;

		$v = isset( $sizeAttr[ $vKey ] ) ? floatval( $sizeAttr[ $vKey ] )
			: ( isset( $sizeAttr['value'] ) ? floatval( $sizeAttr['value'] ) : $default );
		$u = isset( $sizeAttr[ $uKey ] ) ? $sizeAttr[ $uKey ]
			: ( isset( $sizeAttr['valueUnit'] ) ? $sizeAttr['valueUnit'] : 'px' );

		return [
			'height' => $v . $u,
			'width'  => ( $v * $ratio ) . $u,
		];
	}


}
