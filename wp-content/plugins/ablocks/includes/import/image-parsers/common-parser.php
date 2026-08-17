<?php

namespace ABlocks\import\ImageParsers;

use DOMDocument;

class CommonParser extends AbstractImageParser {

	protected function parse() {
		$this->parse_background();
		$this->parse_background_hover();
		$this->parse_background_overlay();
		$this->parse_background_overlay_hover();
		$this->parse_icon();
	}

	private function parse_background() {
		if ( ! isset( $this->block['attrs']['_background'], $this->block['attrs']['_background']['backgroundType'] ) || 'image' !== $this->block['attrs']['_background']['backgroundType'] ) {
			return;
		}

		$imgUrl = $this->block['attrs']['_background']['imgUrl'];
		if ( empty( $imgUrl ) ) {
			return;
		}

		if ( $this->check_external_url( $imgUrl ) ) {
			$attachment_id = $this->upload_file( $imgUrl );
			if ( is_wp_error( $attachment_id ) ) {
				return;
			}

			$attachment_url                                  = wp_get_attachment_url( $attachment_id );
			$this->block['attrs']['_background']['imgId'] = $attachment_id;
			$this->block['attrs']['_background']['imgUrl'] = $attachment_url;
		}

	}

	private function parse_background_hover() {
		if ( ! isset( $this->block['attrs']['_background'], $this->block['attrs']['_background']['backgroundTypeH'] ) || 'image' !== $this->block['attrs']['_background']['backgroundTypeH'] ) {
			return;
		}

		$imgUrl = $this->block['attrs']['_background']['imgUrlH'];
		if ( empty( $imgUrl ) ) {
			return;
		}

		if ( $this->check_external_url( $imgUrl ) ) {
			$attachment_id = $this->upload_file( $imgUrl );
			if ( is_wp_error( $attachment_id ) ) {
				return;
			}

			$attachment_url                                  = wp_get_attachment_url( $attachment_id );
			$this->block['attrs']['_background']['imgIdH'] = $attachment_id;
			$this->block['attrs']['_background']['imgUrlH'] = $attachment_url;
		}
	}

	private function parse_background_overlay() {
		if ( ! isset( $this->block['attrs']['_backgroundOverlay'], $this->block['attrs']['_backgroundOverlay']['backgroundOverlayType'] ) || 'image' !== $this->block['attrs']['_backgroundOverlay']['backgroundOverlayType'] ) {
			return;
		}

		$imgUrl = $this->block['attrs']['_backgroundOverlay']['imgUrl'];
		if ( empty( $imgUrl ) ) {
			return;
		}

		if ( $this->check_external_url( $imgUrl ) ) {
			$attachment_id = $this->upload_file( $imgUrl );
			if ( is_wp_error( $attachment_id ) ) {
				return;
			}

			$attachment_url                                         = wp_get_attachment_url( $attachment_id );
			$this->block['attrs']['_backgroundOverlay']['imgId'] = $attachment_id;
			$this->block['attrs']['_backgroundOverlay']['imgUrl'] = $attachment_url;
		}

	}

	private function parse_background_overlay_hover() {
		if ( ! isset( $this->block['attrs']['_backgroundOverlay'] ) || 'image' !== $this->block['attrs']['_backgroundOverlay']['backgroundOverlayTypeH'] ) {
			return;
		}

		$imgUrl = $this->block['attrs']['_backgroundOverlay']['imgUrlH'];
		if ( empty( $imgUrl ) ) {
			return;
		}

		if ( $this->check_external_url( $imgUrl ) ) {
			$attachment_id = $this->upload_file( $imgUrl );
			if ( is_wp_error( $attachment_id ) ) {
				return;
			}

			$attachment_url                                         = wp_get_attachment_url( $attachment_id );
			$this->block['attrs']['_backgroundOverlay']['imgIdH'] = $attachment_id;
			$this->block['attrs']['_backgroundOverlay']['imgUrlH'] = $attachment_url;
		}

	}

	private function parse_icon() {
		if ( ! isset( $this->block['attrs']['iconImageUrl'] ) ) {
			return;
		}

		$dom = new DOMDocument();
		$dom->loadHTML( $this->block['innerHTML'], LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

		$parsed_img = $this->parse_img_tag( $dom, 0 );
		if ( ! $parsed_img ) {
			return;
		}
		list($attachment_id, $attachment_url, $remote_image_url) = $parsed_img;

		$this->block['attrs']['iconImageID'] = $attachment_id;
		$this->block['attrs']['iconImageUrl'] = $attachment_url;
		$this->block['innerHTML'] = str_replace( $remote_image_url, $attachment_url, $this->block['innerHTML'] );
		$this->block['innerContent'] = array_map( fn( $content) => str_replace( $remote_image_url, $attachment_url, $content ), $this->block['innerContent'] );

	}
}
