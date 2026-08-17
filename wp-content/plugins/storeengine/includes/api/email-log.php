<?php
/**
 * REST endpoints for the email log (customer-communication audit).
 *
 * GET    /storeengine/v1/email-logs       — paged list with filters
 * GET    /storeengine/v1/email-logs/{id}  — one row
 * DELETE /storeengine/v1/email-logs/{id}  — manual delete (cleanup cron handles bulk)
 *
 * @version 1.0.0
 */

namespace StoreEngine\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Addons\Email\ResendRegistry;
use StoreEngine\Classes\EmailLog as EmailLogEntity;
use StoreEngine\Classes\EmailLogCleanup;
use StoreEngine\Classes\EmailLogCollection;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Controller;
use WP_REST_Request;
use WP_REST_Server;

class EmailLog extends WP_REST_Controller {

	public function __construct() {
		$this->namespace = STOREENGINE_PLUGIN_SLUG . '/v1';
		$this->rest_base = 'email-logs';
	}

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_items' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => $this->get_collection_params(),
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/settings', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_settings' ],
				'permission_callback' => [ $this, 'permissions_check' ],
				'args'                => [
					'retention_days_sent'   => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
					'retention_days_failed' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/purge', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'purge_now' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/resendable-types', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_resendable_types' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)/resend', [
			'args' => [
				'id' => [
					'description' => __( 'Email log entry ID.', 'storeengine' ),
					'type'        => 'integer',
				],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'resend_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>[\d]+)', [
			'args' => [
				'id' => [
					'description' => __( 'Email log entry ID.', 'storeengine' ),
					'type'        => 'integer',
				],
			],
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_item' ],
				'permission_callback' => [ $this, 'permissions_check' ],
			],
		] );
	}

	public function get_items( $request ) {
		$args = [
			'page'     => max( 1, (int) $request->get_param( 'page' ) ),
			'per_page' => max( 1, (int) $request->get_param( 'per_page' ) ),
			'orderby'  => 'sent_at_gmt',
			'order'    => 'DESC',
			'where'    => [],
		];

		if ( $email_type = $request->get_param( 'email_type' ) ) {
			$args['where'][] = [ 'key' => 'email_type', 'value' => sanitize_text_field( $email_type ) ];
		}

		if ( $status = $request->get_param( 'status' ) ) {
			$args['where'][] = [ 'key' => 'status', 'value' => sanitize_text_field( $status ) ];
		}

		if ( $customer_id = (int) $request->get_param( 'customer_id' ) ) {
			$args['where'][] = [ 'key' => 'customer_id', 'value' => $customer_id ];
		}

		if ( $order_id = (int) $request->get_param( 'order_id' ) ) {
			$args['where'][] = [ 'key' => 'order_id', 'value' => $order_id ];
		}

		if ( $related_entity_type = $request->get_param( 'related_entity_type' ) ) {
			$args['where'][] = [ 'key' => 'related_entity_type', 'value' => sanitize_text_field( $related_entity_type ) ];
		}

		if ( $related_entity_id = (int) $request->get_param( 'related_entity_id' ) ) {
			$args['where'][] = [ 'key' => 'related_entity_id', 'value' => $related_entity_id ];
		}

		if ( $recipient = $request->get_param( 'recipient' ) ) {
			// LIKE so admins can search a partial recipient — e.g. domain only.
			$args['where'][] = [
				'key'     => 'recipient',
				'value'   => '%' . sanitize_text_field( $recipient ) . '%',
				'compare' => 'LIKE',
			];
		}

		if ( $search = $request->get_param( 'search' ) ) {
			$search          = sanitize_text_field( $search );
			$args['where'][] = [
				'relation' => 'OR',
				[ 'key' => 'subject', 'value' => '%' . $search . '%', 'compare' => 'LIKE' ],
				[ 'key' => 'recipient', 'value' => '%' . $search . '%', 'compare' => 'LIKE' ],
			];
		}

		if ( $date_from = $request->get_param( 'date_from' ) ) {
			$args['where'][] = [ 'key' => 'sent_at_gmt', 'value' => sanitize_text_field( $date_from ), 'compare' => '>=' ];
		}

		if ( $date_to = $request->get_param( 'date_to' ) ) {
			$args['where'][] = [ 'key' => 'sent_at_gmt', 'value' => sanitize_text_field( $date_to ), 'compare' => '<=' ];
		}

		$collection = new EmailLogCollection( $args );

		$data = [];
		foreach ( $collection->get_results() as $row ) {
			$data[] = $this->format_row( $row );
		}

		$response = rest_ensure_response( $data );
		$response->header( 'X-WP-Total', (string) $collection->get_found_results() );
		$response->header( 'X-WP-TotalPages', (string) $collection->get_max_num_pages() );

		return $response;
	}

	public function get_item( $request ) {
		$id = (int) $request->get_param( 'id' );

		try {
			$row = new EmailLogEntity( $id );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'storeengine_email_log_not_found', __( 'Email log entry not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		if ( ! $row->get_id() ) {
			return new WP_Error( 'storeengine_email_log_not_found', __( 'Email log entry not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		return rest_ensure_response( $this->format_row( $row ) );
	}

	public function get_settings( $request ) {
		return rest_ensure_response( EmailLogCleanup::get_settings() );
	}

	public function save_settings( $request ) {
		$settings = [
			'retention_days_sent'   => max( 1, (int) $request->get_param( 'retention_days_sent' ) ),
			'retention_days_failed' => max( 1, (int) $request->get_param( 'retention_days_failed' ) ),
		];

		update_option( 'storeengine_email_log_settings', $settings );

		return rest_ensure_response( [
			'message'  => __( 'Email log settings saved.', 'storeengine' ),
			'settings' => $settings,
		] );
	}

	public function purge_now( $request ) {
		EmailLogCleanup::execute_cleanup();

		return rest_ensure_response( [ 'purged' => true ] );
	}

	public function get_resendable_types( $request ) {
		return rest_ensure_response( [ 'types' => ResendRegistry::init()->get_resendable_types() ] );
	}

	public function resend_item( $request ) {
		$id = (int) $request->get_param( 'id' );

		try {
			$row = new EmailLogEntity( $id );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'storeengine_email_log_not_found', __( 'Email log entry not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		if ( ! $row->get_id() ) {
			return new WP_Error( 'storeengine_email_log_not_found', __( 'Email log entry not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		if ( 'queued' === $row->get_status() ) {
			// queued means a previous send is still in-flight (or got stuck). Resending
			// here would create a confusing duplicate row.
			return new WP_Error( 'storeengine_email_log_still_queued', __( 'This email is still being processed. Wait for it to complete before resending.', 'storeengine' ), [ 'status' => 409 ] );
		}

		$handler = ResendRegistry::init()->get_handler( (string) $row->get_email_type() );
		if ( ! $handler ) {
			return new WP_Error( 'storeengine_email_log_not_resendable', __( 'This email type cannot be resent automatically.', 'storeengine' ), [ 'status' => 400 ] );
		}

		// Mark provenance on the next captured row so the UI can render a
		// "resent from #N" indicator. We tag a transient bookmark; the
		// universal logger merges it into the new row's payload on its next
		// capture and then clears it.
		$current_user = wp_get_current_user();
		add_filter( 'storeengine/email_log/next_capture_payload', static function ( array $extra ) use ( $row, $current_user ) {
			$extra['resent_from_log_id']  = $row->get_id();
			$extra['resent_by_user_id']   = $current_user ? (int) $current_user->ID : 0;
			$extra['resent_at_gmt']       = gmdate( 'Y-m-d H:i:s' );
			return $extra;
		} );

		try {
			$result = $handler( $row );
		} catch ( \Throwable $e ) {
			Helper::log_error( $e );

			return new WP_Error( 'storeengine_email_log_resend_failed', $e->getMessage() ?: __( 'Resend failed unexpectedly.', 'storeengine' ), [ 'status' => 500 ] );
		}

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// The handler dispatched via mail_send() → the universal logger captured
		// a new row. Return the most recent one (descending by id) for the same
		// email_type + recipient as the original — gives the UI a fresh entity
		// to render without an extra round-trip.
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Prepared lookup of the freshly-written log row on a custom StoreEngine table; not cacheable.
		$new_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}storeengine_email_log WHERE email_type = %s AND id > %d ORDER BY id DESC LIMIT 1",
				$row->get_email_type(),
				$row->get_id()
			)
		);

		return rest_ensure_response( [
			'resent'         => true,
			'original_id'    => $row->get_id(),
			'new_log_id'     => $new_id ?: null,
		] );
	}

	public function delete_item( $request ) {
		$id = (int) $request->get_param( 'id' );

		try {
			$row = new EmailLogEntity( $id );
		} catch ( \Throwable $e ) {
			return new WP_Error( 'storeengine_email_log_not_found', __( 'Email log entry not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		if ( ! $row->get_id() ) {
			return new WP_Error( 'storeengine_email_log_not_found', __( 'Email log entry not found.', 'storeengine' ), [ 'status' => 404 ] );
		}

		$row->delete( true );

		return rest_ensure_response( [ 'deleted' => true, 'id' => $id ] );
	}

	/**
	 * Shape an EmailLog entity into the REST response payload.
	 * Includes the decoded payload (not the raw JSON) so frontend doesn't
	 * have to double-decode.
	 */
	protected function format_row( EmailLogEntity $row ): array {
		return [
			'id'                  => $row->get_id(),
			'sent_at_gmt'         => $row->get_sent_at_gmt(),
			'email_type'          => $row->get_email_type(),
			'recipient'           => $row->get_recipient(),
			'subject'             => $row->get_subject(),
			'status'              => $row->get_status(),
			'customer_id'         => $row->get_customer_id() ?: null,
			'order_id'            => $row->get_order_id() ?: null,
			'related_entity_type' => $row->get_related_entity_type(),
			'related_entity_id'   => $row->get_related_entity_id() ?: null,
			'error_message'       => $row->get_error_message(),
			'payload'             => $row->get_meta_payload(),
		];
	}

	public function permissions_check( $request ) {
		return Helper::check_rest_user_cap( 'manage_options' );
	}

	public function get_collection_params(): array {
		return [
			'page'                => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
			'per_page'            => [ 'default' => 20, 'sanitize_callback' => 'absint' ],
			'email_type'          => [ 'sanitize_callback' => 'sanitize_text_field' ],
			'status'              => [ 'sanitize_callback' => 'sanitize_text_field' ],
			'customer_id'         => [ 'sanitize_callback' => 'absint' ],
			'order_id'            => [ 'sanitize_callback' => 'absint' ],
			'related_entity_type' => [ 'sanitize_callback' => 'sanitize_text_field' ],
			'related_entity_id'   => [ 'sanitize_callback' => 'absint' ],
			'recipient'           => [ 'sanitize_callback' => 'sanitize_text_field' ],
			'search'              => [ 'sanitize_callback' => 'sanitize_text_field' ],
			'date_from'           => [ 'sanitize_callback' => 'sanitize_text_field' ],
			'date_to'             => [ 'sanitize_callback' => 'sanitize_text_field' ],
		];
	}
}
