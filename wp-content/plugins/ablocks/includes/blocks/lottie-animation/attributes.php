<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


use ABlocks\Components\ButtonGroup;

$attributes = [
	'block_id' => [
		'type' => 'string',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => 2,
	),
	'asset_url' => [
		'type' => 'string',
		'default' => '',
	],
	'custom_url' => [
		'type' => 'string',
		'default' => '',
	],
	'uploaded_json' => [
		'type' => 'object',
		'default' => '',
	],
	'trigger' => [
		'type' => 'string',
		'default' => 'viewport',
	],
	'hoverArea' => [
		'type' => 'string',
		'default' => 'animation',
	],
	'onHoverOut' => [
		'type' => 'string',
		'default' => 'nothing',
	],
	'reverse' => [
		'type' => 'boolean',
		'default' => false,
	],
	'loop' => [
		'type' => 'boolean',
		'default' => false,
	],
	'animationSpeed' => [
		'type' => 'number',
		'default' => 1,
	],
	'timesLoop' => [
		'type' => 'number',
		'default' => '',
	],
];

$attributes = array_merge(
	$attributes,
	ButtonGroup::get_attribute( 'animationSource', false, [
		'value' => 'upload',
	] ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
