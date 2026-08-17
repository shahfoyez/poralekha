<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ABlocks\Controls\Alignment;
use ABlocks\Classes\CssGenerator;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;
use ABlocks\Controls\Icon;
use ABlocks\Helper;
$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'allowMultiple' => [
		'type' => 'boolean',
		'default' => false,
	],
	'enableFaqSchema' => [
		'type' => 'boolean',
		'default' => false,
	],
	'initialOpen' => [
		'type' => 'number',
		'default' => 1,
	],
	'iconColor' => [
		'type' => 'string',
		'default' => '#000000',
	],
	'iconColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'iconActiveColor' => [
		'type' => 'string',
		'default' => '',
	],
	'headerTextColor' => [
		'type' => 'string',
		'default' => '#000000'
	],
	'headerTextColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'headerTextActiveColor' => [
		'type' => 'string',
		'default' => '',
	],
	'bodyBackgroundH' => [
		'type' => 'string',
		'default' => '',
	],
	'bodyBackground' => [
		'type' => 'string',
		'default' => '#F9F9F9'
	],
	'headingTag' => [
		'type' => 'string',
		'default' => 'p'
	],
	'iconPosition' => [
		'type' => 'string',
		'default' => 'right'
	],
	'showIcon' => [
		'type' => 'boolean',
		'default' => true
	],
	'headerBackgroundActiveColor' => [
		'type' => 'string',
		'default' => '',
	],
	'headerBackgroundColorH' => [
		'type' => 'string',
		'default' => '',
	],
	'headerBackgroundColor' => [
		'type' => 'string',
		'default' => '#F9F9F9'
	],
];
$attributes = array_merge(
	$attributes,
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'center' ] ),
	Typography::get_attribute( 'headerTypography', true ),
	TextShadow::get_attribute( 'headerTextShadow' ),
	TextStroke::get_attribute( 'headerTextStroke', true ),
	Border::get_attribute( 'itemBorder', true ),
	Dimensions::get_attribute( 'headerPadding', true ),
	Dimensions::get_attribute( 'bodyPadding', true ),
	Border::get_attribute( 'headerBorder', true ),
	Icon::get_attribute( 'leftCloseIcon', [
		'path' => 'M256 504c137 0 248-111 248-248S393 8 256 8 8 119 8 256s111 248 248 248zm0-448c110.5 0 200 89.5 200 200s-89.5 200-200 200S56 366.5 56 256 145.5 56 256 56zm20 328h-40c-6.6 0-12-5.4-12-12V256h-67c-10.7 0-16-12.9-8.5-20.5l99-99c4.7-4.7 12.3-4.7 17 0l99 99c7.6 7.6 2.2 20.5-8.5 20.5h-67v116c0 6.6-5.4 12-12 12z',
		'viewBox' => '0 0 512 512',
		'className' => 'far fa-arrow-alt-circle-up',
		'hasNoSelectorOrSource' => true,
	] ),
	Icon::get_attribute( 'leftActiveIcon', [
		'path' => 'M256 8C119 8 8 119 8 256s111 248 248 248 248-111 248-248S393 8 256 8zm0 448c-110.5 0-200-89.5-200-200S145.5 56 256 56s200 89.5 200 200-89.5 200-200 200zm-32-316v116h-67c-10.7 0-16 12.9-8.5 20.5l99 99c4.7 4.7 12.3 4.7 17 0l99-99c7.6-7.6 2.2-20.5-8.5-20.5h-67V140c0-6.6-5.4-12-12-12h-40c-6.6 0-12 5.4-12 12z',
		'viewBox' => '0 0 512 512',
		'className' => 'far fa-arrow-alt-circle-down',
		'hasNoSelectorOrSource' => true,
	] ),
	Icon::get_attribute( 'rightCloseIcon', [
		'path' => 'M416 208H272V64c0-17.67-14.33-32-32-32h-32c-17.67 0-32 14.33-32 32v144H32c-17.67 0-32 14.33-32 32v32c0 17.67 14.33 32 32 32h144v144c0 17.67 14.33 32 32 32h32c17.67 0 32-14.33 32-32V304h144c17.67 0 32-14.33 32-32v-32c0-17.67-14.33-32-32-32z',
		'viewBox' => '0 0 448 512',
		'className' => 'fas fa-plus',
		'hasNoSelectorOrSource' => true,
	] ),
	Icon::get_attribute( 'rightActiveIcon', [
		'path' => 'M416 208H32c-17.67 0-32 14.33-32 32v32c0 17.67 14.33 32 32 32h384c17.67 0 32-14.33 32-32v-32c0-17.67-14.33-32-32-32z',
		'viewBox' => '0 0 448 512',
		'className' => 'fas fa-minus',
		'hasNoSelectorOrSource' => true,
	] ),
	Range::get_attribute( [
		'attributeName' => 'itemSpace',
		'isResponsive' => false,
		'defaultValue' => 10,
		'attributeObjectKey' => 'value',
		'hasUnit' => false,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute([
		'attributeName' => 'iconSize',
		'isResponsive' => false,
		'defaultValue' => 30,
		'attributeObjectKey' => 'value',
	]),
	Range::get_attribute([
		'attributeName' => 'iconSpace',
		'isResponsive' => false,
		'defaultValue' => 10,
		'attributeObjectKey' => 'value',
	]),
);
return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

