<?php
namespace ABlocks\Blocks\LoopBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;

class Block extends BlockBaseAbstract {
	protected $block_name = 'loop-builder';

	public function build_css( $attributes ) {
		// Generate CSS start
		$css_generator = new CssGenerator( $attributes );
		return $css_generator->generate_css();
	}

	public function render_block_content( $attributes, $content, $block ) {
		return $content;
	}
}
