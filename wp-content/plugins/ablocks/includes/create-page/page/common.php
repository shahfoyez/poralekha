<?php
namespace ABlocks\CreatePage\Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

abstract class Common {
	protected const DEFAULT_STATUS = 'publish';
	protected string $page_type;
	protected ?string $settings_field = null;

	protected string $slug;
	protected string $title;
	protected string $content;
	protected string $status = 'publish';
	protected array $allowed_status = [ 'publish', 'draft' ];


	public static function init() : void {
		// ensure WP_Rewrite is initialized
		if ( ! isset( $GLOBALS['wp_rewrite'] ) ) {
			global $wp_rewrite;
			// phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
			$wp_rewrite = new \WP_Rewrite();
		}

		try {
			( new static() )->create();
		} catch ( \Error $e ) {
			echo esc_html( $e->getMessage() );
		}

	}

	private function create(): void {
		$page_id = wp_insert_post( new \WP_Post( (object) [
			'ID'           => 0,
			'post_title'   => sanitize_text_field( $this->title ),
			'post_content' => ( $this->content ),
			// phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
			'post_status'  => in_array( $this->status, $this->allowed_status ) ? $this->status : self::DEFAULT_STATUS,
			'post_name'    => empty( $this->slug ) ? sanitize_title( $this->title ) : sanitize_title( $this->slug ),
			'post_type'    => 'page',
		] ) );
		if (
			! $this->is_page_exists( $this->page_type ) &&
			! is_wp_error( $page_id )
		) {
			update_post_meta( $page_id, 'ablock_page_type', $this->page_type );
			$settings_field = (string) $this->settings_field;
			if ( ! empty( $settings_field ) ) {

				$ablocks_settings = json_decode( get_option( ABLOCKS_SETTINGS_NAME, '{}' ), true );

				$ablocks_settings[ $settings_field ] = $page_id;
				update_option( ABLOCKS_SETTINGS_NAME, wp_json_encode( $ablocks_settings ) );

			}
		}
	}
	protected function is_page_exists( string $meta_value ) :  bool {
		global $wpdb;
		$query = "SELECT COUNT(*) FROM {$wpdb->postmeta} 
            WHERE meta_key = %s 
			AND meta_value = %s";
		// phpcs:ignore  WordPress.DB.DirectDatabaseQuery.DirectQuery,  WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		return intval( $wpdb->get_var( $wpdb->prepare( $query, 'ablock_page_type', $meta_value ) ) ) > 0;
	}
}
