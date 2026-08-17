<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Icon;
use ABlocks\Controls\Range;


if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'dashboard_page' => array(
		'type' => 'string',
		'default' => '',
	),
	'sidebarBackground' => array(
		'type' => 'string',
		'default' => '',
	),
	'sidebarUserBackground' => array(
		'type' => 'string',
		'default' => '',
	),
	'contentBackground' => array(
		'type' => 'string',
		'default' => '',
	),
	'userTextColor' => array(
		'type' => 'string',
		'default' => '',
	),
	'breadcrumbColor' => array(
		'type' => 'string',
		'default' => '',
	),
	'menuListActiveBackground' => array(
		'type' => 'string',
		'default' => '',
	),
	'menuListActiveTextColor' => array(
		'type' => 'string',
		'default' => '',
	),
	'menuListHoverBackground' => array(
		'type' => 'string',
		'default' => '',
	),
	'menuListHoverTextColor' => array(
		'type' => 'string',
		'default' => '',
	),
	'menuListBackground' => array(
		'type' => 'string',
		'default' => '',
	),
	'menuListTextColor' => array(
		'type' => 'string',
		'default' => '',
	),
];


return array_merge(
	$attributes,
	Range::get_attribute([
		'attributeName' => 'bothGap',
		'isResponsive' => false,
		'defaultValue' => 30,
	]),
	Border::get_attribute( 'sidebarBorder', true ),
	Border::get_attribute( 'userSidebarBorder', true ),
	Border::get_attribute( 'menuListBorder', true ),
	Border::get_attribute( 'contentBorder', true ),
	Typography::get_attribute( 'userTypography', true ),
	Typography::get_attribute( 'breadcrumbtTypography', true ),
	Typography::get_attribute( 'menuListTypography', true ),
	Dimensions::get_attribute( 'menuListPadding', true ),
	Dimensions::get_attribute( 'contentPadding', true ),
	\ABlocks\Classes\BlockGlobal::get_attributes()
);

