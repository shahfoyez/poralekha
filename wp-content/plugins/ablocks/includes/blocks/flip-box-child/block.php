<?php

namespace ABlocks\Blocks\FlipBoxChild;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Typography;

class Block extends BlockBaseAbstract {

	protected $parent_block_name = 'flip-box';
	protected $block_name = 'flip-box-child';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes );
		return $css_generator->generate_css();
	}


}
