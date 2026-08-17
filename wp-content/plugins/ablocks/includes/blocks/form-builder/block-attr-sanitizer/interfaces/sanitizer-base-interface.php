<?php
namespace ABlocks\Blocks\FormBuilder\BlockAttrSanitizer\Interfaces;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface SanitizerBaseInterface {

	public static function init() : void;
	public function sanitize( int $post_id, WP_Post $post, bool $update): void;
}
