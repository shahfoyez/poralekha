<?php
namespace ABlocks\Frontend\DynamicContent\Interpreters;

use ABlocks\Helper;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
abstract class SiteInfo extends Abstracts\Interpreter {
	abstract public function info() : string;

	public function content() : string {
		$this->setting = array_values( array_filter( $this->setting ) );
		$info = $this->info();
		return count( $this->setting ) > 2 ? ( $this->setting[0] ?? '' ) . ( empty( $info ) ? ( $this->setting[2] ?? '' ) : $info ) . ( $this->setting[1] ?? '' ) : $info;
	}
}
