<?php
/**
 * Get by meta for collection.
 *
 * @version 1.0.0
 * @since StoreEngine v1.8.0
 */

namespace StoreEngine\Traits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait CollectionGetByMeta {
	public static function get_by_meta( string $key, $value = null ): self {
		$meta_query = [ 'key' => $key ];

		if ( ! $value ) {
			$meta_query['compare'] = 'EXISTS';
		} else if ( is_array( $value ) ) {
			$meta_query['value'] = $value;
			$meta_query['compare'] = 'IN';
		} else {
			$meta_query['value'] = $value;
			$meta_query['compare'] = '=';
		}

		return new self(
			[
				'per_page' => -1,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Generic meta lookup helper; meta_query is intrinsic to this API.
				'meta_query' => [
					'relation' => 'AND',
					$meta_query
				]
			]
		);
	}
}

// End of a file collection-get-by-meta.php.
