<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ABlocks\Controls\Range;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'gridStyle' => [
		'type' => 'string',
		'default' => 'grid'
	],
	'gridColumns' => [
		'type' => 'number',
		'default' => '2'
	],
	'bgColor' => [
		'type' => 'string',
		'default' => '',
	]


];
$attributes = array_merge(
	$attributes,
	Range::get_attribute( [
		'attributeName' => 'templateGridColumns',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 2,
		'defaultValueTablet' => 2,
		'defaultValueMobile' => 1,
		'hasUnit' => false,
		'unitDefaultValue' => '',
	] ),
	Range::get_attribute([
		'attributeName' => 'itemGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 20,
		'hasUnit' => false,
		'unitDefaultValue' => 'px',
	]),
	Dimensions::get_attribute( 'padding', false ),
	Border::get_attribute( 'border', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

