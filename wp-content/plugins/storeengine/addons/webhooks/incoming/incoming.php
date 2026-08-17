<?php

namespace StoreEngine\Addons\Webhooks\Incoming;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Incoming webhooks coordinator.
 *
 * Where the (outgoing) Webhooks addon lets StoreEngine *announce* events to the
 * world, incoming webhooks let the world *drive* StoreEngine: a 3PL POSTs
 * "order shipped", an ERP pushes stock levels, a no-code tool (Zapier / Make /
 * n8n) creates a customer. Each incoming webhook is a config (endpoint key +
 * secret + mapped action) stored as a `storeengine_incoming_webhook` post; the
 * public receiver route authenticates the request and runs the mapped action
 * (or just fires an internal hook for automation).
 */
class Incoming {

	// WordPress caps post type names at 20 chars, so the internal slug is terse.
	// The public REST base stays the readable `incoming-webhook`.
	const POST_TYPE = 'storeengine_in_hook';

	const META_KEY    = '_storeengine_incoming_endpoint_key';
	const META_SECRET = '_storeengine_incoming_secret';
	const META_AUTH   = '_storeengine_incoming_auth_type';
	const META_ACTION = '_storeengine_incoming_action';

	public static function init() {
		$self = new self();

		add_action( 'init', [ $self, 'register_post_type' ] );
		add_action( 'rest_api_init', [ $self, 'register_meta' ] );
		add_action( 'rest_api_init', [ Receiver::class, 'register_routes' ] );

		// Auto-provision an endpoint key + secret the first time a config is saved
		// so the admin never has to hand-craft them (and they can't be blank).
		add_action( 'rest_after_insert_' . self::POST_TYPE, [ $self, 'ensure_credentials' ], 10, 1 );

		// Deferred processing path (opt-in via the `.../defer` filter) — mirrors the
		// outgoing addon's Action Scheduler delivery.
		add_action( 'storeengine/incoming_webhook/process', [ Processor::class, 'process_scheduled' ], 10, 1 );
	}

	/**
	 * Register the config post type. Admin CRUD rides the default REST posts
	 * controller (same approach as the outgoing `storeengine_webhook` type).
	 */
	public function register_post_type() {
		register_post_type(
			self::POST_TYPE,
			[
				'labels'                => [
					'name'          => esc_html__( 'Incoming Webhooks', 'storeengine' ),
					'singular_name' => esc_html__( 'Incoming Webhook', 'storeengine' ),
				],
				'public'                => false,
				'publicly_queryable'    => false,
				'show_ui'               => false,
				'show_in_menu'          => false,
				'hierarchical'          => false,
				'rewrite'               => false,
				'query_var'             => false,
				'has_archive'           => false,
				'delete_with_user'      => false,
				'supports'              => [ 'title', 'author', 'custom-fields' ],
				'show_in_rest'          => true,
				'rest_base'             => 'incoming-webhook',
				'rest_namespace'        => STOREENGINE_PLUGIN_SLUG . '/v1',
				'rest_controller_class' => 'WP_REST_Posts_Controller',
				'capability_type'       => 'post',
				'capabilities'          => [
					'edit_post'          => 'manage_options',
					'read_post'          => 'manage_options',
					'delete_post'        => 'manage_options',
					'delete_posts'       => 'manage_options',
					'edit_posts'         => 'manage_options',
					'edit_others_posts'  => 'manage_options',
					'publish_posts'      => 'manage_options',
					'read_private_posts' => 'manage_options',
					'create_posts'       => 'manage_options',
				],
			]
		);
	}

	public function register_meta() {
		register_meta( 'post', self::META_KEY, [
			'object_subtype'    => self::POST_TYPE,
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => [ $this, 'can_manage' ],
		] );

		register_meta( 'post', self::META_SECRET, [
			'object_subtype'    => self::POST_TYPE,
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_text_field',
			'auth_callback'     => [ $this, 'can_manage' ],
		] );

		register_meta( 'post', self::META_AUTH, [
			'object_subtype'    => self::POST_TYPE,
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_key',
			'auth_callback'     => [ $this, 'can_manage' ],
		] );

		register_meta( 'post', self::META_ACTION, [
			'object_subtype'    => self::POST_TYPE,
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => true,
			'sanitize_callback' => 'sanitize_key',
			'auth_callback'     => [ $this, 'can_manage' ],
		] );
	}

	public function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Ensure a freshly-created config has a URL key and (for the default HMAC
	 * mode) a signing secret. Existing values are never overwritten so the admin
	 * can rotate them deliberately.
	 *
	 * @param \WP_Post $post
	 */
	public function ensure_credentials( $post ) {
		$id = $post->ID;

		if ( ! get_post_meta( $id, self::META_KEY, true ) ) {
			update_post_meta( $id, self::META_KEY, self::generate_key() );
		}

		if ( ! get_post_meta( $id, self::META_SECRET, true ) ) {
			update_post_meta( $id, self::META_SECRET, self::generate_secret() );
		}

		if ( ! get_post_meta( $id, self::META_AUTH, true ) ) {
			update_post_meta( $id, self::META_AUTH, 'hmac' );
		}
	}

	/**
	 * URL-safe, unguessable endpoint key. This is the routable part of the
	 * public URL, so it must be unique across configs.
	 */
	public static function generate_key(): string {
		do {
			$key = wp_generate_password( 24, false, false );
		} while ( self::find_by_key( $key ) );

		return $key;
	}

	public static function generate_secret(): string {
		return wp_generate_password( 48, true, true );
	}

	/**
	 * Resolve a config post by its endpoint key.
	 *
	 * @param string $key
	 *
	 * @return \WP_Post|null
	 */
	public static function find_by_key( string $key ) {
		if ( '' === $key ) {
			return null;
		}

		$posts = get_posts( [
			'post_type'        => self::POST_TYPE,
			'post_status'      => [ 'publish', 'draft' ],
			'numberposts'      => 1,
			'meta_key'         => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			'meta_value'       => $key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			'suppress_filters' => false,
		] );

		return $posts ? $posts[0] : null;
	}

	/**
	 * Full public URL for a config's endpoint key.
	 */
	public static function endpoint_url( string $key ): string {
		return rest_url( STOREENGINE_PLUGIN_SLUG . '/v1/incoming/' . $key );
	}
}
