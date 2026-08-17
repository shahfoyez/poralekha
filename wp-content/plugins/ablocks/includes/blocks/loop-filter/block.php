<?php
namespace ABlocks\Blocks\LoopFilter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Range;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'loop-builder';
	protected $block_name = 'loop-filter';

	public function build_css( $attributes ) {

		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->filter_button_alignment( $attributes ),
			$this->filter_button_alignment( $attributes, 'Tablet' ),
			$this->filter_button_alignment( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-loop-term-filter',
			$this->get_button_style( $attributes ),
			$this->get_button_style( $attributes, 'Tablet' ),
			$this->get_button_style( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-loop-term-filter:hover',
			$this->get_button_style_hover( $attributes ),
			$this->get_button_style_hover( $attributes, 'Tablet' ),
			$this->get_button_style_hover( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-loop-term-filter--active',
			$this->get_active_button_style( $attributes ),
			$this->get_active_button_style( $attributes, 'Tablet' ),
			$this->get_active_button_style( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-loop-term-filter--active:hover',
			$this->get_active_button_style_hover( $attributes ),
			$this->get_active_button_style_hover( $attributes, 'Tablet' ),
			$this->get_active_button_style_hover( $attributes, 'Mobile' )
		);
		return $css_generator->generate_css();
	}

	public function render_block_content( $attributes, $content, $block_instance ) {
		global $post;

		$current_post_id = isset( $post->ID ) ? $post->ID : 0;
		$active_term_id = ! empty( $attributes['active_term_id'] ) ? $attributes['active_term_id'] : '*';
		if ( ! empty( $attributes['taxonomy'] ) && $attributes['taxonomy'] !== 'inherit' ) {
			$taxonomy = $attributes['taxonomy'];
		} else {
			// Try to get the current queried taxonomy
			$queried_object = get_queried_object();
			if ( ! empty( $queried_object ) && ! empty( $queried_object->taxonomy ) ) {
				$taxonomy = $queried_object->taxonomy;
			} else {
				$taxonomy = 'category'; // fallback
			}
		}
		// Determine archive state and post type (null if not archive)
		$is_archive = is_archive() ? 'true' : 'false';
		$archive_post_type = is_post_type_archive() ? get_query_var( 'post_type' ) : get_post_type();
		if ( is_array( $archive_post_type ) ) {
			$archive_post_type = reset( $archive_post_type );
		}
		$archive_post_type = esc_attr( $archive_post_type );

		$taxonomy_term_items = isset( $attributes['taxonomy_term_items'] ) && is_array( $attributes['taxonomy_term_items'] ) ? $attributes['taxonomy_term_items'] : [];

		if ( count( $taxonomy_term_items ) ) {
			foreach ( $taxonomy_term_items as $term_item ) {
				$term_value = isset( $term_item['value'] ) ? $term_item['value'] : '';
				$term_label = isset( $term_item['label'] ) ? $term_item['label'] : '';

				$is_active = (string) $active_term_id === (string) $term_value ? ' ablocks-loop-term-filter--active' : '';

				echo '<span class="ablocks-loop-term-filter' . esc_attr( $is_active ) . '" '
					. 'data-post-id="' . esc_attr( $current_post_id ) . '" '
					. 'data-term-id="' . esc_attr( $term_value ) . '" '
					. 'data-taxonomy="' . esc_attr( $taxonomy ) . '" '
					. 'data-is-archive="' . esc_attr( $is_archive ) . '" '
					. 'data-archive-post-type="' . esc_attr( $archive_post_type ) . '" '
					. 'type="button">'
					. esc_html( $term_label )
					. '</span>';
			}
		}
	}


	public function filter_button_alignment( $attributes, $device = '' ) {
		$css = [];

		$css = array_merge(
			$css,
			Range::get_css([
				'attributeValue' => $attributes['FilterBtnGap'] ?? null,
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => true,
				'defaultValue' => 10,
				'unitDefaultValue' => 'px',
				'property' => 'gap',
				'device' => $device,
			])
		);
		$css['display'] = 'flex';
		$css['width'] = '100%';
		$css['flex-wrap'] = 'wrap';
		if ( ! empty( $attributes['buttonAlignment'] ) ) {
			$css['flex-direction'] = $attributes['buttonAlignment'];
		}

		$alignment_css = isset( $attributes['filterBtnAlignment'] )
		? Alignment::get_css( $attributes['filterBtnAlignment'], 'align-items', $device )
		: [];
		$css = array_merge( $css, $alignment_css );

		$row_alignment_css = isset( $attributes['rowAlignBtn'] )
		? Alignment::get_css( $attributes['rowAlignBtn'], 'justify-content', $device )
		: [];
		$css = array_merge( $css, $row_alignment_css );

		return $css;
	}

	public function get_button_style( $attributes, $device = '' ) {
		$normal_button_border = ! empty( $attributes['filterBtnBorder'] ) ? Border::get_css( $attributes['filterBtnBorder'], '', $device ) : array();
		$typographyGlobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : array();
		$css = array_merge(
			[ 'color' => Color::get_css( isset( $attributes['filterBtnTextColor'] ) ? $attributes['filterBtnTextColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['filterBtnBgColor'] ) ? $attributes['filterBtnBgColor'] : '' ) ],
			$normal_button_border,
			Typography::get_css( isset( $attributes['typography'] ) ? $attributes['typography'] : array(), '', $device, $typographyGlobal ),
			Dimensions::get_css( isset( $attributes['filterButtonPadding'] ) ? $attributes['filterButtonPadding'] : [], 'padding', $device ),
			Dimensions::get_css( isset( $attributes['filterButtonMargin'] ) ? $attributes['filterButtonMargin'] : [], 'margin', $device ),
		);
		return $css;

	}

	public function get_button_style_hover( $attributes, $device = '' ) {
		return array_merge(
			isset( $attributes['filterBtnBorder'] ) ? Border::get_hover_css( $attributes['filterBtnBorder'], '', $device ) : [],
		);
	}
	public function get_active_button_style( $attributes, $device = '' ) {
		$button_border_css = ! empty( $attributes['activeBtnBorder'] )
		? Border::get_css( $attributes['activeBtnBorder'], '', $device )
		: array();
		$typographyGlobal = ! empty( $attributes['typographyGlobal'] ) ? $attributes['typographyGlobal'] : array();
		$css = array_merge(
			[ 'color' => Color::get_css( isset( $attributes['activeBtnTextColor'] ) ? $attributes['activeBtnTextColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['activeBtnBgColor'] ) ? $attributes['activeBtnBgColor'] : '' ) ],
			$button_border_css,
			Typography::get_css( isset( $attributes['typography'] ) ? $attributes['typography'] : array(), '', $device, $typographyGlobal ),
		);
		return $css;
	}

	public function get_active_button_style_hover( $attributes, $device = '' ) {
		return array_merge(
			isset( $attributes['activeBtnBorder'] ) ? Border::get_hover_css( $attributes['activeBtnBorder'], '', $device ) : [],
		);
	}
}

