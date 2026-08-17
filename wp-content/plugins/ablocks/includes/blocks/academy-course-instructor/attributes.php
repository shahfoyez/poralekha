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
	'titleColorH' => array(
		'type' => 'string',
		'default' => '#999',
	),
	'titleColor' => array(
		'type' => 'string',
		'default' => '#999',
	),
	'textColorH' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'textColor' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'star_color' => array(
		'type' => 'string',
		'default' => '#f4c150',
	),
	'star_colorH' => array(
		'type' => 'string',
		'default' => '#f4c150',
	),
	'insColor' => array(
		'type' => 'string',
		'default' => '#111',
	),
	'insColorH' => array(
		'type' => 'string',
		'default' => '#111',
	),

];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'titleTypography', true ),
	Typography::get_attribute( 'textTypography', true ),
	Typography::get_attribute( 'insTypography', true ),
	Range::get_attribute([
		'attributeName' => 'titleTransition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
	Range::get_attribute([
		'attributeName' => 'textTransition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
	Range::get_attribute([
		'attributeName' => 'avatarH',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 62,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'avatarW',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 62,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'star_size',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 14,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

