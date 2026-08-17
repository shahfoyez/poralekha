<?php
namespace ABlocks\Classes\PageCache;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use ABlocks\Classes\FileUpload;
use ABlocks\Classes\CacheBackend;

/**
 * Page Cache — filesystem layout, reads, writes and purges.
 *
 * Files live at:
 *
 *     uploads/ablocks_uploads/page-cache/{host}/{url-path}/index.html
 *                                                         index.html.gz
 *
 * The layout mirrors the URL path rather than hashing it. Hashing would be
 * simpler here, but nginx cannot compute a hash in a `try_files` rule, so a hash
 * layout permanently forecloses the server-level tier. Mirroring costs nothing
 * now and is the only layout all three tiers can share.
 *
 * Because nginx matches against its already-decoded `$uri`, the on-disk path
 * must be the *decoded* URL path — including UTF-8 slugs. That rules out
 * sanitising by transliteration or hashing unsafe segments; instead we reject
 * anything that cannot be represented safely. See {@see Store::sanitize_segment()}.
 *
 * Writes are direct filesystem calls rather than WP_Filesystem. This is
 * deliberate: WP_Filesystem cannot do an atomic rename, may fall back to an FTP
 * transport that prompts for credentials, and requires loading wp-admin includes
 * on a frontend request. A page cache needs write-then-rename so a concurrent
 * reader never observes a half-written page. Every path written here is
 * confined to the plugin's own uploads subdirectory and is validated against it
 * before use.
 */
class Store {

	const DIR_NAME  = 'page-cache';
	const FILE_NAME = 'index.html';

	/**
	 * Resolved base directory, memoised per request.
	 *
	 * @var string|null
	 */
	private static $base_dir = null;

	/**
	 * Absolute path to the page-cache root, without a trailing slash.
	 *
	 * @return string
	 */
	public static function base_dir() {
		if ( null === self::$base_dir ) {
			$upload = new FileUpload();
			self::$base_dir = untrailingslashit( $upload->get_upload_dir() ) . '/' . self::DIR_NAME;
		}
		return self::$base_dir;
	}

	/**
	 * Absolute path to the cache file for a host + URL path, or null when the
	 * request cannot be represented safely on disk.
	 *
	 * @param string $host    Host name.
	 * @param string $path    URL path (decoded or encoded; decoded here).
	 * @param string $variant Optional variant suffix, e.g. 'mobile'.
	 * @return string|null
	 */
	public static function file_path( $host, $path, $variant = '' ) {
		$host = self::sanitize_host( $host );
		if ( '' === $host ) {
			return null;
		}

		$segments = self::sanitize_path( $path );
		if ( null === $segments ) {
			return null;
		}

		$file = self::FILE_NAME;
		if ( '' !== $variant ) {
			$variant = preg_replace( '/[^a-z0-9-]/', '', strtolower( $variant ) );
			if ( '' !== $variant ) {
				$file = 'index-' . $variant . '.html';
			}
		}

		$dir = self::base_dir() . '/' . $host;
		if ( ! empty( $segments ) ) {
			$dir .= '/' . implode( '/', $segments );
		}

		$full = $dir . '/' . $file;

		// Final containment assertion. The segment rules below already make
		// traversal impossible, but this is the check that actually matters, so
		// it is enforced independently of them rather than trusted to them.
		if ( ! self::is_inside_base( $full ) ) {
			return null;
		}

		return $full;
	}

	/**
	 * Cache file path for the current request, or null when not representable.
	 *
	 * @return string|null
	 */
	public static function current_file_path() {
		$host = isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '';
		return self::file_path( is_string( $host ) ? $host : '', Rules::request_path(), self::current_variant() );
	}

	/**
	 * Variant suffix for the current request ('' or 'mobile').
	 *
	 * @return string
	 */
	public static function current_variant() {
		$enabled = (bool) \ABlocks\Helper::get_settings( 'perf_page_cache_mobile_variant', false );
		if ( ! $enabled ) {
			return '';
		}
		$agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
		if ( ! is_string( $agent ) || '' === $agent ) {
			return '';
		}
		return preg_match( '/Mobile|Android|iPhone|iPad|iPod|Opera Mini|IEMobile/i', $agent ) ? 'mobile' : '';
	}

