<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Alignment;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'blockVersion' => [
		'type' => 'number',
		'default' => 2,
	],
	'homeBreadcrumbs' => [
		'type' => 'string',
		'default' => '',
	],
	'enableFaqSchema' => [
		'type' => 'boolean',
		'default' => false,
	],	
	'SeparatorChange' => [
		'type' => 'string',
		'default' => '',
	],
	'breadcrumbTitlecolor' => [
		'type' => 'string',
		'default' => '',
	],
	'breadcrumbHoverLinkcolor' => [
		'type' => 'string',
		'default' => '',
	],
	'beforeSeparator' => [
		'type' => 'boolean',
		'default' => false,
	],
	'breadcrumbItemBackground' => [
		'type' => 'string',
		'default' => '',
	],
	'breadcrumbLinkcolor' => [
		'type' => 'string',
		'default' => '',
	],
	'breadcrumbseparatorcolor' => [
		'type' => 'string',
		'default' => '',
	],
	'beforeBreadcrumbImage' => [
		'type' => 'string',
		'default' => '',
	],
	'beforeBreadcrumbText' => [
		'type' => 'string',
		'default' => '',
	],
	'beforeBreadcrumbTextImage' => [
		'type' => 'string',
		'default' => '',
	],
	'beforeBreadcrumbBackgroundcolor' => [
		'type' => 'string',
		'default' => '',
	],
	'beforeTextImage' => [
		'type' => 'boolean',
		'default' => false,
	],
	'positionBreadcrumb' => [
		'type' => 'object',
		'default' => '',
	],
	'breadcrumbSpaceBetween' => [
		'type' => 'number',
		'default' => 10,
	],
	'breadcrumbseparsize' => [
		'type' => 'number',
		'default' => 20,
	],

];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'breadcrumbTitleTypography', true ),
	Dimensions::get_attribute( 'beforeBreadcrumbBorderRadius', true ),
	Dimensions::get_attribute( 'BreadcrumbBorderRadius', true ),
	Dimensions::get_attribute( 'beforeBreadcrumbPaddingcolor', true ),
	Dimensions::get_attribute( 'breadcrumbItemPadding', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

