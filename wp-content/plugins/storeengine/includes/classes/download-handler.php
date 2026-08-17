<?php

namespace StoreEngine\Classes;

use stdClass;
use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class DownloadHandler {
	use Singleton;

	protected function __construct() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['download_file'], $_GET['order'], $_GET['key'] ) && ( isset( $_GET['email'] ) || isset( $_GET['uid'] ) ) ) {
			add_action( 'init', [ __CLASS__, 'download_product' ], - 1 );
		}

		if (
			isset(
				$_REQUEST['se_secure_dl'],
				$_REQUEST['resource'],
				$_REQUEST['type'],
				$_REQUEST['X-SE-Signature'],
				$_REQUEST['X-SE-Credential'],
				$_REQUEST['X-SE-Date'],
				$_REQUEST['X-SE-Expires'],
				$_REQUEST['X-SE-SignedHeaders'],
				$_REQUEST['X-SE-Algorithm']
			)
		) {
			add_action( 'init', [ __CLASS__, 'handle_secure_download' ], - 1 );
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
	}

	public static function handle_secure_download() {
		$is_valid = UrlPresigner::init()->validateCurrentRequest();

		if ( is_wp_error( $is_valid ) ) {
			self::download_error( $is_valid->get_error_message(), '', rest_authorization_required_code() );
		}

		// phpcs:disable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$se_secure_dl = absint( $_REQUEST['se_secure_dl'] );
		$product      = Helper::get_product( $se_secure_dl );
		$resource     = sanitize_text_field( wp_unslash( $_REQUEST['resource'] ) );
		$dl_type      = sanitize_text_field( wp_unslash( $_REQUEST['type'] ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotValidated

		if ( ! $product || empty( $resource ) || empty( $dl_type ) ) {
			self::download_error( __( 'Invalid download link.', 'storeengine' ), '', rest_authorization_required_code() );
		}

		if ( 'publish' !== $product->get_status() ) {
			self::download_error( __( 'File unavailable.', 'storeengine' ) );
		}

		if ( has_action( "storeengine/secure_download/{$dl_type}" ) ) {
			try {
				do_action( "storeengine/secure_download/{$dl_type}", $resource, $product );
			} catch ( \StoreEngine\Classes\Exceptions\StoreEngineException $e ) {
				$code = absint( $e->getCode() ?: 500 );
				if ( 404 === $code ) {
					self::download_error( __( 'No file defined', 'storeengine' ) );
				}

				self::download_error( $e->getMessage(), '',  $code );
			} catch ( \Exception $e ) {
				self::download_error( $e->getMessage(), '', 500 );
			}

			// Error if not handled properly.
			self::download_error(
				__( 'Not Implemented', 'storeengine' ),
				__( 'Not Implemented', 'storeengine' ),
				501
			);
		} else {
			/**
			 * Filter download filepath.
			 *
			 * @param string $file_data File path.
			 * @param string $email_address Email address.
			 * @param Order|bool $order Order object or false.
			 * @param AbstractProduct $product Product object.
			 * @param stdClass $download Download data.
			 */
			$file_data = apply_filters( "storeengine/secure_downloads/$dl_type/file_data", [], $resource, $product );

			if ( is_wp_error( $file_data ) ) {
				$status = is_numeric( $file_data->get_error_code() ) ? $file_data->get_error_code() : 500;
				self::download_error( $file_data->get_error_message(), '', $status );
			}

			if ( empty( $file_data['file_path'] ) || empty( $file_data['file_name'] ) ) {
				self::download_error( __( 'No file defined', 'storeengine' ) );
			}

			self::download( $file_data['file_path'], $file_data['file_name'], $product->get_id() );
		}
	}

	/**
	 * Check if we need to download a file and check validity.
	 */
	public static function download_product() {
		global $wpdb;
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		$product_id = absint( $_GET['download_file'] ?? 0 ); // phpcs:ignore WordPress.VIP.SuperGlobalInputUsage.AccessDetected, WordPress.VIP.ValidatedSanitizedInput.InputNotValidated, WordPress.Security.ValidatedSanitizedInput.InputNotValidated
		$product    = Helper::get_product( $product_id );
		$key        = sanitize_text_field( wp_unslash( $_GET['key'] ?? '' ) );
		$order_key  = sanitize_text_field( wp_unslash( $_GET['order'] ?? '' ) );
		$email      = sanitize_email( wp_unslash( $_GET['email'] ?? '' ) );
		$uid        = sanitize_text_field( wp_unslash( $_GET['uid'] ?? '' ) );
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$downloadable_file = ! $product ? [] : array_filter( $product->get_downloadable_files(), fn( $download ) => $download['id'] === $key );
		$downloadable_file = reset( $downloadable_file );
		$order             = Helper::get_order_by_key( $order_key );

		if ( ! $product || empty( $key ) || is_wp_error( $order ) || empty( $downloadable_file ) || empty( $downloadable_file['enabled'] ) ) {
			self::download_error( __( 'Invalid download link.', 'storeengine' ), '', rest_authorization_required_code() );
		}

		// Fallback, accept email address if it's passed.
		if ( empty( $email ) && empty( $uid ) ) {
			self::download_error( __( 'Invalid download link.', 'storeengine' ), '', rest_authorization_required_code() );
		}

		if ( ! $email && $uid ) {
			$email = $order->get_billing_email();

			if ( ! hash_equals( $uid, hash( 'sha256', $email ) ) ) {
				self::download_error( __( 'Invalid download link.', 'storeengine' ), '', rest_authorization_required_code() );
			}
		}

		// get_user_by-email would fail if a customer usage different billing email.
		// Also permission table doesn't have email column.
//		$user = get_user_by( 'email', $email );
//
//		if ( ! $user ) {
//			self::download_error( __( 'Invalid download link.', 'storeengine' ), '', rest_authorization_required_code() );
//		}

		// Don't check user id here. As you might want to share the link with others.
		// And, settings might allow non-logged-in user's to download.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery
		$download_permission = $wpdb->get_row(
			$wpdb->prepare( "
				SELECT *
				FROM {$wpdb->prefix}storeengine_downloadable_product_permissions
				WHERE
					order_id = %d
				  AND download_id = %s
				  AND product_id = %d
				ORDER BY id DESC
				LIMIT 1;",
				$order->get_id(),
				$key,
				$product->get_id()
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.DirectQuery

		if (
			(
				$order->is_type( 'order' )
				&& ! ( $order->has_status( Helper::get_order_paid_statuses() ) || $order->is_paid() )
			)
			||
			( $order->is_type( 'subscription' ) && ! $order->has_status( 'active' ) )
		) {
			// @TODO use $order->set_download_permission_granted() in download-permission hook
			// @TODO use $order->get_download_permission_granted() here to reduce condition.
			self::download_error( __( 'Invalid download link.', 'storeengine' ), '', rest_authorization_required_code() );
		}

		/**
		 * Filter download filepath.
		 *
		 * @param string $file_path File path.
		 * @param string $email_address Email address.
		 * @param Order|bool $order Order object or false.
		 * @param AbstractProduct $product Product object.
		 * @param stdClass $download Download data.
		 */
		$file_path = apply_filters( 'storeengine/downloads/product_filepath', $downloadable_file['file'], $email, $order, $product, $download_permission );

		if ( ! $file_path ) {
			self::download_error( __( 'No file defined', 'storeengine' ) );
		}

		$parsed_file_path = self::parse_file_path( $file_path );
		//$download_range   = self::get_download_range( @filesize( $parsed_file_path['file_path'] ) );

		//if ( ! $download_range['is_range_request'] ) {
			// @TODO If the remaining download count goes to 0, allow range requests to be able to finish streaming from iOS devices.
			//self::check_downloads_remaining( $download );
		//}

		self::check_download_expiry( $download_permission );
		self::check_download_login_required( $download_permission );

		// Track the download in logs and change remaining/counts.
		//$current_user_id = get_current_user_id();
		//$ip_address      = Helper::get_user_ip();

		// @TODO: track download.
//		self::track_download(
//			$download_permission,
//			$current_user_id > 0 ? $current_user_id : null,
//			! empty( $ip_address ) ? $ip_address : null,
//			$download_range['is_range_request']
//		);

		self::download( $file_path, $downloadable_file['name'] ?? basename( $file_path ), $product->get_id() );
	}

	/**
	 * Check if the download has expired.
	 *
	 * @param stdClass $download_permission Download permission instance.
	 */
	private static function check_download_expiry( stdClass $download_permission ) {
		if ( ! is_null( $download_permission->access_expires ) && strtotime( $download_permission->access_expires ) < strtotime( 'midnight', time() ) ) {
			self::download_error( __( 'Sorry, this download has expired', 'storeengine' ), '', 403 );
		}
	}

	/**
	 * Check if a download requires the user to login first.
	 *
	 * @param stdClass $download_permission Download instance.
	 */
	private static function check_download_login_required( stdClass $download_permission ) {
		$user_id = (int) $download_permission->user_id;
		if ( $user_id && Helper::get_settings( 'downloads_require_login', false ) ) {
			if ( ! is_user_logged_in() ) {
				if ( Helper::get_settings( 'dashboard_page' ) ) {
					wp_safe_redirect( add_query_arg( 'storeengine_error', rawurlencode( __( 'You must be logged in to download files.', 'storeengine' ) ), Helper::get_dashboard_url() ) );
					exit;
				} else {
					self::download_error( __( 'You must be logged in to download files.', 'storeengine' ) . ' <a href="' . esc_url( storeengine_login_url( Helper::get_dashboard_url() ) ) . '">' . __( 'Login', 'storeengine' ) . '</a>', __( 'Log in to Download Files', 'storeengine' ), 403 );
				}
			} elseif ( get_current_user_id() !== $user_id ) {
				self::download_error( __( 'This is not your download link.', 'storeengine' ), '', 403 );
			}
		}
	}

	/**
	 * Download a file - hook into init function.
	 *
	 * @param string  $file_path  URL to file.
	 * @param ?string  $filename  Name of the file.
	 * @param ?int $product_id Product ID of the product being downloaded.
	 */
	public static function download( string $file_path, ?string $filename = null, ?int $product_id = null ) {
		if ( ! $filename ) {
			$filename = basename( $file_path );
		}

		if ( strstr( $filename, '?' ) ) {
			$filename = current( explode( '?', $filename ) );
		}

		$filename = apply_filters( 'storeengine/downloads/attachment_filename', $filename, $file_path, $product_id );

		// Add action to prevent issues in IE.
		add_action( 'nocache_headers', [ __CLASS__, 'ie_nocache_headers_fix' ] );

		// Trigger download via one of the methods.
		$file_download_method = apply_filters(
			'storeengine/downloads/file_download_method',
			Helper::get_settings( 'file_download_method', 'force' ),
			$file_path,
			$product_id
		);

		if ( 'xsendfile' !== $file_download_method ) {
			self::download_file_force( $file_path, $filename );
			return;
		}

		self::download_file_xsendfile( $file_path, $filename );
	}

	/**
	 * Download a file using X-Sendfile, X-Lighttpd-Sendfile, or X-Accel-Redirect if available.
	 *
	 * @param string $file_path File path.
	 * @param string $filename  File name.
	 */
	public static function download_file_xsendfile( string $file_path, string $filename ) {
		$parsed_file_path = self::parse_file_path( $file_path );

		/**
		 * Fallback on force download method for remote files. This is because:
		 * 1. xsendfile needs proxy configuration to work for remote files, which cannot be assumed to be available on most hosts.
		 * 2. Force download method is more secure than redirect method if `allow_url_fopen` is enabled in `php.ini`.
		 */
		if ( $parsed_file_path['remote_file'] ) {
			self::download_file_force( $file_path, $filename );
			return;
		}

		// If file not inside secure-directory then X-Sendfile/X-Accel-Redirect will not work.
		if ( ! str_starts_with( $parsed_file_path['file_path'], STOREENGINE_SECURE_UPLOADS_DIR ) ) {
			// @XXX SE downloads should not be in regular uploads (e.g. uploads/yyyy/mm/file.ext),
			//      it should be moved to secure directory upon upload.
			self::download_file_force( $file_path, $filename );
			return;
		}


		if ( function_exists( 'apache_get_modules' ) && in_array( 'mod_xsendfile', apache_get_modules(), true ) ) {
			self::download_headers( $parsed_file_path['file_path'], $filename );
			header( 'X-Sendfile: ' . $parsed_file_path['file_path'] );
			exit;
		} elseif ( stristr( getenv( 'SERVER_SOFTWARE' ), 'lighttpd' ) ) {
			self::download_headers( $parsed_file_path['file_path'], $filename );
			header( 'X-Lighttpd-Sendfile: ' . $parsed_file_path['file_path'] );
			exit;
		} elseif ( stristr( getenv( 'SERVER_SOFTWARE' ), 'nginx' ) || stristr( getenv( 'SERVER_SOFTWARE' ), 'cherokee' ) ) {
			self::download_headers( $parsed_file_path['file_path'], $filename );
			$filepath = trim( preg_replace( '`^' . str_replace( '\\', '/', getcwd() ) . '`', '', $parsed_file_path['file_path'] ), '/' );
			header( "X-Accel-Redirect: /$filepath" );
			exit;
		}

		// Fallback.
		Helper::log_error(
			sprintf(
			/* translators: %1$s contains the filepath of the digital asset. */
				__( '%1$s could not be served using the X-Accel-Redirect/X-Sendfile method. A Force Download will be used instead.', 'storeengine' ),
				$file_path
			),
			false
		);

		self::download_file_force( $file_path, $filename );
	}

	/**
	 * Redirect to a file to start the download.
	 *
	 * @param string $file_path File path.
	 * @param string $filename  File name.
	 */
	public static function download_file_redirect( $file_path, $filename = '' ) {
		header( 'Location: ' . $file_path );
		exit;
	}

	/**
	 * Parse the HTTP_RANGE request from iOS devices.
	 * Does not support multi-range requests.
	 *
	 * @param int $file_size Size of file in bytes.
	 *
	 * @return array {
	 *     Information about range download request: beginning and length of
	 *     file chunk, whether the range is valid/supported and whether the request is a range request.
	 *
	 * @type int $start Byte offset of the beginning of the range. Default 0.
	 * @type int $length Length of the requested file chunk in bytes. Optional.
	 * @type bool $is_range_valid Whether the requested range is a valid and supported range.
	 * @type bool $is_range_request Whether the request is a range request.
	 * }
	 */
	protected static function get_download_range( int $file_size ): array {
		$start          = 0;
		$download_range = array(
			'start'            => $start,
			'is_range_valid'   => false,
			'is_range_request' => false,
		);

		if ( ! $file_size ) {
			return $download_range;
		}

		$end                      = $file_size - 1;
		$download_range['length'] = $file_size;

		if ( isset( $_SERVER['HTTP_RANGE'] ) ) {
			$http_range                         = sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) );
			$download_range['is_range_request'] = true;

			$c_start = $start;
			$c_end   = $end;
			// Extract the range string.
			list( , $range ) = explode( '=', $http_range, 2 );
			// Make sure the client hasn't sent us a multibyte range.
			if ( strpos( $range, ',' ) !== false ) {
				return $download_range;
			}

			/*
			 * If the range starts with an '-' we start from the beginning.
			 * If not, we forward the file pointer
			 * and make sure to get the end byte if specified.
			 */
			if ( '-' === $range[0] ) {
				// The n-number of the last bytes is requested.
				$c_start = $file_size - substr( $range, 1 );
			} else {
				$range   = explode( '-', $range );
				$c_start = ( isset( $range[0] ) && is_numeric( $range[0] ) ) ? (int) $range[0] : 0;
				$c_end   = ( isset( $range[1] ) && is_numeric( $range[1] ) ) ? (int) $range[1] : $file_size;
			}

			/*
			 * Check the range and make sure it's treated according to the specs: http://www.w3.org/Protocols/rfc2616/rfc2616-sec14.html.
			 * End bytes can not be larger than $end.
			 */
			$c_end = ( $c_end > $end ) ? $end : $c_end;
			// Validate the requested range and return an error if it's not correct.
			if ( $c_start > $c_end || $c_start > $file_size - 1 || $c_end >= $file_size ) {
				return $download_range;
			}
			$start  = $c_start;
			$end    = $c_end;
			$length = $end - $start + 1;

			$download_range['start']          = $start;
			$download_range['length']         = $length;
			$download_range['is_range_valid'] = true;
		}

		return $download_range;
	}

	/**
	 * Force download - this is the default method.
	 *
	 * @param string $file_path File path.
	 * @param string $filename File name.
	 */
	public static function download_file_force( string $file_path, string $filename ) {
		$parsed_file_path = self::parse_file_path( $file_path );
		$download_range = self::get_download_range( @filesize( $file_path ) );

		self::download_headers( $parsed_file_path['file_path'], $filename, $download_range );

		$start  = $download_range['start'] ?? 0;
		$length = $download_range['length'] ?? 0;

		if ( ! self::readfile_chunked( $parsed_file_path['file_path'], $start, $length ) ) {
			if ( $parsed_file_path['remote_file'] ) {
				self::download_file_redirect( $file_path );
			} else {
				self::download_error( __( 'File not found', 'storeengine' ) );
			}
		}

		exit;
	}

	/**
	 * Parse file path and see if its remote or local.
	 *
	 * @param string $file_path File path.
	 *
	 * @return array
	 */
	public static function parse_file_path( string $file_path ): array {
		$wp_uploads     = wp_upload_dir();
		$wp_uploads_dir = $wp_uploads['basedir'];
		$wp_uploads_url = $wp_uploads['baseurl'];

		/**
		 * Replace uploads dir, site url etc with absolute counterparts if we can.
		 * Note the str_replace on site_url is on purpose, so if https is forced
		 * via filters we can still do the string replacement on a HTTP file.
		 */
		$replacements = [
			$wp_uploads_url                  => $wp_uploads_dir,
			network_site_url( '/', 'https' ) => ABSPATH,
			str_replace( 'https:', 'http:', network_site_url( '/', 'http' ) ) => ABSPATH,
			site_url( '/', 'https' )         => ABSPATH,
			str_replace( 'https:', 'http:', site_url( '/', 'http' ) ) => ABSPATH,
		];

		$count            = 0;
		$file_path        = str_replace( array_keys( $replacements ), array_values( $replacements ), $file_path, $count );
		$parsed_file_path = wp_parse_url( $file_path );
		$remote_file      = null === $count || 0 === $count; // Remote file only if there were no replacements.

		// Paths that begin with '//' are always remote URLs.
		if ( '//' === substr( $file_path, 0, 2 ) ) {
			$file_path = ( is_ssl() ? 'https:' : 'http:' ) . $file_path;

			/**
			 * Filter the remote filepath for download.
			 *
			 * @param string $file_path File path.
			 *
			 * @since 6.5.0
			 */
			return array(
				'remote_file' => true,
				'file_path'   => apply_filters( 'storeengine/downloads/parse_remote_file_path', $file_path ),
			);
		}

		// See if path needs an abspath prepended to work.
		if ( file_exists( ABSPATH . $file_path ) ) {
			$remote_file = false;
			$file_path   = ABSPATH . $file_path;
		} elseif ( '/wp-content' === substr( $file_path, 0, 11 ) ) {
			$remote_file = false;
			$file_path   = realpath( WP_CONTENT_DIR . substr( $file_path, 11 ) );

			// Check if we have an absolute path.
		} elseif ( ( ! isset( $parsed_file_path['scheme'] ) || ! in_array( $parsed_file_path['scheme'], [ 'http', 'https', 'ftp' ], true ) ) && isset( $parsed_file_path['path'] ) ) {
			$remote_file = false;
			$file_path   = $parsed_file_path['path'];
		}

		/**
		 * Filter the filepath for download.
		 *
		 * @param string $file_path File path.
		 * @param bool $remote_file Remote File Indicator.
		 *
		 * @since 6.5.0
		 */
		return [
			'remote_file' => $remote_file,
			'file_path'   => apply_filters( 'storeengine/downloads/parse_file_path', $file_path, $remote_file ),
		];
	}

	/**
	 * Read file chunked.
	 *
	 * Reads file in chunks so big downloads are possible without changing PHP.INI - http://codeigniter.com/wiki/Download_helper_for_large_files/.
	 *
	 * @param string $file File.
	 * @param int $start Byte offset/position of the beginning from which to read from the file.
	 * @param int $length Length of the chunk to be read from the file in bytes, 0 means full file.
	 *
	 * @return bool Success or fail
	 */
	protected static function readfile_chunked( string $file, $start = 0, $length = 0 ): bool {
		// Never stream a local file that resolves outside the allowed roots.
		// Blocks path-traversal (../../) or absolute-path downloadable "URLs"
		// (e.g. /etc/passwd) from exfiltrating arbitrary server files.
		if ( ! self::is_allowed_local_path( $file ) ) {
			return false;
		}

		if ( ! defined( 'STOREENGINE_CHUNK_SIZE' ) ) {
			define( 'STOREENGINE_CHUNK_SIZE', 1024 * 1024 );
		}

		// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged
		$handle = @fopen( $file, 'r' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_fopen, WordPress.WP.AlternativeFunctions.file_system_operations_fopen

		if ( false === $handle ) {
			return false;
		}

		if ( ! $length ) {
			$length = @filesize( $file ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
		}

		$read_length = STOREENGINE_CHUNK_SIZE;

		if ( $length ) {
			$end = $start + $length - 1;

			@fseek( $handle, $start ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
			$p = @ftell( $handle ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

			while ( ! @feof( $handle ) && $p <= $end ) { // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
				// Don't run past the end of file.
				if ( $p + $read_length > $end ) {
					$read_length = $end - $p + 1;
				}

				echo @fread( $handle, $read_length ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.XSS.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_read_fread, WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_fread
				$p = @ftell( $handle ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged

				if ( ob_get_length() ) {
					ob_flush();
					flush();
				}
			}
		} else {
			while ( ! @feof( $handle ) ) {
				echo @fread( $handle, $read_length ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.WP.AlternativeFunctions.file_system_operations_fread
				if ( ob_get_length() ) {
					ob_flush();
					flush();
				}
			}
		}

		return @fclose( $handle ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_system_read_fclose, WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		// phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * Whether a resolved LOCAL file path is safe to serve.
	 *
	 * Canonicalises the path with realpath() and confirms it lives inside one of
	 * the allowed roots (wp-content, the uploads dir, or StoreEngine's secure
	 * uploads dir). Rejects traversal ("../") and absolute paths pointing anywhere
	 * else on the filesystem, closing arbitrary-file-read via a crafted
	 * downloadable file URL.
	 *
	 * ABSPATH is deliberately NOT an allowed root: legitimate product downloads
	 * live under uploads / wp-content, never in the WP install root. Whitelisting
	 * ABSPATH would let a resolved path point straight at core files such as
	 * wp-config.php (which sits inside ABSPATH by default). A known-sensitive
	 * denylist is applied on top as defence in depth, in case a host filters an
	 * over-broad root back in via `storeengine/downloads/allowed_roots`.
	 *
	 * @param string $file Absolute local file path (already parsed).
	 * @return bool True if the file may be served.
	 */
	protected static function is_allowed_local_path( string $file ): bool {
		if ( '' === $file ) {
			return false;
		}

		$real = realpath( $file );
		if ( false === $real ) {
			return false; // Non-existent / unresolvable path.
		}

		// Deny known-sensitive files outright, even if they sit inside an allowed
		// root — blocks config/secret files and dotfiles (.htaccess, .htpasswd, …).
		$basename = strtolower( basename( $real ) );
		$denied   = [ 'wp-config.php', 'wp-config-sample.php', '.htaccess', '.htpasswd', '.user.ini', 'php.ini' ];
		if ( in_array( $basename, $denied, true ) || str_starts_with( $basename, '.' ) ) {
			return false;
		}

		$roots   = [ WP_CONTENT_DIR ];
		$uploads = wp_upload_dir();
		if ( ! empty( $uploads['basedir'] ) ) {
			$roots[] = $uploads['basedir'];
		}
		if ( defined( 'STOREENGINE_SECURE_UPLOADS_DIR' ) && STOREENGINE_SECURE_UPLOADS_DIR ) {
			$roots[] = STOREENGINE_SECURE_UPLOADS_DIR;
		}

		/**
		 * Allow hosts to whitelist additional download roots (absolute paths).
		 *
		 * @param string[] $roots Allowed root directories.
		 */
		$roots = (array) apply_filters( 'storeengine/downloads/allowed_roots', $roots );

		foreach ( $roots as $root ) {
			$root_real = realpath( $root );
			if ( $root_real && str_starts_with( $real, rtrim( $root_real, '/\\' ) . DIRECTORY_SEPARATOR ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Filter headers for IE to fix issues over SSL.
	 *
	 * IE bug prevents download via SSL when Cache Control and Pragma no-cache headers set.
	 *
	 * @param array $headers HTTP headers.
	 *
	 * @return array
	 */
	public static function ie_nocache_headers_fix( array $headers ): array {
		if ( is_ssl() && ! empty( $GLOBALS['is_IE'] ) ) {
			$headers['Cache-Control'] = 'private';
			unset( $headers['Pragma'] );
		}
		return $headers;
	}

	/**
	 * Set headers for the download.
	 *
	 * @param string $file_path File path.
	 * @param string $filename File name.
	 * @param array $download_range Array containing info about range download request (see {@see get_download_range} for structure).
	 */
	private static function download_headers( string $file_path, string $filename, array $download_range = [] ) {
		// phpcs:disable WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky
		if ( function_exists( 'set_time_limit' ) && false === strpos( ini_get( 'disable_functions' ), 'set_time_limit' ) && ! ini_get( 'safe_mode' ) ) { // phpcs:ignore PHPCompatibility.IniDirectives.RemovedIniDirectives.safe_modeDeprecatedRemoved
			@set_time_limit( 0 ); // phpcs:ignore Squiz.PHP.DiscouragedFunctions.Discouraged
		}

		if ( function_exists( 'apache_setenv' ) ) {
			@apache_setenv( 'no-gzip', 1 ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_apache_setenv
		}

		@ini_set( 'zlib.output_compression', 'Off' ); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_ini_set, Squiz.PHP.DiscouragedFunctions.Discouraged
		@session_write_close(); // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged, WordPress.VIP.SessionFunctionsUsage.session_session_write_close, Squiz.PHP.DiscouragedFunctions.Discouraged

		if ( ob_get_level() ) {
			$levels = ob_get_level();
			for ( $i = 0; $i < $levels; $i ++ ) {
				@ob_end_clean();
			}
		} else {
			@ob_end_clean();
		}

		header( 'X-Robots-Tag: noindex, nofollow', true );
		header( 'Content-Type: ' . self::get_download_content_type( $file_path ) );
		header( 'Content-Description: File Transfer' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '";' );
		header( 'Content-Transfer-Encoding: binary' );

		$file_size = @filesize( $file_path );
		if ( ! $file_size ) {
			return;
		}

		if ( isset( $download_range['is_range_request'] ) && true === $download_range['is_range_request'] ) {
			if ( false === $download_range['is_range_valid'] ) {
				header( 'HTTP/1.1 416 Requested Range Not Satisfiable' );
				header( 'Content-Range: bytes 0-' . ( $file_size - 1 ) . '/' . $file_size );
				exit;
			}

			$start  = $download_range['start'];
			$end    = $download_range['start'] + $download_range['length'] - 1;
			$length = $download_range['length'];

			header( 'HTTP/1.1 206 Partial Content' );
			header( "Accept-Ranges: 0-$file_size" );
			header( "Content-Range: bytes $start-$end/$file_size" );
			header( "Content-Length: $length" );
		} else {
			header( 'Content-Length: ' . $file_size );
		}
		// phpcs:enable WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.IniSet.Risky
	}

	/**
	 * Get content type of a download.
	 *
	 * @param string $file_path File path.
	 *
	 * @return string
	 */
	private static function get_download_content_type( $file_path ) {
		$file_extension = strtolower( substr( strrchr( $file_path, '.' ), 1 ) );
		$ctype          = 'application/force-download';

		foreach ( get_allowed_mime_types() as $mime => $type ) {
			$mimes = explode( '|', $mime );
			if ( in_array( $file_extension, $mimes, true ) ) {
				$ctype = $type;
				break;
			}
		}

		return $ctype;
	}

	/**
	 * Die with an error message if the download fails.
	 *
	 * @param string $message Error message.
	 * @param string $title Error title.
	 * @param integer $status Error status.
	 */
	public static function download_error( string $message, string $title = '', int $status = 404 ) {
		/*
		 * Since we will now render a message instead of serving a download, we should unwind some of the previously set
		 * headers.
		 * Also if headers are already sent, log the issue.
		 */
		$header_sent = headers_sent();
		if ( ! $header_sent ) {
			header( 'Content-Type: ' . get_option( 'html_type' ) . '; charset=' . get_option( 'blog_charset' ) );
			header_remove( 'Content-Description;' );
			header_remove( 'Content-Disposition' );
			header_remove( 'Content-Transfer-Encoding' );
		}

		if ( ! $title ) {
			$title = __( 'Error while processing file', 'storeengine' );
		}

		if ( 400 > $status ) {
			$status = 500;
		}

		// translators: %s. Full url of the download request.
		$req_uri = sprintf( __( 'Requested URL: %s', 'storeengine' ), sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotValidated

		$referrer_uri = null;
		if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
			// translators: %s. HTTP Referrer.
			$referrer_uri = sprintf( __( 'HTTP Referrer: %s', 'storeengine' ), sanitize_text_field( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) );
		}

		Logger::log(
			'Download Failed: ' . $title,
			[
				'message'     => $message,
				'header_sent' => $header_sent,
				'request'     => $req_uri,
				'referrer'    => $referrer_uri,
				'customer'    => get_current_user_id(),
			],
			Logger::INFO,
			'system'
		);

		if ( ! strstr( $message, '<a ' ) ) {
			$message .= ' <a href="' . esc_url( Helper::get_page_permalink( 'shop_page' ) ) . '">' . esc_html__( 'Go to shop', 'storeengine' ) . '</a>';
		}

		wp_die( wp_kses_post( $message ), esc_html( $title ), [ 'response' => $status ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}
}

// End of file download-handler.php.
