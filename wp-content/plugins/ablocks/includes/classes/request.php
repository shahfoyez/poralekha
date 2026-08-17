<?php
namespace ABlocks\Classes;

use Exception;
/**
 * @class Request
 */
class Request {
	private string $base_url;
	private array $headers = [];

	public function __construct( string $base_url = '', array $headers = [] ) {
		$this->base_url = $base_url;
		$this->headers = $headers;
	}

	public function get( string $pathOrQuery = '' ) {
		return $this->send( $pathOrQuery );
	}

	public function post( string $pathOrQuery = '', $body = null ) {
		return $this->send( $pathOrQuery, 'POST', $body );
	}

	public function put( string $pathOrQuery = '', $body = null ) {
		return $this->send( $pathOrQuery, 'PUT', $body );
	}

	public function send( string $pathOrQuery = '', string $method = 'GET', $body = null ) {
		$args = [
			'method' => $method,
			'headers' => $this->headers,
		];
		// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
		if ( in_array( $method, [ 'PUT', 'POST' ] ) && $body ) {
			$args['body'] = $body;
		}

		$url = $this->base_url . $pathOrQuery;

		if ( empty( $url ) ) {
			return false;
		}

		$res = wp_remote_request( $url, $args );
		return [
			'error' => is_wp_error( $res ),
			'status_code' => intval( wp_remote_retrieve_response_code( $res ) ),
			'body' => json_decode( wp_remote_retrieve_body( $res ), true ),
			'result' => $res,
		];
	}

}
