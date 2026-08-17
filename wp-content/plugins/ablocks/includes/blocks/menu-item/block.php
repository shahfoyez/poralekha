<?php
namespace ABlocks\Blocks\MenuItem;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'menu';
	protected $block_name = 'menu-item';

	public function build_css( $attributes ) {
		// Generate CSS
		$css_generator = new CssGenerator( $attributes );

		return $css_generator->generate_css();
	}
}
