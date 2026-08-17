<?php
namespace ABlocks\Blocks\Video;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\BlockBaseAbstract;
use ABlocks\Classes\CssGenerator;
use ABlocks\Classes\CssGeneratorV2;
use ABlocks\Controls\CssFilter;

class Block extends BlockBaseAbstract {
	protected $block_name = 'video';

	public function build_css_v1( $attributes ) {

		// Generate CSS start
		$css_generator = new CssGenerator( $attributes, $this->block_name );

		// Video Container CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}} .ablocks-block-container',
			$this->get_video_container_css( $attributes ),
			$this->get_video_container_css( $attributes, 'Tablet' ),
			$this->get_video_container_css( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
		// Generate CSS end
	}
	public function build_css_v2( $attributes ) {

		// Generate CSS start
		$css_generator = new CssGeneratorV2( $attributes, $this->block_name );

		// Video Container CSS
		$css_generator->add_class_styles(
			'{{WRAPPER}}',
			$this->get_video_container_css( $attributes ),
			$this->get_video_container_css( $attributes, 'Tablet' ),
			$this->get_video_container_css( $attributes, 'Mobile' ),
		);

		return $css_generator->generate_css();
		// Generate CSS end
	}

	public function build_css( $attributes ) {
		if ( isset( $attributes['blockVersion'] ) && (int) $attributes['blockVersion'] === 2 ) {
			return $this->build_css_v2( $attributes );
		}
		return $this->build_css_v1( $attributes );
	}
	public function get_video_container_css( $attributes, $device = '' ) {
		$css = [];
		if ( ! empty( $attributes['aspectRatio'] ) ) {
			$css['aspect-ratio'] = $attributes['aspectRatio'];
		}

		return array_merge(
			$css,
			isset( $attributes['cssFilter'] ) ? CssFilter::get_css( $attributes['cssFilter'], '', $device ) : [],
		);
	}
}
