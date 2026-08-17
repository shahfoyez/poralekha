<?php

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
	'authorColor' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => 2,
	),
	'authorHoverColor' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'dateColor' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'dateHoverColor' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'desColor' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'desHoverColor' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'sumColor' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'sumColorH' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'iconColor' => array(
		'type' => 'string',
		'default' => '#f4c150',
	),
	'iconColorH' => array(
		'type' => 'string',
		'default' => '#f4c150',
	),
];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'sumTypography', true ),
	Typography::get_attribute( 'authorTypography', true ),
	Typography::get_attribute( 'dateTypography', true ),
	Typography::get_attribute( 'desTypography', true ),
	Range::get_attribute([
		'attributeName' => 'avatarHeight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 50,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'avatarWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 50,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'iconSize',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 16,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'authorTransition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
	Range::get_attribute([
		'attributeName' => 'dateTransition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
	Range::get_attribute([
		'attributeName' => 'desTransition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
	Range::get_attribute([
		'attributeName' => 'sumTransition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

