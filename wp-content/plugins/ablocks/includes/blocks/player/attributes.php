<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use ABlocks\Controls\Range;

$attributes = [
	'block_id' => [
		'type' => 'string',
		'default' => '',
	],
	'blockVersion' => array(
		'type' => 'number',
		'default' => 2,
	),
	'videoSource' => [
		'type' => 'string',
		'default' => 'youtube',
	],
	'youtubeUrl' => [
		'type' => 'string',
		'default' => 'https://www.youtube.com/watch?v=Yu0HH5S-8RY&t=1s',
	],
	'selfHostedUrl' => [
		'type' => 'string',
		'default' => '',
	],
	'videoUrl' => [
		'type' => 'string',
		'default' => ''
	],
	'audioUrl' => [
		'type' => 'string',
		'default' => ''
	],
	'vimeoUrl' => [
		'type' => 'string',
		'default' => 'https://vimeo.com/889428749'
	],
	'videoStartTime' => [
		'type' => 'string',
		'default' => ''
	],
	'videoEndTime' => [
		'type' => 'string',
		'default' => ''
	],
	'youtubeUiColor' => [
		'type' => 'string',
		'default' => ''
	],
	'youtubeProgressColor' => [
		'type' => 'string',
		'default' => ''
	],
	'playerbgColor' => [
		'type' => 'string',
		'default' => '#0077CC'
	],
	'selfVideoUiColor' => [
		'type' => 'string',
		'default' => ''
	],
	'selfVideoProgressUiColor' => [
		'type' => 'string',
		'default' => ''
	],
	'selfVideobgColor' => [
		'type' => 'string',
		'default' => ''
	],
	'vimeoUiColor' => [
		'type' => 'string',
		'default' => ''
	],
	'vimeoProgressUiColor' => [
		'type' => 'string',
		'default' => ''
	],
	'vimeobgColor' => [
		'type' => 'string',
		'default' => ''
	],
	'posterImage' => [
		'type' => 'string',
		'default' => ''
	],
	'autoplay' => [
		'type' => 'boolean',
		'default' => false,
	],
	'mute' => [
		'type' => 'boolean',
		'default' => false,
	],
	'loop' => [
		'type' => 'boolean',
		'default' => false,
	],

];
$attributes = array_merge(
	$attributes,
	Range::get_attribute([
		'attributeName' => 'youtubeIconSize',
		'isResponsive' => false,
		'defaultValue' => 20,
	]),
	Range::get_attribute([
		'attributeName' => 'youtubePlayPauseIconSize',
		'isResponsive' => false,
		'defaultValue' => 20,
	]),
	Range::get_attribute([
		'attributeName' => 'selfHostedIconSize',
		'isResponsive' => false,
		'defaultValue' => 20,
	]),
	Range::get_attribute([
		'attributeName' => 'selfHostedPlayIconSize',
		'isResponsive' => false,
		'defaultValue' => 20,
	]),
	Range::get_attribute([
		'attributeName' => 'vimeoIconSize',
		'isResponsive' => false,
		'defaultValue' => 20,
	]),
	Range::get_attribute([
		'attributeName' => 'vimeoPlayIconSize',
		'isResponsive' => false,
		'defaultValue' => 20,
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );
