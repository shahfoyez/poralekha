<?php

namespace StoreEngine\Addons\EmbeddableCheckout\Api;

use StoreEngine\Addons\EmbeddableCheckout\Models\EmbedKey;
use StoreEngine\API\AbstractRestApiController;
use StoreEngine\Utils\Helper;
use WP_Error;
use WP_REST_Request;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authenticated, manager-only CRUD for Quick Checkout embed keys.
 */
class EmbedKeys extends AbstractRestApiController {

	protected $rest_base = 'embed-keys';

	public static function init() {
		$self = new self();
		add_action( 'rest_api_init', [ $self, 'register_routes' ] );
	}

	public function register_routes() {
		register_rest_route( $this->namespace, '/' . $this->rest_base, [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'list_keys' ],
				'permission_callback' => [ $this, 'permission_callback' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_key' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [
					'label'           => [ 'type' => 'string', 'required' => true ],
					'allowed_origins' => [
						'required' => true,
						'type'     => 'array',
						'items'    => [ 'type' => 'string' ],
					],
					'product_scope'   => [
						'type'    => 'string',
						'enum'    => [ EmbedKey::SCOPE_ALL, EmbedKey::SCOPE_IDS ],
						'default' => EmbedKey::SCOPE_ALL,
					],
					'product_ids'     => [
						'type'    => 'array',
						'items'   => [ 'type' => 'integer' ],
						'default' => [],
					],
				],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => [ $this, 'update_key' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
			],
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_key' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
			],
		] );

		register_rest_route( $this->namespace, '/' . $this->rest_base . '/(?P<id>\d+)/revoke', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'revoke_key' ],
				'permission_callback' => [ $this, 'permission_callback' ],
				'args'                => [ 'id' => [ 'type' => 'integer', 'required' => true ] ],
			],
		] );
	}

	public function permission_callback() {
		return Helper::check_rest_user_cap( 'manage_options' );
	}

	public function list_keys() {
		return rest_ensure_response( array_map( [ $this, 'present' ], EmbedKey::all() ) );
	}

	public function create_key( WP_REST_Request $request ) {
		$id  = EmbedKey::create( [
			'label'           => (string) $request->get_param( 'label' ),
			'allowed_origins' => $request->get_param( 'allowed_origins' ),
			'product_scope'   => (string) $request->get_param( 'product_scope' ),
			'product_ids'     => (array) $request->get_param( 'product_ids' ),
		] );
		$row = EmbedKey::find( $id );

		return rest_ensure_response( $this->present( $row, true ) );
	}

	public function update_key( WP_REST_Request $request ) {
		$id  = (int) $request['id'];
		$row = EmbedKey::find( $id );
		if ( ! $row ) {
			return new WP_Error( 'storeengine_embed_key_not_found', __( 'Key not found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		EmbedKey::update( $id, $request->get_params() );

		return rest_ensure_response( $this->present( EmbedKey::find( $id ) ) );
	}

	public function delete_key( WP_REST_Request $request ) {
		$id  = (int) $request['id'];
		$row = EmbedKey::find( $id );
		if ( ! $row ) {
			return new WP_Error( 'storeengine_embed_key_not_found', __( 'Key not found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		if ( ! empty( $row['is_system'] ) ) {
			return new WP_Error( 'storeengine_embed_system_key_protected', __( 'The system key cannot be deleted.', 'storeengine' ), [ 'status' => 403 ] );
		}
		EmbedKey::delete( $id );

		return rest_ensure_response( [ 'deleted' => true, 'id' => $id ] );
	}

	public function revoke_key( WP_REST_Request $request ) {
		$id  = (int) $request['id'];
		$row = EmbedKey::find( $id );
		if ( ! $row ) {
			return new WP_Error( 'storeengine_embed_key_not_found', __( 'Key not found.', 'storeengine' ), [ 'status' => 404 ] );
		}
		if ( ! empty( $row['is_system'] ) ) {
			return new WP_Error( 'storeengine_embed_system_key_protected', __( 'The system key cannot be revoked.', 'storeengine' ), [ 'status' => 403 ] );
		}
		EmbedKey::revoke( $id );

		return rest_ensure_response( $this->present( EmbedKey::find( $id ) ) );
	}

	protected function present( ?array $row, bool $reveal_key = false ): array {
		if ( ! $row ) {
			return [];
		}

		return [
			'id'              => $row['key_id'],
			'label'           => $row['label'],
			'embed_key'       => $reveal_key ? $row['embed_key'] : substr( $row['embed_key'], 0, 12 ) . '…',
			'allowed_origins' => $row['allowed_origins'],
			'product_scope'   => $row['product_scope'],
			'product_ids'     => $row['product_ids'],
			'is_system'       => (bool) $row['is_system'],
			'created_at'      => $row['created_at'],
			'last_used_at'    => $row['last_used_at'],
			'revoked_at'      => $row['revoked_at'],
		];
	}
}
