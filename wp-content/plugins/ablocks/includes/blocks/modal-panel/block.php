<?php

namespace ABlocks\Blocks\ModalPanel;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;

class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'modal';
	protected $block_name = 'modal-panel';

	public function __construct() {
		parent::__construct();
		add_filter( 'ablocks/get_render_block_content', [ $this, 'render_static_block_content' ], 10, 3 );
	}

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes );
		return $css_generator->generate_css();
	}

	public function render_static_block_content( $content, $attributes, $block_instance ) {
		if ( $block_instance->name === $this->namespace . '/' . $this->block_name ) {
			return preg_replace(
				'/<div\s+class="ablocks-block-modal---panel-wrap"(?![^>]*style=)/i',
				'<div class="ablocks-block-modal---panel-wrap" style="opacity:0;visibility:hidden;"',
				$content
			);
		}
		return $content;
	}


}
