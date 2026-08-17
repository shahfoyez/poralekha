<?php
namespace ABlocks\Blocks\LoopTemplate;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Range;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Color;

class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'loop-builder';
	protected $block_name = 'loop-template';
	protected $is_skip_inner_block = true;

	public function build_css( $attributes ) {

		// Generate CSS start
		$css_generator = new CssGenerator( $attributes, $this->block_name );
		// Generate CSS using the loop template styles
		$css_generator->add_class_styles(
			'{{WRAPPER}} .wp-block-ablocks-loop-template',
			$this->loop_template_wrapper( $attributes ),
			$this->loop_template_wrapper( $attributes, 'Tablet' ),
			$this->loop_template_wrapper( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-loop-template-item',
			$this->template_card_style_css( $attributes ),
			$this->template_card_style_css( $attributes, 'Tablet' ),
			$this->template_card_style_css( $attributes, 'Mobile' )
		);
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-loop-template-item:hover',
			$this->template_card_style_hover_css( $attributes ),
			$this->template_card_style_hover_css( $attributes, 'Tablet' ),
			$this->template_card_style_hover_css( $attributes, 'Mobile' )
		);

		return $css_generator->generate_css();
	}
	private function loop_template_wrapper( $attributes, $device = '' ) {
		$css = [];
		// Temporary approch instead of attribute migration. Might remove it in future
		if ( ! empty( $attributes['gridColumns'] ) && (int) $attributes['gridColumns'] !== 2 ) {
			$attributes['templateGridColumns'][ 'value' . $device ] = $attributes['gridColumns'];
			$attributes['gridColumns'] = '';
		}
		// Temporary approch instead of attribute migration. Might remove it in future

		if ( ! empty( $attributes['gridStyle'] ) && $attributes['gridStyle'] === 'grid' ) {
			$css['display'] = 'grid';
			$columnCSS = Range::get_css([
				'attributeValue' => $attributes['templateGridColumns'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => false,
				'defaultValue' => 2,
				'defaultValueTablet' => 2,
				'defaultValueMobile' => 1,
				'unitDefaultValue' => '',
				'property' => 'grid-template-columns',
				'device' => $device,
			]);
			if ( ! empty( $columnCSS ) ) {
				$css['grid-template-columns'] = 'repeat(' . $columnCSS['grid-template-columns'] . ', 1fr)';
			};
		};

		return array_merge(
			$css,
			Range::get_css([
				'attributeValue' => $attributes['itemGap'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'hasUnit' => false,
				'unitDefaultValue' => 'px',
				'defaultValue' => 10,
				'property' => 'gap',
				'device' => $device,
			])
		);
	}

	public function render_block_content( $attributes, $content, $block ) {
		$page_key            = isset( $block->context['queryId'] ) ? 'query-' . $block->context['queryId'] . '-page' : 'query-page';
		$enhanced_pagination = isset( $block->context['enhancedPagination'] ) && $block->context['enhancedPagination'];
		// phpcs:ignore  WordPress.Security.NonceVerification.Recommended 
		$page                = empty( $_GET[ $page_key ] ) ? 1 : (int) $_GET[ $page_key ];

		// Use global query if needed.
		$use_global_query = ( isset( $block->context['query']['inherit'] ) && $block->context['query']['inherit'] );
		// Disable use of global query if this is an AJAX request.
		if ( wp_doing_ajax() ) {
			$use_global_query = false;
		}

		if ( $use_global_query ) {
			global $wp_query;

			if ( in_the_loop() ) {
				$query = clone $wp_query;
				$query->rewind_posts();
			} else {
				$query = $wp_query;
			}
		} else {
			$query_args = build_query_vars_from_query_block( $block, $page );
			$query_args = isset( $attributes['query'] ) ? array_merge( $query_args, $attributes['query'] ) : $query_args;
			$query      = new \WP_Query( $query_args );
		}

		if ( ! $query->have_posts() ) {
			return '';
		}

		if ( block_core_post_template_uses_featured_image( $block->inner_blocks ) ) {
			update_post_thumbnail_cache( $query );
		}

		$classnames = '';
		if ( isset( $block->context['displayLayout'] ) && isset( $block->context['query'] ) ) {
			if ( isset( $block->context['displayLayout']['type'] ) && 'flex' === $block->context['displayLayout']['type'] ) {
				$classnames = "is-flex-container columns-{$block->context['displayLayout']['columns']}";
			}
		}
		if ( isset( $attributes['style']['elements']['link']['color']['text'] ) ) {
			$classnames .= ' has-link-color';
		}

		// Ensure backwards compatibility by flagging the number of columns via classname when using grid layout.
		if ( isset( $attributes['layout']['type'] ) && 'grid' === $attributes['layout']['type'] && ! empty( $attributes['layout']['columnCount'] ) ) {
			$classnames .= ' ' . sanitize_title( 'columns-' . $attributes['layout']['columnCount'] );
		}

		$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => trim( $classnames ) ) );

		$content = '';
		while ( $query->have_posts() ) {
			$query->the_post();

			$block_instance = $block->parsed_block;

			$block_instance['blockName'] = 'core/null';

			$post_id              = get_the_ID();
			$post_type            = get_post_type();
			$filter_block_context = static function ( $context ) use ( $post_id, $post_type ) {
				$context['postType'] = $post_type;
				$context['postId']   = $post_id;
				return $context;
			};

			add_filter( 'render_block_context', $filter_block_context, 1 );

			$block_content = ( new \WP_Block( $block_instance ) )->render( array( 'dynamic' => false ) );
			remove_filter( 'render_block_context', $filter_block_context, 1 );

			$post_classes = implode( ' ', get_post_class( 'ablocks-loop-template-item' ) );

			$inner_block_directives = $enhanced_pagination ? ' data-wp-key="post-template-item-' . $post_id . '"' : '';

			$content .= '<div' . $inner_block_directives . ' class="' . esc_attr( $post_classes ) . '">' . $block_content . '</div>';
		}//end while

		wp_reset_postdata();

		return sprintf(
			'<div %1$s>%2$s</div>',
			$wrapper_attributes,
			$content
		);
	}

	public function template_card_style_css( $attributes, $device = '' ) {
		$templte_padding_css = ! empty( $attributes['padding'] ) ? Dimensions::get_css( $attributes['padding'], 'padding', $device ) : array();
		return array_merge(
			[ 'background' => Color::get_css( isset( $attributes['bgColor'] ) ? $attributes['bgColor'] : '' ) ],
			$templte_padding_css,
			! empty( $attributes['border'] ) ? Border::get_css( $attributes['border'], '', $device ) : [],
		);
	}
	public function template_card_style_hover_css( $attributes, $device = '' ) {
		$css = [];
		return array_merge(
			$css,
			Border::get_hover_css( $attributes['border'], '', $device ),
		);
	}

}
