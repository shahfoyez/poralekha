<?php
namespace ABlocks\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class FileUpload {

	public function upload_file( $file, $supported_file_types = [] ) {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		if ( ! empty( $file ) && ! empty( $file['name'] ) ) {
			$filename = $file['name'];
			do_action( 'ablocks/before_upload_file', $filename );
		}

		$this->create_folder();

		$results = array(
			'error' => apply_filters( 'ablocks/file_upload_error_message', __( 'Error occurred, please try again', 'ablocks' ) ),
			'path' => '',
			'url' => '',
			'file_name' => ''
		);

		$path = ( isset( $file['name'] ) ) ? sanitize_text_field( $file['name'] ) : '';
		$ext  = pathinfo( $path, PATHINFO_EXTENSION );

		if ( count( $supported_file_types ) && ! in_array( $ext, $supported_file_types, true ) ) {
			return apply_filters( 'ablocks/not_supported_upload_file_error_message', __( 'Invalid file extension', 'ablocks' ) );
		}

		$filename    = md5( time() ) . basename( $path );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$file        = ( isset( $file['tmp_name'] ) ) ? file_get_contents( sanitize_text_field( $file['tmp_name'] ) ) : '';
		$upload_file = wp_upload_bits( $filename, null, $file );

		if ( $upload_file['error'] ) {
			$results['error'] = $upload_file['error'];
			return $results;
		}

		$wp_filesystem->move( $upload_file['file'], $destination_path );

		$file_data  = $this->get_file_data( $filename );
		$results['error'] = '';
		$results['path']        = $file_data['path'];
		$results['url']         = $file_data['url'];
		$results['file_name']   = $filename;

		return $results;
	}

	public function create_file( $file_name, $content = '' ) {
		do_action( 'ablocks/before_create_file', $file_name );
		$this->create_folder();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents
		return file_put_contents( $this->get_file_path( $file_name ), $content );
	}

	public function get_file_data( $filename ) {
		return array(
			'path' => $this->get_file_path( $filename ),
			'url' => $this->get_file_url( $filename ),
		);
	}

	public function get_file_path( $filename ) {
		return $this->get_upload_dir() . '/' . $filename;
	}

	public function get_file_url( $filename ) {
		return $this->get_upload_url() . '/' . $filename;
	}

	public function get_upload_url() {
		$upload     = wp_upload_dir();
		$upload_url = $upload['baseurl'];
		$upload_url = $upload_url . '/ablocks_uploads';
		return set_url_scheme( $upload_url );
	}

	public function get_upload_dir() {
		$upload     = wp_upload_dir();
		$upload_dir = $upload['basedir'];
		$upload_dir = $upload_dir . '/ablocks_uploads';
		return $upload_dir;
	}
	public function create_folder() {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$upload_dir = $this->get_upload_dir();

		if ( ! $wp_filesystem->is_dir( $upload_dir ) ) {
			wp_mkdir_p( $upload_dir );
		}
	}
	public function has_upload_file( $filename ) {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$upload_dir = $this->get_upload_dir();

		return $wp_filesystem->is_file( $upload_dir . '/' . $filename );
	}
	public function delete_file( $file_name ) {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}
		$upload_dir = $this->get_upload_dir();
		$file_path = $upload_dir . '/' . $file_name;

		if ( $wp_filesystem->is_file( $file_path ) ) {
			return $wp_filesystem->delete( $file_path );
		} elseif ( $wp_filesystem->is_dir( $file_path ) ) {
			return $wp_filesystem->rmdir( $file_path, true );
		}
		return false;
	}
	public function delete_files() {
		global $wp_filesystem;
		if ( empty( $wp_filesystem ) ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';
			WP_Filesystem();
		}

		$upload_dir = $this->get_upload_dir();

		// Get all files in the directory
		$files = $wp_filesystem->dirlist( $upload_dir );
		if ( is_array( $files ) && count( $files ) ) {
			foreach ( $files as $file => $fileinfo ) {
				$file_path = $upload_dir . '/' . $file;
				if ( $wp_filesystem->is_file( $file_path ) ) {
					$wp_filesystem->delete( $file_path );
				} elseif ( $wp_filesystem->is_dir( $file_path ) ) {
					$wp_filesystem->rmdir( $file_path, true );
				}
			}
		}
		return true;
	}
}
