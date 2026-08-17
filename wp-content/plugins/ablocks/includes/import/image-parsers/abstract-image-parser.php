<?php

namespace ABlocks\import\ImageParsers;

use ABlocks\import\ImageParsers\ablocks\ImageParser;
use DOMDocument;
use WP_Error;

abstract class AbstractImageParser {

	protected array $block;

	public function __construct( array $block ) {
		$this->block = $block;
	}

	abstract protected function parse();

	public function do_parse(): array {
		$this->parse();
		return $this->block;
	}

	/**
	 * Function handles downloading a remote file and inserting it
	 * into the WP Media Library.
	 *
	 * @param string $url HTTP URL address of a remote file.
	 *
	 * @return int|WP_Error The ID of the attachment or a WP_Error on failure
	 * @see https://developer.wordpress.org/reference/functions/media_handle_sideload/
	 */
	protected function upload_file( string $url ) {
		// URL Validation.
		if ( ! wp_http_validate_url( $url ) ) {
			return new WP_Error( 'invalid_url', 'File URL is invalid', array( 'status' => 400 ) );
		}

		$previous_uploaded = $this->previous_uploaded_file( $url );
		if ( $previous_uploaded ) {
			return $previous_uploaded;
		}

		// Gives us access to the download_url() and media_handle_sideload() functions.
		if ( ! function_exists( 'download_url' ) || ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/media.php';
		}

		// Download file to temp dir.
		$temp_file = download_url( $url );

		// if the file was not able to be downloaded.
		if ( is_wp_error( $temp_file ) ) {
			return $temp_file;
		}

		// An array similar to that of a PHP `$_FILES` POST array
		$file_url_path = wp_parse_url( $url, PHP_URL_PATH );
		$file_info     = wp_check_filetype( $file_url_path );
		$file          = array(
			'tmp_name' => $temp_file,
			'type'     => $file_info['type'],
			'name'     => basename( $file_url_path ),
			'size'     => filesize( $temp_file ),
		);

		// Move the temporary file into the upload directory.
		$attachment_id = media_handle_sideload( $file );
		update_post_meta( $attachment_id, '_ablocks_demo_ref', $url );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.unlink_unlink 
		@unlink( $temp_file );

		return $attachment_id;
	}

	protected function previous_uploaded_file( string $url ) {
		$attachment = get_posts( [
			'post_type'      => 'attachment',
			'meta_key'       => '_ablocks_demo_ref',
			'meta_value'     => $url,
			'posts_per_page' => 1,
		] );

		if ( ! empty( $attachment ) ) {
			return $attachment[0]->ID;
		}

		return false;
	}

	protected function check_external_url( string $url ): bool {
		$link_url = wp_parse_url( $url );
		$home_url = wp_parse_url( home_url() );

		return ( ! empty( $link_url['host'] ) && ( $link_url['host'] !== $home_url['host'] ) );
	}

	/**
	 * Parse single <img/> element from DOM.
	 *
	 * @param DOMDocument $dom DOM.
	 * @param int         $index Index number.
	 *
	 * @return array|false
	 */
	protected function parse_img_tag( DOMDocument $dom, int $index ) {
		$imgHtml = $dom->getElementsByTagName( 'img' )->item( $index );

		if ( ! $imgHtml ) {
			return false;
		}

		$imgSRC = $imgHtml->attributes->getNamedItem( 'src' );
		// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Calling a property from native class `DOMDocument`.
		$imgURL = $imgSRC->nodeValue;
		if ( ! $this->check_external_url( $imgURL ) ) {
			return false;
		}

		$attachment_id = $this->upload_file( $imgURL );
		if ( is_wp_error( $attachment_id ) ) {
			return false;
		}

		$attachment_url = wp_get_attachment_url( $attachment_id );

		return array( $attachment_id, $attachment_url, $imgURL );
	}
}