	/**
	 * Write a page to the cache atomically.
	 *
	 * @param string $file Absolute target path from {@see Store::file_path()}.
	 * @param string $html Rendered HTML.
	 * @param bool   $gzip Also write a .gz sibling for gzip_static.
	 * @return bool
	 */
	public static function write( $file, $html, $gzip = true ) {
		if ( empty( $file ) || ! self::is_inside_base( $file ) ) {
			return false;
		}

		// Mirror into a persistent object cache when one exists. On multi-server
		// setups the filesystem is per-node, so a page written on one server is a
		// miss on every other; an object cache is shared. Files are still written
		// regardless, because the nginx tier can only ever serve from disk.
		self::mirror_to_object_cache( $file, $html );

		$dir = dirname( $file );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		self::protect_base_dir();

		if ( ! self::atomic_put( $file, $html ) ) {
			return false;
		}

		$gz_file = $file . '.gz';
		if ( $gzip && function_exists( 'gzencode' ) ) {
			$encoded = gzencode( $html, 6 );
			if ( false !== $encoded ) {
				// A failed .gz write is not fatal — the plain file still serves.
				self::atomic_put( $gz_file, $encoded );
			}
		} elseif ( file_exists( $gz_file ) ) {
			// Setting turned off after entries were written with a sibling: drop
			// the stale .gz, or gzip_static would keep serving old content.
			wp_delete_file( $gz_file );
		}

		return true;
	}

	/**
	 * Object-cache key for a cache file path.
	 *
	 * @param string $file Absolute path.
	 * @return string
	 */
	public static function object_key( $file ) {
		return 'page_' . md5( wp_normalize_path( (string) $file ) );
	}

	/**
	 * Copy a rendered page into the object cache, when one is persistent.
	 *
	 * A no-op without Redis/Memcached: storing pages in transients would put
	 * whole HTML documents in `wp_options` and read them back with a database
	 * query, which is slower than the filesystem it is meant to replace.
	 *
	 * @param string $file Absolute path the page was written to.
	 * @param string $html Rendered HTML.
	 * @return bool
	 */
	private static function mirror_to_object_cache( $file, $html ) {
		if ( ! CacheBackend::is_persistent() ) {
			return false;
		}

		$ttl = (int) \ABlocks\Helper::get_settings( 'perf_page_cache_ttl', 0 );
		$ttl = $ttl > 0 ? $ttl * HOUR_IN_SECONDS : DAY_IN_SECONDS;

		return CacheBackend::set( self::object_key( $file ), $html, $ttl, false );
	}

	/**
	 * Atomically write a file anywhere inside the plugin's uploads directory.
	 *
	 * Exposed so sibling features (the style consolidator) share one
	 * write-then-rename implementation rather than each rolling their own. The
	 * containment check is deliberately against the plugin's uploads root rather
	 * than the page-cache subdirectory, since callers legitimately write to
	 * neighbouring directories — but nothing outside that root.
	 *
	 * @param string $file     Absolute target path.
	 * @param string $contents Payload.
	 * @return bool
	 */
	public static function put_file( $file, $contents ) {
		$upload = new FileUpload();
		$root   = wp_normalize_path( untrailingslashit( $upload->get_upload_dir() ) );
		$path   = wp_normalize_path( (string) $file );

		if ( false !== strpos( $path, '../' ) || 0 !== strpos( $path, $root . '/' ) ) {
			return false;
		}

		return self::atomic_put( $file, $contents );
	}

	/**
	 * Delete one cache entry and its siblings.
	 *
	 * @param string $file Absolute path.
	 * @return bool
	 */
	public static function delete_file( $file ) {
		if ( empty( $file ) || ! self::is_inside_base( $file ) ) {
			return false;
		}
		$deleted = false;
		CacheBackend::delete( self::object_key( $file ) );
		foreach ( [ $file, $file . '.gz' ] as $target ) {
			if ( file_exists( $target ) ) {
				wp_delete_file( $target );
				$deleted = true;
			}
		}
		return $deleted;
	}

	/**
	 * Delete every variant cached for a URL.
	 *
	 * @param string $url Absolute or relative URL.
	 * @return bool True when at least one file was removed.
	 */
	public static function delete_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( empty( $parts ) ) {
			return false;
		}
		$host = isset( $parts['host'] ) ? $parts['host'] : ( isset( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : '' );
		$path = isset( $parts['path'] ) ? $parts['path'] : '/';

		$deleted = false;
		foreach ( [ '', 'mobile' ] as $variant ) {
			$file = self::file_path( is_string( $host ) ? $host : '', $path, $variant );
			if ( $file && self::delete_file( $file ) ) {
				$deleted = true;
			}
		}
		return $deleted;
	}

	/**
	 * Remove every cached page.
	 *
	 * @return int Files removed.
	 */
	public static function flush() {
		$base = self::base_dir();
		if ( ! is_dir( $base ) ) {
			return 0;
		}

		// Only host directories are removed. Files sitting directly in the base
		// are the guards (index.php, .htaccess) — and on Apache the .htaccess
		// carries the tier-3 serve rules, so wiping it on every purge would
		// quietly demote the site back to PHP-served caching.
		$removed = 0;
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Return value is checked; a directory vanishing mid-purge is normal and must not warn.
		$entries = @scandir( $base );
		if ( false === $entries ) {
			return 0;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $base . '/' . $entry;
			if ( is_dir( $path ) && ! is_link( $path ) ) {
				$removed += self::delete_tree( $path, true );
			}
		}

		self::protect_base_dir();
		return $removed;
	}

