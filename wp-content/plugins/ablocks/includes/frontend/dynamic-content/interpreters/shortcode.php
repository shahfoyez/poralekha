<?php
namespace ABlocks\Frontend\DynamicContent\Interpreters;

use ABlocks\Helper;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class Shortcode extends Abstracts\Interpreter {
	protected string $shortcode;
	protected string $pre;
	protected string $post;
	protected string $default;

	public function __construct( string $name, array $setting, bool $is_richtext = false ) {
		parent::__construct( $name, $setting, $is_richtext );
		$this->shortcode = strval( $this->setting[0] ?? '' );
		$this->pre     = strval( $this->setting[3] ?? '' );
		$this->post    = strval( $this->setting[4] ?? '' );
		$this->default = strval( $this->setting[5] ?? '' );
	}
	public function content() : string {
		$this->content = do_shortcode( $this->shortcode );
		return $this->pre . ( $this->content === '' ? $this->default : $this->content ) . $this->post;
	}
	public function req_param_value( array $params ) : string {
		return sanitize_text_field( $params[ $this->param ] ?? '' );
	}
}
