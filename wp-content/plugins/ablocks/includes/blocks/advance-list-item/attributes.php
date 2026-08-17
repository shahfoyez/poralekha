<?php

use ABlocks\Controls\Alignment;
use ABlocks\Controls\GroupButton;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Border;
use ABlocks\Controls\Range;
use ABlocks\Controls\Icon;
use ABlocks\Controls\Link;
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'changedAttributes' => array(
		'type' => 'array',
		'default' => array(),
	),
	'device'    => [
		'type'          => 'string',
		'default'       => '',
	],
	'markerType'    => [
		'type'          => 'string',
		'default'       => 'Icon',
	],
	'shapeType'     => [
		'type'          => 'string',
		'default'       => 'dotted',
	],
	'shapeColor'    => [
		'type'          => 'string',
		'default'       => 'black',
	],
	'markerColor'   => [
		'type'          => 'string',
		'default'       => '',
	],
	'allowDivider'  => [
		'type'          => 'boolean',
		'default'       => false,
	],
	'isLastChild'   => [
		'type'          => 'boolean',
		'default'       => false,
	],
	'emoji'     => [
		'type'          => 'string',
		'default'       => '👍',
	],
	'advanceListItemText'         => [
		'type'          => 'string',
		'source'        => 'html',
		'selector'      => '.ablocks-advance-list-item-text',
		'default'       => 'Task Checklist',
	],
	'advanceListItemTextTag'    => [
		'type'          => 'string',
		'default'       => 'p',
	],
	'dropCaps'          => [
		'type'          => 'boolean',
		'default'       => false,
	],
	'dropCapsTextColor' => [
		'type'          => 'string',
		'default'       => '',
	],
	'advanceListItemTextSize'     => [
		'type'          => 'string',
		'default'       => 'md',
	],
	'textColor'         => [
		'type'          => 'string',
		'default'       => '',
	],
	// divider related attributes
	'dividerPatternUrl' => [
		'type' => 'string',
		'default' => '',
	],
	'dividerType' => [
		'type' => 'string',
		'default' => '',
	],
	'color' => [
		'type' => 'string',
		'default' => '',
	],
];

$attributes = array_merge(
	$attributes,
	Icon::get_attribute( 'icon', [
		'className' => 'fas fa-check',
		'path' => 'M173.898 439.404l-166.4-166.4c-9.997-9.997-9.997-26.206 0-36.204l36.203-36.204c9.997-9.998 26.207-9.998 36.204 0L192 312.69 432.095 72.596c9.997-9.997 26.207-9.997 36.204 0l36.203 36.204c9.997 9.997 9.997 26.206 0 36.204l-294.4 294.401c-9.998 9.997-26.207 9.997-36.204-.001z',
		'viewBox' => '0 0 512 512',
		'size' => '20',
		'color' => '#000',
	] ),
	Link::get_attribute( 'link' ),
	Alignment::get_attribute( 'iconAlignment', true, [ 'value' => 'row' ] ),
	Alignment::get_attribute( 'alignment', true, [ 'value' => '' ] ),
	GroupButton::get_attribute( 'listsDirection', true, [
		'value' => 'column',
	] ),
	Typography::get_attribute( 'listTypography', true ),
	TextShadow::get_attribute( 'listTextShadow' ),
	TextStroke::get_attribute( 'listTextStroke', true ),
	Range::get_attribute( [
		'attributeName' => 'markerSize',
		'attributeObjectKey' => 'value',
		'defaultValue' => 10,
		'hasUnit' => false,
	] ),
	Range::get_attribute( [
		'attributeName' => 'innerGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 6,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute( [
		'attributeName' => 'width',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => null,
		'unitDefaultValue' => '%',
		'hasUnit' => false,
	] ),
	Range::get_attribute( [
		'attributeName' => 'weight',
		'isResponsive' => false,
		'defaultValue' => null,
	] ),
	Range::get_attribute( [
		'attributeName' => 'size',
		'isResponsive' => false,
		'defaultValue' => null,
	] ),
	Range::get_attribute( [
		'attributeName' => 'shapeSize',
		'isResponsive' => true,
		'defaultValue' => 14,
	] ),
);
return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

