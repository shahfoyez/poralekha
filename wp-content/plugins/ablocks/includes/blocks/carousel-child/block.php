<?php
namespace ABlocks\Blocks\CarouselChild;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Alignment;


class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'carousel';
	protected $block_name = 'carousel-child';
	public function build_css( $attributes ) {

		$css_generator = new CssGenerator( $attributes );

		return $css_generator->generate_css();
	}




}
