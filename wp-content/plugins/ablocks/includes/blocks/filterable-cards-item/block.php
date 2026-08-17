<?php
namespace ABlocks\Blocks\FilterableCardsItem;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;


class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'filterable-cards';
	protected $block_name = 'filterable-cards-item';

	public function build_css( $attributes ) {

		// Generate CSS start
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		return $css_generator->generate_css();
	}
}
