<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\BoxShadow;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => 2,
	),
	'product_id' => array(
		'type' => 'number',
		'default' => 0,
	),
	'isCustom' => array(
		'type' => 'boolean',
		'default' => false,
	),
	'imageOpacityH' => array(
		'type' => 'number',
		'default' => 1,
	),
	'imageOpacity' => array(
		'type' => 'number',
		'default' => 1,
	),
];

$attributes = array_merge(
	$attributes,
	Range::get_attribute([
		'attributeName' => 'imageWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	]),
	Range::get_attribute([
		'attributeName' => 'imageHeight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 100,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	]),
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	BoxShadow::get_attribute( 'boxShadow' ),
	Border::get_attribute( 'border', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

