<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Alignment;

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'tagName' => [
		'type' => 'string',
		'default' => 'td'
	],
	'cellColor' => [
		'type' => 'string',
		'default' => ''
	],
	'cellColorH' => [
		'type' => 'string',
		'default' => ''
	],
	'rowSpan' => [
		'type' => 'string',
		'default' => '1'
	],
	'colSpan' => [
		'type' => 'string',
		'default' => '1'
	],
];

return array_merge( $attributes, Alignment::get_attribute( 'textAlignment', true, [ 'value' => 'left' ] ) );
