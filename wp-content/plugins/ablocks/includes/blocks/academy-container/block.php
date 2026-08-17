<?php
namespace ABlocks\Blocks\AcademyContainer;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\Alignment;
use ABlocks\Controls\Range;
use ABlocks\Controls\Dimensions;
use ABlocks\Helper;


class Block extends BlockBaseAbstract {
	protected $parent_block_name = 'academy-certificate';
	protected $block_name = 'academy-container';

	public function build_css( $attributes ) {
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );
		$css_generator->add_class_styles(
			'{{WRAPPER}} .academy-inner-container > div ',
			$this->get_wrapper_css( $attributes ),
			$this->get_wrapper_css( $attributes, 'Tablet' ),
			$this->get_wrapper_css( $attributes, 'Mobile' )
		);
		return $css_generator->generate_css();
	}

	public function get_wrapper_css( $attributes, $device = '' ) {
		$css = [];

		$float_css = Alignment::get_css(
			$attributes['floatAlignment'] ?? [],
			'float',
			$device
		);

		if ( isset( $float_css['float'] ) && 'default' === $float_css['float'] ) {
			unset( $float_css['float'] );
		}

		return array_merge(
			$css, $float_css,
			Range::get_css([
				'attributeValue' => $attributes['containerWidth'],
				'attribute_object_key' => 'value',
				'isResponsive' => true,
				'defaultValue' => 200,
				'hasUnit' => true,
				'unitDefaultValue' => 'px',
				'property' => 'width',
				'device' => $device,
			]),
			Dimensions::get_css( $attributes['floatMargin'] ?? [], 'margin', $device ),
		);
	}
}
