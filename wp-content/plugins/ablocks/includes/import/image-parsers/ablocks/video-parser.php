<?php

namespace ABlocks\import\ImageParsers\ablocks;

use ABlocks\import\ImageParsers\AbstractImageParser;
use DOMDocument;

class VideoParser extends AbstractImageParser {

	protected function parse() {
		$dom = new DOMDocument();
		$dom->loadHTML( $this->block['innerHTML'], LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		$video_element = $dom->getElementsByTagName( 'video' )->item( 0 );
		if ( ! $video_element ) {
			return;
		}

		$poster_url = $video_element->getAttribute( 'poster' );
		if ( ! $poster_url || ! $this->check_external_url( $poster_url ) ) {
			return;
		}

		$attachment_id = $this->upload_file( $poster_url );
		if ( is_wp_error( $attachment_id ) ) {
			return;
		}
		$attachment_url = wp_get_attachment_url( $attachment_id );

		$this->block['attrs']['poster'] = $attachment_url;
		$this->block['innerHTML'] = str_replace( $poster_url, $attachment_url, $this->block['innerHTML'] );
		$this->block['innerContent'] = array_map( fn( $content) => str_replace( $poster_url, $attachment_url, $content ), $this->block['innerContent'] );
	}
}
