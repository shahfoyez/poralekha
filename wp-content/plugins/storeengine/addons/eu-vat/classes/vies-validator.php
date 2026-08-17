<?php
/**
 * VIES / HMRC validator.
 *
 * Why three transports
 * ────────────────────
 * VIES exposes a SOAP service. SoapClient is the cleanest path but is missing
 * on hosts that don't enable the SOAP PHP extension. cURL is universally
 * available; file_get_contents is the bare-minimum fallback for hosts that
 * disable both. We try them in that order.
 *
 * UK numbers (GB...) — VIES dropped UK after Brexit, so we route those to
 * HMRC's free public lookup endpoint (no auth needed for the lookup-only
 * endpoint).
 *
 * Result shape
 * ────────────
 * [
 *   'valid'       => bool,
 *   'country'     => 'DE',
 *   'number'      => '811569869',
 *   'name'        => 'SAP SE'      // when returned by VIES/HMRC, optional
 *   'address'     => '...'          // when returned, optional
 *   'method'      => 'soap'|'curl'|'file_get_contents'|'hmrc',
 *   'error'       => string|null,   // populated on transport failure
 *   'request_id'  => string|null,   // VIES request identifier when available
 *   'checked_at'  => int            // unix timestamp
 * ]
 *
 * @package StoreEngine\Addons\EuVat
 */

namespace StoreEngine\Addons\EuVat\Classes;

