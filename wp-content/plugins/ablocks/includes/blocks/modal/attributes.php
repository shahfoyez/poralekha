<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Range;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Background;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Border;
use ABlocks\Components\ButtonGroup;


$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'popupOnTop' => array(
		'type' => 'boolean',
		'default' => false,
	),
	'disableCloseButton' => array(
		'type' => 'boolean',
		'default' => false,
	),
	'useHoverTrigger' => array(
		'type' => 'boolean',
		'default' => false,
	),
	'enableAutoTriggerTimer' => array(
		'type' => 'boolean',
		'default' => false,
	),
	'showAutoOnce' => array(
		'type' => 'boolean',
		'default' => false,
	),
	'showOnMouseOutOfWindow' => array(
		'type' => 'boolean',
		'default' => false,
	),
	'backdropColor' => array(
		'type' => 'string',
		'default' => '#000000b3',
	),
	'closeBtnBackgroundColor' => array(
		'type' => 'string',
		'default' => '',
	),
	'closeBtnColor' => array(
		'type' => 'string',
		'default' => '',
	),
	'noTrigger' => array(
		'type' => 'boolean',
		'default' => false,
	),
];

$attributes = array_merge(
	$attributes,
	ButtonGroup::get_attribute( 'openPanel', false, [
		'value' => 'close',
	] ),
	ButtonGroup::get_attribute( 'popupPosition', false, [
		'value' => 'popup',
	] ),
	ButtonGroup::get_attribute( 'panelBlockPosition', false, [
		'value' => 'bottom',
	] ),
	ButtonGroup::get_attribute( 'panelContentPosition', false, [
		'value' => 'auto',
	] ),
	ButtonGroup::get_attribute( 'closePosition', false, [
		'value' => 'right',
	] ),
	Range::get_attribute([
		'attributeName' => 'popupTopOffset',
		'isResponsive' => false,
		'defaultValue' => 0,
	]),
	Range::get_attribute([
		'attributeName' => 'panelWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 50,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	]),
	Range::get_attribute([
		'attributeName' => 'panelHeight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 50,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	]),
	Range::get_attribute([
		'attributeName' => 'autoTriggerTime',
		'isResponsive' => false,
		'defaultValue' => 0,
	]),
	Range::get_attribute([
		'attributeName' => 'closeBtnTop',
		'isResponsive' => false,
		'defaultValue' => 5,
		'hasUnit' => false,
	]),
	Range::get_attribute([
		'attributeName' => 'closeBtnSide',
		'isResponsive' => false,
		'defaultValue' => 5,
		'hasUnit' => false,
	]),
	Dimensions::get_attribute( 'panelPadding', true ),
	Background::get_attribute( 'panelBackground' ),
	Border::get_attribute( 'panelBorder', true ),
	BoxShadow::get_attribute( 'panelShadow', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
