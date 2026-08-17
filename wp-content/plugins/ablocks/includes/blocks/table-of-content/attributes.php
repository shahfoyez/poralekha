<?php
namespace ABlocks\Blocks\TableOfContent;

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Range;
use ABlocks\Controls\Icon;
use ABlocks\Controls\BoxShadow;
use ABlocks\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'tocTableTitle' => array(
		'type' => 'string',
		'default' => 'Table Of Contents',
	),
	'H1' => array(
		'type' => 'boolean',
		'default' => true,
	),
	'H2' => array(
		'type' => 'boolean',
		'default' => true,
	),
	'H3' => array(
		'type' => 'boolean',
		'default' => true,
	),
	'H4' => array(
		'type' => 'boolean',
		'default' => true,
	),
	'H5' => array(
		'type' => 'boolean',
		'default' => true,
	),
	'H6' => array(
		'type' => 'boolean',
		'default' => true,
	),
	'hideTitle' => array(
		'type' => 'boolean',
		'default' => true,
	),
	'collapSible' => array(
		'type' => 'boolean',
		'default' => true,
	),
	'markerView' => array(
		'type' => 'string',
		'default' => 'disc',
	),
	'titleColor' => array(
		'type' => 'string',
		'default' => '#000000',
	),
	'itemColor' => array(
		'type' => 'string',
		'default' => '#333',
	),
	'iconColor' => array(
		'type' => 'string',
	),
	'activeColor' => array(
		'type' => 'string',
		'default' => '#2763f3',
	),
	'headerBG' => array(
		'type' => 'string',
		'default' => '#ddd',
	),
	'bodyBG' => array(
		'type' => 'string',
		'default' => '',
	),

];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'titleTypography', true ),
	Typography::get_attribute( 'contentTypography', true ),
	Border::get_attribute( 'headerBorder', true ),
	Border::get_attribute( 'iconBorder', true ),
	BoxShadow::get_attribute( 'iconBoxShadow', true ),
	Dimensions::get_attribute( 'header_padding', true ),
	Dimensions::get_attribute( 'icon_padding', true ),
	Dimensions::get_attribute( 'list_padding', true ),
	Icon::get_attribute( 'openIcon', [
		'path' => 'M207.029 381.476L12.686 187.132c-9.373-9.373-9.373-24.569 0-33.941l22.667-22.667c9.357-9.357 24.522-9.375 33.901-.04L224 284.505l154.745-154.021c9.379-9.335 24.544-9.317 33.901.04l22.667 22.667c9.373 9.373 9.373 24.569 0 33.941L240.971 381.476c-9.373 9.372-24.569 9.372-33.942 0z',
		'viewBox' => '0 0 448 512',
		'className' => 'fas fa-chevron-down',
	] ),
	Icon::get_attribute( 'closeIcon', [
		'path' => 'M240.971 130.524l194.343 194.343c9.373 9.373 9.373 24.569 0 33.941l-22.667 22.667c-9.357 9.357-24.522 9.375-33.901.04L224 227.495 69.255 381.516c-9.379 9.335-24.544 9.317-33.901-.04l-22.667-22.667c-9.373-9.373-9.373-24.569 0-33.941L207.03 130.525c9.372-9.373 24.568-9.373 33.941-.001z',
		'viewBox' => '0 0 448 512',
		'className' => 'fas fa-chevron-up',
	] ),
	Range::get_attribute([
		'attributeName' => 'iconSize',
		'isResponsive' => false,
		'defaultValue' => 20,
	]),
	Range::get_attribute( [
		'attributeName' => 'listItemGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 30,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

