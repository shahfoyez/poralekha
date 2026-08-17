<?php
/**
 * Memory-safe ZIP helpers for the backup module.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Backup;

use StoreEngine\Classes\Exceptions\StoreEngineException;
use ZipArchive;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArchiveWriter {

	/**
	 * Recursively zip a directory (entries added via addFile so large jsonl
	 * files are streamed by the zip extension, never loaded into memory).
	 *
	 * @throws StoreEngineException
	 */
	public static function zip_dir( string $src_dir, string $dest_zip ): void {
		if ( ! class_exists( ZipArchive::class ) ) {
			throw new StoreEngineException( 'PHP ZipArchive extension is required for backups.', 'backup-no-zip' );
		}

		$src_dir = rtrim( $src_dir, '/\\' );
		$zip     = new ZipArchive();
		if ( true !== $zip->open( $dest_zip, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
			throw new StoreEngineException( 'Unable to create backup archive.', 'backup-zip-open' );
		}

		/** @var \SplFileInfo[] $iterator */
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $src_dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$path     = $item->getPathname();
			$relative = ltrim( substr( $path, strlen( $src_dir ) ), '/\\' );
			$relative = str_replace( '\\', '/', $relative );

			if ( $item->isDir() ) {
				$zip->addEmptyDir( $relative );
			} else {
				$zip->addFile( $path, $relative );
			}
		}

		$zip->close();
	}

	/**
	 * Append a directory tree into an existing zip under an internal prefix,
	 * streaming each file (no temp copy). Used for the optional "files" group so
	 * we never duplicate large uploads to disk.
	 *
	 * @param string|string[]|null $skip_real Absolute realpath(s) to subdir(s) to
	 *                                        exclude (e.g. the backups dir, or the
	 *                                        deployment versioned-files dir).
	 *
	 * @throws StoreEngineException
	 */
	public static function append_dir( string $zip_path, string $src_dir, string $internal_prefix, $skip_real = null ): void {
		if ( ! is_dir( $src_dir ) ) {
			return;
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			throw new StoreEngineException( 'Unable to open archive to append files.', 'backup-zip-append' );
		}

		$skips = array_filter( array_map(
			'strval',
			is_array( $skip_real ) ? $skip_real : [ $skip_real ]
		) );

		$src_dir = rtrim( $src_dir, '/\\' );
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $src_dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::SELF_FIRST
		);

		foreach ( $iterator as $item ) {
			$path = $item->getPathname();
			$real = (string) realpath( $path );
			foreach ( $skips as $skip ) {
				if ( str_starts_with( $real, $skip ) ) {
					continue 2; // never recurse into an excluded dir.
				}
			}
			if ( $item->isDir() ) {
				continue;
			}
			$relative = ltrim( substr( $path, strlen( $src_dir ) ), '/\\' );
			$relative = $internal_prefix . '/' . str_replace( '\\', '/', $relative );
			$zip->addFile( $path, $relative );
		}

		$zip->close();
	}

	/**
	 * Read a single entry from a zip without extracting everything (used to peek
	 * at manifest.json during import inspection).
	 */
	public static function read_entry( string $zip_path, string $entry ): ?string {
		if ( ! class_exists( ZipArchive::class ) ) {
			return null;
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			return null;
		}
		$contents = $zip->getFromName( $entry );
		$zip->close();

		return false === $contents ? null : $contents;
	}

	/**
	 * Extract an entire archive to a directory.
	 *
	 * @throws StoreEngineException
	 */
	public static function unzip( string $zip_path, string $dest_dir ): void {
		if ( ! class_exists( ZipArchive::class ) ) {
			throw new StoreEngineException( 'PHP ZipArchive extension is required for restore.', 'backup-no-zip' );
		}
		if ( ! is_dir( $dest_dir ) ) {
			wp_mkdir_p( $dest_dir );
		}
		$zip = new ZipArchive();
		if ( true !== $zip->open( $zip_path ) ) {
			throw new StoreEngineException( 'Unable to open backup archive.', 'backup-unzip-open' );
		}

		// Zip Slip hardening: never hand the raw archive to extractTo() blindly —
		// a crafted entry name (../../wp-config.php, /etc/..., phar://...) could
		// write outside the restore working directory. Validate every entry name
		// first, then extract ONLY the safe set. Because each name passed to
		// extractTo() is a containment-checked relative path, the entries can only
		// land inside $dest_dir.
		$safe = [];
		for ( $i = 0; $i < $zip->numFiles; $i ++ ) {
			$name = $zip->getNameIndex( $i );
			if ( false === $name || '' === $name ) {
				continue;
			}
			if ( ! self::is_safe_zip_entry( $name ) ) {
				$zip->close();
				throw new StoreEngineException(
					esc_html( sprintf( 'Backup archive contains an unsafe path (%s) and was rejected.', $name ) ),
					'backup-unzip-unsafe-path'
				);
			}
			$safe[] = $name;
		}

		if ( $safe && true !== $zip->extractTo( $dest_dir, $safe ) ) {
			$zip->close();
			throw new StoreEngineException( 'Failed to extract backup archive.', 'backup-unzip-extract' );
		}

		$zip->close();
	}

	/**
	 * Whether a zip entry name is safe to extract under a destination directory.
	 *
	 * Rejects absolute paths (unix `/…`, Windows `C:\…`), parent-directory
	 * traversal (`..`), stream wrappers (`phar://`, `php://`, …) and null bytes —
	 * the building blocks of a Zip Slip escape.
	 */
	protected static function is_safe_zip_entry( string $name ): bool {
		$name = str_replace( '\\', '/', $name );

		if ( '' === $name || '/' === $name[0] ) {
			return false; // Absolute (unix) path.
		}
		if ( preg_match( '#^[a-zA-Z]:#', $name ) ) {
			return false; // Absolute (Windows drive) path.
		}
		if ( false !== strpos( $name, '://' ) ) {
			return false; // Stream wrapper.
		}
		if ( false !== strpos( $name, "\0" ) ) {
			return false; // Null byte.
		}

		foreach ( explode( '/', $name ) as $segment ) {
			if ( '..' === $segment ) {
				return false; // Parent-directory traversal.
			}
		}

		return true;
	}

	/**
	 * Recursively delete a directory (cleanup of temp working dirs).
	 */
	public static function rrmdir( string $dir ): void {
		if ( ! is_dir( $dir ) ) {
			return;
		}
		$items = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ),
			\RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $items as $item ) {
			// phpcs:disable WordPress.WP.AlternativeFunctions.unlink_unlink, WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
			if ( $item->isDir() ) {
				@rmdir( $item->getPathname() );
			} else {
				@unlink( $item->getPathname() );
			}
			// phpcs:enable
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		@rmdir( $dir );
	}
}

// End of file archive-writer.php.
