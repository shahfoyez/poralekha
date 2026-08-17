<?php
/**
 * AWS s3 style Presigned URL (AWS SigV4-inspired)
 *
 * Usage:
 *   $signer = new UrlPresigner(KEY_ID, SECRET, 'auto', 'storeengine');
 *   $url = $signer->generateUrl('https://example.com/secure-downloads/file.zip', 300, 'GET');
 *
 *   // In your download endpoint (front controller), call:
 *   $ok = $signer->validateCurrentRequest();
 *   if ($ok) { ... serve file ... } else { http_response_code(403); exit; }
 */

namespace StoreEngine\Classes;

use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Utils\Constants;
use StoreEngine\Utils\Helper;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class UrlPresigner {

	private const OPTION_NAME = 'storeengine_url_presigner_keys';

	private ?string $keyId;
	private ?string $secret;

	private ?string $payload_hash;
	private string $region;
	private string $service;
	private int $maxSkewSeconds;

	protected static $instance;

	public static function init() {
		return self::get_instance();
	}

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Singleton constructor.
	 *
	 * @throws Exceptions\StoreEngineException
	 */
	protected function __construct() {
		$this->maxSkewSeconds = 300; // ±5 minutes clock skew
		$this->region         = 'auto';
		$this->service        = 'storeengine';
		$this->payload_hash   = Constants::get_constant( 'SE_URL_PRE_SIGNER_PAYLOAD_HASH' ) ?? Constants::get_constant( 'SECURE_AUTH_KEY' );

		if ( Constants::get_constant( 'SE_URL_PRE_SIGNER_KEY' ) && Constants::get_constant( 'SE_URL_PRE_SIGNER_SECRET' ) ) {
			$this->keyId  = Constants::get_constant( 'SE_URL_PRE_SIGNER_KEY' );
			$this->secret = Constants::get_constant( 'SE_URL_PRE_SIGNER_SECRET' );
		} else {
			// Get Keys.
			$keys = static::getKeys();

			// Changing below payload hash will invalidate previously signed URLs.
			$this->keyId  = $keys['active']['id'];
			$this->secret = $keys['active']['secret'];

			add_action( 'init', function () {
				// Schedule security key rotation.
				add_action( 'storeengine/url_presigner/rotate_keys', [ __CLASS__, 'rotateKeys' ] );
				add_action( 'storeengine/url_presigner/cleanup_keys', [ __CLASS__, 'cleanupKeys' ] );

				if ( ! \StoreEngine::init()->queue()->get_next( 'storeengine/url_presigner/rotate_keys', null, 'url_presigner' ) ) {
					\StoreEngine::init()->queue()->schedule_cron( time(), '0 0 1 * *', 'storeengine/url_presigner/rotate_keys', [], 'url_presigner' );
				}
			} );
		}

		if ( ! $this->payload_hash ) {
			throw new StoreEngineException( esc_html__( 'Secure URL presigning payload hash key is not defined.', 'storeengine' ), 'payload-pre-signing-hash-missing' );
		}

		if ( ! $this->keyId || ! $this->secret ) {
			throw new StoreEngineException( esc_html__( 'Secure URL presigning key-id & secret is not defined.', 'storeengine' ), 'payload-pre-signing-hash-missing' );
		}
	}

	protected static function getKeys(): array {
		$keys = get_option( self::OPTION_NAME, [] );

		if ( empty( $keys ) ) {
			$keys = [
				'active'   => self::generateKeys(),
				'previous' => null,
			];

			update_option( self::OPTION_NAME, $keys, false );
		}

		return wp_parse_args( $keys, [
			'active'   => null,
			'previous' => null,
		] );
	}

	protected static function generateKeys(): array {
		return [
			'id'         => 'SE-KI-X-' . wp_generate_password( 20, false, false ),
			'secret'     => wp_generate_password( 64, true, true ),
			'created_at' => time(),
		];
	}

	public static function rotateKeys() {
		$keys = self::getKeys();

		// Move current active -> previous
		if ( ! empty( $keys['active'] ) ) {
			$keys['previous'] = $keys['active'];
		}

		// Generate new active
		$keys['active'] = static::generateKeys();

		update_option( self::OPTION_NAME, $keys, false );

		// Cleanup previous-key after 10 minutes.
		if ( ! \StoreEngine::init()->queue()->get_next( 'storeengine/url_presigner/cleanup_keys', null, 'url_presigner' ) ) {
			\StoreEngine::init()->queue()->schedule_single( time() + ( 10 * MINUTE_IN_SECONDS ), 'storeengine/url_presigner/cleanup_keys', [], 'url_presigner' );
		}
	}

	/**
	 * Cleanup old keys (remove previous if expired).
	 */
	public static function cleanupKeys(): void {
		$keys             = self::getKeys();
		$keys['previous'] = null;

		update_option( self::OPTION_NAME, $keys, false );
	}

	/**
	 * Generate a pre-signed URL.
	 *
	 * @param string $url Absolute URL you’ll serve from (scheme+host+path).
	 * @param int $expiresIn Seconds from now (e.g., 300). Expire below `maxSkewSeconds` will not work.
	 * @param string $method GET/HEAD (avoid POST/PUT for downloads).
	 * @param array $headers Associative array of extra headers to sign (lowercase keys). e.g. ['x-se-user'=>'123']
	 * @param string|null $pinIp Optional IP to bind the URL to (signed as X-SE-IP).
	 */
	public function signUrl( string $url, int $expiresIn = 300, string $method = 'GET', array $headers = [], ?string $pinIp = null ): string {
		$parsed = $this->parseUrlStrict( $url );

		$now   = current_time( 'Ymd\THis\Z', 1 );
		$date  = substr( $now, 0, 8 ); // Ymd
		$scope = $this->credentialScope( $date );
		$qs    = $this->parseQuery( $parsed['query'] ?? '' );

		// Required SE query params (AWS-like)
		$qs['X-SE-Algorithm']  = 'SE-HMAC-SHA256';
		$qs['X-SE-Credential'] = rawurlencode( $this->keyId . '/' . $scope );
		$qs['X-SE-Date']       = $now;
		$qs['X-SE-Expires']    = (string) max( 1, (int) $expiresIn );
		//$qs['X-SE-SignedHeaders'] = 'host';

		if ( $pinIp ) {
			$qs['X-SE-IP'] = $pinIp;
		}

		// Canonical headers (we sign host by default; you can add more)
		$signedHeaders = [ 'host' => strtolower( $parsed['host'] ) ];
		foreach ( $headers as $k => $v ) {
			$k                   = strtolower( $k );
			$signedHeaders[ $k ] = trim( (string) $v );
		}
		$qs['X-SE-SignedHeaders'] = implode( ';', array_keys( $signedHeaders ) );

		// Build canonical request
		$canonicalRequest = $this->canonicalRequest(
			strtoupper( $method ),
			$this->canonicalUri( $parsed['path'] ?? '/' ),
			$this->canonicalQuery( $qs, /*excludeSig*/ true ),
			$this->canonicalHeaders( $signedHeaders ),
			implode( ';', array_keys( $signedHeaders ) )
		);

		// String to sign
		$stringToSign = $this->stringToSign( $now, $scope, $canonicalRequest );

		// Signature
		$signingKey = $this->deriveSigningKey( $date );
		$signature  = hash_hmac( 'sha256', $stringToSign, $signingKey );

		$qs['X-SE-Signature'] = $signature;

		// Rebuild URL
		return $parsed['scheme'] . '://' . $parsed['host']
			   . ( isset( $parsed['port'] ) ? ':' . $parsed['port'] : '' )
			   . $this->canonicalUri( $parsed['path'] ?? '/' )
			   . '?' . $this->canonicalQuery( $qs, /*excludeSig*/ false );
	}

	/**
	 * Validate the current HTTP request against the signature.
	 * Call this from your secure download endpoint before serving the file.
	 */
	public function validateCurrentRequest() {
		// Build target URL from current request.
		$scheme = ( ! empty( $_SERVER['HTTPS'] ) && $_SERVER['HTTPS'] !== 'off' ) ? 'https' : 'http';
		$host   = wp_unslash( $_SERVER['HTTP_HOST'] ?? 'localhost' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		// Strip fragment
		$uri    = strtok( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ), '#' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$method = wp_unslash( $_SERVER['REQUEST_METHOD'] ?? 'GET' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$parsed = $this->parseUrlStrict( $scheme . '://' . $host . $uri );
		$qs     = $this->parseQuery( $parsed['query'] ?? '' );

		// Required Query Params.
		$params = [
			'X-SE-Algorithm',
			'X-SE-Credential',
			'X-SE-Date',
			'X-SE-Expires',
			'X-SE-SignedHeaders',
			'X-SE-Signature',
		];

		// Required params
		foreach ( $params as $param ) {
			if ( ! isset( $qs[ $param ] ) ) {
				return new WP_Error( 'missing-sig-params', __( 'This download link is no longer valid.', 'storeengine' ) );
			}
		}

		if ( $qs['X-SE-Algorithm'] !== 'SE-HMAC-SHA256' ) {
			return new WP_Error( 'invalid-sig-algo', __( 'This download link is no longer valid.', 'storeengine' ) );
		}

		// Check expiration & clock skew
		$requestTime = $this->parseAmzDatetime( $qs['X-SE-Date'] ); // returns UNIX timestamp
		if ( $requestTime === null ) {
			return new WP_Error( 'missing-sig-date', __( 'This download link is no longer valid.', 'storeengine' ) );
		}

		$expiresIn = (int) $qs['X-SE-Expires'];
		$now       = time();

		if ( ( $now + $this->maxSkewSeconds ) > ( $requestTime + $expiresIn ) ) {
			// expired
			return new WP_Error( 'url-expired', __( 'Download link has expired. Please request a new one.', 'storeengine' ) );
		}

		if ( ( $requestTime - $this->maxSkewSeconds ) > $now ) {
			// signed in the future beyond skew
			return new WP_Error( 'url-expired', __( 'Download link has expired. Please request a new one.', 'storeengine' ) );
		}

		// Optional IP pinning
		if ( ! empty( $qs['X-SE-IP'] ) ) {
			$clientIp = Helper::get_user_ip();
			if ( $clientIp !== $qs['X-SE-IP'] ) {
				return new WP_Error( 'invalid-remote-ip', sprintf(
				// translators: %s. User's IP (REMOTE_ADDR/HTTP_X_REAL_IP);
					__( 'Access denied from this location (%s).', 'storeengine' ),
					$clientIp
				) );
			}
		}

		// Validate credential scope date/region/service
		$credential = rawurldecode( $qs['X-SE-Credential'] );
		// expected format: <keyId>/<date>/<region>/<service>/SE-Secure-Request
		$parts = explode( '/', $credential );

		if ( count( $parts ) !== 5 || $parts[0] !== $this->keyId || $parts[4] !== 'SE-Secure-Request' ) {
			return new WP_Error( 'sig-parts-mismatched', __( 'This download link is no longer valid.', 'storeengine' ) );
		}

		[ $keyId, $date, $region, $service, $term ] = $parts;

		if ( $region !== $this->region || $service !== $this->service ) {
			return new WP_Error( 'invalid-sig-service-region', __( 'This download link is no longer valid.', 'storeengine' ) );
		}


		// Rebuild signed headers from request
		$signedHeaderNames = explode( ';', strtolower( $qs['X-SE-SignedHeaders'] ) );
		$signedHeaders     = [];

		foreach ( $signedHeaderNames as $h ) {
			if ( $h === '' ) {
				continue;
			}
			if ( $h === 'host' ) {
				$signedHeaders['host'] = strtolower( $parsed['host'] );
			} else {
				// Pull from HTTP_* server vars
				$key = 'HTTP_' . strtoupper( str_replace( '-', '_', $h ) );
				if ( ! isset( $_SERVER[ $key ] ) ) {
					// header missing -> fail
					return false;
				}
				$signedHeaders[ $h ] = trim( $_SERVER[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Using for signature validation, not for rendering.
			}
		}

		// Build canonical request (exclude signature)
		$qsForSigning = $qs;
		unset( $qsForSigning['X-SE-Signature'] );

		$canonicalRequest = $this->canonicalRequest(
			strtoupper( $method ),
			$this->canonicalUri( $parsed['path'] ?? '/' ),
			$this->canonicalQuery( $qsForSigning, true ),
			$this->canonicalHeaders( $signedHeaders ),
			implode( ';', array_keys( $signedHeaders ) )
		);

		$scope        = $this->credentialScope( $date );
		$stringToSign = $this->stringToSign( gmdate( 'Ymd\THis\Z', $requestTime ), $scope, $canonicalRequest );
		$signingKey   = $this->deriveSigningKey( $date );
		$expectedSig  = hash_hmac( 'sha256', $stringToSign, $signingKey );

		if ( ! hash_equals( $expectedSig, $qs['X-SE-Signature'] ) ) {
			return new WP_Error( 'invalid-sig', __( 'Signature verification failed.', 'storeengine' ) );
		}

		return true;
	}

	// ===== Helpers =====

	private function parseUrlStrict( string $url ): array {
		$p = wp_parse_url( $url );
		if ( ! $p || empty( $p['scheme'] ) || empty( $p['host'] ) ) {
			throw new \InvalidArgumentException( 'URL must include scheme and host.' );
		}
		if ( ! isset( $p['path'] ) ) {
			$p['path'] = '/';
		}

		return $p;
	}

	private function parseQuery( string $query ): array {
		$out = [];
		if ( $query !== '' ) {
			foreach ( explode( '&', $query ) as $pair ) {
				if ( $pair === '' ) {
					continue;
				}

				[
					$key,
					$value,
				] = array_pad( explode( '=', $pair, 2 ), 2, '' );

				$out[ rawurldecode( $key ) ] = rawurldecode( $value );
			}
		}

		return $out;
	}

	private function canonicalUri( string $path ): string {
		// Normalize each segment like AWS (double-encode reserved chars)
		$segments = explode( '/', $path );
		$enc      = array_map( fn( $s ) => implode( '%20', array_map( 'rawurlencode', explode( ' ', $s ) ) ), $segments );

		return implode( '/', $enc ) ?: '/';
	}

	private function canonicalQuery( array $params, bool $excludeSig ): string {
		if ( $excludeSig ) {
			unset( $params['X-SE-Signature'] );
		}
		ksort( $params, SORT_STRING );
		$pairs = [];
		foreach ( $params as $k => $v ) {
			$pairs[] = rawurlencode( (string) $k ) . '=' . rawurlencode( (string) $v );
		}

		return implode( '&', $pairs );
	}

	private function canonicalHeaders( array $headers ): string {
		ksort( $headers, SORT_STRING );
		$lines = [];
		foreach ( $headers as $k => $v ) {
			$v       = preg_replace( '/\s+/', ' ', trim( (string) $v ) );
			$lines[] = strtolower( $k ) . ':' . $v;
		}

		return implode( "\n", $lines ) . "\n";
	}

	private function canonicalRequest( string $method, string $uri, string $query, string $headers, string $signedHeaders ): string {
		return implode( "\n", [
			$method,
			$uri,
			$query,
			$headers,
			$signedHeaders,
			hash( 'sha256', $this->payload_hash ),
		] );
	}

	private function stringToSign( string $amzDatetime, string $scope, string $canonicalRequest ): string {
		return "SE-HMAC-SHA256\n" .
			   $amzDatetime . "\n" .
			   $scope . "\n" .
			   hash( 'sha256', $canonicalRequest );
	}

	private function credentialScope( string $date ): string {
		// <date>/<region>/<service>/SE-Secure-Request  (AWS-like)
		return $date . '/' . $this->region . '/' . $this->service . '/SE-Secure-Request';
	}

	private function deriveSigningKey( string $date ): string {
		// AWS-like key ladder: kDate = HMAC("STOREENGINE4".$secret, date) ...
		$kSecret = 'STOREENGINE4' . $this->secret;
		$kDate   = hash_hmac( 'sha256', $date, $kSecret, true );
		$kRegion = hash_hmac( 'sha256', $this->region, $kDate, true );
		$kSvc    = hash_hmac( 'sha256', $this->service, $kRegion, true );

		return hash_hmac( 'sha256', 'SE-Secure-Request', $kSvc, true );
	}

	private function parseAmzDatetime( string $s ): ?int {
		// Expect: YYYYMMDDTHHMMSSZ
		if ( ! preg_match( '/^\d{8}T\d{6}Z$/', $s ) ) {
			return null;
		}

		$dt = \DateTime::createFromFormat( 'Ymd\THis\Z', $s, new \DateTimeZone( 'UTC' ) );

		return $dt ? $dt->getTimestamp() : null;
	}

	/**
	 * Cloning is forbidden.
	 */
	public function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'storeengine' ), '0.0.4' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances of this class is forbidden.', 'storeengine' ), '0.0.4' );
	}
}

// End of file url-presigner.php.
