<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Background;
use ABlocks\Controls\Border;
use ABlocks\Controls\Dimensions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'selectTextcolor' => [
		'type'    => 'string',
		'default' => '',
	],
	'selectTextcolorH' => [
		'type'    => 'string',
		'default' => '',
	],
	'selectBackground' => [
		'type'    => 'string',
		'default' => '',
	],
	'selectBackgroundH' => [
		'type'    => 'string',
		'default' => '',
	],
	'selectWidth' => [
		'type'    => 'number',
		'default' => 236,
	],

];

$attributes = array_merge(
	$attributes,
	Typography::get_attribute( 'selectTypography', true ),
	Dimensions::get_attribute( 'selectPadding', true ),
	Border::get_attribute( 'selectBorder', true ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
