<?php

namespace ABlocks\import\ImageParsers\ablocks;

use ABlocks\import\ImageParsers\AbstractImageParser;
use DOMDocument;

class ImageComparisonParser extends AbstractImageParser {

	protected function parse() {
		$dom = new DOMDocument();
		$dom->loadHTML( $this->block['innerHTML'], LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		$before_parsed_img = $this->parse_img_tag( $dom, 0 );
		$after_parsed_img = $this->parse_img_tag( $dom, 1 );
		if ( ! $before_parsed_img || ! $after_parsed_img ) {
			return;
		}
		list( , $before_attachment_url, $before_image_url ) = $before_parsed_img;
		list( , $after_attachment_url, $after_image_url ) = $after_parsed_img;

		$this->block['attrs']['beforeImage'] = $before_attachment_url;
		$this->block['attrs']['afterImage'] = $after_attachment_url;
		$this->block['innerHTML'] = str_replace( [ $before_image_url, $after_image_url ], [ $before_attachment_url, $after_attachment_url ], $this->block['innerHTML'] );
		$this->block['innerContent'] = array_map( fn( $content) => str_replace( [ $before_image_url, $after_image_url ], [ $before_attachment_url, $after_attachment_url ], $content ), $this->block['innerContent'] );
	}
}
