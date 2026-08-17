<?php

use ABlocks\Controls\Typography;
use ABlocks\Controls\Range;
use ABlocks\Controls\Dimensions;
use ABlocks\Controls\Border;
use ABlocks\Controls\BoxShadow;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\TextShadow;
use ABlocks\Controls\TextStroke;
use ABlocks\Controls\Link;

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
	'not_found_text' => [
		'type'    => 'string',
		'default' => 'Custom message when no bookmarks exist',
	],
	'reactionUlStyle' => [
		'type'    => 'string',
		'default' => '',
	],
	'reacTitleColor' => [
		'type'    => 'string',
		'default' => '#000000',
	],	
	'reacListColor' => [
		'type'    => 'string',
		'default' => '#000000',
	],		
	'nobookmarksColor' => [
		'type'    => 'string',
		'default' => '#000000',
	],	
];

$attributes = array_merge(
	Alignment::get_attribute( 'reactionAlignment', true, [ 'value' => 'left' ] ),
	Typography::get_attribute( 'reacTitleTypography', true ),
	Typography::get_attribute( 'reacListTypography', true ),
	Typography::get_attribute( 'nobookmarksTypography', true ),
	TextShadow::get_attribute( 'textShadow' ),
	TextStroke::get_attribute( 'textStroke', true ),
	$attributes,
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

