<?php

namespace ABlocks\import\ImageParsers\wp;

use ABlocks\import\ImageParsers\AbstractImageParser;
use DOMDocument;

class CoreImageParser extends AbstractImageParser {

	public function parse() {
		$dom = new DOMDocument();
		// Suppress warnings for malformed HTML.
		libxml_use_internal_errors( true );
		$dom->loadHTML( $this->block['innerHTML'] );
		libxml_clear_errors();
		$imageHTML = $dom->getElementsByTagName( 'img' )->item( 0 );
		if ( ! $imageHTML ) {
			return;
		}

		$imgSRC = $imageHTML->attributes->getNamedItem( 'src' );
		if ( ! $imgSRC ) {
			return;
		}

		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Calling a property from native class `DOMDocument`.
		$imgURL = $imgSRC->nodeValue;
		if ( ! $this->check_external_url( $imgURL ) ) {
			return;
		}

		$attachment_id = $this->upload_file( $imgURL );
		if ( is_wp_error( $attachment_id ) ) {
			return;
		}

		$attachment_url = wp_get_attachment_url( $attachment_id );

		$this->block['attrs']['id']  = $attachment_id;
		$this->block['innerHTML']    = str_replace( $imgURL, $attachment_url, $this->block['innerHTML'] );
		$this->block['innerContent'] = array( str_replace( $imgURL, $attachment_url, $this->block['innerHTML'] ) );
	}
}
