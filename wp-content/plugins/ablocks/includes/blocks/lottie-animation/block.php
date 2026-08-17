<?php
namespace ABlocks\Blocks\LottieAnimation;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGeneratorV2;

class Block extends BlockBaseAbstract {
	protected $block_name = 'lottie-animation';

	public function build_css( $attributes ) {

		// Generate CSS start
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		return $css_generator->generate_css();
	}
}
