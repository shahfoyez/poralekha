<?php
namespace ABlocks\Frontend\DynamicContent\Interpreters;

use ABlocks\Helper;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class CurrentDateTime extends Abstracts\Interpreter {
	public function content() : string {
		$format = ( $this->setting[1] === 'custom' ? $this->setting[2] : $this->setting[1] ) . ' ' . ( $this->setting[0] === 'default' ? 'h:i A' : $this->setting[0] );
		$pre = '';
		$post = '';
		$default = $this->setting[3] ?? '';
		if ( count( $this->setting ) > 5 ) {
			$pre  = $this->setting[3] ?? '';
			$post = $this->setting[4] ?? '';
			$default = $this->setting[5] ?? '';
		}
		if ( empty( trim( $format ) ) ) {
			$format = 'd M, Y h:i a';
		}
		return $pre . wp_date( empty( $format ) ? $default : $format ) . $post;
	}
}
