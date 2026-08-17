<?php
namespace ABlocks\Blocks\ToggleChild;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;

class Block extends BlockBaseAbstract {

	protected $parent_block_name = 'toggle';
	protected $block_name = 'toggle-child';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes );

		return $css_generator->generate_css();
	}
}
