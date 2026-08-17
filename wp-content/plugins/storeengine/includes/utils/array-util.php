<?php

namespace StoreEngine\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ArrayUtil {
	public static function insert_assoc_on_pos( array $array, array $daa, $key, bool $after = false ): array {
		$index      = array_search( $key, $array );
		$index      = $after ? $index + 1 : $index;
		$arrayEnd   = array_splice( $array, $index );
		$arrayStart = array_splice( $array, 0, $index );

		return array_merge( $arrayStart, $daa, $arrayEnd );
	}

	/**
	 * Flatten multidimensional array.
	 *
	 * @param array $array
	 * @param bool $keys
	 *
	 * @return array
	 */
	public static function flatten( array $array, bool $keys = false ): array {
		$return = [];

		if ( $keys ) {
			array_walk_recursive( $array, function ( $a, $b ) use ( &$return ) {
				$return[ $b ] = $a;
			} );

			return $return;
		}

		array_walk_recursive( $array, function ( $a ) use ( &$return ) {
			$return[] = $a;
		} );

		return $return;
	}

	/**
	 * Sort array by priority key.
	 *
	 * @param array{priority?:int}&array $list
	 *
	 * @return void
	 * @since 1.8.0
	 */
	public static function priority_sort( array &$list ) {
		uasort( $list, fn( $a, $b ) => ( $a['priority'] ?? 0 ) <=> ( $b['priority'] ?? 0 ) );
	}

	/**
	 * Determine whether all elements in the array satisfy the given predicate.
	 *
	 * Iterates over each element and evaluates the predicate. Returns `false`
	 * immediately on the first failure (short-circuit).
	 *
	 * @template T
	 *
	 * @param array<T> $arr Input array.
	 * @param callable(T):mixed $predicate Predicate function. Should return a boolean
	 *                                     (or truthy/falsy value in non-strict mode).
	 * @param bool $strict When true, predicate must return `true` (=== true).
	 *                                     When false, truthy/falsy evaluation is used.
	 *
	 * @return bool True if all elements satisfy the predicate, otherwise false.
	 *
	 * @example
	 * // All numbers are positive
	 * ArrayUtil::every([1, 2, 3], fn($n) => $n > 0); // true
	 *
	 * @example
	 * // One element fails
	 * ArrayUtil::every([1, -1, 3], fn($n) => $n > 0); // false
	 *
	 * @example
	 * // Strict mode (must return === true)
	 * ArrayUtil::every([true, 1], fn($v) => $v, true); // false
	 *
	 * @example
	 * // Empty array → vacuously true
	 * ArrayUtil::every([], fn($v) => false); // true
	 *
	 * @since 1.8.0
	 */
	public static function every( array $arr, callable $predicate, bool $strict = false ): bool {
		foreach ( $arr as $e ) {
			$result = $predicate( $e );

			if ( $strict ) {
				if ( true !== $result ) {
					return false;
				}
			} else {
				if ( ! $result ) {
					return false;
				}
			}
		}

		return true;
	}

	/**
	 * Determine whether at least one element in the array satisfies the predicate.
	 *
	 * Iterates over each element and evaluates the predicate. Returns `true`
	 * immediately on the first match (short-circuit).
	 *
	 * @template T
	 *
	 * @param array<T> $arr Input array.
	 * @param callable(T):mixed $predicate Predicate function. Should return a boolean
	 *                                     (or truthy/falsy value in non-strict mode).
	 * @param bool $strict When true, predicate must return `true` (=== true).
	 *                                     When false, truthy/falsy evaluation is used.
	 *
	 * @return bool True if any element satisfies the predicate, otherwise false.
	 *
	 * @example
	 *  // At least one even number
	 *  ArrayUtil::any([1, 3, 4], fn($n) => $n % 2 === 0); // true
	 *
	 * @example
	 *  // No matches
	 *  ArrayUtil::any([1, 3, 5], fn($n) => $n % 2 === 0); // false
	 *
	 * @example
	 *  // Strict mode
	 *  ArrayUtil::any([1, true], fn($v) => $v, true); // true
	 *
	 * @example
	 *  // Empty array → always false
	 *  ArrayUtil::any([], fn($v) => true); // false
	 *
	 * @since 1.8.0
	 */
	public static function any( array $arr, callable $predicate, bool $strict = false ): bool {
		foreach ( $arr as $e ) {
			$result = $predicate( $e );

			if ( $strict ) {
				if ( true === $result ) {
					return true;
				}
			} else {
				if ( $result ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Determine whether no elements in the array satisfy the predicate.
	 *
	 * Equivalent to negating {@see ArrayUtil::any()}.
	 *
	 * @template T
	 *
	 * @param array<T> $arr
	 * @param callable(T):mixed $predicate
	 * @param bool $strict
	 *
	 * @return bool True if no elements satisfy the predicate, otherwise false.
	 *
	 * @since 1.8.0
	 */
	public static function none( array $arr, callable $predicate, bool $strict = false ): bool {
		return ! self::any( $arr, $predicate, $strict );
	}

	/**
	 * Alias of {@see ArrayUtil::every()}.
	 *
	 * @template T
	 *
	 * @param array<T> $arr
	 * @param callable(T):mixed $predicate
	 * @param bool $strict
	 *
	 * @return bool
	 *
	 * @since 1.8.0
	 */
	public static function all( array $arr, callable $predicate, bool $strict = false ): bool {
		return self::every( $arr, $predicate, $strict );
	}

	/**
	 * Alias of {@see ArrayUtil::any()}.
	 *
	 * @template T
	 *
	 * @param array<T> $arr
	 * @param callable(T):mixed $predicate
	 * @param bool $strict
	 *
	 * @return bool
	 *
	 * @since 1.8.0
	 */
	public static function some( array $arr, callable $predicate, bool $strict = false ): bool {
		return self::any( $arr, $predicate, $strict );
	}
}

// End of file array-util.php.
