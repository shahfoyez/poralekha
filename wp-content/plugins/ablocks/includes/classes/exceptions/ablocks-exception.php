<?php

namespace ABlocks\Classes\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

use Exception;
use Throwable;
use WP_Error;

/**
 * WP_Error compatible exception.
 */
class AblocksException extends Exception {

	protected string $wp_code = '';

	protected $data = [];

	/**
	 * @var Throwable|AblocksException|null
	 */
	protected ?Throwable $previous = null;

	/**
	 * @param string     $message
	 * @param string     $wp_code
	 * @param mixed      $data
	 * @param int        $code
	 * @param ?Throwable $previous
	 */
	public function __construct( string $message, string $wp_code = '', $data = null, int $code = 0, ?Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );

		$this->wp_code  = $wp_code ?: $this->get_class_code();
		$this->previous = $previous;

		if ( ! $data ) {
			$data = [];
		}

		if ( empty( $data['status'] ) ) {
			$data['status'] = $code ? $code : 500;
		}

		$this->data = $data;
	}

	/**
	 * @throws self
	 */
	public static function throw( string $message, $data = null, int $code = 0, ?Throwable $previous = null ) {
		// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped 
		throw new self( $message, '', $data, $code, $previous );
	}

	protected function get_class_code(): string {
		$code = str_replace( [ __NAMESPACE__, 'ABlocks', 'Exception' ], '', __CLASS__ );
		return $code ?: 'UNKNOWN-ERROR';
	}

	public function get_wp_error_code(): string {
		return $this->wp_code;
	}

	public function get_data( string $key = null ) {
		if ( $key ) {
			if ( isset( $this->data[ $key ] ) ) {
				return $this->data[ $key ];
			}

			return null;
		}

		return $this->data;
	}

	public function set_data( array $data ) {
		$this->data = $data;
	}

	public function add_data( string $key, $value ) {
		$this->data[ $key ] = $value;
	}

	public function get_wp_error(): WP_Error {
		return $this->toWpError();
	}

	public function toWpError(): WP_Error {
		return new WP_Error( $this->wp_code, $this->getMessage(), $this->data );
	}

	public function get_previous_data(): ?array {

		if ( ! $this->previous ) {
			return null;
		}

		if ( is_a( $this->previous, self::class ) ) {
			return $this->previous->__toArray();
		}

		return [
			'code'    => $this->previous,
			'type'    => get_class( $this->previous ),
			'message' => $this->previous->getMessage(),
			'data'    => null,
			'trace'   => $this->previous->getTrace(),
		];
	}

	public function __toArray(): array {
		return [
			'code'     => $this->wp_code,
			'type'     => get_class( $this ),
			'message'  => $this->getMessage(),
			'data'     => $this->data,
			'trace'    => $this->getTrace(),
			'previous' => $this->get_previous_data(),
		];
	}

	public function __toJsonString( $flags = 0, $depth = 512 ): string {
		return wp_json_encode( $this->__toArray(), $flags, $depth );
	}

	public static function from_wp_error( WP_Error $wp_error ): self {
		return new self( $wp_error->get_error_message(), $wp_error->get_error_code(), $wp_error->get_error_data() );
	}
}
