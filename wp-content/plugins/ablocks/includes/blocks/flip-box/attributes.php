<?php

use ABlocks\Controls\Border;
use ABlocks\Controls\Background;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Components\ButtonGroup;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
];

$attributes = array_merge(
	$attributes,
	ButtonGroup::get_attribute( 'flipDirection', false, [
		'value' => 'left',
	] ),
	ButtonGroup::get_attribute( 'showSide', false, [
		'value' => 'front',
	] ),
	Range::get_attribute([
		'attributeName' => 'transitionSpeed',
		'attributeObjectKey' => 'value',
		'defaultValue' => 0.6,
		'unitDefaultValue' => 's',
	]),
	Border::get_attribute( 'cardBorder', true ),
	Background::get_attribute( 'frontCardBackground', true ),
	Background::get_attribute( 'backCardBackground', true ),
	Dimensions::get_attribute( 'frontPadding', true ),
	Dimensions::get_attribute( 'backPadding', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