	/**
	 * Delete entries older than the configured TTL.
	 *
	 * @param int $ttl_hours Hours; 0 disables expiry.
	 * @return int Files removed.
	 */
	public static function prune( $ttl_hours = null ) {
		if ( null === $ttl_hours ) {
			$ttl_hours = (int) \ABlocks\Helper::get_settings( 'perf_page_cache_ttl', 0 );
		}
		$ttl_hours = (int) $ttl_hours;
		if ( $ttl_hours <= 0 ) {
			return 0;
		}

		$base = self::base_dir();
		if ( ! is_dir( $base ) ) {
			return 0;
		}

		$cutoff  = time() - ( $ttl_hours * HOUR_IN_SECONDS );
		$removed = 0;

		foreach ( self::iterate_files( $base ) as $path ) {
			if ( '.gz' === substr( $path, -3 ) ) {
				continue; // Handled with its parent.
			}
			if ( filemtime( $path ) < $cutoff ) {
				$removed += self::delete_file( $path ) ? 1 : 0;
			}
		}

		return $removed;
	}

	/**
	 * Cached-page count and total size on disk.
	 *
	 * @return array{pages:int, files:int, bytes:int}
	 */
	public static function stats() {
		$base  = self::base_dir();
		$stats = [
			'pages' => 0,
			'files' => 0,
			'bytes' => 0,
		];
		if ( ! is_dir( $base ) ) {
			return $stats;
		}
		foreach ( self::iterate_files( $base ) as $path ) {
			$stats['files']++;
			$stats['bytes'] += (int) filesize( $path );
			if ( '.html' === substr( $path, -5 ) ) {
				$stats['pages']++;
			}
		}
		return $stats;
	}

