<?php
namespace ABlocks\Blocks\TableHeader;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;

class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'table';
	protected $block_name = 'table-header';

	public function build_css( $attributes ) {
		// Generate CSS start
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		return $css_generator->generate_css();
	}


}
