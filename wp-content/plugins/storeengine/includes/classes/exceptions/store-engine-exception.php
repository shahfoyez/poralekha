<?php

namespace StoreEngine\Classes\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

use Exception;
use Throwable;
use WP_Error;

/**
 * WP_Error compatible exception.
 */
class StoreEngineException extends Exception {

	protected string $wp_code = '';

	protected $data = [];

	/**
	 * @var Throwable|StoreEngineException|null
	 */
	protected ?Throwable $previous = null;

	/**
	 * @param string $message
	 * @param string $wp_code
	 * @param mixed $data
	 * @param int $code
	 * @param ?Throwable $previous
	 */
	public function __construct( string $message, string $wp_code = '', $data = null, int $code = 0, ?Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );

		$this->wp_code  = $wp_code ?: sanitize_title( $this->get_class_code() );
		$this->previous = $previous;

		if ( is_numeric( $data ) && $data > 0 && ! $code ) {
			$code = $data;
			$data = [ 'status' => $data ];
		}

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
		throw new self( wp_kses_post( $message ), '', $data, $code, $previous ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}

	protected function get_class_code(): string {
		$code = str_replace( [ __NAMESPACE__ . '\\', 'StoreEngine', 'Exception' ], '', get_called_class() );
		if ( $code ) {
			preg_match_all( '/([A-Z][a-z]+|[A-Z]+(?![a-z]))/', $code, $matches );

			if ( ! empty( $matches[0] ) ) {
				return strtolower( implode( '-', $matches[0] ) );
			}
		}

		return $code ?: 'unknown-error';
	}

	public function get_wp_error_code(): string {
		return $this->wp_code;
	}

	public function get_data( ?string $key = null ) {
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

	public function set_message( string $message ) {
		if ( $this->message ) {
			// Keep a track of old messages.
			if ( ! isset( $this->data['old_message'] ) ) {
				$this->data['old_message'] = [];
			}

			$this->data['old_message'][] = $this->message;
		}

		$this->message = $message;
	}

	public function add_data( string $key, $value ) {
		$this->data[ $key ] = $value;
	}

	public function get_wp_error(): WP_Error {
		return new WP_Error( $this->wp_code, $this->getMessage(), $this->data );
	}

	/**
	 * @alias get_wp_error()
	 * @return WP_Error
	 */
	public function toWpError(): WP_Error {
		return $this->get_wp_error();
	}

	public function get_previous_data(): ?array {
		if ( ! $this->previous ) {
			return null;
		}

		if ( is_a( $this->previous, self::class ) && method_exists( $this->previous, 'get_data' ) ) {
			return $this->previous->get_data();
		}

		return null;
	}

	const WITH_MESSAGE = 1;
	const WITH_PREVIOUS = 2;
	const WITH_TRACE = 4;
	const WITH_WP_TRACE = 8;

	public function to_array( $options = null ): array {
		$data = [
			'wp_code' => $this->wp_code,
			'type'    => str_replace( 'StoreEngine\Classes\Exceptions\\', '', get_class( $this ) ),
			'data'    => $this->get_data(),
			'code'    => $this->getCode(),
			'file'    => $this->getFile(),
			'line'    => $this->getLine(),
		];

		if ( $options & self::WITH_MESSAGE ) {
			$data['message'] = $this->getMessage();
		}

		if ( $options & self::WITH_PREVIOUS ) {
			$data['previous'] = $this->previous && method_exists( $this->previous, 'to_array' ) ? $this->previous->to_array() : null;
		}

		if ( $options & self::WITH_TRACE ) {
			$data['backtrace'] = $this->getTrace();
		}

		if ( $options & self::WITH_WP_TRACE ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_wp_debug_backtrace_summary -- Serializing the WP call trace into the exception payload for structured error reporting.
			$data['backtrace'] = wp_debug_backtrace_summary( null, 0, false );
		}

		return $data;
	}

	public function __toJsonString( $flags = 0, $depth = 512 ): string {
		return wp_json_encode( $this->to_array(), $flags, $depth );
	}

	public static function from_wp_error( WP_Error $wp_error ): self {
		return new self(
			wp_kses_post( $wp_error->get_error_message() ),
			esc_html( $wp_error->get_error_code() ),
			$wp_error->get_error_data()
		);
	}

	public static function convert_exception( Throwable $exception, string $wp_code = null, int $error_code = null, $data = null ): self {
		if ( is_a( $exception, __CLASS__ ) ) {
			return new self(
				wp_kses_post( $exception->getMessage() ),
				$wp_code ?? esc_html( $exception->get_wp_error_code() ),
				$data ?? $exception->get_data(),
				$error_code ?? $exception->getCode(),
				$exception
			);
		}

		return new self(
			wp_kses_post( $exception->getMessage() ),
			$wp_code ?? 'UNKNOWN_ERROR',
			$data ?? [],
			$error_code ?? $exception->getCode(),
			$exception
		);
	}

	public static function from_stripe_api_error( \StoreEngine\Stripe\Exception\ApiErrorException $exception, string $wp_code, array $extra = null ): self {
		// phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
		$data = [
			'response' => [
				'http_status'  => esc_attr( $exception->getHttpStatus() ),
				'requestId'    => esc_attr( $exception->getRequestId() ),
				'stripeCode'   => esc_attr( $exception->getStripeCode() ),
				'body'         => $exception->getJsonBody() ?? $exception->getHttpBody(),
				'original_msg' => esc_html( (string) $exception ),
			],
		];

		if ( $extra ) {
			$data = array_merge( $extra, $data );
		}

		return new self( esc_html( $exception->getMessage() ), $wp_code, $data, $exception->getCode(), $exception );
		// phpcs:enable WordPress.Security.EscapeOutput.ExceptionNotEscaped
	}
}
