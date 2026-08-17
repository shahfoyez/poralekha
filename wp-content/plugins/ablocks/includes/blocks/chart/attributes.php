<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Controls\Alignment;
use ABlocks\Controls\Range;

$attributes = [
	'block_id' => array(
		'type' => 'string',
		'default' => '',
	),
	'blockVersion' => array(
		'type' => 'number',
		'default' => '',
	),
	'chartType' => [
		'type' => 'string',
		'default'  => 'bar',
	],
	'chartBG' => [
		'type' => 'string',
		'default'  => '#fff',
	],
	'data' => [
		'type' => 'object',
		'default' => [
			'labels' => [ 'January', 'February', 'March', 'April', 'May', 'June' ],
			'datasets' => [
				[
					'label' => 'Sales',
					'data' => [ 300, 500, 200, 800, 700, 900 ],
					'backgroundColor' => 'rgba(75, 192, 192, 0.6)',
					'borderColor' => 'rgba(75, 192, 192, 1)',
					'borderWidth' => 1,
					'pointStyle' => 'star',
					'pointRadius' => 8,
					'pointHoverRadius' => 12,
				],
				[
					'label' => 'buys',
					'data' => [ 400, 600, 300, 900, 800, 800 ],
					'backgroundColor' => 'yellow',
					'borderColor' => 'red',
					'borderWidth' => 1,
					'pointStyle' => 'triangle',
					'pointRadius' => 8,
					'pointHoverRadius' => 12,
				],
			],
		],
	],
	'options' => [
		'type' => 'object',
		'default' => [
			'responsive' => true,
			'aspectRatio' => 1,
			'maintainAspectRatio' => true,
			'scales' => [
				'x' => [
					'ticks' => [
						'color' => 'blue',
						'font' => [
							'size' => 14,
						],
					],
					'grid' => [
						'display' => true,
						'color' => 'lightgray',
						'lineWidth' => 1,
					],
					'title' => [
						'display' => true,
						'text' => 'X-Axis Label',
						'color' => 'darkblue',
						'align' => 'center',
						'font' => [
							'size' => 16,
							'weight' => 'bold',
						],
					],
				],
				'y' => [
					'ticks' => [
						'color' => 'green',
						'font' => [
							'size' => 14,
						],
					],
					'grid' => [
						'display' => true,
						'color' => 'gray',
						'lineWidth' => 1,
					],
					'title' => [
						'display' => true,
						'text' => 'Y-Axis Label',
						'color' => 'darkgreen',
						'align' => 'center',
						'font' => [
							'size' => 16,
							'weight' => 'bold',
						],
					],
				],
			],
			'plugins' => [
				'legend' => [
					'position' => 'top',
					'labels' => [
						'usePointStyle' => true,
					],
				],
				'title' => [
					'display' => true,
					'text' => 'Monthly Sales',
					'position' => 'top',
					'align' => 'center',
					'padding' => 10,
					'font' => [
						'size' => 20,
						'weight' => 'bold',
					],
				],
				'subtitle' => [
					'display' => false,
					'text' => 'Custom Chart Subtitle',
					'position' => 'top',
					'align' => 'center',
					'padding' => 10,
					'font' => [
						'size' => 12,
						'weight' => 'bold',
					],
				],
			],
		],
	],

];

$attributes = array_merge(
	$attributes,
	Alignment::get_attribute( 'alignment', true, [ 'value' => 'left' ] ),
	Range::get_attribute([
		'attributeName' => 'chartHeight',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 620,
		'hasUnit' => true,
		'unitDefaultValue' => 'px',
	]),
	Range::get_attribute([
		'attributeName' => 'chartWidth',
		'attributeObjectKey' => 'value',
		'isResponsive' => false,
		'defaultValue' => 95,
		'hasUnit' => true,
		'unitDefaultValue' => '%',
	]),
);

return array_merge( $attributes, \ABlocks\Classes\BlockGlobal::get_attributes() );

