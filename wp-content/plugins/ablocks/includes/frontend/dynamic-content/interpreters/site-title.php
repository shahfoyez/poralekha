<?php
namespace ABlocks\Frontend\DynamicContent\Interpreters;

use ABlocks\Helper;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class SiteTitle extends SiteInfo {
	public function info() : string {
		return get_bloginfo( 'name' );
	}
}
