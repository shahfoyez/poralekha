<?php

namespace ABlocks\import\ImageParsers\ablocks;

use ABlocks\import\ImageParsers\AbstractImageParser;

class ModalParser extends AbstractImageParser {

	public function parse() {
		$this->parse_background();
		$this->parse_background_hover();
	}

	private function parse_background() {
		if ( ! isset( $this->block['attrs']['panelBackground'] ) || 'image' !== $this->block['attrs']['panelBackground']['backgroundType'] ) {
			return;
		}

		$imgUrl = $this->block['attrs']['panelBackground']['imgUrl'];
		if ( empty( $imgUrl ) ) {
			return;
		}

		if ( $this->check_external_url( $imgUrl ) ) {
			$attachment_id = $this->upload_file( $imgUrl );
			if ( is_wp_error( $attachment_id ) ) {
				return;
			}

			$attachment_url                                  = wp_get_attachment_url( $attachment_id );
			$this->block['attrs']['panelBackground']['imgId'] = $attachment_id;
			$this->block['attrs']['panelBackground']['imgUrl'] = $attachment_url;
		}
	}

	private function parse_background_hover() {
		if ( ! isset( $this->block['attrs']['panelBackground'] ) || 'image' !== $this->block['attrs']['panelBackground']['backgroundTypeH'] ) {
			return;
		}

		$imgUrl = $this->block['attrs']['panelBackground']['imgUrlH'];
		if ( empty( $imgUrl ) ) {
			return;
		}

		if ( $this->check_external_url( $imgUrl ) ) {
			$attachment_id = $this->upload_file( $imgUrl );
			if ( is_wp_error( $attachment_id ) ) {
				return;
			}

			$attachment_url                                  = wp_get_attachment_url( $attachment_id );
			$this->block['attrs']['panelBackground']['imgIdH'] = $attachment_id;
			$this->block['attrs']['panelBackground']['imgUrlH'] = $attachment_url;
		}
	}
}
