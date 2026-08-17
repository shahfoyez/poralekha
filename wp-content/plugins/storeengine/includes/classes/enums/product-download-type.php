<?php

namespace StoreEngine\Classes\enums;

/**
 * Enum class for all the product tax statuses.
 */
class ProductDownloadType {
	/**
	 * Download type for non-versioned item.
	 *
	 * @var string
	 */
	const INSTANT = 'instant';

	/**
	 * Download type for non-versioned item (via deployments).
	 *
	 * @var string
	 */
	const VERSIONED = 'versioned';
}

// End of file product-download-type.php.
