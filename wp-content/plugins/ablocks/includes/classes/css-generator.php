<?php
namespace ABlocks\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\AssetsGenerator;
use ABlocks\Classes\FontStack;

class CssGenerator {
	private $custom_css = '';
	private $parent_class;
	private $class_styles = [];

	public function __construct( $attributes = [] ) {
		if ( ! isset( $attributes['block_id'] ) ) {
			return;
		}
		$this->parent_class = '.ablocks-block-' . $attributes['block_id'];
		if ( isset( $attributes['_custom_css'] ) ) {
			$this->custom_css = $attributes['_custom_css'];
		}


		// Alert - don't touch here
		if ( isset( $attributes['_margin'] ) ) { // check has advanced tab or not
			$this->add_class_styles(
				'{{WRAPPER}}',
				BlockGlobal::get_wrapper_css( $attributes ),
				BlockGlobal::get_wrapper_css( $attributes, 'Tablet' ),
				BlockGlobal::get_wrapper_css( $attributes, 'Mobile' )
			);
			$this->add_class_styles(
				'{{WRAPPER}}:hover',
				BlockGlobal::get_wrapper_hover_css( $attributes ),
				BlockGlobal::get_wrapper_hover_css( $attributes, 'Tablet' ),
				BlockGlobal::get_wrapper_hover_css( $attributes, 'Mobile' )
			);
			$this->add_class_styles(
				'{{WRAPPER}}.ablocks-hide-on-desktop,{{WRAPPER}}.ablocks-hide-on-tablet,{{WRAPPER}}.ablocks-hide-on-mobile',
				BlockGlobal::get_wrapper_device_responsive_css( $attributes ),
				BlockGlobal::get_wrapper_device_responsive_css( $attributes, 'Tablet' ),
				BlockGlobal::get_wrapper_device_responsive_css( $attributes, 'Mobile' )
			);
			$this->add_class_styles(
				'{{WRAPPER}}:hover > .ablocks-block-container',
				BlockGlobal::get_container_hover_css( $attributes ),
				BlockGlobal::get_container_hover_css( $attributes, 'Tablet' ),
				BlockGlobal::get_container_hover_css( $attributes, 'Mobile' )
			);
			$this->add_class_styles(
				'{{WRAPPER}} > .ablocks-block-container',
				BlockGlobal::get_container_css( $attributes ),
				BlockGlobal::get_container_css( $attributes, 'Tablet' ),
				BlockGlobal::get_container_css( $attributes, 'Mobile' )
			);
		}//end if
	}

	public function add_class_styles( $class_name, $desktop_styles, $tablet_styles = [], $mobile_styles = [] ) {
		$this->class_styles[] = [
			'class_name' => $class_name,
			'desktop_styles' => $desktop_styles,
			'tablet_styles' => $tablet_styles,
			'mobile_styles' => $mobile_styles
		];
	}

