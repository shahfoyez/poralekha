<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Range;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Alignment;


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
	'user_id' => [
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
		'default' => 'Bookmark',
	],
	'label_plural' => [
		'type'    => 'string',
		'default' => 'Bookmarks',
	],
	'wrapper_class' => [
		'type'    => 'string',
		'default' => 'wrapper',
	],
	'numberColor' => [
		'type'    => 'string',
		'default' => '',
	],
];

$attributes = array_merge(
	$attributes,
	Alignment::get_attribute( 'countAlignment', true, [ 'value' => 'left' ] ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

