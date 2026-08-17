<?php

namespace StoreEngine\Classes;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use stdClass;
use StoreEngine\Classes\Exceptions\StoreEngineInvalidArgumentException;
use WP_Meta_Query;
use wpdb;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

#[\AllowDynamicProperties]
abstract class AbstractCollection implements IteratorAggregate, Countable {

	protected string $table = '';

	protected string $object_type = 'data';

	protected string $meta_type = '';

	protected string $hook_prefix = '';

	protected string $primary_key = 'ID';

	protected string $orderBy = ''; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	protected string $order = 'DESC';

	protected string $parent_key = 'parent';

	protected string $menu_order = 'menu_order';

	protected bool $returnNative; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	protected string $returnType = OBJECT; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase

	protected bool $cache_query = true;

	/**
	 * Table name without db prefix.
	 *
	 * wp_storeengine_orders         -> storeengine_orders
	 * wp_storeengine_product_price  -> storeengine_product_price
	 * wp_storeengine_orders         -> storeengine_orders
	 * wp_storeengine_licenses       -> storeengine_licenses
	 * wp_storeengine_payment_tokens -> storeengine_payment_tokens
	 *
	 * @var string|null
	 */
	protected ?string $cache_group = null;

	protected ?string $global_variable_name = null;

	/**
	 * Stores the ->query_vars state like md5(serialize( $this->query_vars ) ) so we know
	 * whether we have to re-parse because something has changed
	 *
	 * @var bool|string
	 */
	private $query_vars_hash = false;

	protected ?array $results = null;

	protected int $result_count = 0;

	/**
	 * Index of the current item in the loop.
	 *
	 * @var int
	 */
	public int $current_result = - 1;

	/**
	 * Whether the caller is before the loop.
	 *
	 * @var bool
	 */
	public bool $before_loop = true;

	/**
	 * Whether the loop has started and the caller is in the loop.
	 *
	 * @var bool
	 */
	public bool $in_the_loop = false;

	/**
	 * The current result.
	 *
	 *  This property does not get populated when the `fields` argument is set to
	 *  `ids` or `id=>parent`.
	 *
	 * @var mixed
	 */
	protected $result = null;

	protected int $found_results = 0;

	protected int $max_num_pages = 0;

	protected ?int $page = null;

	protected ?int $per_page = null;

	protected ?bool $nopaging = null;

	/**
	 * Signifies whether the current query is for a single post.
	 *
	 * @var bool
	 */
	public bool $is_single = false;

	/**
	 * SQL for the database query.
	 *
	 * @var string
	 */
	public string $request;

	public ?array $query = null;

	protected array $query_vars = [];

	protected array $must_where = [];

	protected bool $need_setup = true;

	/**
	 * @var wpdb
	 */
	protected $wpdb;

	/**
	 * Constructor.
	 *
	 * Sets up the WordPress query, if parameter is not empty.
	 *
	 * @param string|array $query URL query string or array of vars.
	 *
	 * @throws StoreEngineInvalidArgumentException
	 * @see \WP_Query::parse_query() for all available arguments.
	 */
	public function __construct( $query = '' ) {
		$this->setup();

		if ( ! empty( $query ) ) {
			$this->query( $query );
		}
	}

