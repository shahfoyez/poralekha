<?php
namespace ABlocks\Blocks\DynamicText;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Typography;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Color;
use ABlocks\Helper;

class Block extends BlockBaseAbstract {

	protected $block_name = 'dynamic-text';

	public function build_css( $attributes ) {
		$dynamic_text_css = new CssGeneratorV2( $attributes, $this->block_name );

		$dynamic_text_css->add_class_styles(
			'{{WRAPPER}} .ablocks-dynamic-text-output',
			$this->get_dynamic_text_css( $attributes ),
			$this->get_dynamic_text_css( $attributes, 'Tablet' ),
			$this->get_dynamic_text_css( $attributes, 'Mobile' )
		);
		$dynamic_text_css->add_class_styles(
			'{{WRAPPER}} .ablocks-dynamic-text-output a',
			$this->get_dynamic_text_css( $attributes ),
			$this->get_dynamic_text_css( $attributes, 'Tablet' ),
			$this->get_dynamic_text_css( $attributes, 'Mobile' )
		);

		return $dynamic_text_css->generate_css();
	}
	public function get_dynamic_text_css( $attributes, $device = '' ) {

		$dt_text_css = [];
		if ( ! empty( $attributes['dynamicTextColor'] ) ) {
			$dt_text_css['color'] = $attributes['dynamicTextColor'];
		}
		$textAlignCss = ! empty( $attributes['alignment'] ) ? Alignment::get_css( $attributes['alignment'], 'text-align', $device ) : [];

		return array_merge(
			$dt_text_css,
			[ 'color' => Color::get_css( isset( $attributes['dynamicTextColor'] ) ? $attributes['dynamicTextColor'] : '' ) ],
			Typography::get_css( $attributes['dynamicTypography'] ?? [], '', $device ),
			$textAlignCss
		);
	}

	private function trim_text( $text, $mode_trim, $words_limit, $symbol ) {
		$text = wp_strip_all_tags( $text );
		if ( $words_limit && $mode_trim ) {
			return esc_html( wp_trim_words( $text, $words_limit, $symbol ) );
		} else {
			return esc_html( $text );
		}
	}
	private function render_core( $key, $post_id, $words_limit, $mode_trim, $symbol ) {
		switch ( $key ) {
			case 'core:title':
				$title = get_the_title( $post_id );
				if (
					empty( $title )
					&& $this->is_editor_preview()
				) {
					return __( 'No title available — please add title', 'ablocks' );
				}
				return $this->trim_text( $title, $mode_trim, $words_limit, $symbol );
			case 'core:excerpt':
				return $this->trim_text( get_the_excerpt( $post_id ), $mode_trim, $words_limit, $symbol );
			case 'core:date':
				return esc_html( get_the_date( get_option( 'date_format' ), $post_id ) );
			case 'core:last_modified':
				return esc_html( get_the_modified_date( get_option( 'date_format' ), $post_id ) );
			case 'core:author':
				return esc_html( get_the_author_meta( 'display_name', get_post_field( 'post_author', $post_id ) ) );
			case 'core:permalink':
				$url = get_permalink( $post_id );
				return '<a href="' . esc_url( $url ) . '">' . esc_html( $url ) . '</a>';
			case 'core:content':
				static $guard = false;
				static $content_cache = [];
				if ( $guard ) {
					return '';
				}
				// Render the post's content once per request; multiple dynamic-text
				// "content" blocks (or a loop/archive) would otherwise re-run
				// do_blocks() on the full post each time.
				if ( ! isset( $content_cache[ $post_id ] ) ) {
					$guard = true;
					$content_cache[ $post_id ] = do_blocks( get_post_field( 'post_content', $post_id ) );
					$guard = false;
				}
				$content = $content_cache[ $post_id ];
				if (
				  empty( trim( wp_strip_all_tags( $content ) ) )
				  && $this->is_editor_preview()
				) {
					return __( 'No content available — please add content', 'ablocks' );
				}
				return ( $words_limit && $mode_trim )
					? $this->trim_text( $content, $mode_trim, $words_limit, $symbol )
					: $content;
		}//end switch
		return '';
	}
	private function render_tax( $key, $post_id ) {
		$taxonomy = strtok( str_replace( '__tax:', '', $key ), ':' );
		$terms = get_the_terms( $post_id, $taxonomy );
		if ( empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}
		return implode( ', ', wp_list_pluck( $terms, 'name' ) );
	}
	private function is_editor_preview() {
			return Helper::is_gutenberg_editor();
	}
	public function render_block_content( $attributes, $content = '', $block = null ) {
		$post_id = $attributes['postId']
			?? ( $block->context['postId'] ?? get_the_ID() );

		if ( ! $post_id ) {
			return '<em>No preview post found.</em>';
		}
		$key     = ! empty( $attributes['metaKey'] ) ? $attributes['metaKey'] : 'core:title';
		$limit   = (int) ( $attributes['trimLimit'] ?? 0 );
		$trim    = ! empty( $attributes['trimMode'] );
		$symbol  = $attributes['trimEndSymbol'] ?? '…';

		if ( strpos( $key, 'core:' ) === 0 ) {
			$output = $this->render_core( $key, $post_id, $limit, $trim ? 'words' : null, $symbol );
		} elseif ( strpos( $key, '__tax:' ) === 0 ) {
			$output = $this->render_tax( $key, $post_id );
		} else {
			$output = '';
		}

		if ( ! $output ) {
			return '';
		}
		if ( ! empty( $attributes['linkAdd'] ) && $key !== 'core:permalink' ) {
			$output = '<a href="' . esc_url( get_permalink( $post_id ) ) . '">' . $output . '</a>';
		}
		return '<div class="ablocks-dynamic-text-output">' . $output . '</div>';
	}
}
