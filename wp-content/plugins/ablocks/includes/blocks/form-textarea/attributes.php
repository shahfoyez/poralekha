<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ABlocks\Controls\Range;

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => ''
	),

	'customName' => [
		'type' => 'string',
		'default' => '',
	],
	'name' => [
		'type' => 'string',
		'default' => ''
	],
	'helperText' => [
		'type' => 'string',
		'default' => '',
	],
	'nameType' => [
		'type' => 'string',
		'default' => '',
	],
	'errorMsg' => [
		'type' => 'string',
		'default' => 'This field is required',
	],

	'label' => [
		'type' => 'string',
		'default' => 'Message'
	],
	'isRequired' => [
		'type' => 'boolean',
		'default' => true
	],
	'placeholder' => [
		'type' => 'string',
		'default' => 'Enter your message'
	]
];

$attributes = array_merge(
	$attributes,
	Range::get_attribute( [
		'attributeName' => 'textAreaRow',
		'isResponsive' => false,
		'defaultValue' => 5,
	] ),
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

