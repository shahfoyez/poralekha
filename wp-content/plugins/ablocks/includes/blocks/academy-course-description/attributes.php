<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\BoxShadow;


$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => 2,
	),
	'heading_color' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'heading_colorH' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'description_color' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'description_colorH' => array(
		'type' => 'string',
		'default' => '#111',
	),

];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'heading_typography', true ),
	Typography::get_attribute( 'description_typography', true ),
	Range::get_attribute([
		'attributeName' => 'heading_transition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
	Range::get_attribute([
		'attributeName' => 'description_transition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

