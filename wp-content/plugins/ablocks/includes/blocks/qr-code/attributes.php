<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Alignment;


$attributes = [
	'block_id' => [
		'type' => 'string',
	],
	'qrValue' => [
		'type'    => 'string',
		'default' => '',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => 2,
	),
	'bgColor' => [
		'type'    => 'string',
		'default' => '#FFF',
	],
	'qrLevel' => [
		'type'    => 'string',
		'default' => 'M',
	],
	'fgColor' => [
		'type'    => 'string',
		'default' => '#000',
	],
	'imageSrc' => [
		'type'    => 'string',
		'default' => '',
	],
	'isImage' => [
		'type'    => 'boolean',
		'default' => false,
	],
	'excavateValue' => [
		'type'    => 'boolean',
		'default' => false,
	],
	'logoOpacity' => [
		'type'    => 'number',
		'default' => 0.6,
	],
	'logoWidth' => [
		'type'    => 'number',
		'default' => 50,
	],
	'logoHeight' => [
		'type'    => 'number',
		'default' => 50,
	],
	'qrData' => [
		'type'    => 'object',
		'default' => (object) [],
	],
	'imageValue' => [
		'type' => 'object',
		'default' => (object) [],
	],
	'qrSize' => [
		'type'    => 'number',
		'default' => 250,
	],
	'positionX' => [
		'type'    => 'number',
		'default' => 0,
	],
	'positionY' => [
		'type'    => 'number',
		'default' => 0,
	],
];

$attributes = array_merge(
	$attributes,
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );


