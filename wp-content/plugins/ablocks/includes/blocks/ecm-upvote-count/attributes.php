<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Range;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Alignment;
use ABlocks\Components\ButtonGroup;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => [
		'type' => 'number',
		'default' => 2,
	],
	'transition' => [
		'type' => 'number',
		'default' => 10,
	],
	'showTitle' => [
		'type' => 'boolean',
		'default' => true,
	],
		'fillColor' => [
		'type' => 'string',
		'default' => '',
	],
		'iconColor' => [
		'type' => 'string',
		'default' => '',
	],
		'titleColor' => [
		'type' => 'string',
		'default' => '',
	],
		'bgColor' => [
		'type' => 'string',
		'default' => '',
	],
		'activeFillColor' => [
		'type' => 'string',
		'default' => '',
	],
		'activeIconColor' => [
		'type' => 'string',
		'default' => '',
	],
		'activeTitleColor' => [
		'type' => 'string',
		'default' => '',
	],
		'activeBGColor' => [
		'type' => 'string',
		'default' => '',
	],	

	'post_id' => [
		'type'    => 'number',
	],
	'show_label' => [
		'type'    => 'boolean',
		'default' => true,
	],
	'show_icon' => [
		'type'    => 'boolean',
		'default' => true,
	],
	'label_single' => [
		'type'    => 'string',
		'default' => 'upvote',
	],
	'label_plural' => [
		'type'    => 'string',
		'default' => 'upvotes',
	],
	'wrapper_class' => [
		'type'    => 'string',
		'default' => 'upvote-count-wrapper',
	],
	'upvoteNumberColor' => [
		'type'    => 'string',
		'default' => '',
	],
];

$attributes = array_merge(
		ButtonGroup::get_attribute( 'justificationAlign', true, [
		'value' => 'center',
	] ),
		Border::get_attribute( 'border', true ),
		Border::get_attribute( 'activeBorder', true ),
		Typography::get_attribute( 'titleTypography', true ),
		Typography::get_attribute( 'activeTitleTypography', true ),
		Dimensions::get_attribute( 'padding', true ),
        Range::get_attribute( [
		'attributeName' => 'iconSize',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 20,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute( [
		'attributeName' => 'itemGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 20,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	$attributes,
	Alignment::get_attribute( 'countAlignment', true, [ 'value' => 'left' ] ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

