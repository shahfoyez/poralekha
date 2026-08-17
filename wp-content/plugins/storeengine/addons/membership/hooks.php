<?php

namespace StoreEngine\Addons\Membership;

use StoreEngine\Classes\Integration;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Hooks {
	protected int $post_id = 0;

	public static function init() {
		$self = new self();
		add_filter( 'storeengine/backend_scripts_data', [ $self, 'add_user_roles' ] );
		add_action( 'wp_enqueue_scripts', [ $self, 'enqueue_scripts' ] );
		add_action( 'save_post', array( $self, 'set_post_id' ), 10, 3 );
		add_action( 'save_post_' . STOREENGINE_MEMBERSHIP_POST_TYPE, array( $self, 'sync_product_titles' ), 30, 2 );
		add_action( 'updated_post_meta', array( $self, 'set_user_meta_data' ), 10, 4 );
		add_filter( 'storeengine/admin_menu_list', [ $self, 'admin_menu_items' ] );
		add_filter( 'display_post_states', array( $self, 'add_display_post_states' ), 10, 2 );
		add_action( 'delete_post_storeengine_groups', [ $self, 'handle_access_group_deletion' ] );
		add_action( 'storeengine/integrations/created', [ $self, 'clear_cache' ] );
	}

	public function clear_cache( Integration $integration ) {
		if ( 'storeengine/membership-addon' !== $integration->get_provider() ) {
			return;
		}

		wp_cache_flush_group( 'se_membership_plans' );
	}

	public function admin_menu_items( array $items ): array {
		return array_merge( $items, [
			STOREENGINE_PLUGIN_SLUG . '-membership-rules' => [
				'title'      => __( 'Access Rules', 'storeengine' ),
				'capability' => 'manage_options',
				// Both slugs are folded into the dedicated "Membership" menu group
				// (see StoreEngine\Admin\Menu::menu_groups()); canonical_order()
				// pins Access Rules=80, Members=81 so the order never drifts.
				'priority'   => 65,
			],
			STOREENGINE_PLUGIN_SLUG . '-membership-members' => [
				'title'      => __( 'Members', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 66,
			],
			STOREENGINE_PLUGIN_SLUG . '-membership-analytics' => [
				'title'      => __( 'Analytics', 'storeengine' ),
				'capability' => 'manage_options',
				'priority'   => 67,
			],
		] );
	}

	public function enqueue_scripts() {
		wp_enqueue_style( 'storeengine-membership-styles', STOREENGINE_MEMBERSHIP_ASSETS_DIR . 'css/style.css', [], STOREENGINE_MEMBERSHIP_VERSION );
	}

	public function add_user_roles( $script_data ) {
		$script_data['user_roles'] = Helper::get_all_roles();
		$script_data['all_posts']  = $this->get_all_posts();

		return $script_data;
	}

	public function get_all_posts( array $arg = [] ) {
		$post_type = ! empty( $arg['postType'] ) ? $arg['postType'] : 'page';
		$postId    = ! empty( $arg['postId'] ) ? $arg['postId'] : 0;
		$keyword   = ! empty( $arg['keyword'] ) ? $arg['keyword'] : '';

		if ( $postId ) {
			$args = array(
				'post_type' => $post_type,
				'p'         => $postId,
			);
		} else {
			$args = array(
				'post_type'      => $post_type,
				'posts_per_page' => 10,
			);
			if ( ! empty( $keyword ) ) {
				$args['s'] = $keyword;
			}
			if ( ! current_user_can( 'manage_options' ) ) {
				$args['author'] = get_current_user_id();
			}
		}
		$results = array();
		$posts   = get_posts( $args );
		if ( is_array( $posts ) ) {
			foreach ( $posts as $post ) {
				$results[] = array(
					'label' => $post->post_title,
					'value' => $post->ID,
				);
			}
		}

		return $results;
	}

	public function set_post_id( $post_id, $post_after, $post_before ) {
		if ( STOREENGINE_MEMBERSHIP_POST_TYPE !== $post_after->post_type ) {
			return;
		}

		// Clear cache
		wp_cache_delete( $post_id, 'post_meta' );
		wp_cache_flush_group( 'se_membership_plans' );

		$this->post_id = absint( $post_id );
	}

	public function set_user_meta_data( $meta_id, $object_id, $meta_key, $meta_value ) {
		$user_roles_meta = '_storeengine_membership_user_roles';

		if ( ( $this->post_id !== $object_id ) && ( $user_roles_meta !== $meta_key ) ) {
			return;
		}

		// Reset.
		$this->post_id = 0;

		$user_roles = get_post_meta( $object_id, $user_roles_meta, true );

		if ( ! empty( $user_roles ) ) {
			$roles = [];
			foreach ( $user_roles as $role ) {
				$roles[] = $role['value'];
			}

			$membership_user_ids = get_users( [
				'role__in' => $roles,
				'fields'   => 'ID',
			] );

			$non_membership_user_ids = get_users( [
				'role__not_in' => $roles,
				'fields'       => 'ID',
			] );

			$this->remove_non_membership_user_meta_data( $non_membership_user_ids, $object_id );
			$this->add_membership_user_meta_data( $object_id, $membership_user_ids );
		}
	}

	public function remove_non_membership_user_meta_data( $non_membership_user_ids = [], $object_id = '' ) {
		if ( empty( $non_membership_user_ids ) || '' === $object_id ) {
			return;
		}

		$user_membership_meta_key = '_storeengine_user_membership_data';

		foreach ( $non_membership_user_ids as $user_id ) {
			$existing_data = get_user_meta( $user_id, $user_membership_meta_key, true );
			$existing_data = is_array( $existing_data ) ? $existing_data : [];

			if ( isset( $existing_data[ $object_id ] ) ) {
				unset( $existing_data[ $object_id ] );
				update_user_meta( $user_id, $user_membership_meta_key, $existing_data );

				do_action( 'storeengine/membership/user_removed_from_group', $user_id, $object_id );
			}
		}
	}

	public function add_membership_user_meta_data( $object_id = '', $membership_user_ids = [] ) {
		if ( empty( $membership_user_ids ) || ( '' === $object_id ) ) {
			return;
		}

		$content_protects_meta      = '_storeengine_membership_content_protect_types';
		$membership_expiration_meta = '_storeengine_membership_expiration';
		$content_protects_data      = get_post_meta( $object_id, $content_protects_meta, true );
		$membership_expiration_data = get_post_meta( $object_id, $membership_expiration_meta, true );
		$user_membership_meta_key   = '_storeengine_user_membership_data';

		foreach ( $membership_user_ids as $user_id ) {
			$existing_data = get_user_meta( $user_id, $user_membership_meta_key, true );
			$existing_data = is_array( $existing_data ) ? $existing_data : [];

			$is_new = ! isset( $existing_data[ $object_id ] );

			$existing_data[ $object_id ] = [
				'content_protect_types' => $content_protects_data,
				'expiration_date'       => $membership_expiration_data,
			];

			update_user_meta( $user_id, $user_membership_meta_key, $existing_data );

			if ( $is_new ) {
				do_action( 'storeengine/membership/user_added_to_group', $user_id, $object_id );
			}
		}
	}

	/**
	 * Keep each membership's auto-created product title in sync with the access
	 * group name, so a renamed group doesn't leave a stale product title on the
	 * checkout / order line. Only auto-created products (stamped by
	 * IntegrationTrait::create_product) are touched — never a merchant's
	 * predefined product.
	 *
	 * @param int      $post_id
	 * @param \WP_Post $post
	 */
	public function sync_product_titles( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$integrations = Helper::get_integration_repository_by_id( 'storeengine/membership-addon', (int) $post_id );
		if ( empty( $integrations ) ) {
			return;
		}

		$seen = [];
		foreach ( $integrations as $repository ) {
			$product_id = (int) $repository->price->get_product_id();
			if ( ! $product_id || isset( $seen[ $product_id ] ) ) {
				continue;
			}
			$seen[ $product_id ] = true;

			$is_auto = 'storeengine/membership-addon' === get_post_meta( $product_id, '_storeengine_integration_product', true );
			if ( ! $is_auto ) {
				continue;
			}

			if ( get_the_title( $product_id ) !== $post->post_title ) {
				wp_update_post( [
					'ID'         => $product_id,
					'post_title' => $post->post_title,
				] );
			}
		}
	}

	public function add_display_post_states( $post_states, $post ) {
		if ( (int) Helper::get_settings( 'membership_pricing_page' ) === $post->ID ) {
			$post_states['storeengine_page_for_membership_pricing'] = __( 'StoreEngine Membership Pricing Page', 'storeengine' );
		}

		return $post_states;
	}

	public function handle_access_group_deletion( int $post_id ) {
		$integrations_repository = Helper::get_integration_repository_by_id( 'storeengine/membership-addon', $post_id );

		foreach ( $integrations_repository as $integration ) {
			$integration->integration->delete();
		}
	}
}
