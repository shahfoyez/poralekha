<?php
namespace ABlocks\Blocks\FormBuilder\BlockAttrSanitizer\Abstracts;

use ABlocks\Blocks\FormBuilder\BlockAttrSanitizer\interfaces\SanitizerInterface;
use ABlocks\Blocks\FormBuilder\BlockAttrSanitizer\Exceptions\BlockNotFoundException;
use WP_Post;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class SanitizerAbstract implements SanitizerInterface {

	protected array $block_found = [];
	protected string $block;
	protected WP_Post $post;

	public function __construct( WP_Post $post ) {
		$this->post = $post;
	}

	abstract public function modify_attr( array $attr) : array;

	abstract public function verify( array $attr) : bool;

	public function find_block( array $blocks ) : array {
		$modified_blocks = [];
		foreach ( $blocks as $block ) {

			if (
				$this->block === $block['blockName'] &&
				$this->verify( $block['attrs'] )
			) {
				$block['attrs'] = $this->modify_attr( $block['attrs'] );
			}

			if ( isset( $block['innerBlocks'] ) && is_array( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = $this->find_block( $block['innerBlocks'] );
			}

			$modified_blocks[] = $block;

			if (
				$this->block === $block['blockName'] &&
				$this->verify( $block['attrs'] )
			) {
				$this->block_found[] = $block;
			}
		}//end foreach
		return $modified_blocks;
	}

	public function sanitize() : void {
		if ( ! has_block( $this->block, $this->post ) ) {
			throw new BlockNotFoundException( 'Block not found' );
		}

		$post_content = $this->post->post_content;
		$blocks       = parse_blocks( $post_content );

		$this->find_block( $blocks );

		$post_content = $this->post->post_content;
		foreach ( $this->block_found as $block ) {
			if ( empty( $block['attrs']['block_id'] ?? '' ) ) {
				continue;
			}
			$post_content = preg_replace(
				'|<!--\s*wp:ablocks\/form-builder\b[^>]*"block_id":"' . preg_quote( $block['attrs']['block_id'] ?? '' ) . '"[^>]*-->.*?<!--\s*\/wp:ablocks\/form-builder\s*-->|ims',
				serialize_block( $block ),
				$post_content
			);
		}

		if ( $post_content !== $this->post->post_content ) {
			wp_update_post([
				'ID'           => $this->post->ID,
				'post_content' => $post_content
			]);
		}
	}

	public function log( string $e ) : void {
		// @todo
	}
}
