<?php

namespace ABlocks\import;

use ABlocks\import\ImageParsers\ablocks\CounterParser;
use ABlocks\import\ImageParsers\ablocks\DividerParser;
use ABlocks\import\ImageParsers\ablocks\FlipBoxParser;
use ABlocks\import\ImageParsers\ablocks\IconParser;
use ABlocks\import\ImageParsers\ablocks\ImageComparisonParser;
use ABlocks\import\ImageParsers\ablocks\ImageHotspotParser;
use ABlocks\import\ImageParsers\ablocks\ImageParser;
use ABlocks\import\ImageParsers\ablocks\ImageScrollParser;
use ABlocks\import\ImageParsers\ablocks\ModalParser;
use ABlocks\import\ImageParsers\ablocks\TabsParser;
use ABlocks\import\ImageParsers\ablocks\VideoParser;
use ABlocks\import\ImageParsers\AbstractImageParser;
use ABlocks\import\ImageParsers\CommonParser;
use ABlocks\import\ImageParsers\wp\CoreImageParser;
use ABlocks\import\ImageParsers\wp\GroupParser;

class ImageParserFactory {

	/**
	 * @var array<string, AbstractImageParser> $parsers Contain all parsers.
	 */
	private array $parsers = array();

	private function __construct() {
		$this->set_parsers();
	}

	private function set_parsers() {
		$this->parsers = apply_filters( 'ablocks/register_image_parsers', array(
			'core/group' => GroupParser::class,
			'core/image' => CoreImageParser::class,
			'ablocks/image' => ImageParser::class,
			'ablocks/image-comparison' => ImageComparisonParser::class,
			'ablocks/video' => VideoParser::class,
			'ablocks/tabs' => TabsParser::class,
			'ablocks/image-scroll' => ImageScrollParser::class,
			'ablocks/modal' => ModalParser::class,
			'ablocks/flip-box' => FlipBoxParser::class,
			'ablocks/image-hotspot' => ImageHotspotParser::class,
		) );
	}

	public static function instance() {
		static $instance = false;

		if ( ! $instance ) {
			$instance = new self();
		}

		return $instance;
	}

	public function parse( array $blocks ): array {
		$parsed_blocks = array();
		foreach ( $blocks as $block ) {
			if ( count( $block['innerBlocks'] ) > 0 ) {
				$block['innerBlocks'] = $this->parse( $block['innerBlocks'] );
			}

			$parsed_blocks[] = $this->process( $block );
		}

		return $parsed_blocks;
	}

	private function process( array $block ): array {
		$block = ( new CommonParser( $block ) )->do_parse();

		if ( ! array_key_exists( $block['blockName'], $this->parsers ) ) {
			return $block;
		}

		/** @var AbstractImageParser $parser */
		$parser = new $this->parsers[ $block['blockName'] ]( $block );

		return $parser->do_parse();
	}
}

