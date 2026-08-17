<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Range;

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
		'default' => 2,
	),
	'titleContentColor' => array(
		'type' => 'string',
		'default' => '',
	),
	'titleContentColorH' => array(
		'type' => 'string',
		'default' => '',
	),
	'detailsColor' => array(
		'type' => 'string',
		'default' => '',
	),
	'detailsColorH' => array(
		'type' => 'string',
		'default' => '',
	),
	'titleContentColor' => array(
		'type' => 'string',
		'default' => '',
	),
	'titleContentColorH' => array(
		'type' => 'string',
		'default' => '',
	),
	'emailColor' => array(
		'type' => 'string',
		'default' => '',
	),
	'emailColorH' => array(
		'type' => 'string',
		'default' => '',
	),
];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'titleContentTypography', true ),
	Typography::get_attribute( 'detailsTypography', true ),
	Typography::get_attribute( 'emailTypography', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

