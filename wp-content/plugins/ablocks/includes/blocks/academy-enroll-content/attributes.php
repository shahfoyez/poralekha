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
	'blockVersion' => array(
		'type' => 'number',
		'default' => 2,
	),
	'contentBg' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'contentBgH' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'iconColor' => array(
		'type' => 'string',
		'default' => '#595959',
	),
	'iconColorH' => array(
		'type' => 'string',
		'default' => '#595959',
	),
	'listColor' => array(
		'type' => 'string',
		'default' => '#595959',
	),
	'listColorH' => array(
		'type' => 'string',
		'default' => '#595959',
	),
	'shareColor' => array(
		'type' => 'string',
		'default' => '#444',
	),
	'shareColorH' => array(
		'type' => 'string',
		'default' => '#444',
	),
	'shareBg' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'shareBgH' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'wishlistColor' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'wishlistColorH' => array(
		'type' => 'string',
		'default' => '#fff',
	),
	'wishlistBg' => array(
		'type' => 'string',
		'default' => '#7b68ee',
	),
	'wishlistBgH' => array(
		'type' => 'string',
		'default' => '#7b68ee',
	),

];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'listTypography', true ),
	Typography::get_attribute( 'shareTypography', true ),
	Border::get_attribute( 'shareBorder', true ),
	Dimensions::get_attribute( 'sharePadding', true ),
	Range::get_attribute([
		'attributeName' => 'contentTransition',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 0,
		'hasUnit' => false,
		'unitDefaultValue' => 's',
	]),
	Range::get_attribute([
		'attributeName' => 'iconSize',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 14,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'buttonIconSize',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 14,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

