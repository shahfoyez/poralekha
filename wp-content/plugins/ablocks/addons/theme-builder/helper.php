<?php
namespace ABlocksThemeBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Helper {
	private static $current_page_type = null;

	protected static $current_page_data = [];

	public static function get_template_id( $type ) {
		$option = [
			'location'  => 'ablocks_tb_template_display_locations',
			'exclusion' => 'ablocks_tb_template_not_display_locations',
			'users'     => 'ablocks_tb_template_target_user_roles',
		];

		$ablocks_templates = self::get_posts_by_conditions( 'ablocks_tb', $option );
		foreach ( $ablocks_templates as $template ) {
			if ( get_post_meta( absint( $template['id'] ), 'ablocks_tb_template_type', true ) === $type ) {
				if ( function_exists( 'pll_current_language' ) ) {
					if ( pll_current_language( 'slug' ) === pll_get_post_language( $template['id'], 'slug' ) ) {
						return $template['id'];
					}
				} else {
					return $template['id'];
				}
			}
		}

		return '';
	}

	public static function get_posts_by_conditions( $post_type, $option ) {
		global $wpdb;
		global $post;

		$post_type = $post_type ? esc_sql( $post_type ) : esc_sql( $post->post_type );

		if ( is_array( self::$current_page_data ) && isset( self::$current_page_data[ $post_type ] ) ) {
			return self::$current_page_data[ $post_type ];
		}

		$current_page_type = self::get_current_page_type();

		self::$current_page_data[ $post_type ] = [];

		$option['current_post_id'] = get_the_ID();
		$meta_header               = self::get_meta_option_post( $post_type, $option );

		if ( false === $meta_header ) {
			$current_post_type = esc_sql( get_post_type() );
			$current_post_id   = false;
			$q_obj             = \get_queried_object();
			$current_id        = esc_sql( get_the_ID() );

			if ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
				$default_lang      = apply_filters( 'wpml_default_language', '' );
				$current_lang      = apply_filters( 'wpml_current_language', '' );

				if ( $default_lang !== $current_lang ) {
					$current_post_type = get_post_type( $current_id );
					$current_id        = apply_filters( 'wpml_object_id', $current_id, $current_post_type, true, $default_lang );
				}
			}

			$location = isset( $option['location'] ) ? $option['location'] : '';
			$location = esc_sql( $location );

			$query  = "
				SELECT p.ID, pm.meta_value 
				FROM {$wpdb->postmeta} AS pm
				INNER JOIN {$wpdb->posts} AS p ON pm.post_id = p.ID
				WHERE pm.meta_key = %s 
				AND p.post_type = %s 
				AND p.post_status = 'publish'
			";

			$meta_conditions = [
				"pm.meta_value LIKE '%s:5:\"value\";s:12:\"basic-global\";%'"
			];

			switch ( $current_page_type ) {
				case 'is_404':
					$meta_conditions[] = "pm.meta_value LIKE '%\"special-404\"%'";
					break;
				case 'is_search':
					$meta_conditions[] = "pm.meta_value LIKE '%\"special-search\"%'";
					break;
				case 'is_archive':
				case 'is_tax':
				case 'is_date':
				case 'is_author':
					$meta_conditions[] = "pm.meta_value LIKE '%\"basic-archives\"%'";
					$meta_conditions[] = $wpdb->prepare(
						'pm.meta_value LIKE %s',
						"%\"{$current_post_type}|all|archive\"%"
					);

					if ( 'is_tax' === $current_page_type && is_object( $q_obj ) ) {
						$meta_conditions[] = $wpdb->prepare(
							'pm.meta_value LIKE %s',
							"%\"{$current_post_type}|all|taxarchive|{$q_obj->taxonomy}\"%"
						);
						$meta_conditions[] = $wpdb->prepare(
							'pm.meta_value LIKE %s',
							"%\"tax-{$q_obj->term_id}\"%"
						);
					} elseif ( 'is_date' === $current_page_type ) {
						$meta_conditions[] = "pm.meta_value LIKE '%\"special-date\"%'";
					} elseif ( 'is_author' === $current_page_type ) {
						$meta_conditions[] = "pm.meta_value LIKE '%\"special-author\"%'";
					}
					break;
				case 'is_home':
					$meta_conditions[] = "pm.meta_value LIKE '%\"special-blog\"%'";
					break;
				case 'is_front_page':
					$current_post_id = $current_id;
					$meta_conditions[] = "pm.meta_value LIKE '%\"special-front\"%'";
					$meta_conditions[] = $wpdb->prepare(
						'pm.meta_value LIKE %s',
						"%\"{$current_post_type}|all\"%"
					);
					$meta_conditions[] = $wpdb->prepare(
						'pm.meta_value LIKE %s',
						"%\"post-{$current_id}\"%"
					);
					break;
				case 'is_singular':
					$current_post_id = $current_id;
					$meta_conditions[] = "pm.meta_value LIKE '%\"basic-singulars\"%'";
					$meta_conditions[] = $wpdb->prepare(
						'pm.meta_value LIKE %s',
						"%\"{$current_post_type}|all\"%"
					);
					$meta_conditions[] = $wpdb->prepare(
						'pm.meta_value LIKE %s',
						"%\"post-{$current_id}\"%"
					);

					$taxonomies = get_object_taxonomies( $q_obj->post_type );
					$terms      = wp_get_post_terms( $q_obj->ID, $taxonomies );

					foreach ( $terms as $term ) {
						$meta_conditions[] = $wpdb->prepare(
							'pm.meta_value LIKE %s',
							"%\"tax-{$term->term_id}-single-{$term->taxonomy}\"%"
						);
					}
					break;
				case 'is_woo_shop_page':
					$meta_conditions[] = "pm.meta_value LIKE '%\"special-woo-shop\"%'";
					break;
				case '':
					$current_post_id = $current_id;
					break;
			}//end switch

			$meta_where = implode( ' OR ', $meta_conditions );
			// phpcs:ignore  WordPress.DB.PreparedSQL.NotPrepared 
			$query      = $wpdb->prepare( $query, $location, $post_type ) . " AND ({$meta_where}) ORDER BY p.post_date DESC";

			// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared 
			$posts = $wpdb->get_results( $query );

			foreach ( $posts as $local_post ) {
				self::$current_page_data[ $post_type ][ $local_post->ID ] = [
					'id'       => $local_post->ID,
					'location' => unserialize( $local_post->meta_value ),
				];
			}

			$option['current_post_id'] = $current_post_id;

			self::remove_exclusion_rule_posts( $post_type, $option );
			self::remove_user_rule_posts( $post_type, $option );
		}//end if

