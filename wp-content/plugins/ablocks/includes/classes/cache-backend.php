<?php
namespace ABlocks\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Shared cache backend for the Performance Suite.
 *
 * Wraps the object cache, falling back to transients, and — more importantly —
 * lets a feature ask whether caching is actually worth doing before it starts.
 *
 * ## Why the distinction matters
 *
 * Without a persistent object cache (Redis, Memcached), `wp_cache_*` is
 * per-request memory only and transients are rows in `wp_options`, so every
 * read is a database round trip. That is fine when the cached work is
 * expensive, and actively harmful when it is not.
 *
 * Measured here on the block template cache: resolving templates costs 7–10
 * queries, but caching the result in transients produced *more* queries than it
 * saved — 81 per request cached versus 75 uncached — because each lookup adds
 * its own option reads and has to unserialize large WP_Block_Template objects.
 * With a persistent object cache the same lookup is an out-of-process fetch
 * with no database involvement, and the trade flips.
 *
 * So features that cache cheap-but-frequent work call
 * {@see CacheBackend::is_persistent()} and disable themselves when it returns
 * false, rather than assuming a cache is free.
 */
class CacheBackend {

	const GROUP = 'ablocks_perf';

	/**
	 * Is a persistent (cross-request, out-of-process) object cache available?
	 *
	 * @return bool
	 */
	public static function is_persistent() {
		return function_exists( 'wp_using_ext_object_cache' ) && wp_using_ext_object_cache();
	}

	/**
	 * Read a value.
	 *
	 * @param string $key          Cache key.
	 * @param bool   $use_fallback Fall back to a transient when no object cache exists.
	 * @return mixed|false
	 */
	public static function get( $key, $use_fallback = true ) {
		if ( self::is_persistent() ) {
			$found = false;
			$value = wp_cache_get( $key, self::GROUP, false, $found );
			if ( $found ) {
				return $value;
			}
			return false;
		}

		return $use_fallback ? get_transient( self::GROUP . '_' . $key ) : false;
	}

	/**
	 * Write a value.
	 *
	 * @param string $key          Cache key.
	 * @param mixed  $value        Payload.
	 * @param int    $ttl          Lifetime in seconds.
	 * @param bool   $use_fallback Fall back to a transient when no object cache exists.
	 * @return bool
	 */
	public static function set( $key, $value, $ttl, $use_fallback = true ) {
		$ttl = max( 60, (int) $ttl );

		if ( self::is_persistent() ) {
			return (bool) wp_cache_set( $key, $value, self::GROUP, $ttl );
		}

		return $use_fallback ? (bool) set_transient( self::GROUP . '_' . $key, $value, $ttl ) : false;
	}

	/**
	 * Delete a value.
	 *
	 * @param string $key Cache key.
	 * @return bool
	 */
	public static function delete( $key ) {
		if ( self::is_persistent() ) {
			return (bool) wp_cache_delete( $key, self::GROUP );
		}
		return (bool) delete_transient( self::GROUP . '_' . $key );
	}

	/**
	 * Read a monotonic generation counter.
	 *
	 * Stored autoloaded so it costs no query on a normal page load. Bumping it
	 * invalidates every key built from it, which is one write regardless of how
	 * many entries exist and cannot half-complete the way a scan-and-delete can.
	 *
	 * @param string $option Option name.
	 * @return int
	 */
	public static function generation( $option ) {
		return (int) get_option( $option, 0 );
	}

	/**
	 * Advance a generation counter.
	 *
	 * @param string $option Option name.
	 * @return void
	 */
	public static function bump_generation( $option ) {
		update_option( $option, self::generation( $option ) + 1, true );
	}
}
