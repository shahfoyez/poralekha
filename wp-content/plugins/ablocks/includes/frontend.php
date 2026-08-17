<?php
namespace ABlocks;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Frontend {
	public static function init() {
		$self = new self();
		$self->dispatch_hooks();
	}

	public function dispatch_hooks() {
		Frontend\Template::init();
		Frontend\Link::init();
		Frontend\LinkPassthrough::init();
		if ( ! is_admin() || defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			add_filter( 'render_block', [ Frontend\DynamicContent\Interpreter::class, 'init' ], 10, 3 );
		}
	}

}
