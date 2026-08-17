<?php

namespace StoreEngine\Addons\Membership;

use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class HelperAddon {
	public static function get_all_plans( $the_post_id = 0 ) {
		global $wpdb;

		$post_type    = STOREENGINE_MEMBERSHIP_POST_TYPE;
		$meta_args    = 'pm.meta_value LIKE "%basic-global%"';
		$content_meta = self::get_dynamic_meta_values( [ 'current_post_id' => $the_post_id ] );

		$cache_key = 'se_membership_plans_' . md5( wp_json_encode( $content_meta ) );
		$has_cache = wp_cache_get( $cache_key, 'se_membership_plans' );
		if ( is_array( $has_cache ) ) {
			return $has_cache;
		}

		if ( ! empty( $content_meta ) ) {
			foreach ( $content_meta as $meta ) {
				$meta_args .= $wpdb->prepare( ' OR pm.meta_value LIKE %s', '%' . $wpdb->esc_like( $meta ) . '%' );
			}
		}

		$exclude_query = $wpdb->prepare(
			"SELECT p.ID FROM {$wpdb->postmeta} as pm
			INNER JOIN {$wpdb->posts} as p ON pm.post_id = p.ID
			WHERE pm.meta_key = '_storeengine_membership_content_protect_excluded_items'
			AND p.post_type = %s
			AND p.post_status = 'publish'
			AND ($meta_args)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$post_type
		);

		// phpcs:disable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Query prepared above
		$plans = $wpdb->get_results(
			$wpdb->prepare( "
				SELECT p.ID, p.post_name, pm.meta_value
				FROM $wpdb->postmeta AS pm
				INNER JOIN $wpdb->posts AS p ON pm.post_id = p.ID
				RIGHT JOIN $wpdb->postmeta AS pm2 ON pm2.post_id = p.ID AND pm2.meta_key = '_storeengine_membership_priority'
				WHERE pm.meta_key = '_storeengine_membership_content_protect_types'
					AND p.post_type = %s
					AND p.post_status = 'publish'
					AND ($meta_args)
				AND p.ID NOT IN ($exclude_query)
				ORDER BY pm2.meta_value + 0 DESC
			", $post_type
			)
		);

		// phpcs:enable PluginCheck.Security.DirectDB.UnescapedDBParameter, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		$all_plans = [];

		foreach ( $plans as $plan ) {
			$all_plans[ $plan->ID ] = [
				'id'        => $plan->ID,
				'post_name' => $plan->post_name,
				'include'   => ! empty( $plan->meta_value ) ? maybe_unserialize( $plan->meta_value ) : [],
			];
		}

		wp_cache_set( $cache_key, $all_plans, 'se_membership_plans' );

		return $all_plans;
	}

	public static function get_the_content_type() {
		$page_type = '';
		if ( is_archive() ) {
			$page_type = 'is_archive';

			if ( is_category() || is_tag() || is_tax() ) {
				$page_type = 'is_tax';
			} elseif ( is_date() ) {
				$page_type = 'is_date';
			} elseif ( is_author() ) {
				$page_type = 'is_author';
			} elseif ( function_exists( 'is_shop' ) && is_shop() ) {
				$page_type = 'is_woo_shop_page';
			}
		} elseif ( is_home() ) {
			$page_type = 'is_home';
		} elseif ( is_front_page() ) {
			$page_type = 'is_front_page';
		} elseif ( is_singular() ) {
			$page_type = 'is_singular';
		} elseif ( is_admin() ) {
			$screen = get_current_screen();
			if ( isset( $screen->base ) && ( 'post' === $screen->base ) ) {
				$page_type = 'is_singular';
			}
		}//end if

		return $page_type;
	}

	public static function get_dynamic_meta_values( $args = [] ) {
		$current_page_type = self::get_the_content_type();
		$post_type         = get_post_type();
		if ( empty( $post_type ) ) {
			$post_type = '';
		}
		$current_post_type = esc_sql( $post_type );
		$post_id           = absint( $args['current_post_id'] ?? 0 );
		$q_obj             = $post_id ? get_post( $post_id ) : get_queried_object();
		$meta_args         = [];

		switch ( $current_page_type ) {
			case 'is_404':
				$meta_args[] = 'special-404';
				break;
			case 'is_search':
				$meta_args[] = 'special-search';
				break;
			case 'is_front_page':
				$meta_args[] = 'special-front';
				$meta_args[] = "{$current_post_type}|all";
				break;
			case 'is_woo_shop_page':
				$meta_args[] = 'special-woo-shop';
				break;
			case 'is_archive':
			case 'is_tax':
			case 'is_date':
			case 'is_author':
				$meta_args[] = 'basic-archives';
				$meta_args[] = "{$current_post_type}|all|archive";

				if ( 'is_tax' === $current_page_type && ( is_category() || is_tag() || is_tax() ) ) {
					if ( is_object( $q_obj ) && ! empty( $q_obj->taxonomy ) && ! empty( $q_obj->term_id ) ) {
						if ( in_array( $q_obj->taxonomy, [ 'category', 'academy_courses_category' ], true ) ) {
							$meta_args[] = 'post|all|taxarchive|category';
						} elseif ( in_array( $q_obj->taxonomy, [ 'post_tag', 'academy_courses_tag' ], true ) ) {
							$meta_args[] = 'post|all|taxarchive|post_tag';
						}
						$meta_args[] = "{$current_post_type}|all|taxarchive|{$q_obj->taxonomy}";
					}
				} elseif ( 'is_date' === $current_page_type ) {
					$meta_args[] = 'special-date';
				} elseif ( 'is_author' === $current_page_type ) {
					$meta_args[] = 'special-author';
				}
				break;
			case 'is_home':
				$meta_args[] = 'special-blog';
				break;
			default:
				$meta_args[] = 'basic-singulars';
				$meta_args[] = "$current_post_type|all";
				break;
		}//end switch

		if ( $q_obj instanceof WP_Post ) {
			$meta_args[] = "post-{$q_obj->ID}-|";
			// Check parent.
			$parent_id = wp_get_post_parent_id( $q_obj->ID );
			$parent_id = ! empty( $parent_id ) ? $parent_id : 0;
			if ( $parent_id ) {
				$meta_args[] = "postchild-{$parent_id}-|";
			}

			$taxonomies = ! empty( $q_obj->post_type ) ? get_object_taxonomies( $q_obj->post_type ) : [];
			$terms      = wp_get_post_terms( $q_obj->ID, $taxonomies );
			if ( ! empty( $terms ) && is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$meta_args[] = "tax-{$term->term_id}--single-{$term->taxonomy}";
				}
			}
		}

		return apply_filters( 'storeengine_get_dynamic_meta_values', $meta_args, $q_obj );
	}

	public static function get_all_groups() {
		$args = array(
			'post_type'      => 'storeengine_groups',
			'posts_per_page' => - 1,
			'post_status'    => 'publish',
		);

		$groups = get_posts( $args );

		return $groups;
	}

	public static function get_groups_meta_values( $meta_key, $column_name = 'value' ) {
		$meta_values = array();

		$groups = self::get_all_groups();
		if ( is_array( $groups ) ) {
			foreach ( $groups as $group ) {
				$meta_value = get_post_meta( $group->ID, $meta_key, true );
				if ( is_array( $meta_value ) && count( $meta_value ) > 0 ) {
					foreach ( $meta_value as $single_meta ) {
						$meta_values[] = 'all' !== $column_name ? $single_meta[ $column_name ] : $single_meta;
					}
				}
			}
		}

		return array_unique( $meta_values );
	}

	public static function included_user_roles() {
		$user_roles = self::get_groups_meta_values( '_storeengine_membership_user_roles' );

		return $user_roles;
	}

	public static function get_user_role_by_id( $user_id ) {
		if ( is_numeric( $user_id ) ) {
			$user = get_userdata( $user_id );
			if ( $user ) {
				return $user->roles;
			}
		}
	}

	public static function current_user_can_access( $user_id ) {
		$included_roles = self::included_user_roles();
		$user_role      = self::get_user_role_by_id( $user_id ) ?? [];
		$matched        = array_intersect( $included_roles, $user_role );

		return ! empty( $matched );
	}

	public static function is_plan_expired( $user_id ) {
		$user_membership_meta_key = '_storeengine_user_membership_data';
		$user_meta                = get_user_meta( $user_id, $user_membership_meta_key, true );

		$expired      = false;
		$current_date = gmdate( 'Y-m-d' );
		if ( ! empty( $user_meta ) ) {
			foreach ( $user_meta as $group ) {
				$is_enabled_expiration = $group['expiration_date']['is_enable_expiration'] ?? 0;
				$expiration_date       = $group['expiration_date']['specific_date'] ?? '';

				if ( $is_enabled_expiration && strtotime( $expiration_date ) && $current_date > $expiration_date ) {
					$expired = true;
					break;
				}
			}
		}

		return $expired;
	}


}
