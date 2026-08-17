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
		'default' => 'Checkbox',
	],

	'errorMsg' => [
		'type' => 'string',
		'default' => 'This field is required',
	],
	'helperText' => [
		'type' => 'string',
		'default' => '',
	],
	'inputType' => [
		'type' => 'string',
		'default' => ''
	],
	'name' => [
		'type' => 'string',
		'default' => ''
	],
	'isRequired' => [
		'type' => 'boolean',
		'default' => false,
	],
	'isChecked' => [
		'type' => 'boolean',
		'default' => false,
	],
	'placeholder' => [
		'type' => 'string',
		'default' => 'Enter your message'
	],
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

