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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'course_id' => array(
		'type' => 'number',
		'default' => 0,
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => 2,
	),
	'headingColor' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'headingColorH' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'listColor' => array(
		'type' => 'string',
		'default' => '#999',
	),
	'listColorH' => array(
		'type' => 'string',
		'default' => '#999',
	),
	'tabColor' => array(
		'type' => 'string',
		'default' => '#999',
	),
	'tabColorH' => array(
		'type' => 'string',
		'default' => '#999',
	),
];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'headingTypography', true ),
	Typography::get_attribute( 'listTypography', true ),
	Typography::get_attribute( 'tabTypography', true ),
	Border::get_attribute( 'listBorder', true ),
	Dimensions::get_attribute( 'listMargin', true ),
	Range::get_attribute([
		'attributeName' => 'headingTransition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
	Range::get_attribute([
		'attributeName' => 'listTransition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
	Range::get_attribute([
		'attributeName' => 'tabTransition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