use SoapClient;
use SoapFault;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ViesValidator {

	const VIES_WSDL = 'https://ec.europa.eu/taxation_customs/vies/services/checkVatService.wsdl';
	const VIES_URL  = 'https://ec.europa.eu/taxation_customs/vies/services/checkVatService';
	const HMRC_URL  = 'https://api.service.hmrc.gov.uk/organisations/vat/check-vat-number/lookup/';

	public function validate( VatNumber $vat ): array {
		if ( 'GB' === $vat->country ) {
			return $this->validate_hmrc( $vat );
		}

		// Try SOAP first, then cURL, then file_get_contents.
		foreach ( [ 'soap', 'curl', 'file_get_contents' ] as $method ) {
			$result = $this->try_method( $method, $vat );
			if ( null !== $result ) {
				return $result;
			}
		}

		return $this->error_result( $vat, 'all_methods_failed', 'soap' );
	}

	private function try_method( string $method, VatNumber $vat ): ?array {
		switch ( $method ) {
			case 'soap':
				return class_exists( '\SoapClient' ) ? $this->validate_soap( $vat ) : null;
			case 'curl':
				return function_exists( 'curl_init' ) ? $this->validate_curl( $vat ) : null;
			case 'file_get_contents':
				return $this->validate_fgc( $vat );
		}
		return null;
	}

	private function validate_soap( VatNumber $vat ): ?array {
		try {
			$client = new SoapClient( self::VIES_WSDL, [
				'connection_timeout' => 15,
				'exceptions'         => true,
			] );
			$response = $client->checkVat( [
				'countryCode' => $vat->country,
				'vatNumber'   => $vat->number,
			] );

			return [
				'valid'      => (bool) ( $response->valid ?? false ),
				'country'    => $vat->country,
				'number'     => $vat->number,
				'name'       => isset( $response->name )    ? trim( (string) $response->name )    : '',
				'address'    => isset( $response->address ) ? trim( (string) $response->address ) : '',
				'method'     => 'soap',
				'error'      => null,
				'request_id' => null,
				'checked_at' => time(),
			];
		} catch ( SoapFault $e ) {
			return null; // Fall through to next transport.
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	private function validate_curl( VatNumber $vat ): ?array {
		$envelope = $this->soap_envelope( $vat );
		$response = wp_remote_post( self::VIES_URL, [
			'headers'   => [
				'Content-Type' => 'text/xml; charset=utf-8',
				'SOAPAction'   => '""',
			],
			'body'      => $envelope,
			'timeout'   => 30,
			'sslverify' => apply_filters( 'storeengine/eu_vat/curl_disable_ssl', false ) ? false : true,
		] );

		if ( is_wp_error( $response ) ) {
			return null;
		}

		$body = wp_remote_retrieve_body( $response );
		if ( '' === $body ) {
			return null;
		}

		return $this->parse_vies_xml( $body, $vat, 'curl' );
	}

	private function validate_fgc( VatNumber $vat ): ?array {
		$envelope = $this->soap_envelope( $vat );
		$context  = stream_context_create( [
			'http' => [
				'method'  => 'POST',
				'header'  => "Content-Type: text/xml; charset=utf-8\r\nSOAPAction: \"\"\r\n",
				'content' => $envelope,
				'timeout' => 30,
			],
		] );
		$body = @file_get_contents( self::VIES_URL, false, $context );

		if ( false === $body ) {
			return null;
		}

		return $this->parse_vies_xml( $body, $vat, 'file_get_contents' );
	}

	private function validate_hmrc( VatNumber $vat ): array {
		$response = wp_remote_get( self::HMRC_URL . rawurlencode( $vat->number ), [
			'timeout' => 15,
			'headers' => [ 'Accept' => 'application/vnd.hmrc.2.0+json' ],
		] );

		if ( is_wp_error( $response ) ) {
			return $this->error_result( $vat, $response->get_error_message(), 'hmrc' );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== (int) $code ) {
			return [
				'valid'      => false,
				'country'    => $vat->country,
				'number'     => $vat->number,
				'name'       => '',
				'address'    => '',
				'method'     => 'hmrc',
				'error'      => null,
				'request_id' => null,
				'checked_at' => time(),
			];
		}

		$target = $body['target'] ?? [];
		$name   = $target['name'] ?? '';
		$lines  = $target['address']['line1'] ?? '';

		return [
			'valid'      => ! empty( $target ),
			'country'    => $vat->country,
			'number'     => $vat->number,
			'name'       => is_string( $name ) ? trim( $name ) : '',
			'address'    => is_string( $lines ) ? trim( $lines ) : '',
			'method'     => 'hmrc',
			'error'      => null,
			'request_id' => null,
			'checked_at' => time(),
		];
	}

	private function soap_envelope( VatNumber $vat ): string {
		return '<?xml version="1.0" encoding="UTF-8"?>'
			. '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/" xmlns:urn="urn:ec.europa.eu:taxud:vies:services:checkVat:types">'
			. '<soapenv:Header/><soapenv:Body><urn:checkVat>'
			. '<urn:countryCode>' . $vat->country . '</urn:countryCode>'
			. '<urn:vatNumber>' . $vat->number . '</urn:vatNumber>'
			. '</urn:checkVat></soapenv:Body></soapenv:Envelope>';
	}

	private function parse_vies_xml( string $xml, VatNumber $vat, string $method ): ?array {
		// VIES wraps fields with namespace prefixes; strip them for simpler extraction.
		$clean = preg_replace( '/(<\/?)[a-zA-Z0-9]+:/', '$1', $xml );
		$root  = @simplexml_load_string( $clean );
		if ( false === $root ) {
			return null;
		}

		$valid = (string) ( $root->Body->checkVatResponse->valid ?? '' );
		if ( '' === $valid ) {
			return null;
		}

		return [
			'valid'      => 'true' === $valid,
			'country'    => $vat->country,
			'number'     => $vat->number,
			'name'       => isset( $root->Body->checkVatResponse->name )    ? trim( (string) $root->Body->checkVatResponse->name )    : '',
			'address'    => isset( $root->Body->checkVatResponse->address ) ? trim( (string) $root->Body->checkVatResponse->address ) : '',
			'method'     => $method,
			'error'      => null,
			'request_id' => null,
			'checked_at' => time(),
		];
	}

	private function error_result( VatNumber $vat, string $error, string $method ): array {
		return [
			'valid'      => false,
			'country'    => $vat->country,
			'number'     => $vat->number,
			'name'       => '',
			'address'    => '',
			'method'     => $method,
			'error'      => $error,
			'request_id' => null,
			'checked_at' => time(),
		];
	}
}
