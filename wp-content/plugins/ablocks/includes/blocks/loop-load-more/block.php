<?php
namespace ABlocks\Blocks\LoopLoadMore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'loop-builder';
	protected $block_name = 'loop-load-more';

	public function build_css( $attributes ) {

		// Generate CSS start
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );
			// button style
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->loop_load_More_Wrapper( $attributes ),
			$this->loop_load_More_Wrapper( $attributes, 'Tablet' ),
			$this->loop_load_More_Wrapper( $attributes, 'Mobile' ),
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-loop-load-more__text',
			$this->loop_load_More_button( $attributes ),
			$this->loop_load_More_button( $attributes, 'Tablet' ),
			$this->loop_load_More_button( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-loop-load-more__text:hover',
			$this->loop_load_More_button_hover( $attributes ),
			$this->loop_load_More_button_hover( $attributes, 'Tablet' ),
			$this->loop_load_More_button_hover( $attributes, 'Mobile' )
		);
		return $css_generator->generate_css();
	}
	public function render_block_content( $attributes, $content, $block_instance ) {
		global $post;

		$current_post_id    = isset( $post->ID ) ? intval( $post->ID ) : 0;
		$load_more_text     = isset( $attributes['loadMoreButtonText'] ) ? esc_html( $attributes['loadMoreButtonText'] ) : '';
		$no_more_items_text = isset( $attributes['noMoreItemsText'] ) ? esc_attr( $attributes['noMoreItemsText'] ) : '';

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
		$taxonomy = esc_attr( $taxonomy );

		// Determine archive info
		$is_archive = is_archive() ? 'true' : 'false';
		$archive_post_type = is_post_type_archive() ? get_query_var( 'post_type' ) : get_post_type();
		if ( is_array( $archive_post_type ) ) {
			$archive_post_type = reset( $archive_post_type );
		}
		$archive_post_type = esc_attr( $archive_post_type );

		$term_id = null;
		if ( is_tax() || is_category() || is_tag() ) {
			$term = get_queried_object();

			if ( $term instanceof \WP_Term ) {
				$term_id = $term->term_id;
			}
		}

		echo '<span 
			class="ablocks-loop-load-more__text" 
			data-post-id="' . esc_attr( $current_post_id ) . '" 
			data-term-id="' . esc_attr( $term_id ) . '" 
			data-no-item-text="' . esc_attr( $no_more_items_text ) . '" 
			data-more-button-text="' . esc_attr( $load_more_text ) . '" 
			data-taxonomy="' . esc_attr( $taxonomy ) . '" 
			data-is-archive="' . esc_attr( $is_archive ) . '" 
			data-archive-post-type="' . esc_attr( $archive_post_type ) . '"
		>' . esc_attr( $load_more_text ) . '</span>';
	}


	private function loop_load_More_Wrapper( $attributes, $device = '' ) {
		$css = [];
		if ( isset( $attributes['moreButtonAlignment'] ) ) {
			$css['justify-content'] = $attributes['moreButtonAlignment'];
		}

		return array_merge(
			$css,
			Range::get_css([
				'attributeValue' => $attributes['loadMoreButtonGap'] ?? null,
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => false,
				'unitDefaultValue' => 'px',
				'defaultValue' => '',
				'property' => 'margin-top',
				'device' => $device,
			])
		);
	}
	private function loop_load_More_button( $attributes, $device = '' ) {
		$typographyGlobal = isset( $attributes['moreButtonTypographyGlobal'] ) ? $attributes['moreButtonTypographyGlobal'] : array();

		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['loadMoreButtonTextColor'] ) ? $attributes['loadMoreButtonTextColor'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['loadMoreButtonBackground'] ) ? $attributes['loadMoreButtonBackground'] : '' ) ],
			Border::get_css( $attributes['moreButtonBorder'], '', $device ),
			BoxShadow::get_css( $attributes['moreButtonboxShadow'], '', $device ),
			Typography::get_css( $attributes['moreButtonTypography'], '', $device, $typographyGlobal ),
			Dimensions::get_css( $attributes['moreButtonPadding'], 'padding', $device ),
		);
	}
	private function loop_load_More_button_hover( $attributes, $device = '' ) {
		$css = [];
		if ( ! empty( $attributes['loadMoreButtonTransition'] ) ) {
			$css['transition-duration'] = $attributes['loadMoreButtonTransition'] . 's';
		}
		return array_merge(
			[ 'color' => Color::get_css( isset( $attributes['loadMoreButtonTextColorH'] ) ? $attributes['loadMoreButtonTextColorH'] : '' ) ],
			[ 'background' => Color::get_css( isset( $attributes['loadMoreButtonBackgroundH'] ) ? $attributes['loadMoreButtonBackgroundH'] : '' ) ],
			$css,
			Border::get_hover_css( $attributes['moreButtonBorder'], '', $device ),
			BoxShadow::get_hover_css( $attributes['moreButtonboxShadow'], '', $device )
		);
	}
}