	/**
	 * Write the directory guards.
	 *
	 * The .htaccess is defence in depth only — nginx ignores it, and nginx is a
	 * first-class target here. The real protection is the rule that we only ever
	 * cache the fully-anonymous view of an already-public URL, so a leaked file
	 * contains exactly what curl on that URL already returns. See Rules.
	 */
	public static function protect_base_dir() {
		$base = self::base_dir();
		if ( ! is_dir( $base ) && ! wp_mkdir_p( $base ) ) {
			return;
		}

		$index = $base . '/index.php';
		if ( ! file_exists( $index ) ) {
			self::atomic_put( $index, "<?php\n// Silence is golden.\n" );
		}

		$htaccess = $base . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// Tier 3 on Apache replaces this file with serve rules.
			self::atomic_put( $htaccess, "# aBlocks page cache — served by PHP.\n<Files \"*.html\">\nRequire all denied\n</Files>\n" );
		}
	}

	/**
	 * Write a file atomically: temp file in the same directory, then rename.
	 *
	 * Renaming is atomic within a filesystem, so a concurrent reader sees either
	 * the old file or the new one, never a partial write. The temp file is
	 * created in the destination directory rather than the system temp dir,
	 * because rename() is only atomic when both paths share a filesystem.
	 *
	 * @param string $file     Target path.
	 * @param string $contents Payload.
	 * @return bool
	 */
	private static function atomic_put( $file, $contents ) {
		$dir = dirname( $file );
		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		$tmp = tempnam( $dir, '.ablocks-cache-' );
		if ( false === $tmp ) {
			return false;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_read_file_put_contents -- WP_Filesystem cannot write-then-rename atomically; see class docblock.
		$written = file_put_contents( $tmp, $contents, LOCK_EX );
		if ( false === $written ) {
			wp_delete_file( $tmp );
			return false;
		}

		// tempnam() creates 0600; cache files must be readable by the web server
		// user when nginx serves them directly (tier 3).
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best-effort: a failed chmod is recoverable, and a warning printed here would land inside the HTML being cached.
		@chmod( $tmp, defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644 );

		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Return value is checked; the warning is suppressed because concurrent writers racing on the same page is normal and must stay silent.
		if ( ! @rename( $tmp, $file ) ) {
			wp_delete_file( $tmp );
			return false;
		}

		return true;
	}

	/**
	 * Recursively delete a directory's contents.
	 *
	 * @param string $dir         Directory, must be inside the cache base.
	 * @param bool   $remove_self Remove $dir itself as well.
	 * @return int Files removed.
	 */
	private static function delete_tree( $dir, $remove_self = true ) {
		if ( ! is_dir( $dir ) || ! self::is_inside_base( $dir, true ) ) {
			return 0;
		}

		$removed = 0;
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Return value is checked; a directory vanishing mid-purge is normal and must not warn.
		$entries = @scandir( $dir );
		if ( false === $entries ) {
			return 0;
		}

		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if ( is_link( $path ) ) {
				// Never follow a symlink out of the cache tree.
				wp_delete_file( $path );
				$removed++;
				continue;
			}
			if ( is_dir( $path ) ) {
				$removed += self::delete_tree( $path, true );
				continue;
			}
			wp_delete_file( $path );
			$removed++;
		}

		if ( $remove_self ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Best effort: a non-empty or already-removed directory is not an error here.
			@rmdir( $dir );
		}

		return $removed;
	}

	/**
	 * Yield every file under a directory.
	 *
	 * @param string $dir Directory.
	 * @return \Generator
	 */
	private static function iterate_files( $dir ) {
		// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Return value is checked; a directory vanishing mid-purge is normal and must not warn.
		$entries = @scandir( $dir );
		if ( false === $entries ) {
			return;
		}
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$path = $dir . '/' . $entry;
			if ( is_link( $path ) ) {
				continue;
			}
			if ( is_dir( $path ) ) {
				yield from self::iterate_files( $path );
				continue;
			}
			if ( '.html' === substr( $path, -5 ) || '.gz' === substr( $path, -3 ) ) {
				yield $path;
			}
		}
	}

	/**
	 * Normalise a host for use as a directory name.
	 *
	 * HTTP_HOST is attacker-controlled, so this is strict: lowercase, and only
	 * the characters a hostname may legitimately contain. A port is kept (as
	 * `_`-joined) so :8080 dev sites do not collide with production.
	 *
	 * @param string $host Raw host.
	 * @return string Sanitised host, or '' when unusable.
	 */
	private static function sanitize_host( $host ) {
		$host = strtolower( trim( (string) $host ) );
		$host = str_replace( ':', '_', $host );
		$host = preg_replace( '/[^a-z0-9._\-]/', '', $host );
		$host = trim( (string) $host, '.-_' );
		if ( '' === $host || strlen( $host ) > 253 ) {
			return '';
		}
		return $host;
	}

	/**
	 * Split a URL path into validated directory segments.
	 *
	 * @param string $path URL path.
	 * @return string[]|null Segments, or null when the path is not representable.
	 */
	private static function sanitize_path( $path ) {
		$path = (string) $path;
		$path = (string) strtok( $path, '?' );
		$path = (string) strtok( $path, '#' );

		$raw = explode( '/', $path );
		$out = [];

		foreach ( $raw as $segment ) {
			if ( '' === $segment ) {
				continue;
			}
			$clean = self::sanitize_segment( $segment );
			if ( null === $clean ) {
				return null;
			}
			$out[] = $clean;
		}

		// Absurd depth is either an attack or a misconfiguration; either way it
		// should not create a thousand-deep directory tree.
		if ( count( $out ) > 20 ) {
			return null;
		}

		return $out;
	}

	/**
	 * Validate one path segment.
	 *
	 * Percent-decoded first, because nginx matches on its decoded $uri and the
	 * on-disk name must agree with it. Anything that could escape the tree or
	 * confuse the filesystem is rejected outright rather than rewritten —
	 * rewriting would silently map two distinct URLs onto one file.
	 *
	 * @param string $segment Raw segment.
	 * @return string|null Validated segment, or null to reject the whole path.
	 */
	private static function sanitize_segment( $segment ) {
		$segment = rawurldecode( $segment );

		if ( '' === $segment || '.' === $segment || '..' === $segment ) {
			return null;
		}
		// Null bytes, control characters, separators and Windows-reserved chars.
		if ( preg_match( '#[\x00-\x1F\x7F/\\\\:*?"<>|]#', $segment ) ) {
			return null;
		}
		// A leading dot would create hidden directories and can shadow guards
		// such as .htaccess.
		if ( '.' === $segment[0] ) {
			return null;
		}
		// Individual path components are capped well below the common 255-byte
		// filesystem limit.
		if ( strlen( $segment ) > 200 ) {
			return null;
		}

		return $segment;
	}

	/**
	 * Is a path contained by the cache base directory?
	 *
	 * Compares lexically on a normalised path so it works for files that do not
	 * exist yet (realpath() would return false for those).
	 *
	 * @param string $path      Candidate path.
	 * @param bool   $allow_base Treat the base directory itself as inside.
	 * @return bool
	 */
	private static function is_inside_base( $path, $allow_base = false ) {
		$base = wp_normalize_path( self::base_dir() );
		$path = wp_normalize_path( (string) $path );

		if ( false !== strpos( $path, '../' ) ) {
			return false;
		}
		if ( $allow_base && $path === $base ) {
			return true;
		}
		return 0 === strpos( $path, $base . '/' );
	}
}