	final protected function setup() {
		global $wpdb;

		if ( ! $this->need_setup ) {
			return;
		}

		$this->need_setup = false;
		$this->wpdb       = $wpdb;

		$nativeTypes = [ OBJECT, ARRAY_A ];
		$this->returnNative = in_array( strtoupper( $this->returnType ), $nativeTypes, true ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$this->returnType   = ! $this->returnNative ? $this->returnType : strtoupper( $this->returnType ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		$table              = str_replace( $wpdb->prefix, '', $this->table );
		$this->object_type  = $this->object_type ?: trim( str_replace( 'storeengine_', '', $table ) );
		$this->hook_prefix  = 'storeengine/collection/' . str_replace( '_', '/', $table );
		$this->table        = $wpdb->prefix . $table;

		if ( ! $this->cache_group ) {
			$this->cache_group = $table;
		}

		if ( ! $this->global_variable_name ) {
			$this->global_variable_name = trim( str_replace( [ '-' ], '_', $this->object_type ) );
		}
	}

	/**
	 * Sets up the query by parsing query string.
	 *
	 * @param string|array $query URL query string or array of query arguments.
	 *
	 * @throws StoreEngineInvalidArgumentException
	 * @see WP_Query::parse_query() for all available arguments.
	 */
	public function query( $query ) {
		$this->init();
		$this->query      = wp_parse_args( $query );
		$this->query_vars = $this->query;

		$this->prepare_results();
	}

	/**
	 * Initiates object properties and sets default values.
	 */
	public function init() {
		unset( $this->results );
		unset( $this->query );
		$this->query_vars     = [];
		$this->result_count   = 0;
		$this->current_result = - 1;
		$this->in_the_loop    = false;
		$this->before_loop    = true;
		unset( $this->request );
		unset( $this->result );
		$this->found_results = 0;
		$this->max_num_pages = 0;

		// init flags
		$this->is_single = false;
	}

	/**
	 * Reparses the query vars.
	 */
	public function parse_query_vars() {
		$this->parse_query();
	}

	/**
	 * Fills in the query variables, which do not exist within the parameter.
	 *
	 * @param array $query_vars Defined query variables.
	 *
	 * @return array Complete query variables with undefined ones filled in empty.
	 */
	public function fill_query_vars( array $query_vars ): array {
		$keys = [ 'error', $this->primary_key, 'fields', $this->menu_order ];

		foreach ( $keys as $key ) {
			if ( ! isset( $query_vars[ $key ] ) ) {
				$query_vars[ $key ] = '';
			}
		}

		if ( ! isset( $query_vars['where'] ) ) {
			$query_vars['where'] = [];
		}

		if ( ! is_array( $query_vars['where'] ) ) {
			$query_vars['where'] = [];
		}

		return $query_vars;
	}

	protected function get_default_per_page(): int {
		$per_page = get_option( 'posts_per_page', 10 );
		$per_page = apply_filters_deprecated( "storeengine/$this->object_type/collection/per_page", [ $per_page ], '1.6.9', $this->hook_prefix . '/per_page' );
		$per_page = apply_filters( $this->hook_prefix . '/per_page', $per_page ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

		return absint( $per_page );
	}

	protected function parse_query( $query = '' ) {
		if ( ! empty( $query ) ) {
			$this->init();
			$this->query = wp_parse_args( $query );
		} elseif ( ! isset( $this->query ) ) {
			$this->query = $this->query_vars;
		}

		$this->query = wp_parse_args( $this->query, [
			$this->primary_key => '',
			'fields'           => '',
			'count'            => '',
			'per_page'         => $this->get_default_per_page(),
			'page'             => 1,
			'offset'           => null,
			'where'            => [],
			'no_found_rows'    => false,
			'orderby'          => $this->orderBy ?: $this->primary_key,
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			'order'            => $this->order,
			'suppress_filters' => false,
		] );
		$this->query_vars = $this->fill_query_vars( $this->query );

		if ( ! empty( $this->must_where ) ) {
			$this->query_vars['where'] = array_merge( $this->must_where, $this->query_vars['where'] );
		}

		$args                     = &$this->query_vars;
		$this->query_vars_changed = true;

		if ( 'count' === $args['fields'] ) {
			$args['count'] = true;
		}

		if ( $args['count'] && 'count' !== $args['fields'] ) {
			$args['fields'] = 'count';
			$args['count']  = true;
		}

		if ( $args['count'] ) {
			$args[ $this->primary_key ] = '';
			$args['per_page']           = - 1;
			$args['no_found_rows']      = true;
		}

		if ( ! is_scalar( $args[ $this->primary_key ] ) || (int) $args[ $this->primary_key ] < 0 ) {
			$args[ $this->primary_key ] = 0;
			$args['error']              = '404';
		} elseif ( $args[ $this->primary_key ] ) {
			$args[ $this->primary_key ] = (int) $args[ $this->primary_key ];
		}

		if ( $args[ $this->primary_key ] ) {
			$this->is_single = true;
		}
	}

	/**
	 * Retrieves the value of a query variable.
	 *
	 * @since 1.6.9
	 *
	 * @param string $query_var     Query variable key.
	 * @param mixed  $default_value Optional. Value to return if the query variable is not set.
	 *                              Default empty string.
	 * @return mixed Contents of the query variable.
	 */
	public function get( string $query_var, $default_value = '' ) {
		if ( isset( $this->query_vars[ $query_var ] ) ) {
			return $this->query_vars[ $query_var ];
		}

		return $default_value;
	}

	/**
	 * Sets the value of a query variable.
	 *
	 * @since 1.6.9
	 *
	 * @param string $query_var Query variable key.
	 * @param mixed  $value     Query variable value.
	 */
	public function set( string $query_var, $value ) {
		$this->query_vars[ $query_var ] = $value;
	}

	/**
	 * @return AbstractEntity[]|int[]|array[]|object[]|stdClass[]|null
	 */
	public function get_results(): ?array {
		return $this->results;
	}

	/**
	 * Prepare results, build & execute query.
	 *
	 * If object mapping configured, it will try to map raw db data into php class instance too.
	 *
	 * @return AbstractEntity[]|int[]|array[]|object[]|stdClass[]
	 * @throws StoreEngineInvalidArgumentException
	 */
	protected function prepare_results(): array {
		global $wpdb;

		$this->parse_query();

		/**
		 * Fires after the query variable object is created, but before the actual query is run.
		 *
		 * Note: If using conditional tags, use the method versions within the passed instance
		 * (e.g. $this->is_main_query() instead of is_main_query()). This is because the functions
		 * like is_main_query() test against the global $wp_query instance, not the passed one.
		 *
		 * @param self $query The WP_Query instance (passed by reference).
		 */
		do_action_ref_array( $this->hook_prefix . '/pre_get_results', [ &$this ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		do_action_deprecated( $this->cache_group . '_pre_get_results', [ &$this ], '1.6.9', $this->hook_prefix . '/pre_get_results' );

		// Shorthand.
		$args = &$this->query_vars;

		// Fill again in case 'pre_get_posts' unset some vars.
		$args = $this->fill_query_vars( $args );

		$this->meta_query = new WP_Meta_Query();
		$this->meta_query->parse_query_vars( $args );

		$args['suppress_filters'] = (bool) ( $args['suppress_filters'] ?? false );
		$args['no_found_rows']    = (bool) ( $args['no_found_rows'] ?? false );

		// Set a flag if a 'pre_get_posts' hook changed the query vars.
		$hash = md5( maybe_serialize( $this->query_vars ) );
		if ( $hash !== $this->query_vars_hash ) {
			$this->query_vars_changed = true;
			$this->query_vars_hash    = $hash;
		}

		unset( $hash );

		// First let's clear some variables.
		$page             = max( 1, absint( $args['page'] ?? ( $args['paged'] ?? 1 ) ) );
		$args['per_page'] = intval( $args['per_page'] ?? $this->get_default_per_page() );
		$args['nopaging'] = - 1 === $args['per_page'];

		// If true, forcibly turns off SQL_CALC_FOUND_ROWS even when limits are present.
		if ( isset( $query_vars['no_found_rows'] ) ) {
			$args['no_found_rows'] = (bool) $query_vars['no_found_rows'];
		} else {
			$args['no_found_rows'] = false;
		}

		$this->page     = $page;
		$this->per_page = $args['per_page'];
		$this->nopaging = $args['nopaging'];

		// Prepare the query.
		$distinct = '';
		$where    = '';
		$limits   = $this->get_limit_sql( $args, $page );
		$join     = '';
		$groupby  = '';
		$orderby  = $this->get_orderby_sql( $args );

		$allFields = "{$this->table}.*";

		switch ( $args['fields'] ) {
			case 'ids':
				$fields = "{$this->table}.$this->primary_key";
				break;
			case 'count':
				$fields = "COUNT({$this->table}.$this->primary_key)";
				break;
			case 'id=>parent':
				$fields = "{$this->table}.$this->primary_key, {$this->table}.{$this->parent_key}";
				break;
			default:
				$fields = "{$this->table}.*";
		}

		if ( ! empty( $args[ $this->primary_key ] ) ) {
			$args['where'][] = [
				'key'     => $this->primary_key,
				'value'   => $args[ $this->primary_key ],
				'compare' => '=',
				'type'    => 'UNSIGNED',
			];
		}

		if ( ! empty( $args['where'] ) && is_array( $args['where'] ) ) {
			$where = trim( $this->generate_conditions( $args['where'], $params ) );
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- query prepared above.
			$where = $wpdb->prepare( $where, $params );
			// phpcs:disable WordPress.DB.PreparedSQL.NotPrepared -- query prepared above.
		}

		if ( $this->meta_type && ! empty( $this->meta_query->queries ) ) {
			$groupby = "{$this->table}.{$this->primary_key}";
			$clauses = $this->meta_query->get_sql( $this->meta_type, $this->table, $this->primary_key, $this );
			$join   .= $clauses['join'];
			$where  .= $clauses['where'];
		}

		$pieces = [ 'where', 'groupby', 'join', 'orderby', 'distinct', 'fields', 'limits' ];

		/*
		 * Apply post-paging filters on where and join. Only plugins that
		 * manipulate paging queries should use these hooks.
		 */
		if ( ! $args['suppress_filters'] ) {
			/**
			 * Filters the WHERE clause of the query.
			 *
			 * Specifically for manipulating paging queries.
			 *
			 * @param string $where The WHERE clause of the query.
			 * @param self $query The self instance (passed by reference).
			 */
			$where = apply_filters_ref_array( "{$this->hook_prefix}/where_paged", [ $where, &$this ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

			/**
			 * Filters the GROUP BY clause of the query.
			 *
			 * @param string $groupby The GROUP BY clause of the query.
			 * @param self $query The self instance (passed by reference).
			 */
			$groupby = apply_filters_ref_array( "{$this->hook_prefix}/groupby", [ $groupby, &$this ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

			/**
			 * Filters the JOIN clause of the query.
			 *
			 * Specifically for manipulating paging queries.
			 *
			 * @param string $join The JOIN clause of the query.
			 * @param self $query The self instance (passed by reference).
			 */
			$join = apply_filters_ref_array( "{$this->hook_prefix}/join_paged", [ $join, &$this ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

			/**
			 * Filters the ORDER BY clause of the query.
			 *
			 * @param string $orderby The ORDER BY clause of the query.
			 * @param self $query The self instance (passed by reference).
			 */
			$orderby = apply_filters_ref_array( "{$this->hook_prefix}/orderby", [ $orderby, &$this ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

			/**
			 * Filters the DISTINCT clause of the query.
			 *
			 * @param string $distinct The DISTINCT clause of the query.
			 * @param self $query The self instance (passed by reference).
			 */
			$distinct = apply_filters_ref_array( "{$this->hook_prefix}/distinct", [ $distinct, &$this ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

			/**
			 * Filters the LIMIT clause of the query.
			 *
			 * @param string $limits The LIMIT clause of the query.
			 * @param self $query The self instance (passed by reference).
			 */
			$limits = apply_filters_ref_array( "{$this->hook_prefix}/limits", [ $limits, &$this ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

			/**
			 * Filters the SELECT clause of the query.
			 *
			 * @param string $fields The SELECT clause of the query.
			 * @param self $query The self instance (passed by reference).
			 */
			$fields = apply_filters_ref_array( "{$this->hook_prefix}/fields", [ $fields, &$this ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

			/**
			 * Filters all query clauses at once, for convenience.
			 *
			 * Covers the WHERE, GROUP BY, JOIN, ORDER BY, DISTINCT,
			 * fields (SELECT), and LIMIT clauses.
			 *
			 * @param string[] $clauses {
			 *     Associative array of the clauses for the query.
			 *
			 * @type string $where The WHERE clause of the query.
			 * @type string $groupby The GROUP BY clause of the query.
			 * @type string $join The JOIN clause of the query.
			 * @type string $orderby The ORDER BY clause of the query.
			 * @type string $distinct The DISTINCT clause of the query.
			 * @type string $fields The SELECT clause of the query.
			 * @type string $limits The LIMIT clause of the query.
			 * }
			 *
			 * @param self $query The self instance (passed by reference).
			 */
			$clauses = (array) apply_filters_ref_array( "{$this->hook_prefix}/clauses", [ compact( $pieces ), &$this ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

			$where    = $clauses['where'] ?? '';
			$groupby  = $clauses['groupby'] ?? '';
			$join     = $clauses['join'] ?? '';
			$orderby  = $clauses['orderby'] ?? '';
			$distinct = $clauses['distinct'] ?? '';
			$fields   = $clauses['fields'] ?? '';
			$limits   = $clauses['limits'] ?? '';
		}

		if ( ! empty( $groupby ) ) {
			$groupby = 'GROUP BY ' . $groupby;
		}
		if ( ! empty( $orderby ) ) {
			$orderby = 'ORDER BY ' . $orderby;
		}

		$found_rows = '';
		if ( ! $args['no_found_rows'] && ! empty( $limits ) ) {
			$found_rows = 'SQL_CALC_FOUND_ROWS';
		}

		$where_clause = $where ? 'WHERE 1=1 AND ' . $where : '';

		/**
		 * Beginning of the string is on a new line to prevent leading whitespace.
		 *
		 * The additional indentation of subsequent lines is to ensure the SQL
		 * queries are identical to those generated when splitting queries. This
		 * improves caching of the query by ensuring the same cache key is
		 * generated for the same database queries functionally.
		 *
		 * See https://core.trac.wordpress.org/ticket/56841.
		 * See https://github.com/WordPress/wordpress-develop/pull/6393#issuecomment-2088217429
		 *
		 * @noinspection SqlConstantExpression
		 */
		$old_request =
			"SELECT $found_rows $distinct $fields
					 FROM {$this->table} $join
					 {$where_clause}
					 $groupby
					 $orderby
					 $limits;";

		if ( 'count' === $args['fields'] ) {
			$count_field = str_replace( 'COUNT(', 'COUNT(' . $distinct . ' ', $fields );
			$old_request =
				"SELECT $found_rows $count_field
					 FROM {$this->table} $join
					 {$where_clause}
					 $groupby
					 $orderby
					 $limits;";
		}

		$this->request = $old_request;

		if ( ! $args['suppress_filters'] ) {
			/**
			 * Filters the completed SQL query before sending.
			 *
			 * @param string $request The complete SQL query.
			 * @param self $query The WP_Query instance (passed by reference).
			 */
			$this->request = apply_filters_ref_array( "{$this->hook_prefix}/request", [ $this->request, &$this ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		}

		/**
		 * Filters the posts array before the query takes place.
		 *
		 * Return a non-null value to bypass WordPress' default post queries.
		 *
		 * Filtering functions that require pagination information are encouraged to set
		 * the `found_posts` and `max_num_pages` properties of the WP_Query object,
		 * passed to the filter by reference. If WP_Query does not perform a database
		 * query, it will not have enough information to generate these values itself.
		 *
		 * @param object[]|array[]|int[]|null $posts Return an array of result data to short-circuit WP's query,
		 *                                    or null to allow WP to run its normal queries.
		 * @param self $query The WP_Query instance (passed by reference).
		 */
		$this->results = apply_filters_ref_array( "{$this->hook_prefix}/pre_query", [ null, &$this ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

		/*
		 * Ensure the ID database query is able to be-cached.
		 *
		 * Random queries are expected to have unpredictable results and
		 * cannot be cached. Note the space before `RAND` in the string
		 * search, that to ensure against a collision with another
		 * function.
		 *
		 * If `$fields` has been modified by the `posts_fields`,
		 * `posts_fields_request`, `post_clauses` or `posts_clauses_request`
		 * filters, then caching is disabled to prevent caching collisions.
		 */
		$id_query_is_cacheable = ! str_contains( strtoupper( $orderby ), ' RAND(' );

		$cacheable_field_values = [
			"{$this->table}.*",
			"{$this->table}.{$this->primary_key}",
			"COUNT({$this->table}.$this->primary_key)",
		];

		if ( ! in_array( $fields, $cacheable_field_values, true ) ) {
			$id_query_is_cacheable = false;
		}

		$cache_key   = '';
		$cache_found = false;

		if ( $this->cache_query && $id_query_is_cacheable ) {
			$new_request = str_replace( $fields, "{$this->table}.*", $this->request );
			$cache_key   = $this->generate_cache_key( $args, $new_request );

			if ( null === $this->results ) {
				$cached_results = wp_cache_get( $cache_key, $this->cache_group . '-queries', false, $cache_found );

				if ( $cached_results ) {
					$result_ids = array_map( 'intval', $cached_results['results'] );

					$this->result_count  = count( $result_ids );
					$this->found_results = $cached_results['found_results'];
					$this->max_num_pages = $cached_results['max_num_pages'];

					if ( 'ids' === $args['fields'] || 'count' === $args['fields'] ) {
						$this->results = $result_ids;

						return $this->results;
					} elseif ( 'id=>parent' === $args['fields'] ) {
						$this->_prime_result_parent_id_caches( $result_ids );

						$result_parent_cache_keys = [];
						foreach ( $result_ids as $item_id ) {
							$result_parent_cache_keys[] = $this->object_type . '_parent:' . $item_id;
						}

						/** @var int[] $result_parents */
						$result_parents = wp_cache_get_multiple( $result_parent_cache_keys, $this->cache_group );

						foreach ( $result_parents as $cache_key => $result_parent ) {
							$obj                      = new stdClass();
							$obj->ID                  = (int) str_replace( $this->object_type . '_parent:', '', $cache_key );
							$obj->{$this->parent_key} = (int) $result_parent;

							$this->results[] = $obj;
						}

						return $result_parents;
					} else {
						$this->_prime_item_caches( $result_ids );

						$this->results = [];
						foreach ( $result_ids as $result ) {
							$this->results[] = $this->map_result( $result );
						}
					}
				}
			}
		}

		if ( 'count' === $args['fields'] ) {
			if ( null === $this->results ) {
				// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- query prepared above.
				$this->results = $wpdb->get_col( $this->request );
				// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- query prepared above.
			}

			$this->results      = array_map( 'intval', $this->results );
			$this->result_count = count( $this->results );
			$this->set_found_results( $args, $limits );

			if ( $this->cache_query && $id_query_is_cacheable ) {
				$cache_value = [
					'results'       => $this->results,
					'found_results' => 0,
					'max_num_pages' => 0,
				];

				wp_cache_set( $cache_key, $cache_value, $this->cache_group . '-queries' );
			}

			return $this->results;
		}

		if ( 'ids' === $args['fields'] ) {
			if ( null === $this->results ) {
				// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- query prepared above.
				$this->results = $wpdb->get_col( $this->request );
				// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- query prepared above.
			}

			$this->results      = array_map( 'intval', $this->results );
			$this->result_count = count( $this->results );
			$this->set_found_results( $args, $limits );

			if ( $this->cache_query && $id_query_is_cacheable ) {
				$cache_value = [
					'results'       => $this->results,
					'found_results' => $this->found_results,
					'max_num_pages' => $this->max_num_pages,
				];

				wp_cache_set( $cache_key, $cache_value, $this->cache_group . '-queries' );
			}

			return $this->results;
		}

		if ( 'id=>parent' === $args['fields'] ) {
			if ( null === $this->results ) {
				// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- query prepared above.
				$this->results = $wpdb->get_results( $this->request );
				// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- query prepared above.
			}

			$this->result_count = count( $this->results );
			$this->set_found_results( $args, $limits );

			/** @var int[] $result_parents */
			$result_parents       = [];
			$result_ids           = [];
			$result_parents_cache = [];

			foreach ( $this->results as $key => $item ) {
				$this->results[ $key ]->{$this->primary_key} = (int) $item->{$this->primary_key};
				$this->results[ $key ]->{$this->parent_key}  = (int) $item->{$this->parent_key};

				$result_parents[ (int) $item->{$this->primary_key} ] = (int) $item->{$this->parent_key};
				$result_ids[]                                        = (int) $item->{$this->primary_key};

				$result_parents_cache[ $this->cache_group . '_parent:' . $item->{$this->primary_key} ] = (int) $item->{$this->parent_key};
			}

			// Prime post parent caches, so that on second run, there is not another database query.
			wp_cache_add_multiple( $result_parents_cache, $this->cache_group );

			if ( $this->cache_query && $id_query_is_cacheable ) {
				$cache_value = [
					'results'       => $result_ids,
					'found_results' => $this->found_results,
					'max_num_pages' => $this->max_num_pages,
				];

				wp_cache_set( $cache_key, $cache_value, $this->cache_group . '-queries' );
			}

			return $result_parents;
		}

		$is_unfiltered_query = $old_request === $this->request && $allFields === $fields;

		if ( null === $this->results ) {
			$split_the_query = ( $is_unfiltered_query && ( wp_using_ext_object_cache() || ( ! empty( $limits ) && $args['per_page'] < 500 ) ) );

			/**
			 * Filters whether to split the query.
			 *
			 * Splitting the query will cause it to fetch just the IDs of the found posts
			 * (and then individually fetch each post by ID), rather than fetching every
			 * complete row at once. One massive result vs. many small results.
			 *
			 * @param bool $split_the_query Whether or not to split the query.
			 * @param self $query The WP_Query instance.
			 * @param string $old_request The complete SQL query before filtering.
			 * @param string[] $clauses {
			 *     Associative array of the clauses for the query.
			 *
			 * @type string $where The WHERE clause of the query.
			 * @type string $groupby The GROUP BY clause of the query.
			 * @type string $join The JOIN clause of the query.
			 * @type string $orderby The ORDER BY clause of the query.
			 * @type string $distinct The DISTINCT clause of the query.
			 * @type string $fields The SELECT clause of the query.
			 * @type string $limits The LIMIT clause of the query.
			 * }
			 */
			$split_the_query = apply_filters( "{$this->hook_prefix}/split_query", $split_the_query, $this, $old_request, compact( $pieces ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

			if ( $split_the_query ) {
				// First get the IDs and then fill in the objects.

				$where_clause = $where ? 'WHERE 1=1 AND ' . $where : '';

				/**
				 * Beginning of the string is on a new line to prevent leading whitespace. See https://core.trac.wordpress.org/ticket/56841.
				 *
				 * @noinspection SqlConstantExpression
				 */
				$this->request =
					"SELECT $found_rows $distinct {$this->table}.{$this->primary_key}
					 FROM {$this->table} $join
					 {$where_clause}
					 $groupby
					 $orderby
					 $limits;";

				/**
				 * Filters the Post IDs SQL request before sending.
				 *
				 * @param string $request The post ID request.
				 * @param self $query The WP_Query instance.
				 */
				$this->request = apply_filters( "{$this->hook_prefix}/request_ids", $this->request, $this ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

				// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- query prepared above.
				$result_ids = $wpdb->get_col( $this->request );
				// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- query prepared above.

				if ( $result_ids ) {
					$this->results = $result_ids;
					$this->set_found_results( $args, $limits );
					$this->_prime_item_caches( $result_ids );
				} else {
					$this->results = [];
				}
			} else {
				// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- query prepared above.
				$this->results = $wpdb->get_results( $this->request );
				// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- query prepared above.
				$this->set_found_results( $args, $limits );
			}
		}

		$raw_results = $this->results;
		// Convert to Entity objects.
		if ( $this->results ) {
			$results = [];
			foreach ( $this->results as $result ) {
				if ( is_scalar( $result ) ) {
					$results[] = $this->map_result( absint( $result ) );
				} else {
					$results[] = $this->map_result( $result );
				}
			}

			$this->results = $results;
		}

		if ( $this->cache_query && $id_query_is_cacheable && ! $cache_found ) {
			$result_ids = $raw_results;
			if (
				! empty( $raw_results ) &&
				(
					! empty( $raw_results[0] ) &&
					(
						( is_object( $raw_results[0] ) && isset( $raw_results[0]->{$this->primary_key} ) ) ||
						( is_array( $raw_results[0] ) && isset( $raw_results[0][ $this->primary_key ] ) )
					)
				)
			) {
				$result_ids = wp_list_pluck( $raw_results, $this->primary_key );
			}

			$cache_value = [
				'results'       => $result_ids,
				'found_results' => $this->found_results,
				'max_num_pages' => $this->max_num_pages,
			];

			wp_cache_set( $cache_key, $cache_value, $this->cache_group . '-queries' );
		}

		if ( $this->results ) {
			$this->result_count = count( $this->results );
			$this->result       = reset( $this->results );
		} else {
			$this->results      = [];
			$this->result_count = 0;
		}

		return $this->results;
	}

	protected function get_return_type( $result ) {
		if ( ! $this->returnNative && class_exists( $this->returnType ) ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			return $this->returnType; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.PropertyNotSnakeCase, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}

		return false;
	}

	protected function map_result( $result ) {
		$className = $this->get_return_type( $result );

		return $className ? new $className( $result ) : $result;
	}

	/**
	 * Sets up the amount of found posts and the number of pages (if limit clause was used)
	 * for the current query.
	 *
	 * @param array $args Query variables.
	 * @param string $limits LIMIT clauses of the query.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	final protected function set_found_results( array $args, string $limits ) {
		global $wpdb;

		/*
		 * Bail if posts is an empty array. Continue if posts is an empty string,
		 * null, or false to accommodate caching plugins that fill posts later.
		 */
		if ( $args['no_found_rows'] || ( is_array( $this->results ) && ! $this->results ) ) {
			return;
		}

		if ( ! empty( $limits ) ) {
			/**
			 * Filters the query to run for retrieving the found posts.
			 *
			 * @param string $found_posts_query The query to run to find the found posts.
			 * @param self $query The self instance (passed by reference).
			 */
			$found_results_query = apply_filters_ref_array( "{$this->hook_prefix}/found_results_query", [ 'SELECT FOUND_ROWS()', &$this ] );  // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

			// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- query prepared and cached.
			$this->found_results = (int) $wpdb->get_var( $found_results_query );
			// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared -- query prepared and cached.
		} else {
			if ( is_array( $this->results ) ) {
				$this->found_results = count( $this->results );
			} else {
				if ( null === $this->results ) {
					$this->found_results = 0;
				} else {
					$this->found_results = 1;
				}
			}
		}

		/**
		 * Filters the number of found posts for the query.
		 *
		 * @param int $found_posts The number of posts found.
		 * @param self $query The self instance (passed by reference).
		 */
		$this->found_results = (int) apply_filters_ref_array( "{$this->hook_prefix}/found_results", [ // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
			$this->found_results,
			&$this
		] );

		if ( ! empty( $limits ) ) {
			$this->max_num_pages = (int) ceil( $this->found_results / $args['per_page'] );
		}
	}

	/**
	 * Sets up the next post and iterate current post index.
	 *
	 * @return mixed Next post.
	 */
	public function next_result() {
		++ $this->current_result;

		$this->result = $this->results[ $this->current_result ] ?? null;

		return $this->result;
	}

	/**
	 * Sets up the current post.
	 *
	 * Retrieves the next post, sets up the post, sets the 'in the loop'
	 * property to true.
	 *
	 * @global mixed $item Global post object.
	 */
	public function the_result() {
		if ( ! $this->global_variable_name ) {
			return;
		}
		if ( isset( $GLOBALS[ $this->global_variable_name ] ) ) {
			unset( $GLOBALS[ $this->global_variable_name ] );
		}

		if ( ! $this->in_the_loop ) {
			// Only prime the post cache for queries limited to the ID field.
			$item_ids = array_filter( $this->results, 'is_numeric' );
			// Exclude any falsey values, such as 0.
			$item_ids = array_filter( $item_ids );
			if ( $item_ids ) {
				$this->_prime_item_caches( $item_ids );
			}
		}

		$this->in_the_loop = true;
		$this->before_loop = false;

		if ( - 1 === $this->current_result ) { // Loop has just started.
			/**
			 * Fires once the loop is started.
			 *
			 * @param self $query The WP_Query instance (passed by reference).
			 */
			do_action_ref_array( $this->hook_prefix . '/loop_start', [ &$this ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
			do_action_deprecated( $this->cache_group . '_loop_start', [ &$this ], '1.6.9', $this->hook_prefix . '/loop_start' );
		}

		$GLOBALS[ $this->global_variable_name ] = $this->next_result();  // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variable contains prefixed string.
		$this->setup_result_data( $GLOBALS[ $this->global_variable_name ] );
	}

	/**
	 * After looping through a nested query, this function
	 * restores the $post global to the current post in this query.
	 *
	 * @global object $post Global post object.
	 */
	public function reset_result_data() {
		if ( ! empty( $this->result ) ) {
			$GLOBALS[ $this->cache_group . '_result' ] = $this->result; // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- variable contains prefixed string.
			$this->setup_result_data( $this->result );
		}
	}

	/**
	 * Sets up global result data.
	 *
	 * @param object|int $result WP_Post instance or Post ID/object.
	 *
	 * @return true True when finished.
	 */
	public function setup_result_data( $result ): bool {
		/**
		 * Fires once the result data has been set up.
		 *
		 * @param object $result The Post object (passed by reference).
		 * @param self $query The current Query object (passed by reference).
		 */
		do_action_ref_array( $this->hook_prefix . '/the_result', [ &$result, &$this ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
		do_action_deprecated( $this->cache_group . '_the_result', [ &$this ], '1.6.9', $this->hook_prefix . '/the_result' );

		return true;
	}

	/**
	 * Determines whether there are more posts available in the loop.
	 *
	 * Calls the {@see 'loop_end'} action when the loop is complete.
	 *
	 * @return bool True if posts are available, false if end of the loop.
	 */
	public function have_results(): bool {
		if ( $this->current_result + 1 < $this->result_count ) {
			return true;
		} elseif ( $this->current_result + 1 === $this->result_count && $this->result_count > 0 ) {
			/**
			 * Fires once the loop has ended.
			 *
			 * @param self $query The WP_Query instance (passed by reference).
			 */
			do_action_ref_array( $this->hook_prefix . '/loop_end', [ &$this ] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
			do_action_deprecated( $this->cache_group . '_loop_end', [ &$this ], '1.6.9', $this->hook_prefix . '/loop_end' );
			// Do some cleaning up after the loop.
			$this->rewind_results();
		} elseif ( 0 === $this->result_count ) {
			$this->before_loop = false;

			/**
			 * Fires if no results are found in a post query.
			 *
			 * @param self $query The WP_Query instance.
			 */
			do_action_ref_array( $this->hook_prefix . '/loop_no_results', [&$this] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
			do_action_deprecated( $this->cache_group . '_loop_no_results', [ &$this ], '1.7.1', $this->hook_prefix . '/loop_no_results' );
		}

		$this->in_the_loop = false;

		return false;
	}

	/**
	 * Rewinds the posts and resets post index.
	 */
	public function rewind_results() {
		$this->current_result = - 1;
		$this->result         = null;
		if ( $this->result_count > 0 ) {
			$this->result = $this->results[0];
		}
	}

	final protected function get_limit_sql( array $args = [], int $page = 1 ): string {
		if ( empty( $args['nopaging'] ) ) {
			$page = max( 1, absint( $page ) );

			// If 'offset' is provided, it takes precedence over 'paged'.
			if ( isset( $args['offset'] ) && is_numeric( $args['offset'] ) ) {
				$args['offset'] = absint( $args['offset'] );
				$pgstrt         = $args['offset'] . ', ';
			} else {
				$pgstrt = absint( ( $page - 1 ) * $args['per_page'] ) . ', ';
			}

			return 'LIMIT ' . $pgstrt . $args['per_page'];
		}

		return '';
	}

	final protected function get_orderby_sql( array $args = [] ): string {
		if ( ! empty( $args['orderby'] ) && 'none' !== $args['orderby'] ) {
			if ( in_array( strtoupper( $args['order'] ), [ 'ASC', 'DESC' ], true ) ) {
				$args['order'] = strtoupper( $args['order'] );
			} else {
				$args['order'] = 'DESC';
			}

			$orderby = urldecode( $args['orderby'] );

			if ( str_contains( $orderby, '.' ) ) {
				$parts   = explode( '.', $orderby, 2 );
				$orderby = sprintf( '%s`%s`', $parts[0], $parts[1] );
			} else {
				$orderby = sprintf( '`%s`', $args['orderby'] );
			}

			$orderby = wp_slash( $orderby );

			return $orderby . ' ' . $args['order'];
		}

		return '';
	}

	/**
	 * @throws StoreEngineInvalidArgumentException
	 */
	protected function generate_conditions( $conditions, &$params = [] ): string {
		global $wpdb;

		if ( ! is_array( $conditions ) ) {
			throw new StoreEngineInvalidArgumentException(
				sprintf( 'Where args (conditions) must be an array %s given.', esc_html( gettype( $conditions ) ) ),
				'invalid-where-args',
				[
					'conditions' => $conditions, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					'collection' => get_class( $this ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
				]
			);
		}

		// Allow single where.
		if ( isset( $conditions['key'], $conditions['value'] ) ) {
			$conditions = [
				'relation' => $conditions['relation'] ?? 'AND',
				[
					'key'     => $conditions['key'],
					'value'   => $conditions['value'],
					'compare' => $conditions['compare'] ?? '=',
					'type'    => $conditions['type'] ?? null,
				],
			];
		}

		// Get relation if it's named
		$relation = 'AND';

		if ( isset( $conditions['relation'] ) ) {
			$relation = strtoupper( $conditions['relation'] );
			unset( $conditions['relation'] );
		}

		$queryParts = [];

		foreach ( $conditions as $condition ) {
			if ( isset( $condition['key'] ) ) {
				// Normalize
				$key     = $this->table . '.' . $condition['key'];
				$value   = $this->normalize_value( $condition['value'] ?? '' );
				$compare = isset( $condition['compare'] ) ? strtoupper( $condition['compare'] ) : '=';
				$type    = isset( $condition['type'] ) ? strtoupper( $condition['type'] ) : null;

				// Cast key if needed
				if ( $type ) {
					if ( 'NUMERIC' === $type ) {
						$type = 'SIGNED';
					}
					if ( 'STRING' === $type ) {
						$type = 'CHAR';
					}

					$allowedTypes = [ 'BINARY', 'CHAR', 'DATE', 'DATETIME', 'DECIMAL', 'SIGNED', 'UNSIGNED', 'TIME' ];

					if ( ! in_array( $type, $allowedTypes, true ) ) {
						throw new StoreEngineInvalidArgumentException(
							'Unsupported cast type (' . esc_html( $type ) . ') in condition.',
							'unsupported-cast-type',
							[
								'condition'  => $condition, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
								'collection' => get_class( $this ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
							]
						);
					}

					$key = "CAST($key AS $type)";
				}

				switch ( $compare ) {
					case 'IN':
					case 'NOT IN':
						if ( ! is_array( $value ) || empty( $value ) ) {
							throw new StoreEngineInvalidArgumentException(
								'Comparing with `' . esc_html( $compare ) . '` needs a non-empty array as value, ' . esc_html( gettype( $value ) ) . ' given.',
								'unsupported-value',
								[
									'condition'  => $condition, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
									'collection' => get_class( $this ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
								]
							);
						}

						$placeholders = implode( ', ', array_fill( 0, count( $value ), is_numeric( current( $value ) ) ? '%d' : '%s' ) );
						$queryParts[] = "$key $compare ($placeholders)";

						foreach ( $value as $val ) {
							$params[] = $val;
						}
						break;

					case 'BETWEEN':
					case 'NOT BETWEEN':
						if ( ! is_array( $value ) || count( $value ) !== 2 ) {
							if ( ! is_array( $value ) ) {
								$type = gettype( $value );
							} else {
								$count = count( $value );
								$type  = $count . ( 1 === $count ? ' value' : ' values' );
							}

							throw new StoreEngineInvalidArgumentException(
								'Comparing with `' . esc_html( $compare ) . '` requires an array of exactly two values, ' . esc_html( $type ) . ' given.',
								'unsupported-value',
								[
									'condition'  => $condition, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
									'collection' => get_class( $this ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
								]
							);
						}

						$queryParts[] = "$key $compare %s AND %s";
						$params[]     = $value[0];
						$params[]     = $value[1];
						break;

					case 'LIKE':
					case 'NOT LIKE':
						$queryParts[] = "$key $compare %s";
						$params[]     = implode( '%', array_map( fn( $v ) => $v ? $wpdb->esc_like( $v ) : '', explode( '%', $value ) ) );
						break;

//					case 'IS NULL':
//					case 'IS NOT NULL':
//						$queryParts[] = "$key $compare";
//						break;

					case 'IS NOT NULL':
					case 'EXISTS':
						$queryParts[] = "$key IS NOT NULL";
						break;

					case 'IS NULL':
					case 'NOT EXISTS':
						$queryParts[] = "$key IS NULL";
						break;

					default:
						if ( ! in_array( $compare, [ '=', '!=', '<>', '<', '>', '<=', '>=', '<=>' ], true ) ) {
							throw new StoreEngineInvalidArgumentException(
								'Invalid compare (' . esc_html( $compare ) . ') in where args (condition) format.',
								'invalid-where-args-format',
								[
									'condition'  => $condition, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
									'collection' => get_class( $this ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
								]
							);
						}

						$placeholder  = is_numeric( $value ) ? '%d' : '%s';
						$queryParts[] = "$key $compare $placeholder";
						$params[]     = $value;
						break;
				}
			} elseif ( is_array( $condition ) && ! empty( $condition ) ) {
				$sub_query    = $this->generate_conditions( $condition, $params );
				$queryParts[] = "($sub_query)";
			} else {
				throw new StoreEngineInvalidArgumentException(
					'Invalid where (condition) args format',
					'invalid-where-args-format',
					[
						'condition'  => $condition, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
						'collection' => get_class( $this ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
					]
				);
			}
		}

		return implode( " $relation ", $queryParts );
	}

	protected function normalize_value( $value ) {
		if ( ! is_string( $value ) ) {
			// Early bail if not string.
			return $value;
		}

		// Full datetime: Y-m-d H:i:s
		$datetime_keywords = [ 'now', 'getdate', 'current_timestamp', 'localtime', 'sysdate' ];
		// Date only: Y-m-d
		$date_keywords = [ 'curdate', 'current_date' ];
		// Time only: H:i:s
		$time_keywords = [ 'curtime', 'current_time' ];
		// Normalize keyword
		$keyword = strtolower( rtrim( trim( $value ), '()' ) );

		if ( in_array( $keyword, $datetime_keywords, true ) ) {
			$value = current_time( 'mysql', 1 );
		} elseif ( in_array( $keyword, $date_keywords, true ) ) {
			$value = substr( current_time( 'mysql', 1 ), 0, 10 );
		} elseif ( in_array( $keyword, $time_keywords, true ) ) {
			$value = current_time( 'H:i:s', 1 );
		}

		return $value;
	}

	/**
	 * Generates cache key.
	 *
	 * @param array $args Query arguments.
	 * @param string $sql SQL statement.
	 *
	 * @return string Cache key.
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	protected function generate_cache_key( array $args, string $sql ): string {
		global $wpdb;

		unset(
			$args['fields'],
			$args['suppress_filters']
		);

		if ( isset( $args['post_status'] ) ) {
			$args['post_status'] = (array) $args['post_status'];
			// Sort post status to ensure same cache key generation.
			sort( $args['post_status'] );
		}

		// Add a default orderby value of date to ensure same cache key generation.
		if ( ! isset( $args['orderby'] ) ) {
			$args['orderby'] = $this->primary_key;
		}

		$placeholder = $wpdb->placeholder_escape();
		array_walk_recursive(
			$args,
			/*
			 * Replace wpdb placeholders with the string used in the database
			 * query to avoid unreachable cache keys. This is necessary because
			 * the placeholder is randomly generated in each request.
			 *
			 * $value is passed by reference to allow it to be modified.
			 * array_walk_recursive() does not return an array.
			 */
			static function ( &$value ) use ( $wpdb, $placeholder ) {
				if ( is_string( $value ) && str_contains( $value, $placeholder ) ) {
					$value = $wpdb->remove_placeholder_escape( $value );
				}
			}
		);

		ksort( $args );

		// Replace wpdb placeholder in the SQL statement used by the cache key.
		$sql = $wpdb->remove_placeholder_escape( $sql );
		$key = md5( maybe_serialize( $args ) . $sql );

		$last_changed = wp_cache_get_last_changed( $this->cache_group );

		return get_class( $this ) . ":$key:$last_changed";
	}

	/**
	 * Adds any items from the given IDs to the cache that do not already exist in cache.
	 *
	 * Objects like Order that have multiple joins should redeclare this method, so complex like order (that includes multiple tables)
	 * gets cached properly.
	 *
	 * @param int[] $ids ID list.
	 *
	 * @see update_post_cache()
	 * @see update_postmeta_cache()
	 * @see update_object_term_cache()
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @see _prime_post_caches()
	 */
	protected function _prime_item_caches( array $ids ) {
		// @TODO Prime cache bug that can cache broken data
		//       As entity cache data after reading which might includes metadata or data from
		//       other table.
		return;

		global $wpdb;

		$non_cached_ids = _get_non_cached_ids( $ids, $this->cache_group );

		if ( ! empty( $non_cached_ids ) ) {
			// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- query prepared and cached.
			$fresh_items = $wpdb->get_results( sprintf( "SELECT $this->table.* FROM $this->table WHERE $this->primary_key IN (%s)", implode( ',', $non_cached_ids ) ) );
			// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- query prepared and cached.

			if ( $fresh_items ) {
				$this->update_items_cache( $fresh_items );
			}
		}
	}

	/**
	 * Updates posts in cache.
	 *
	 * @param object[] $items Array of item objects (passed by reference).
	 *
	 * @see update_post_cache()
	 */
	protected function update_items_cache( array &$items ) {
		if ( ! $items ) {
			return;
		}

		$data = [];
		foreach ( $items as $item ) {
			if ( empty( $item->filter ) || 'raw' !== $item->filter ) {
				$item->filter = 'row';
				$item         = sanitize_post( $item, 'raw' );
			}

			$data[ $item->{$this->primary_key} ] = $item;
		}

		wp_cache_add_multiple( $data, $this->cache_group );
	}

	/**
	 * Prime the cache containing the parent ID of various post objects.
	 *
	 * @param int[] $ids ID list.
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 *
	 * @see _prime_post_parent_id_caches()
	 */
	protected function _prime_result_parent_id_caches( array $ids ) {
		global $wpdb;

		$ids = array_filter( $ids, '_validate_cache_id' );
		$ids = array_unique( array_map( 'intval', $ids ), SORT_NUMERIC );

		if ( empty( $ids ) ) {
			return;
		}

		$cache_keys = [];
		foreach ( $ids as $id ) {
			$cache_keys[ $id ] = $this->object_type . '_parent:' . $id;
		}

		$cached_data = wp_cache_get_multiple( array_values( $cache_keys ), $this->cache_group );

		$non_cached_ids = [];
		foreach ( $cache_keys as $id => $cache_key ) {
			if ( false === $cached_data[ $cache_key ] ) {
				$non_cached_ids[] = $id;
			}
		}

		if ( ! empty( $non_cached_ids ) ) {
			// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- query prepared and cached.
			$fresh_items = $wpdb->get_results( sprintf( "SELECT $this->table.$this->primary_key, $this->table.$this->parent_key FROM $this->table WHERE $this->primary_key IN (%s)", implode( ',', $non_cached_ids ) ) );
			// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- query prepared and cached.

			if ( $fresh_items ) {
				$item_parent_data = [];
				foreach ( $fresh_items as $fresh_item ) {
					$item_parent_data[ $this->object_type . '_parent:' . $fresh_item->{$this->primary_key} ] = (int) $fresh_item->{$this->parent_key};
				}

				wp_cache_add_multiple( $item_parent_data, $this->cache_group );
			}
		}
	}

	public function getIterator(): ArrayIterator {
		return new ArrayIterator( $this->get_results() );
	}

	public function count(): int {
		return $this->result_count;
	}

	public function get_found_results(): int {
		return $this->found_results;
	}

	public function get_max_num_pages(): int {
		return $this->max_num_pages;
	}

	public function get_per_page(): ?int {
		return $this->per_page;
	}

	public function get_no_paging(): ?bool {
		return $this->nopaging;
	}

	/**
	 * Get the pagination.
	 *
	 * @param string $label
	 * @param string|string[] $args
	 *
	 * @return string
	 * @see _navigation_markup()
	 * @see paginate_links()
	 *
	 * @see get_the_posts_pagination()
	 */
	public function paginate_links( string $label, $args = [] ): string {
		$navigation = '';

		if ( ! $this->get_max_num_pages() ) {
			return $navigation;
		}

		// Don't print empty markup if there's only one page.
		if ( $this->get_max_num_pages() > 1 ) {
			// Make sure the nav element has an aria-label attribute: fallback to the screen reader text.
			if ( ! empty( $args['screen_reader_text'] ) && empty( $args['aria_label'] ) ) {
				$args['aria_label'] = $args['screen_reader_text'];
			}

			$args = wp_parse_args(
				$args,
				[
					'base'               => str_replace( PHP_INT_MAX, '%#%', esc_url( get_pagenum_link( PHP_INT_MAX ) ) ),
					'format'             => '?paged=%#%',
					'current'            => max( 1, $paged ?? get_query_var( 'paged' ) ),
					'total'              => $this->get_max_num_pages(),
					'prev_text'          => sprintf(
						'<span class="screen-reader-text">%1$s</span> <i class="storeengine-icon storeengine-icon--%2$s" aria-hidden="true"></i>',
						_x( 'Previous', 'Pagination previous set of items', 'storeengine' ),
						is_rtl() ? 'arrow-right' : 'arrow-left'
					),
					'next_text'          => sprintf(
						'<i class="storeengine-icon storeengine-icon--%2$s" aria-hidden="true"></i> <span class="screen-reader-text">%1$s</span>',
						_x( 'Next', 'Pagination next set of items', 'storeengine' ),
						is_rtl() ? 'arrow-left' : 'arrow-right'
					),
					'screen_reader_text' => sprintf(
					/* translators: Hidden accessibility text. %s. Pagination label. */
						_x( '%s pagination', 'Pagination nav header', 'storeengine' ),
						$label
					),
					'aria_label'         => sprintf(
					/* translators: %s. Pagination label. */
						_x( '%s pagination', 'Pagination nave aria-label', 'storeengine' ),
						$label
					),
					'class'              => 'pagination',
					'show_total'         => true,
				]
			);

			/**
			 * Filters the arguments for posts pagination links.
			 *
			 * @param array $args {
			 *     Optional. Default pagination arguments, see paginate_links().
			 *
			 * @type string $screen_reader_text Screen reader text for navigation element.
			 *                                      Default 'Posts navigation'.
			 * @type string $aria_label ARIA label text for the nav element. Default 'Posts'.
			 * @type string $class Custom class for the nav element. Default 'pagination'.
			 * }
			 */
			$args = apply_filters( "{$this->hook_prefix}/the_pagination_args", $args ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound

			// Make sure we get a string back. Plain is the next best thing.
			if ( isset( $args['type'] ) && 'array' === $args['type'] ) {
				$args['type'] = 'plain';
			}

			// Set up paginated links.
			$links = paginate_links( $args );

			// Wraps passed links in navigational markup.
			if ( $links ) {
				$template = '
	<nav class="%1$s" role="navigation" aria-label="%4$s">
		<h2 class="screen-reader-text">%2$s</h2>
		<div class="nav-links">%3$s</div>
	</nav>';

				/**
				 * Filters the navigation markup template.
				 *
				 * Note: The filtered template HTML must contain specifiers for the navigation
				 * class (%1$s), the screen-reader-text value (%2$s), placement of the navigation
				 * links (%3$s), and ARIA label text if screen-reader-text does not fit that (%4$s):
				 *
				 *     <nav class="navigation %1$s" aria-label="%4$s">
				 *         <h2 class="screen-reader-text">%2$s</h2>
				 *         <div class="nav-links">%3$s</div>
				 *     </nav>
				 *
				 * @param string $template The default template.
				 * @param string $css_class The class passed by the calling function.
				 */
				$template   = apply_filters( "{$this->hook_prefix}/navigation_markup_template", $template, $args['class'] ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound
				$classes    = trim( 'navigation storeengine-pagination ' . sanitize_html_class( $args['class'] ) );
				$navigation = sprintf( $template, $classes, esc_html( $args['screen_reader_text'] ), $links, esc_attr( $args['aria_label'] ) );
			}

			if ( $args['show_total'] ) {
				$navigation = sprintf(
							  /* translators: 1: Starting number of items on the current page, 2: Ending number of items, 3: Total number of items. */
								  '<div class="displaying-num">' . esc_html_x( 'Displaying %1$s&#8211;%2$s of %3$s', 'Displaying total item before pagination. E.g. Displaying 10 of 30', 'storeengine' ) . '</div>',
								  number_format_i18n( ( $this->page - 1 ) * $this->per_page + 1 ),
								  number_format_i18n( min( $this->page * $this->per_page, $this->get_found_results() ) ),
								  number_format_i18n( $this->get_found_results() )
							  )
							  . PHP_EOL
							  . $navigation;

				$navigation = '<div class="storeengine-width-full storeengine-flex storeengine-flex-align-center storeengine-flex-justify-between">' . $navigation . '</div>';
			}
		}

		return $navigation;
	}

	/**
	 * Render/Print pagination.
	 *
	 * @param string $label
	 * @param string|string[] $args
	 *
	 * @return void
	 */
	public function render_pagination( string $label, $args = [] ): void {
		echo wp_kses_post( $this->paginate_links( $label, $args ) );
	}

	/**
	 * @param string $label
	 * @param string|string[] $args
	 *
	 * @return string
	 *
	 * @deprecated
	 */
	public function get_the_pagination( string $label, $args = [] ): string {
		return $this->paginate_links( $label, $args );
	}

	/**
	 * Render/Print pagination.
	 *
	 * @param string $label
	 * @param string|string[] $args
	 *
	 * @return void
	 *
	 * @deprecated
	 */
	public function the_posts_pagination( string $label, $args = [] ): void {
		$this->render_pagination( $label, $args );
	}
}