	public function generate_css() {
		$css_output = '';

		foreach ( $this->class_styles as $class_style ) {
			$desktop_styles = $this->remove_empty_css( $class_style['desktop_styles'] );
			$tablet_styles  = $this->filter_responsive_styles( $desktop_styles, $class_style['tablet_styles'] );
			$mobile_styles  = $this->filter_responsive_styles( array_merge( $desktop_styles, $tablet_styles ), $class_style['mobile_styles'] );

			$desktop_raw = $this->generate_css_for_media_query( 'desktop', $desktop_styles );
			$tablet_raw  = $this->generate_css_for_media_query( 'tablet', $tablet_styles );
			$mobile_raw  = $this->generate_css_for_media_query( 'mobile', $mobile_styles );

			$desktop_css = AssetsGenerator::minify_css( $desktop_raw );
			$tablet_css  = AssetsGenerator::minify_css( $tablet_raw );
			$mobile_css  = AssetsGenerator::minify_css( $mobile_raw );

			$parent_selector = $this->get_parent_selector( $class_style['class_name'] );
			$css_blocks = [];

			$addToCssBlocks = function ( $mediaQuery, $max_width, $css ) use ( &$css_blocks, $parent_selector ) {
				if ( ! empty( $css ) ) {
					$css_blocks[] = ( '' !== $mediaQuery ) ? "@media screen and (max-width: $max_width) {\n$parent_selector {\n$css\n}\n}" : "$parent_selector {\n$css\n}";
				}
			};

			// Always add desktop CSS
			$addToCssBlocks( '', '', $desktop_css );

			// Only add tablet CSS if it differs from desktop
			if ( $tablet_raw !== $desktop_raw ) {
				$addToCssBlocks( 'tablet', $this->get_breakpoint( 'tablet' ), $tablet_css );
			}

			// Only add mobile CSS if it differs from desktop AND tablet
			if ( $mobile_raw !== $tablet_raw ) {
				$addToCssBlocks( 'mobile', $this->get_breakpoint( 'mobile' ), $mobile_css );
			}

			$css_output .= implode( "\n\n", $css_blocks ) . "\n\n";
		}//end foreach

		$css_output .= $this->get_custom_css();

		return preg_replace( '/\s+/', ' ', $css_output );
	}
	public function get_custom_css() {
		return preg_replace( '/\bselector\b/', $this->parent_class, $this->custom_css );
	}
	public function generate_css_for_media_query( $media_query, $styles ) {
		if ( empty( $styles ) ) {
			return '';
		}
		$css_string = implode("\n", array_map(
			function ( $property, $value ) {
				return "$property: $value;";
			},
			array_keys( $styles ),
			$styles
		));

		return $css_string . "\n";
	}

	public function get_breakpoint( $media_query ) {
		switch ( $media_query ) {
			case 'tablet':
				return '800px';
			case 'mobile':
				return '480px';
			default:
				return '1200px';
		}
	}

	public function get_parent_selector( $class_name ) {
		return $this->parent_class ? str_replace( '{{WRAPPER}}', $this->parent_class, $class_name ) : $class_name;
	}

	private function remove_empty_css( $base_styles ) {
		if ( empty( $base_styles ) || ! is_array( $base_styles ) || ( is_array( $base_styles ) && ! count( $base_styles ) ) ) {
			return [];
		}

		$styles = [];

		foreach ( $base_styles as $prop => $value ) {
			if ( 'font-family' === $prop ) {
				// Safety net for styles that bypass the Typography control and set a
				// bare family name directly. FontStack leaves finished values alone.
				$value = FontStack::build( $value );
			}
			// Trim value to avoid whitespace-only entries
			$trimmed = trim( $value );
			// // Remove if empty, or if it matches only a unit (like 'px', '%', 'em') without any number
			if ( $trimmed === '' || preg_match( '/^(px|em|rem|vh|vw|vmin|vmax|cm|mm|in|pt|pc|%|s|ms|deg|fr|ch|ex)$/i', $trimmed ) ) {
				continue;
			}

			$styles[ $prop ] = $trimmed;
		}

		return $styles;
	}

	private function filter_responsive_styles( $base_styles, $responsive_styles ) {
		if ( empty( $responsive_styles ) || ! is_array( $responsive_styles ) ) {
			return [];
		}

		$difference = [];

		foreach ( $responsive_styles as $prop => $value ) {
			$trimmed = trim( $value );

			// // Remove if empty, or if it matches only a unit (like 'px', '%', 'em') without any number
			if ( $trimmed === '' || preg_match( '/^(px|em|rem|vh|vw|vmin|vmax|cm|mm|in|pt|pc|%|s|ms|deg|fr|ch|ex)$/i', $trimmed ) ) {
				continue;
			}

			// Only include if the value is different from base styles
			if ( ! isset( $base_styles[ $prop ] ) || $base_styles[ $prop ] !== $trimmed ) {
				$difference[ $prop ] = $trimmed;
			}
		}

		return $difference;
	}


	public function get_font_family_css( string $font_name, string $category = '' ): string {
		// Kept for back-compat; the stack is built centrally now.
		return FontStack::build( $font_name );
	}
}

