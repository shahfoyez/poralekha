<?php

namespace StoreEngine\Classes;

/**
 * @method array<Price> get_results()
 * @method Price next_result()
 */
class PriceCollection extends AbstractCollection {
	protected string $table = 'storeengine_product_price';

	protected string $object_type = 'price';

	protected string $primary_key = 'id';
	protected string $parent_key = 'product_id';

	protected string $orderBy = 'order'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	protected string $order      = 'ASC';
	protected string $returnType = Price::class; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase
}
