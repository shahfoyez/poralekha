<?php
namespace StoreEngine\API;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Controller;
use WP_REST_Server;

/**
 * Read-only listing of every role registered on the site — including roles
 * contributed by StoreEngine add-ons and any other active plugin — so store
 * admins can see the full access surface in one place.
 */
class Roles extends WP_REST_Controller {

	/**
	 * Core WordPress roles. Everything else is contributed by a plugin/theme.
	 */
	const CORE_ROLES = [ 'administrator', 'editor', 'author', 'contributor', 'subscriber' ];

	public function __construct() {
		$this->namespace = STOREENGINE_PLUGIN_SLUG . '/v1';
		$this->rest_base = 'roles';
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
			],
		] );
	}

	public function permissions_check(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response
	 */
	public function get_items( $request ) {
		if ( ! class_exists( '\WP_Roles' ) ) {
			return rest_ensure_response( [] );
		}

		$wp_roles = wp_roles();

		// One query for all per-role user counts (WP populates `avail_roles`).
		$counts = count_users();
		$avail  = $counts['avail_roles'] ?? [];

		$data = [];
		foreach ( $wp_roles->roles as $slug => $role ) {
			$caps = array_filter( (array) ( $role['capabilities'] ?? [] ) );

			if ( in_array( $slug, self::CORE_ROLES, true ) ) {
				$type = 'wordpress';
			} elseif ( 0 === strpos( $slug, 'storeengine_' ) ) {
				$type = 'storeengine';
			} else {
				$type = 'custom';
			}

			$data[] = [
				'slug'             => $slug,
				'name'             => translate_user_role( $role['name'] ?? $slug ),
				'type'             => $type,
				'user_count'       => (int) ( $avail[ $slug ] ?? 0 ),
				'capability_count' => count( $caps ),
			];
		}

		// Alphabetical by display name for a stable, scannable list.
		usort( $data, static fn( $a, $b ) => strcasecmp( $a['name'], $b['name'] ) );

		return rest_ensure_response( $data );
	}
}
