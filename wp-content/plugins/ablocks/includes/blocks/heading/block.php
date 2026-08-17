<?php
namespace ABlocks\Blocks\Heading;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $block_name = 'heading';

	public function build_css_v1( $attributes ) {
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-heading-text',
			$this->get_heading_text_css( $attributes ),
			$this->get_heading_text_css( $attributes, 'Tablet' ),
			$this->get_heading_text_css( $attributes, 'Mobile' )
		);

		$desktop_heading_a_styles = $this->get_heading_a_css( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--heading a',
			$desktop_heading_a_styles,
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-animated-text path',
			$this->get_svg_path_css( $attributes ),
			$this->get_svg_path_css( $attributes, 'Tablet' ),
			$this->get_svg_path_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-animated-text',
			$this->get_heading_general_css( $attributes ),
			$this->get_heading_general_css( $attributes, 'Tablet' ),
			$this->get_heading_general_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-animated-text-wrapper',
			$this->get_heading_animated_css( $attributes ),
			$this->get_heading_animated_css( $attributes, 'Tablet' ),
			$this->get_heading_animated_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	public function build_css_v2( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);

		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-heading-text',
			$this->get_heading_text_css( $attributes ),
			$this->get_heading_text_css( $attributes, 'Tablet' ),
			$this->get_heading_text_css( $attributes, 'Mobile' )
		);
		$desktop_heading_a_styles = $this->get_heading_a_css( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}}.ablocks-block--heading a',
			$desktop_heading_a_styles,
		);
		if ( ! empty( $attributes['isAnimated'] ) ) {
			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-animated-text path',
				$this->get_svg_path_css( $attributes ),
				$this->get_svg_path_css( $attributes, 'Tablet' ),
				$this->get_svg_path_css( $attributes, 'Mobile' )
			);

			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-animated-text',
				$this->get_heading_general_css( $attributes ),
				$this->get_heading_general_css( $attributes, 'Tablet' ),
				$this->get_heading_general_css( $attributes, 'Mobile' )
			);

			$css_generator->add_class_styles(
				'{{WRAPPER}} .ablocks-animated-text-wrapper',
				$this->get_heading_animated_css( $attributes ),
				$this->get_heading_animated_css( $attributes, 'Tablet' ),
				$this->get_heading_animated_css( $attributes, 'Mobile' )
			);
		}//end if

		return $css_generator->generate_css();
	}

	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}

	public function get_wrapper_css( $attributes, $device = '' ) {
		return isset( $attributes['alignment'] ) ? Alignment::get_css( $attributes['alignment'], 'text-align', $device ) : [];
	}
	public function get_heading_text_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : '';
		$typography_css = ! empty( $attributes['typography'] ) ? Typography::get_css( $attributes['typography'], '', $device, $typographyValueGlobal ) : array();
		$textShadowCss = ! empty( $attributes['textShadow'] ) ? TextShadow::get_css( $attributes['textShadow'], '', $device ) : array();
		$textStrokeCss = ! empty( $attributes['textStroke'] ) ? TextStroke::get_css( $attributes['textStroke'], '', $device ) : array();

		return array_merge( [
			'color' => Color::get_css( isset( $attributes['textColor'] ) ? $attributes['textColor'] : '' ),
		], $typography_css, $textShadowCss, $textStrokeCss );
	}

	public function get_heading_a_css( $attributes ) {
		$decoration = isset( $attributes ['typography']['decoration'] ) ? $attributes['typography'] ['decoration'] : '';
		if ( ! empty( $decoration ) ) {
			return array( 'text-decoration' => $decoration ); }
		return array();
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		if ( ! isset( $block_instance->context['postId'] ) ) {
			return '';
		}
		$title = get_the_title();
		if ( ! $title ) {
			return '';
		}
		$tag_name = 'h2';
		if ( ! empty( $attributes['headingTag'] ) ) {
			$tag_name = $attributes['headingTag'];
		}
		return sprintf(
			'<%1$s>%2$s</%1$s>',
			$tag_name,
			$title
		);
	}


	public function get_svg_path_css( $attributes, $device = '' ) {
		$count = ! empty( $attributes['isInfiniteLoop'] ) ? 'infinite' : 1;

		$highlightStrokeWidth = isset( $attributes['highlightStrokeWidth'] ) && is_array( $attributes['highlightStrokeWidth'] ) ? $attributes['highlightStrokeWidth'] : [];
		$unit = $highlightStrokeWidth[ 'valueUnit' . $device ] ?? $highlightStrokeWidth['valueUnit'] ?? 'px';
		$value = $highlightStrokeWidth[ 'value' . $device ] ?? $highlightStrokeWidth['value'] ?? 10;
		$stroke_width = $value . $unit;
		$highlightDuration = isset( $attributes['highlightDuration'] ) ? $attributes['highlightDuration'] : 0;
		$highlightDelay = isset( $attributes['highlightDelay'] ) ? $attributes['highlightDelay'] : 0;

		return array_merge(
			[
				'stroke' => Color::get_css(
					isset( $attributes['highlightColor'] )
					? $attributes['highlightColor']
					: '#000'
				)
			],
			[
				'stroke-width' => $stroke_width,
				'animation'    => 'draw-auto ' . ( $highlightDuration + $highlightDelay ) . 'ms forwards ' . $count
			]
		);
	}



	public function get_heading_general_css( $attributes, $device = '' ) {
		$typographyValueGlobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : '';
		$typography_value = isset( $attributes['typography'] ) ? $attributes['typography'] : [];
		$stock_unit_value = isset( $attributes['textStroke'] ) ? $attributes['textStroke'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['textColor'] ) ? $attributes['textColor'] : '' ) ],
			Typography::get_css( $typography_value, '', $device, $typographyValueGlobal ),
			TextStroke::get_css( $stock_unit_value, '', $device ),
			TextShadow::get_css( isset( $attributes['textShadow'] ) ? $attributes['textShadow'] : [], '', $device )
		);
	}

	public function get_heading_animated_css( $attributes, $device = '' ) {
		$typography_value = isset( $attributes['animatedTypography'] ) ? $attributes['animatedTypography'] : [];
		$stroke_unit_value = isset( $attributes['animatedTextStroke'] ) ? $attributes['animatedTextStroke'] : [];
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['animatedTextColor'] ) ? $attributes['animatedTextColor'] : '' ) ],
			Typography::get_css( $typography_value, '', $device ),
			TextStroke::get_css( $stroke_unit_value, '', $device ),
			TextShadow::get_css( isset( $attributes['animatedTextShadow'] ) ? $attributes['animatedTextShadow'] : [], '', $device )
		);
	}
}
