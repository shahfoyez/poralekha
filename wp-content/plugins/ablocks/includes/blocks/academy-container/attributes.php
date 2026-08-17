<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Alignment;
use ABlocks\Controls\Range;

$attributes = [
	'block_id' => [
		'type'    => 'string',
		'default' => '',
	],
	'blockVersion' => [
		'type'    => 'number',
		'default' => 2,
	],
	'floatMargin'  => [
		'type'    => 'object',
		'default' => '',
	],
	'variationSelected'  => [
		'type'    => 'boolean',
		'default' => false,
	],
];

$attributes = array_merge(
	$attributes,
	Alignment::get_attribute( 'floatAlignment', true ),
	Range::get_attribute([
		'attributeName' => 'containerWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
		'defaultValue' => 290,
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
