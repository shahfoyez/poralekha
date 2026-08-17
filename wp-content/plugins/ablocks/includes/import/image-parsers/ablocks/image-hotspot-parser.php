<?php

namespace ABlocks\import\ImageParsers\ablocks;

use ABlocks\import\ImageParsers\AbstractImageParser;

class ImageHotspotParser extends AbstractImageParser {

	protected function parse() {
		if ( ! isset( $this->block['attrs']['backgroundImage'] ) ) {
			return;
		}

		$image_url = $this->block['attrs']['backgroundImage'];
		if ( ! $this->check_external_url( $image_url ) ) {
			return;
		}

		$attachment_id = $this->upload_file( $image_url );
		if ( is_wp_error( $attachment_id ) ) {
			return;
		}

		$attachment_url = wp_get_attachment_url( $attachment_id );
		$this->block['attrs']['backgroundImage'] = $attachment_url;
		$remote_images = [ $image_url => $attachment_url ];

		if ( isset( $this->block['attrs']['imageSizes']['thumbnail'] ) ) {
			$thumbnail_url = wp_get_attachment_image_url( $attachment_id );
			$remote_images[ $this->block['attrs']['imageSizes']['thumbnail']['url'] ] = $thumbnail_url;
			$this->block['attrs']['imageSizes']['thumbnail']['url'] = $thumbnail_url;
		}

		if ( isset( $this->block['attrs']['imageSizes']['medium'] ) ) {
			$medium_url = wp_get_attachment_image_url( $attachment_id, 'medium' );
			$remote_images[ $this->block['attrs']['imageSizes']['medium']['url'] ] = $medium_url;
			$this->block['attrs']['imageSizes']['medium']['url'] = $medium_url;
		}

		if ( isset( $this->block['attrs']['imageSizes']['full'] ) ) {
			$full_url = wp_get_attachment_image_url( $attachment_id, 'full' );
			$remote_images[ $this->block['attrs']['imageSizes']['full']['url'] ] = $full_url;
			$this->block['attrs']['imageSizes']['full']['url'] = $full_url;
		}

		$this->block['innerHTML'] = str_replace( array_keys( $remote_images ), array_values( $remote_images ), $this->block['innerHTML'] );
		$this->block['innerContent'] = array_map( fn( $content) => str_replace( array_keys( $remote_images ), array_values( $remote_images ), $content ), $this->block['innerContent'] );
	}
}
