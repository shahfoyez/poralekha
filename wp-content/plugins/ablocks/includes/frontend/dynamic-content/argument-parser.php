<?php
namespace ABlocks\Frontend\DynamicContent;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
class ArgumentParser {

	protected array $args;
	protected array $interpreters;
	protected string $arg_str;
	protected bool $is_richtext;
	protected $context;
	public function __construct( string $arg_str, bool $is_richtext, array $interpreters, array $context = [] ) {
		$this->arg_str = $arg_str;
		$this->is_richtext = $is_richtext;
		$this->interpreters = $interpreters;
		$this->context = $context;
	}
	public function interpret() : ?Interpreters\Abstracts\Interpreter {
		$setting = explode( '|', $this->arg_str );
		$action_name = $setting[0];
		array_shift( $setting );

		if ( ! empty( $class = $this->interpreters[ $action_name ] ?? '' ) &&
			class_exists( $class ) &&
			( $ins = new $class( $action_name, $setting, $this->is_richtext, $this->context ) ) instanceof Interpreters\Abstracts\Interpreter
		) {
			return $ins;
		}
		return null;
	}
}
