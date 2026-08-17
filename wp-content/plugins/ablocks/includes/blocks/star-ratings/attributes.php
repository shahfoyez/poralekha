<?php
namespace ABlocks\Blocks\StarRatings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Alignment;
use ABlocks\Controls\Range;
use ABlocks\Controls\Typography;
use ABlocks\Helper;
use ABlocks\Controls\Icon;

$attributes = [
	'block_id'         => [
		'type'         => 'string',
		'default'      => '',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'scale'            => [
		'type'         => 'number',
		'default'      => 5,
	],
	'ratingColor'      => [
		'type'         => 'string',
		'default'      => '#e99516',
	],
	'ratingUnmarkedColor'  => [
		'type'         => 'string',
		'default'      => '#696969'
	],
	'showCount'        => [
		'type'         => 'boolean',
		'default'      => true,
	],
	'showRatingNumber' => [
		'type'         => 'boolean',
		'default'      => true,
	],
	'ratingNumberColor' => [
		'type' => 'string',
		'default' => '#000000',
	],
	'ratingNumberPosition' => [
		'type' => 'string',
		'default' => 'right',
	],
	'rating'            => [
		'type'         => 'number',
		'default'      => 4,
	],

];

$attributes = array_merge(
	$attributes,
	Range::get_attribute( [
		'attributeName' => 'size',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 24,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute( [
		'attributeName' => 'spacing',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 0,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Range::get_attribute( [
		'attributeName' => 'ratingNumberGap',
		'attributeObjectKey' => 'value',
		'isResponsive' => true,
		'defaultValue' => 0,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	] ),
	Typography::get_attribute( 'ratingNumberTypography', true ),
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	Icon::get_attribute('icon', [
		'path' => 'M528.1 171.5L382 150.2 316.7 17.8c-11.7-23.6-45.6-23.9-57.4 0L194 150.2 47.9 171.5c-26.2 3.8-36.7 36.1-17.7 54.6l105.7 103-25 145.5c-4.5 26.3 23.2 46 46.4 33.7L288 439.6l130.7 68.7c23.2 12.2 50.9-7.4 46.4-33.7l-25-145.5 105.7-103c19-18.5 8.5-50.8-17.7-54.6zM388.6 312.3l23.7 138.4L288 385.4l-124.3 65.3 23.7-138.4-100.6-98 139-20.2 62.2-126 62.2 126 139 20.2-100.6 98z',
		'viewBox' => '0 0 576 512',
		'className' => 'far fa-star',
	] ),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

