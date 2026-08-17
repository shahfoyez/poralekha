<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Alignment;
use ABlocks\Controls\Typography;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Icon;

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'selectedPostType' => [
		'type' => 'string',
		'default' => 'post',
	],
	'selectedTerms' => [
		'type' => 'object',
		'default' => [],
	],
	'activeTaxonomy' => [
		'type' => 'array',
		'default' => [],
	],
	'selectedTaxonomies' => [
		'type' => 'array',
		'default' => [],
	],
	'numberOfPosts' => [
		'type' => 'number',
		'default' => 6,
	],
	'postOrder' => [
		'type' => 'string',
		'default' => 'desc',
	],
	'postOrderBy' => [
		'type' => 'string',
		'default' => 'date',
	],
	'taxonomyLayout' => [
		'type' => 'string',
		'default' => 'flex',
		'copyStyle' => true,
	],
	'pageLinks' => [
		'type' => 'string',
		'default' => 'singlePagelink',
		'copyStyle' => true,
	],
	'itemsPerRow' => [
		'type' => 'number',
		'default' => 3,
		'copyStyle' => true,
	],
	'showTermCount' => [
		'type' => 'boolean',
		'default' => true,
	],
	'enableReloadButton' => [
		'type' => 'boolean',
		'default' => false,
	],
	'allowIcon' => [
		'type' => 'boolean',
		'default' => false,
	],
	'textPostColor' => [
		'type' => 'string',
		'default' => false,
	],
	'postCountBorder' => [
		'type' => 'object',
		'default' => '',
	],
	'postCountBgColor' => [
		'type' => 'string',
		'default' => '',
	],
	'postCountColor' => [
		'type' => 'string',
		'default' => '',
	],
	'taxonomyTitlePadding' => [
		'type' => 'object',
		'default' => '',
	],
	'taxonomyTitletBgColor' => [
		'type' => 'string',
		'default' => '',
	],
	'taxonomyTitlecolor' => [
		'type' => 'string',
		'default' => '',
	],
	'taxonomyTitleDirection' => [
		'type' => 'object',
		'default' => '',
	],
	'taxonomyTitlePosition' => [
		'type' => 'object',
		'default' => 'space-between',
	],
	'taxonomyTitleBorder' => [
		'type' => 'object',
		'default' => '2',
	],
	'postTitleColor' => [
		'type' => 'string',
		'default' => '',
	],
	'postTitleHoverColor' => [
		'type' => 'string',
		'default' => '',
	],
	'postTitleTypography' => [
		'type' => 'object',
		'default' => '',
	],
	'buttonPadding' => [
		'type' => 'object',
		'default' => '',
	],
	'buttonBorder' => [
		'type' => 'object',
		'default' => '',
	],
	'butttonColor' => [
		'type' => 'string',
		'default' => '',
	],
	'buttonBgColor' => [
		'type' => 'string',
		'default' => '',
	],
	'buttonHoverBgColor' => [
		'type' => 'string',
		'default' => '',
	],
	'buttonColor' => [
		'type' => 'string',
		'default' => '',
	],
	'buttonHoverColor' => [
		'type' => 'string',
		'default' => '',
	],
	'buttonTypography' => [
		'type' => 'object',
		'default' => '',
	],
	'buttonPosition' => [
		'type' => 'object',
		'default' => 'block',
	],
	'cardBgColor' => [
		'type' => 'string',
		'default' => 'block',
	],
	'cardPadding' => [
		'type' => 'object',
		'default' => 'block',
	],
	'cardBorder' => [
		'type' => 'object',
		'default' => 'block',
	],

	'icon' => [
		'type' => 'object',
		'default' => 'block',
	],
	'buttonText' => [
		'type' => 'string',
		'default' => 'View More',
	],
	'showTab' => [
		'type' => 'boolean',
		'default' => false
	],
	'showTabScrolling' => [
		'type' => 'boolean',
		'default' => false
	],
	'hoverPostBackgroundColor' => [
		'type' => 'string',
		'default' => '',
	],
	'activePostBackgroundColor' => [
		'type' => 'string',
		'default' => '',
	],
	'postTitleActiveColor' => [
		'type' => 'string',
		'default' => '',
	],
	'activePostBorder' => [
		'type' => 'object',
		'default' => '',
	],
	'postBorder' => [
		'type' => 'object',
		'default' => '',
	],
	'postBackgroundColor' => [
		'type' => 'string',
		'default' => '',
	],
	'showPost' => [
		'type' => 'boolean',
		'default' => true,
	],
	'cardBgHoverColor' => [
		'type' => 'string',
		'default' => '',
	],
	'showPostLink' => [
		'type' => 'boolean',
		'default' => false,
	],
	'showInherit' => [
		'type' => 'boolean',
		'default' => false,
	],
	'showTermCountPrefix' => [
		'type' => 'boolean',
		'default' => false,
	],
	'countPrefixText' => [
		'type' => 'string',
		'default' => '',
	],
	'iconColor' => [
		'type' => 'string',
		'default' => '',
	],
	'showIconDffent' => [
		'type' => 'boolean',
		'default' => false,
	],
	'iconBgColor' => [
		'type' => 'string',
		'default' => '',
	],
	'iconPadding' => [
		'type' => 'object',
		'default' => '',
	],
	'postPadding' => [
		'type' => 'object',
		'default' => '',
	],
	'iconBorderRadius' => [
		'type' => 'object',
		'default' => '',
	],
	'postCountWidth' => [
		'type' => 'number',
		'default' => '',
	],

];

$attributes = array_merge(
	$attributes,
	Icon::get_attribute(),
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	Typography::get_attribute( 'taxonomyTitleTypography', true ),
	Typography::get_attribute( 'postTitleTypography', true ),
	Typography::get_attribute( 'postCountTypography', true ),
	BoxShadow::get_attribute( 'cardBoxShadow', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
