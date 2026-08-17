<?php
namespace ABlocks\Frontend\DynamicContent\Interpreters\Abstracts;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
abstract class Interpreter {
	protected string $name;
	protected array $setting;
	protected string $content = '';
	protected bool $is_richtext;

	public function __construct( string $name, array $setting, bool $is_richtext = false ) {
		$this->name    = $name;
		$this->setting = $setting;
		$this->is_richtext = $is_richtext;
	}
	abstract public function content() : string;
}
