<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ABlocks\Controls\Icon;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Border;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Components\ButtonGroup;
$attributes = [
	'block_id'                   => [
		'type'                   => 'string',
		'default'                => '',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'appearance' => [
		'type'    => 'string',
		'default' => 'icon',

	],
	'buttonText' => [
		'type' => 'string',
		'default' => 'Top',
	],
	'buttonTextColor' => [
		'type' => 'string',
		'default' => '',
	],
	'buttonTextColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'buttonTextColorBg' => [
		'type' => 'string',
		'default' => '',
	],
	'buttonTextColorBgH' => [
		'type' => 'string',
		'default' => '',
	],
	'progressColor' => [
		'type' => 'string',
		'default' => 'red',
	],
	'progressColorBg' => [
		'type' => 'string',
		'default' => '#EEEEEE',
	],
];

$attributes = array_merge(
	$attributes,
	Range::get_attribute([
		'attributeName' => 'strokeSize',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => '3',
		'hasUnit' => false,
	]),
	Range::get_attribute([
		'attributeName' => 'positionBottom',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 20,
		'hasUnit' => false,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'positionLeft',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 20,
		'hasUnit' => false,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'positionRight',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 20,
		'hasUnit' => false,
		'unitDefaultValue' => 'px',
	]),
	Icon::get_attribute( 'icon', [
		'path' => 'M34.9 289.5l-22.2-22.2c-9.4-9.4-9.4-24.6 0-33.9L207 39c9.4-9.4 24.6-9.4 33.9 0l194.3 194.3c9.4 9.4 9.4 24.6 0 33.9L413 289.4c-9.5 9.5-25 9.3-34.3-.4L264 168.6V456c0 13.3-10.7 24-24 24h-32c-13.3 0-24-10.7-24-24V168.6L69.2 289.1c-9.3 9.8-24.8 10-34.3.4z',
		'viewBox' => '0 0 448 512',
		'className' => 'far fa-arrow-alt-circle-up',
		'size' => 40,
	]),
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	Alignment::get_attribute( 'position', true ),
	TextStroke::get_attribute( 'textStroke', true ),
	Typography::get_attribute( 'typography', true ),
	Border::get_attribute( 'border', true ),
	TextShadow::get_attribute( 'textShadow' ),
	Dimensions::get_attribute( 'padding', false ),
	ButtonGroup::get_attribute( 'position', false ),
	ButtonGroup::get_attribute( 'visibleControl', false, [
		'value' => 'visible',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
