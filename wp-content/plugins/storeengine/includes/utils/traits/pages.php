<?php

namespace StoreEngine\Utils\traits;

use StoreEngine\Admin\Settings\Base as BaseSettings;
use StoreEngine\Utils\Fs;
use StoreEngine\Utils\Helper;

trait Pages {
	public static function get_store_page_contents( bool $prefix = false ): array {
		// Set the locale to the store locale to ensure pages are created in the correct language.
		Helper::switch_to_site_locale();
		/**
		 * Determines which pages are created during install.
		 *
		 * @since 0.0.5
		 */
		$page_lists = apply_filters(
			'storeengine/store_page_contents',
			[
				'shop_page'                   => [
					'title'   => _x( 'Store Shop', 'Page title', 'storeengine' ),
					'slug'    => ! $prefix ? _x( 'shop', 'Page slug', 'storeengine' ) : _x( 'store-shop', 'Page slug', 'storeengine' ),
					'content' => '',
				],
				'cart_page'                   => [
					'title'   => _x( 'Store Cart', 'Page title', 'storeengine' ),
					'slug'    => ! $prefix ? _x( 'cart', 'Page slug', 'storeengine' ) : _x( 'store-cart', 'Page slug', 'storeengine' ),
					'content' => Fs::render_template( STOREENGINE_TEMPLATE_PATH . 'page-content/cart.php' ),
				],
				'checkout_page'               => [
					'title'   => _x( 'Store Checkout', 'Page title', 'storeengine' ),
					'slug'    => ! $prefix ? _x( 'checkout', 'Page slug', 'storeengine' ) : _x( 'store-checkout', 'Page slug', 'storeengine' ),
					'content' => Fs::render_template( STOREENGINE_TEMPLATE_PATH . 'page-content/checkout.php' ),
				],
				'thankyou_page'               => [
					'title'   => _x( 'Store Thank You', 'Page title', 'storeengine' ),
					'slug'    => ! $prefix ? _x( 'thank-you', 'Page slug', 'storeengine' ) : _x( 'store-thank-you', 'Page slug', 'storeengine' ),
					'content' => Fs::render_template( STOREENGINE_TEMPLATE_PATH . 'page-content/thankyou.php' ),
				],
				'dashboard_page'              => [
					'title'   => _x( 'Store Dashboard', 'Page title', 'storeengine' ),
					'slug'    => ! $prefix ? _x( 'dashboard', 'Page slug', 'storeengine' ) : _x( 'store-dashboard', 'Page slug', 'storeengine' ),
					'content' => Fs::render_template( STOREENGINE_TEMPLATE_PATH . 'page-content/dashboard.php' ),
				],
				'affiliate_registration_page' => [
					'title'   => _x( 'Store Affiliate Registration', 'Page title', 'storeengine' ),
					'slug'    => ! $prefix ? _x( 'affiliate-registration', 'Page slug', 'storeengine' ) : _x( 'store-affiliate-registration', 'Page slug', 'storeengine' ),
					'content' => '<!-- wp:storeengine/shortcode {"shortcode":"storeengine/storeengine_affiliate_application"} /-->',
				],
				'membership_pricing_page'     => [
					'title'   => _x( 'Store Membership Pricing', 'Page title', 'storeengine' ),
					'slug'    => ! $prefix ? _x( 'membership-pricing', 'Page slug', 'storeengine' ) : _x( 'store-membership-pricing', 'Page slug', 'storeengine' ),
					'content' => '<!-- wp:storeengine/shortcode {"shortcode":"storeengine/storeengine_membership_pricing"} /-->',
				],
			]
		);


		Helper::restore_locale();

		return $page_lists;
	}

	public static function create_initial_pages( bool $prefix = false ) {
		global $storeengine_settings;

		// Prepare settings data for update.
		$settings = (array) $storeengine_settings;

		// WordPress sets fresh_site to 0 after a page gets published.
		// Prevent fresh_site option from being set to 0 so that we can use it for further customizations.
		remove_action( 'publish_page', '_delete_option_fresh_site', 0 );

		foreach ( self::get_store_page_contents( $prefix ) as $key => $page ) {
			$parent  = ! empty( $page['parent'] ) ? Helper::get_settings( $page['parent'], 0 ) : 0;
			$status  = ! empty( $page['post_status'] ) ? $page['post_status'] : 'publish';
			$post_id = self::create_page(
				esc_sql( $page['slug'] ),
				$key,
				$page['title'],
				$page['content'],
				$parent,
				$status
			);

			if ( $post_id ) {
				$settings[ $key ] = $post_id;
			}
		}



		BaseSettings::save_settings( $settings );
	}

	public static function create_page( $slug, $settings_key, $title, $content = '', $parent = 0, $status = 'publish' ): int {
		global $storeengine_settings, $wpdb;
		$settings = (array) $storeengine_settings;

		$settings_value = (int) ( $settings[ $settings_key ] ?? 0 );
		if ( $settings_value > 0 ) {
			$page_object = get_post( $settings_value );

			if ( $page_object && 'page' === $page_object->post_type && ! in_array( $page_object->post_status, [ 'pending', 'trash', 'future', 'auto-draft' ], true ) ) {
				// Valid page is already in place.
				return $settings_value;
			}
		}

		if ( strlen( $content ) > 0 ) {
			// Search for an existing page with the specified page content (typically a shortcode).
			$shortcode        = str_replace( [ '<!-- storeengine:shortcode -->', '<!-- /storeengine:shortcode -->' ], '', $content );
			$valid_page_found = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status NOT IN ( 'pending', 'trash', 'future', 'auto-draft' ) AND post_content LIKE %s LIMIT 1;", "%{$shortcode}%" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			// Search for an existing page with the specified page slug.
			$valid_page_found = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status NOT IN ( 'pending', 'trash', 'future', 'auto-draft' )  AND post_name = %s LIMIT 1;", $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		if ( $valid_page_found ) {
			return $valid_page_found;
		}

		// Search for a matching valid trashed page.
		if ( strlen( $content ) > 0 ) {
			// Search for an existing page with the specified page content (typically a shortcode).
			$trashed_page_found = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status = 'trash' AND post_content LIKE %s LIMIT 1;", "%{$content}%" ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		} else {
			// Search for an existing page with the specified page slug.
			$trashed_page_found = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->posts WHERE post_type='page' AND post_status = 'trash' AND post_name = %s LIMIT 1;", $slug ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		if ( $trashed_page_found ) {
			$page_id = $trashed_page_found;
			wp_update_post( [
				'ID'             => $page_id,
				'post_status'    => $status,
				'comment_status' => 'closed',
			] );
		} else {
			$page_data = [
				'post_status'    => $status,
				'post_type'      => 'page',
				'post_author'    => 1,
				'post_name'      => $slug,
				'post_title'     => $title,
				'post_content'   => $content,
				'post_parent'    => $parent,
				'comment_status' => 'closed',
			];
			$page_id = wp_insert_post( $page_data );

			if ( is_wp_error( $page_id ) ) {
				return 0;
			}

			/**
			 * Fire once StoreEngine page as been created.
			 *
			 * @since 0.0.5
			 *
			 * @param int $page_id Post ID
			 * @param array $page_data Post data.
			 */
			do_action( 'storeengine/page_created', $page_id, $page_data );
		}

		if ( $page_id ) {
			// Set template for handling block-theme compatibility.
			update_post_meta( $page_id, '_wp_page_template', 'storeengine-canvas.php' );

			return $page_id;
		}

		return 0;
	}
}
