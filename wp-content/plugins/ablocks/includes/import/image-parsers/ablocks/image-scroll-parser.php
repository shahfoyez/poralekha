<?php

namespace ABlocks\import\ImageParsers\ablocks;

use ABlocks\import\ImageParsers\AbstractImageParser;

class ImageScrollParser extends AbstractImageParser {

	protected function parse() {
		if ( ! isset( $this->block['attrs']['imageDataAttribute'], $this->block['attrs']['imageDataAttribute']['guid'], $this->block['attrs']['imageDataAttribute']['guid']['rendered'] ) ) {
			return;
		}

		$image_url = $this->block['attrs']['imageDataAttribute']['guid']['rendered'];
		if ( ! $this->check_external_url( $image_url ) ) {
			return;
		}

		$attachment_id = $this->upload_file( $image_url );
		if ( is_wp_error( $attachment_id ) ) {
			return;
		}
		$attachment_url = wp_get_attachment_url( $attachment_id );

		$this->block['attrs']['imgId'] = $attachment_id;
		$this->block['attrs']['imageDataAttribute']['guid']['rendered'] = $attachment_url;
		$this->block['attrs']['imageDataAttribute']['guid']['raw'] = $attachment_url;

		$this->block['innerHTML'] = str_replace( $image_url, $attachment_url, $this->block['innerHTML'] );
		$this->block['innerContent'] = array_map( fn( $content) => str_replace( $image_url, $attachment_url, $content ), $this->block['innerContent'] );
	}
}
