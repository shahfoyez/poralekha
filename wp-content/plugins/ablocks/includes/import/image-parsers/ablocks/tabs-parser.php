<?php

namespace ABlocks\import\ImageParsers\ablocks;

use ABlocks\import\ImageParsers\AbstractImageParser;
use DOMDocument;

class TabsParser extends AbstractImageParser {

	protected function parse() {
		if ( ! isset( $this->block['attrs']['iconImageUrl'] ) ) {
			return;
		}

		$dom = new DOMDocument();
		$dom->loadHTML( $this->block['innerHTML'], LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		$image_url = $this->block['attrs']['iconImageUrl'];
		$images = $dom->getElementsByTagName( 'img' );
		$remote_images = [];

		$i = 1;
		while ( $i < $images->count() ) {
			$image = $images->item( $i );
			$i++;

			$remote_image_url = $image->getAttribute( 'src' );
			if ( ! $this->check_external_url( $remote_image_url ) ) {
				continue;
			}
			$remote_images[] = $remote_image_url;
		}

		$this->block['innerHTML'] = str_replace( $remote_images, $image_url, $this->block['innerHTML'] );
		$this->block['innerContent'] = array_map( fn( $content) => str_replace( $remote_images, $image_url, $content ), $this->block['innerContent'] );
	}
}
