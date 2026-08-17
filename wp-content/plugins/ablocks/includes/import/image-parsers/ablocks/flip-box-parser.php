<?php

namespace ABlocks\import\ImageParsers\ablocks;

use ABlocks\import\ImageParsers\AbstractImageParser;

class FlipBoxParser extends AbstractImageParser {

	public function parse() {
		$this->parse_background();
		$this->parse_background_hover();
	}

	private function parse_background() {
		if ( ! isset( $this->block['attrs']['frontCardBackground'] ) || 'image' !== $this->block['attrs']['frontCardBackground']['backgroundType'] ) {
			return;
		}

		$imgUrl = $this->block['attrs']['frontCardBackground']['imgUrl'];
		if ( empty( $imgUrl ) ) {
			return;
		}

		if ( $this->check_external_url( $imgUrl ) ) {
			$attachment_id = $this->upload_file( $imgUrl );
			if ( is_wp_error( $attachment_id ) ) {
				return;
			}

			$attachment_url                                  = wp_get_attachment_url( $attachment_id );
			$this->block['attrs']['frontCardBackground']['imgId'] = $attachment_id;
			$this->block['attrs']['frontCardBackground']['imgUrl'] = $attachment_url;
		}
	}

	private function parse_background_hover() {
		if ( ! isset( $this->block['attrs']['frontCardBackground'] ) || 'image' !== $this->block['attrs']['frontCardBackground']['backgroundTypeH'] ) {
			return;
		}

		$imgUrl = $this->block['attrs']['frontCardBackground']['imgUrlH'];
		if ( empty( $imgUrl ) ) {
			return;
		}

		if ( $this->check_external_url( $imgUrl ) ) {
			$attachment_id = $this->upload_file( $imgUrl );
			if ( is_wp_error( $attachment_id ) ) {
				return;
			}

			$attachment_url                                  = wp_get_attachment_url( $attachment_id );
			$this->block['attrs']['frontCardBackground']['imgIdH'] = $attachment_id;
			$this->block['attrs']['frontCardBackground']['imgUrlH'] = $attachment_url;
		}
	}
}
