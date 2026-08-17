<?php
use ABlocks\Controls\Range;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => ''
	),

	'label' => [
		'type' => 'string',
		'default' => 'What\'s your favorite programming lang?'
	],
	'name' => [
		'type' => 'string',
		'default' => '',
	],
	'helperText' => [
		'type' => 'string',
		'default' => ''
	],
	'isRequired' => [
		'type' => 'boolean',
		'default' => true,
	],
	'errorMsg' => [
		'type' => 'string',
		'default' => 'This field is required',
	],
	'options' => [
		'type' => 'array',
		'default' => [
			[
				'id' => 1,
				'value' => 'javascript'
			]
		]
	]
];

$attributes = array_merge(
	$attributes,
	Range::get_attribute( [
		'attributeName' => 'inputWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	] ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

