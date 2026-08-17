<?php

namespace StoreEngine\Classes\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

use Exception;
use Throwable;
use WP_Error;

class StoreEngineNotFoundException extends StoreEngineException {
	const WP_ERROR_CODE = 'db-error-no-record';
	public function __construct( string $message, $data = null, ?Throwable $previous = null ) {
		parent::__construct( $message, 'db-error-no-record', $data, 404, $previous );
	}
}

// End of file store-engine-not-found-exception.php.
