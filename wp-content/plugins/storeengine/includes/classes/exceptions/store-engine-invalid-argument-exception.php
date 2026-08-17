<?php

namespace StoreEngine\Classes\Exceptions;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

class StoreEngineInvalidArgumentException extends StoreEngineException {
	/**
	 * Create a new invalid argument exception with a standardized text.
	 *
	 * @param int $position The argument position in the function signature. 1-based.
	 * @param string $name The argument name in the function signature.
	 * @param string|int|float|string[]|int[]|float[] $expected The argument type expected as a string.
	 * @param string|int|float $received The actual argument type received.
	 *
	 * @return StoreEngineInvalidArgumentException
	 */
	public static function create( int $position, string $name, $expected, $received ): StoreEngineInvalidArgumentException {
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace
		$stack  = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 );
		$caller = [
			'name' => 'anonymous-caller',
			'file' => 'unknown',
		]; // Stack 0 is self.

		if ( ! empty( $stack[1] ) ) {
			if ( ! empty( $stack[1]['class'] ) ) {
				$caller['name'] = sprintf( '%s::%s()', $stack[1]['class'], $stack[1]['function'] );
			} else {
				$caller['name'] = sprintf( '%s()', $stack[1]['function'] );
			}

			$caller['file'] = sprintf( 'file:: %s[L:%s]', $stack[1]['file'], $stack[1]['line'] );
		}

		$expected = is_array( $expected ) ? Helper::implode_with( $expected, 'or' ) : $expected;
		if ( ! is_scalar( $expected ) ) {
			// If expected value is not numeric/string, get type of the expected value.
			$expected = gettype( $expected );
		}

		if ( ! is_scalar( $received ) ) {
			$received = gettype( $received );
		}

		return new self(
			sprintf( '%s: Argument #%d (%s) must be (of type) %s, %s given on %s.', $caller['name'], $position, $name, $expected, $received, $caller['file'] ),
			'invalid-argument',
			[
				'received' => $received,
				'expected' => $expected,
			],
			400 // Can be 422 (Unprocessable Entity).
		);
	}
}

// End of file store-engine-invalid-argument-exception.php.
