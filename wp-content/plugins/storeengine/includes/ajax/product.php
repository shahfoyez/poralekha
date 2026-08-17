<?php

namespace StoreEngine\Ajax;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\AbstractRequestHandler;
use StoreEngine\Classes\Analytics;
use StoreEngine\Classes\Exceptions\StoreEngineException;
use StoreEngine\Classes\Integration;
use StoreEngine\Classes\Price;
use StoreEngine\Integrations;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Pixel;
use StoreEngine\Utils\Template as Template;
use WP_Error;
use WP_Query;

class Product extends AbstractAjaxHandler {

	public function __construct() {
		$price_fields = apply_filters( 'storeengine/ajax/product/price_fields', [
			'product_id'            => 'id',
			'price_name'            => 'string',
			'price_type'            => 'string',
			'price'                 => 'float',
			'compare_price'         => 'float',
			'setup_fee'             => 'boolean',
			'setup_fee_name'        => 'string',
			'setup_fee_price'       => 'float',
			'setup_fee_type'        => 'string',
			'trial'                 => 'boolean',
			'trial_days'            => 'integer',
			'expire'                => 'boolean',
			'expire_days'           => 'integer',
			'payment_duration'      => 'integer',
			'payment_duration_type' => 'string',
			'upgradeable'           => 'boolean',
			'order'                 => 'integer',
		] );

		// Actions.
		$this->actions = [
			'search_product_callback'       => [
				'callback'   => [ $this, 'search_product_callback' ],
				'capability' => 'manage_options',
				'fields'     => [
					'search'  => 'string',
					'id'      => 'id',
					// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude -- Request field-type map key, not a query argument; the exclusion is a bounded admin-supplied id list.
					'exclude' => AbstractRequestHandler::IDS,
					'is_digital' => AbstractRequestHandler::BOOLEAN,
					'is_versioned' => AbstractRequestHandler::BOOLEAN,
				]
			],
			'get_product_list'              => [
				'callback'   => [ $this, 'get_product_list' ],
				'capability' => 'manage_options',
				'fields'     => [
					'search'         => 'string',
					'integration_id' => 'int',
					'provider'       => 'string',
				],
			],
			// Price CRUD — vendors need to manage prices on their own
			// products. The primitive cap `edit_storeengine_products` is held
			// by administrators, shop managers, AND the vendor role; each
			// handler additionally enforces post-author ownership for the
			// vendor case via user_can_manage_product_prices().
			'get_product_prices'            => [
				'capability' => 'edit_storeengine_products',
				'callback'   => [ $this, 'get_product_prices' ],
				'fields'     => [
					'product_id' => 'id',
				],
			],
			'create_product_price'          => [
				'capability' => 'edit_storeengine_products',
				'callback'   => [ $this, 'create_product_price' ],
				'fields'     => $price_fields,
			],
			'update_product_price'          => [
				'capability' => 'edit_storeengine_products',
				'callback'   => [ $this, 'update_product_price' ],
				'fields'     => array_merge( [ 'id' => 'id' ], $price_fields ),
			],
			'toggle_price_visibility'       => [
				'capability' => 'edit_storeengine_products',
				'callback'   => [ $this, 'toggle_price_visibility' ],
				'fields'     => [
					'id'        => 'id',
					'is_hidden' => 'boolean',
				],
			],
			'delete_product_price'          => [
				'capability' => 'edit_storeengine_products',
				'callback'   => [ $this, 'delete_product_price' ],
				'fields'     => [ 'id' => 'id' ],
			],
			'top_products'                  => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'top_products' ],
			],
			'get_product_integration_items' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_product_integration_items' ],
				'fields'     => [
					'provider'   => 'string',
					'product_id' => 'id',
					'search'     => 'string',
				],
			],
			'create_product_integration'    => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'create_product_integration' ],
				'fields'     => [
					'provider'       => 'string',
					'price_id'       => 'id',
					'integration_id' => 'id',
					'course_ids'     => [
						[
							'value' => 'id',
							'label' => 'text',
						]
					],
					'product_id'     => 'id',
				],
			],
			'delete_product_integration'    => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'delete_product_integration' ],
				'fields'     => [
					'id'                 => 'id',
					'with_course_bundle' => 'string',
				],
			],
			'get_product_slug'              => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_product_slug' ],
				'fields'     => [
					'ID' => 'id',
				],
			],
			'update_product_slug'           => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'update_product_slug' ],
				'fields'     => [
					'ID'        => 'id',
					'new_slug'  => 'string',
					'new_title' => 'string',
				],
			],
			'get_page_templates'            => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'get_page_templates' ],
			],
			'archive_product_filter'        => [
				'allow_visitor_action' => true,
				'callback'             => [ $this, 'archive_product_filter' ],
				'fields'               => [
					'search'   => 'string',
					'category' => 'strings',
					'tags'     => 'strings',
					'orderby'  => 'string',
					'paged'    => 'integer',
					'count'    => 'integer',
				],
			],
		];
	}

	protected function search_product_callback( array $payload ) {
		if ( ! empty( $payload['id'] ) ) {
			$products = [ Helper::get_product( $payload['id'] ) ];
		} else {
			$args = [
				's'              => $payload['search'] ?? '',
				'posts_per_page' => 15,
				'orderby'        => 'post_title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
				// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- Bounded admin-only (manage_options) product search; excludes a small, caller-supplied id set.
				'post__not_in'   => $payload['exclude'] ?? [],
			];

			$meta = [];
			if ( array_key_exists( 'is_digital', $payload ) ) {
				$meta[] = [
					'key'   => '_storeengine_product_shipping_type',
					'value' => $payload['is_digital'] ? 'digital' : 'physical',
				];
			}

			if ( $meta ) {
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Admin-only product search filtered by product shipping-type meta; required for the digital/physical filter.
				$args['meta_query'] = array_merge( [ 'relation' => 'AND'], $meta );
			}

			$products = Helper::get_products( $args );
		}

		$data = [];

		foreach ( $products as $product ) {
			if ( ! $product ) {
				continue;
			}

			$prices = [];

			add_filter( 'storeengine/product/get_price_types', [ $this, 'allow_bundle_supported_price_types' ] );

			foreach ( $product->get_prices() as $price ) {
				$prices[] = [
					'label'   => $price->get_name(),
					'value'   => $price->get_id(),
					'price'   => $price->get_price(),
					'compare' => $price->get_compare_price(),
				];
			}

			remove_filter( 'storeengine/product/get_price_types', [ $this, 'allow_bundle_supported_price_types' ] );

			$data[] = [
				'label'  => $product->get_name(),
				'value'  => $product->get_id(),
				'prices' => $prices
			];
		}

		wp_send_json_success( $data );
	}

	public function allow_bundle_supported_price_types( array $types ): array {
		if ( in_array( 'subscription', $types, true ) ) {
			$types = array_flip( $types );
			unset( $types['subscription'] );
			$types = array_flip( $types );
		}

		return $types;
	}

	public function get_product_list( array $payload ) {
		if ( ! isset( $payload['integration_id'] ) ) {
			wp_send_json_error( [
				'message' => __( 'integration_id is required', 'storeengine' ),
			] );
		}

		$search   = $payload['search'] ?? '';
		$provider = $payload['provider'] ?? 'storeengine/membership-addon';
		wp_send_json_success( Helper::get_product_list( $payload['integration_id'], $provider, $search ) );
	}

	protected function get_page_templates() {
		wp_send_json_success( wp_get_theme()->get_page_templates() );
	}

	/**
	 * Author-scope guard for the price AJAX endpoints.
	 *
	 * The endpoint cap is `edit_storeengine_products` — held by admins,
	 * shop managers, AND vendors. Admins/shop managers also hold the
	 * "others" cap and can touch anything; vendors only their own
	 * products. Mirrors `ProductPermissions::restrict_others_products()`
	 * which gates the REST product-CRUD endpoints.
	 */
	protected function user_can_manage_product_prices( int $product_id ): bool {
		if ( ! $product_id ) {
			return false;
		}
		if ( current_user_can( 'edit_others_storeengine_products' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}
		$post = get_post( $product_id );
		if ( ! $post || Helper::PRODUCT_POST_TYPE !== $post->post_type ) {
			return false;
		}
		return (int) $post->post_author === get_current_user_id();
	}

	protected function get_product_prices( $payload ) {
		if ( empty( $payload['product_id'] ) ) {
			wp_send_json_error( esc_html__( 'Product ID required.', 'storeengine' ) );
		}

		if ( ! $this->user_can_manage_product_prices( (int) $payload['product_id'] ) ) {
			wp_send_json_error( esc_html__( 'You can only manage prices on your own products.', 'storeengine' ), 403 );
		}

		wp_send_json_success( Helper::get_prices_array_by_product_id( $payload['product_id'] ) );
	}

	protected function create_product_price( $payload ) {
		$data = $this->validate_and_prepare_price_data( $payload );

		if ( is_wp_error( $data ) ) {
			wp_send_json_error( $data->get_error_message() );
		}

		if ( ! $this->user_can_manage_product_prices( (int) ( $data['product_id'] ?? 0 ) ) ) {
			wp_send_json_error( esc_html__( 'You can only add prices to your own products.', 'storeengine' ), 403 );
		}

		$price = new Price();
		$this->populate_price_props( $price, $data );
		$saved = $price->save();

		if ( is_wp_error( $saved ) ) {
			wp_send_json_error( sprintf(
			/* translators: %s: Error message. */
				esc_html__( 'Failed to save new price. Error: %s', 'storeengine' ),
				esc_html( $saved->get_error_message() )
			) );
		}

		wp_send_json_success( $this->prepare_price_data_response( $price ) );
	}

	protected function update_product_price( $payload ) {
		$data = $this->validate_and_prepare_price_data( $payload, 'edit' );

		if ( is_wp_error( $data ) ) {
			wp_send_json_error( $data->get_error_message() );
		}

		try {
			$price = new Price( absint( $payload['id'] ) );
			if ( ! $this->user_can_manage_product_prices( (int) $price->get_product_id() ) ) {
				wp_send_json_error( esc_html__( 'You can only edit prices on your own products.', 'storeengine' ), 403 );
			}
			$this->populate_price_props( $price, $data );
			$saved = $price->save();

			if ( is_wp_error( $saved ) ) {
				wp_send_json_error( sprintf(
				/* translators: %s: Error message. */
					esc_html__( 'Failed to update price. Error: %s', 'storeengine' ),
					esc_html( $saved->get_error_message() )
				) );
			}

			wp_send_json_success( $this->prepare_price_data_response( $price ) );
		} catch ( StoreEngineException $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	protected function toggle_price_visibility( $payload ) {
		if ( empty( $payload['id'] ) ) {
			wp_send_json_error( esc_html__( 'Price id is required!', 'storeengine' ) );
		}

		try {
			$price = new Price( $payload['id'] );
			if ( ! $this->user_can_manage_product_prices( (int) $price->get_product_id() ) ) {
				wp_send_json_error( esc_html__( 'You can only toggle prices on your own products.', 'storeengine' ), 403 );
			}

			$is_hidden = ! array_key_exists( 'is_hidden', $payload ) ? ! $price->get_is_hidden() : (bool) $payload['is_hidden'];

			$price->set_is_hidden( $is_hidden );
			$saved = $price->save();

			if ( is_wp_error( $saved ) ) {
				wp_send_json_error( sprintf(
				/* translators: %s: Error message. */
					esc_html__( 'Failed to save price visibility. Error: %s', 'storeengine' ),
					esc_html( $saved->get_error_message() )
				) );
			}

			wp_send_json_success( $this->prepare_price_data_response( $price ) );
		} catch ( StoreEngineException $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	protected function prepare_price_data_response( Price $price ): array {
		return [
			'id'            => $price->get_id(),
			'price_name'    => $price->get_name(),
			'price_type'    => $price->get_price_type(),
			'price'         => $price->get_price(),
			'compare_price' => $price->get_compare_price(),
			'product_id'    => $price->get_product_id(),
			'order'         => $price->get_order(),
			'settings'      => $price->get_settings(),
			// Include the checkout link so a freshly created/updated price has a
			// working Checkout/Copy link immediately, without a page reload
			// (mirrors the field on the product-fetch response).
			'checkout_link' => add_query_arg( [
				'product_id' => $price->get_product_id(),
				'price_id'   => $price->get_id(),
			], Helper::get_checkout_url() ),
		];
	}

	protected function populate_price_props( Price $price, array $data ) {
		$price->set_props( [
			'price_name'            => $data['price_name'],
			'price_type'            => $data['price_type'],
			'price'                 => $data['price'],
			'compare_price'         => $data['compare_price'] > 0 ? $data['compare_price'] : null,
			'product_id'            => absint( $data['product_id'] ),
			'order'                 => absint( $data['order'] ),
			'setup_fee'             => (bool) ( $data['settings']['setup_fee'] ?: false ),
			'setup_fee_name'        => (string) ( $data['settings']['setup_fee_name'] ?: '' ),
			'setup_fee_price'       => ( $data['settings']['setup_fee_price'] ?: 0 ),
			'setup_fee_type'        => (string) ( $data['settings']['setup_fee_type'] ?: 'fixed' ),
			'trial'                 => (bool) ( $data['settings']['trial'] ?: false ),
			'trial_days'            => absint( $data['settings']['trial_days'] ?: 0 ),
			'expire'                => (bool) ( $data['settings']['expire'] ?: false ),
			'expire_days'           => absint( $data['settings']['expire_days'] ?: 0 ),
			'payment_duration'      => absint( $data['settings']['payment_duration'] ?: 1 ),
			'payment_duration_type' => (string) ( $data['settings']['payment_duration_type'] ?: 'monthly' ),
			'upgradeable'           => (bool) ( $data['settings']['upgradeable'] ?: false ),
		] );

		do_action_ref_array( 'storeengine/ajax/product/set_price_props', [ &$price, $data ] );
	}

	protected function validate_and_prepare_price_data( array $payload, string $context = 'create' ) {
		if ( 'edit' === $context && empty( $payload['id'] ) ) {
			return new WP_Error( 'invalid-price-id', __( 'Product Price ID is missing.', 'storeengine' ) );
		}

		if ( empty( $payload['product_id'] ) ) {
			return new WP_Error( 'invalid-product-id', __( 'Product ID is missing.', 'storeengine' ) );
		}

		if ( empty( $payload['price_name'] ) ) {
			return new WP_Error( 'price_name', __( 'Price name is required.', 'storeengine' ) );
		}

		if ( empty( $payload['price_type'] ) ) {
			return new WP_Error( 'price_type', __( 'Price type is required.', 'storeengine' ) );
		}

		if ( ! empty( $payload['price'] ) && (float) $payload['price'] <= 0 ) {
			return new WP_Error( 'invalid-price', __( 'Price must be greater than 0.', 'storeengine' ) );
		}

		if ( ! empty( $payload['compare_price'] ) ) {
			if ( $payload['compare_price'] <= 0 ) {
				return new WP_Error( 'invalid-compare-price', __( 'Compare price must be greater than 0.', 'storeengine' ) );
			}

			$compare_price = abs( floatval( $payload['compare_price'] ) );
			$price         = abs( floatval( $payload['price'] ?? 0 ) );

			if ( $compare_price <= $price ) {
				return new WP_Error( 'invalid-compare-price', __( 'Compare price must be greater than price.', 'storeengine' ) );
			}
		}

		$validate = apply_filters( 'storeengine/ajax/product/validate_price_data', true, $payload, $context );

		if ( is_wp_error( $validate ) && $validate->has_errors() ) {
			return $validate;
		}

		$settings = apply_filters(
			'storeengine/ajax/product/prepare_price_settings',
			[
				'setup_fee'             => $payload['setup_fee'] ?? false,
				'setup_fee_name'        => $payload['setup_fee_name'] ?? '',
				'setup_fee_price'       => $payload['setup_fee_price'] ?? '',
				'setup_fee_type'        => $payload['setup_fee_type'] ?? '',
				'trial'                 => $payload['trial'] ?? false,
				'trial_days'            => $payload['trial_days'] ?? 7,
				'expire'                => $payload['expire'] ?? false,
				'expire_days'           => $payload['expire_days'] ?? 3,
				'payment_duration'      => $payload['payment_duration'] ?? 0,
				'payment_duration_type' => $payload['payment_duration_type'] ?? '',
				'upgradeable'           => $payload['upgradeable'] ?? false,
			],
			$payload,
			$context
		);

		return [
			'price_name'    => $payload['price_name'],
			'price_type'    => $payload['price_type'],
			'price'         => $payload['price'],
			'compare_price' => $payload['compare_price'] ?? null,
			'product_id'    => $payload['product_id'],
			'settings'      => $settings,
			'order'         => $payload['order'] ?? 0,
		];
	}

	protected function delete_product_price( $payload ) {
		if ( empty( $payload['id'] ) ) {
			wp_send_json_error( esc_html__( 'Price Id missing!', 'storeengine' ) );
		}

		try {
			$price = new Price( $payload['id'] );

			if ( ! $this->user_can_manage_product_prices( (int) $price->get_product_id() ) ) {
				wp_send_json_error( esc_html__( 'You can only delete prices on your own products.', 'storeengine' ), 403 );
			}

			if ( $price->delete() ) {
				do_action( 'storeengine/price_deleted', $payload['id'] );
				wp_send_json_success( true );
			}

			wp_send_json_error( esc_html__( 'Something went wrong! Failed to delete product price!', 'storeengine' ) );
		} catch ( StoreEngineException $e ) {
			wp_send_json_error( $e->getMessage() );
		}
	}

	public function top_products() {
		wp_send_json_success( ( new Analytics() )->get_top_products() );
	}

	public function get_product_integration_items( $payload ) {
		if ( empty( $payload['provider'] ) ) {
			wp_send_json_error( esc_html__( 'Provider missing!', 'storeengine' ) );
		}

		try {
			$integration = Integrations::get_instance()->get_integration( $payload['provider'] );
			wp_send_json_success( [
				'label' => $integration->get_items_label(),
				'items' => $integration->get_items( [
					'product_id' => $payload['product_id'] ?? 0,
					'search'     => $payload['search'] ?? '',
				] ),
			] );
		} catch ( StoreEngineException $exception ) {
			wp_send_json_error( $exception->getMessage() );
		}
	}

	public function create_product_integration( $payload ) {
		if ( empty( $payload['provider'] ) ) {
			wp_send_json_error( esc_html__( 'Provider is required', 'storeengine' ) );
		}
		if ( empty( $payload['product_id'] ) ) {
			wp_send_json_error( esc_html__( 'Product ID is required', 'storeengine' ) );
		}
		if ( empty( $payload['price_id'] ) ) {
			wp_send_json_error( esc_html__( 'Price ID is required', 'storeengine' ) );
		}
		if ( 'storeengine/course-bundle' !== $payload['provider'] && empty( $payload['integration_id'] ) ) {
			wp_send_json_error( esc_html__( 'Integration ID is required', 'storeengine' ) );
		}

		if ( 'storeengine/course-bundle' === $payload['provider'] ) {
			$course_ids = $payload['course_ids'] ?? [];
			if ( empty( $course_ids ) ) {
				wp_send_json_error( esc_html__( 'Please select at least one course.', 'storeengine' ) );
			}
			$has_bundle = get_post_meta( $payload['product_id'], '_academy_course_bundle_id', true );
			if ( ! empty( $has_bundle ) ) {
				wp_send_json_error( esc_html__( 'Each product can only be assigned to a single course bundle.', 'storeengine' ) );
			}

			$bundle_id = wp_insert_post( [
				'post_title'  => get_the_title( $payload['product_id'] ),
				'post_type'   => 'alms_course_bundle',
				'post_status' => 'publish',
				'meta_input'  => [
					'academy_course_bundle_courses_ids' => $course_ids,
					'academy_course_bundle_type'        => 'paid',
				],
			] );

			if ( is_wp_error( $bundle_id ) ) {
				wp_send_json_error( $bundle_id->get_error_message() );
			}

			$payload['integration_id'] = $bundle_id;
		}

		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$has_integration = $wpdb->get_var(
			$wpdb->prepare( "
					SELECT id, product_id, price_id
					FROM
						{$wpdb->prefix}storeengine_integrations
					WHERE
						integration_id = %d
					AND provider = %s AND price_id = %d
			", $payload['integration_id'], $payload['provider'], $payload['price_id'] )
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $has_integration ) {
			wp_send_json_error( esc_html__( 'Same integration already exists!', 'storeengine' ) );
		}

		$integration = new Integration();
		$integration->set_provider( $payload['provider'] );
		$integration->set_product_id( $payload['product_id'] );
		$integration->set_price_id( $payload['price_id'] );
		$integration->set_integration_id( $payload['integration_id'] );
		$integration->save();

		if ( 0 === $integration->get_id() ) {
			wp_send_json_error( esc_html__( 'Failed to create integration!', 'storeengine' ) );
		}

		$data = [
			'id'             => $integration->get_id(),
			'product_id'     => $integration->get_product_id(),
			'price_id'       => $integration->get_price_id(),
			'provider'       => $integration->get_provider(),
			'integration_id' => $integration->get_integration_id(),
		];

		if ( 'storeengine/course-bundle' === $payload['provider'] ) {
			$data['course_ids'] = $payload['course_ids'] ?? [];
		}

		wp_send_json_success( $data );
	}

	public function delete_product_integration( $payload ) {
		if ( empty( $payload['id'] ) ) {
			wp_send_json_error( esc_html__( 'Integration ID is required', 'storeengine' ) );
		}

		$integration = ( new Integration( $payload['id'] ) )->get();
		if ( ! $integration ) {
			wp_send_json_error( esc_html__( 'Integration not found!', 'storeengine' ) );
		}

		if ( 'storeengine/course-bundle' === $integration->get_provider() ) {
			delete_post_meta( $integration->get_product_id(), '_academy_course_bundle_id' );
			delete_post_meta( $integration->get_integration_id(), 'academy_course_bundle_product_id' );

			$with_course_bundle = $payload['with_course_bundle'] && Formatting::string_to_bool( $payload['with_course_bundle'] );
			if ( $with_course_bundle ) {
				wp_delete_post( $integration->get_integration_id(), true );
			}
		}

		if ( ! $integration->delete() ) {
			wp_send_json_error( esc_html__( 'Failed to delete integration!', 'storeengine' ) );
		}

		wp_send_json_success();
	}

	public function get_product_slug( $payload ) {
		if ( empty( $payload['ID'] ) ) {
			wp_send_json_error( esc_html__( 'Product ID is required!', 'storeengine' ) );
		}

		wp_send_json_success( Helper::get_sample_permalink_args( $payload['ID'] ) );
	}

	public function update_product_slug( $payload ) {
		if ( empty( $payload['ID'] ) ) {
			wp_send_json_error( esc_html__( 'Product ID is required!', 'storeengine' ) );
		}

		if ( empty( $payload['new_slug'] ) ) {
			wp_send_json_error( esc_html__( 'New slug is required!', 'storeengine' ) );
		}

		$payload['new_slug'] = sanitize_title( $payload['new_slug'] );

		if ( ! $payload['new_slug'] ) {
			wp_send_json_error( esc_html__( 'New slug is invalid!', 'storeengine' ) );
		}

		$post = get_post( $payload['ID'] );

		if ( ! $post ) {
			wp_send_json_error( __( 'Product not found', 'storeengine' ), 404 );
		}

		if ( $post->post_name === $payload['new_slug'] ) {
			wp_send_json_error( esc_html__( 'Product slug is not changed!', 'storeengine' ) );
		}

		$update = wp_update_post( [ 'ID' => $post->ID, 'post_name' => $payload['new_slug'] ], true );

		if ( is_wp_error( $update ) ) {
			wp_send_json_error( $update->get_error_message() );
		}

		wp_send_json_success( Helper::get_sample_permalink_args( $payload['ID'], $payload['new_title'] ?? null, $payload['new_slug'] ) );
	}

	public function archive_product_filter( $payload ) {
		$search         = $payload['search'] ?? '';
		$category       = $payload['category'] ?? [];
		$tags           = $payload['tags'] ?? [];
		$orderby        = $payload['orderby'] ?? '';
		$paged          = ! empty( $payload['paged'] ) ? $payload['paged'] : 1;
		$per_row        = Helper::get_settings( 'product_archive_products_per_row' );
		$posts_per_page = ! empty( $payload['count'] ) ? absint( $payload['count'] ) : (int) Helper::get_settings( 'product_archive_products_per_page', 12 );

		$args               = Helper::prepare_product_search_query_args( [
			'search'         => $search,
			'category'       => $category,
			'tags'           => $tags,
			'paged'          => max( 1, $paged ),
			'orderby'        => $orderby,
			'posts_per_page' => $posts_per_page,
		] );
		$args['meta_query'] = [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'relation' => 'OR',
			[
				'key'     => '_storeengine_product_hide',
				'value'   => true,
				'compare' => 'NOT EXISTS',
			],
			[
				'key'     => '_storeengine_product_hide',
				'value'   => true,
				'compare' => '!=',
			],
		];

		$grid_class = Helper::get_responsive_column( $per_row );

		// Make archive query.
		// Setup globals for archive ajax.
		$GLOBALS['page']         = max( 1, $paged );
		$GLOBALS['paged']        = max( 1, $paged );
		$GLOBALS['wp_the_query'] = new WP_Query( apply_filters( 'storeengine/product_filter_args', $args ) );
		$GLOBALS['wp_query']     = $GLOBALS['wp_the_query'];
		$products_query          = $GLOBALS['wp_the_query'];

		ob_start();
		?>
		<div class="storeengine-row">
			<?php
			if ( $products_query->have_posts() ) {
				// Load posts loop.
				while ( $products_query->have_posts() ) {
					$products_query->the_post();
					/**
					 * Hook: storeengine/templates/product_loop.
					 */
					do_action( 'storeengine/templates/product_loop' );
					Template::get_template( 'content-product.php', [ 'grid_class' => $grid_class ] );
				}
				Template::get_template( 'archive/pagination.php', [
					'paged'         => $paged,
					'max_num_pages' => $products_query->max_num_pages,
				] );
			} else {
				Template::get_template( 'archive/product-none.php' );
			}
			?>
		</div>
		<?php
		$markup = ob_get_clean();

		wp_send_json_success(
			apply_filters(
				'storeengine/ajax/product_archive_response',
				[
					'markup'      => apply_filters( 'storeengine/product_filter_markup', $markup ),
					'found_posts' => $products_query->found_posts,
					'pixel_data'  => [
						'page_title'  => html_entity_decode( wp_get_document_title() ),
						'currency'    => Formatting::get_currency(),
						'products'    => Pixel::get_product_archive_data(),
					],
				]
			)
		);
	}
}
