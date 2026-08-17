<?php
namespace ABlocks\Blocks\FormMultiStepChild;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;

class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'form-multi-step';
	protected $block_name = 'form-multi-step-child';

	public function build_css( $attributes ) {
		$css_generator = new CssGenerator( $attributes );
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);
		return $css_generator->generate_css();
	}

	public function get_wrapper_css() {
		// phpcs:ignore Squiz.PHP.NonExecutableCode.ReturnNotRequired
		return;
	}
}
