<?php

namespace ABlocks\import\ImageParsers\ablocks;

use ABlocks\import\ImageParsers\AbstractImageParser;
use DOMDocument;

class ImageParser extends AbstractImageParser {

	protected function parse() {
		$dom = new DOMDocument();
		$dom->loadHTML( $this->block['innerHTML'], LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		$source_elements = $dom->getElementsByTagName( 'source' );

		$parsed_img = $this->parse_img_tag( $dom, 0 );
		if ( ! $parsed_img ) {
			return;
		}
		list($attachment_id, $attachment_url, $image_url) = $parsed_img;
		$remote_image_url = [ $image_url => $attachment_url ];

		$i = 0;
		while ( $i < $source_elements->count() ) {
			$source_element = $source_elements->item( $i );
			$i++;

			$source_image_url  = $source_element->getAttribute( 'srcset' );
			if ( ! $source_image_url || ! $this->check_external_url( $source_image_url ) ) {
				continue;
			}

			$attachment_id = $this->upload_file( $source_image_url );
			if ( is_wp_error( $attachment_id ) ) {
				continue;
			}

			$source_attachment_url = wp_get_attachment_url( $attachment_id );
			$remote_image_url[ $source_image_url ] = $source_attachment_url;
		}

		$this->block['attrs']['imgId'] = $attachment_id;
		$this->block['innerHTML'] = str_replace( array_keys( $remote_image_url ), array_values( $remote_image_url ), $this->block['innerHTML'] );
		$this->block['innerContent'] = array_map( fn( $content) => str_replace( array_keys( $remote_image_url ), array_values( $remote_image_url ), $content ), $this->block['innerContent'] );
	}
}
