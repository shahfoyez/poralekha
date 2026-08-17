<?php
namespace ABlocks\Blocks\FormBuilder\BlockAttrSanitizer\Interfaces;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface SanitizerInterface {

	public function __construct( WP_Post $post);
	public function modify_attr( array $attr) : array;
	public function verify( array $attr) : bool;
	public function find_block( array $blocks) : array;
	public function sanitize() : void;
}
