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
	'metaKey' => [
		'type' => 'string',
		'default' => '',
	],
	'linkAdd' => [
		'type' => 'boolean',
		'default' => false
	],
	'trimMode' => [
		'type' => 'boolean',
		'default' => false
	],
	'dynamicTextColor' => [
		'type' => 'string',
		'default' => ''
	],

	'trimLimit' => [
		'type' => 'number',
		'default' => 0
	],
	'trimEndSymbol' => [
		'type' => 'string',
		'default' => ''
	],

];

$attributes = array_merge(
	Typography::get_attribute( 'dynamicTypography', true ),
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	$attributes,
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