		return self::$current_page_data[ $post_type ];
	}

	public static function remove_exclusion_rule_posts( $post_type, $option ) {
		$exclusion       = isset( $option['exclusion'] ) ? $option['exclusion'] : '';
		$current_post_id = isset( $option['current_post_id'] ) ? $option['current_post_id'] : false;

		foreach ( self::$current_page_data[ $post_type ] as $c_post_id => $c_data ) {
			$exclusion_rules = get_post_meta( $c_post_id, $exclusion, true );
			$is_exclude      = self::parse_layout_display_condition( $current_post_id, $exclusion_rules );

			if ( $is_exclude ) {
				unset( self::$current_page_data[ $post_type ][ $c_post_id ] );
			}
		}
	}

	public static function remove_user_rule_posts( $post_type, $option ) {
		$users           = isset( $option['users'] ) ? $option['users'] : '';
		$current_post_id = isset( $option['current_post_id'] ) ? $option['current_post_id'] : false;

		foreach ( self::$current_page_data[ $post_type ] as $c_post_id => $c_data ) {
			$user_rules = get_post_meta( $c_post_id, $users, true );
			$is_user    = self::parse_user_role_condition( $current_post_id, $user_rules );

			if ( ! $is_user ) {
				unset( self::$current_page_data[ $post_type ][ $c_post_id ] );
			}
		}
	}

	public static function get_current_page_type() {
		if ( null === self::$current_page_type ) {
			$page_type  = '';
			$current_id = false;

			if ( is_404() ) {
				$page_type = 'is_404';
			} elseif ( is_search() ) {
				$page_type = 'is_search';
			} elseif ( is_archive() ) {
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
				$page_type  = 'is_front_page';
				$current_id = get_the_id();
			} elseif ( is_singular() ) {
				$page_type  = 'is_singular';
				$current_id = get_the_id();
			} else {
				$current_id = get_the_id();
			}//end if

			self::$current_page_data['ID'] = $current_id;
			self::$current_page_type       = $page_type;
		}//end if

		return self::$current_page_type;
	}

	public static function get_meta_option_post( $post_type, $option ) {
		$page_meta = ( isset( $option['page_meta'] ) && '' != $option['page_meta'] ) ? $option['page_meta'] : false;

		if ( false !== $page_meta ) {
			$current_post_id = isset( $option['current_post_id'] ) ? $option['current_post_id'] : false;
			$meta_id         = get_post_meta( $current_post_id, $option['page_meta'], true );

			if ( false !== $meta_id && '' != $meta_id ) {
				self::$current_page_data[ $post_type ][ $meta_id ] = array(
					'id'       => $meta_id,
					'location' => '',
				);

				return self::$current_page_data[ $post_type ];
			}
		}

		return false;
	}

	public static function parse_layout_display_condition( $post_id, $rules ) {
		$display           = false;
		$current_post_type = get_post_type( $post_id );
		if ( is_array( $rules ) && count( $rules ) ) {
			foreach ( $rules as $key => $rule_item ) {
				$rule = $rule_item['value'];
				if ( strrpos( $rule, 'all' ) !== false ) {
					$rule_case = 'all';
				} else {
					$rule_case = $rule;
				}

				switch ( $rule_case ) {
					case 'basic-global':
						$display = true;
						break;

					case 'basic-singulars':
						if ( is_singular() ) {
							$display = true;
						}
						break;

					case 'basic-archives':
						if ( is_archive() ) {
							$display = true;
						}
						break;

					case 'special-404':
						if ( is_404() ) {
							$display = true;
						}
						break;

					case 'special-search':
						if ( is_search() ) {
							$display = true;
						}
						break;

					case 'special-blog':
						if ( is_home() ) {
							$display = true;
						}
						break;

					case 'special-front':
						if ( is_front_page() ) {
							$display = true;
						}
						break;

					case 'special-date':
						if ( is_date() ) {
							$display = true;
						}
						break;

					case 'special-author':
						if ( is_author() ) {
							$display = true;
						}
						break;

					case 'special-woo-shop':
						if ( function_exists( 'is_shop' ) && is_shop() ) {
							$display = true;
						}
						break;

					case 'all':
						$rule_data = explode( '|', $rule );

						$post_type     = isset( $rule_data[0] ) ? $rule_data[0] : false;
						$archieve_type = isset( $rule_data[2] ) ? $rule_data[2] : false;
						$taxonomy      = isset( $rule_data[3] ) ? $rule_data[3] : false;
						if ( false === $archieve_type ) {
							$current_post_type = get_post_type( $post_id );

							if ( false !== $post_id && $current_post_type == $post_type ) {
								$display = true;
							}
						} else {
							if ( is_archive() ) {
								$current_post_type = get_post_type();
								if ( $current_post_type == $post_type ) {
									if ( 'archive' == $archieve_type ) {
										$display = true;
									} elseif ( 'taxarchive' == $archieve_type ) {
										$obj              = \get_queried_object();
										$current_taxonomy = '';
										if ( '' !== $obj && null !== $obj ) {
											$current_taxonomy = $obj->taxonomy;
										}

										if ( $current_taxonomy == $taxonomy ) {
											$display = true;
										}
									}
								}
							}
						}//end if
						break;

					case 'specifics':
						if ( isset( $rule_item['specific'] ) && is_array( $rule_item['specific'] ) ) {
							foreach ( $rule_item['specific'] as $specific_page ) {
								$specific_page = $specific_page['value'];
								$specific_data = explode( '-', $specific_page );

								$specific_post_type = isset( $specific_data[0] ) ? $specific_data[0] : false;
								$specific_post_id   = isset( $specific_data[1] ) ? $specific_data[1] : false;
								if ( 'post' == $specific_post_type ) {
									if ( $specific_post_id == $post_id ) {
										$display = true;
									}
								} elseif ( isset( $specific_data[2] ) && ( 'single' == $specific_data[2] ) && 'tax' == $specific_post_type ) {
									if ( is_singular() ) {
										$term_details = get_term( $specific_post_id );

										if ( isset( $term_details->taxonomy ) ) {
											$has_term = has_term( (int) $specific_post_id, $term_details->taxonomy, $post_id );

											if ( $has_term ) {
												$display = true;
											}
										}
									}
								} elseif ( 'tax' == $specific_post_type ) {
									$tax_id = get_queried_object_id();
									if ( $specific_post_id == $tax_id ) {
										$display = true;
									}
								}//end if
							}//end foreach
						}//end if
						break;

					default:
						break;
				}//end switch

				if ( $display ) {
					break;
				}
			}//end foreach
		}//end if

		return $display;
	}

	public static function parse_user_role_condition( $post_id, $rules ) {
		$show_popup = true;

		if ( is_array( $rules ) && count( $rules ) ) {
			$show_popup = false;

			foreach ( $rules as $i => $rule ) {
				if ( ! isset( $rule['value'] ) ) {
					continue;
				}
				$rule = $rule['value'];
				switch ( $rule ) {
					case '':
					case 'all':
						$show_popup = true;
						break;

					case 'logged-in':
						if ( is_user_logged_in() ) {
							$show_popup = true;
						}
						break;

					case 'logged-out':
						if ( ! is_user_logged_in() ) {
							$show_popup = true;
						}
						break;

					default:
						if ( is_user_logged_in() ) {
							$current_user = wp_get_current_user();

							if ( isset( $current_user->roles )
									&& is_array( $current_user->roles )
									&& in_array( $rule, $current_user->roles )
								) {
								$show_popup = true;
							}
						}
						break;
				}//end switch

				if ( $show_popup ) {
					break;
				}
			}//end foreach
		}//end if

		return $show_popup;
	}

	public static function get_info_popup_builder_content() {
		global $wpdb;
		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching 
		return $wpdb->get_results("
			SELECT p.ID, p.post_content
			FROM {$wpdb->prefix}posts AS p
			INNER JOIN {$wpdb->prefix}postmeta AS pm
				ON p.ID = pm.post_id
			WHERE p.post_type = 'ablocks_tb'
			AND p.post_status = 'publish'
			AND pm.meta_key = 'ablocks_tb_template_type'
			AND pm.meta_value = 'info-popup-builder'
		");
	}
}
