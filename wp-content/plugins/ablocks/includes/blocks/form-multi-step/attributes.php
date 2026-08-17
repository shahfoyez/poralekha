<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => ''
	),
	'steps' => [
		'type' => 'array',
		'default' => [
			[
				'id' => 1,
				'value' => 'Step One'
			]
		]
	],
	'submitButtonText' => array(
		'type' => 'string',
		'default' => 'Submit'
	),
	'submitButtonSize' => array(
		'type' => 'string',
		'default' => 'default'
	)
];

$attributes = array_merge(
	$attributes,
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

