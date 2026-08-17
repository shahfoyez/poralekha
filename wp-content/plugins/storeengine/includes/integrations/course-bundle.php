<?php

namespace StoreEngine\Integrations;

use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CourseBundle extends AbstractIntegration {
	const ID = 'storeengine/course-bundle';

	public function setup(): void {
		$this->id          = static::ID;
		$this->label       = __( 'Academy Course Bundle', 'storeengine' );
		$this->items_label = __( 'Select Course (Only paid courses)', 'storeengine' );
		$this->logo        = STOREENGINE_ASSETS_URI . 'images/integrations/academy-lms.svg';
		$this->isEnabled   = defined( 'ACADEMY_VERSION' );
	}

	public function dispatch_hooks(): void {
		add_action( 'academy_pro/templates/course_bundle/single_bundle_enroll_content', [ $this, 'single_bundle_price' ] );
		add_action( 'academy_pro/templates/course_bundle/single_bundle_enroll_content', [ $this, 'single_bundle_enroll_content_form' ] );
		add_action( 'academy_pro/templates/course_bundle/single_bundle_enroll_content', [ $this, 'single_enroll_content' ] );
	}

	public function single_bundle_price() {
		$bundle_id = get_the_ID();
		$type = get_post_meta( $bundle_id, 'academy_course_bundle_type', true );
		// return if the monetized engine is not storeengine
		if ( 'storeengine' !== \Academy\Helper::monetization_engine() || 'free' === $type ) {
			return;
		}

		$bundle_id     = get_the_ID();
		$price         = '';
		$regular_price = '';
		$sale_price    = '';

		if ( \Academy\Helper::is_active_storeengine() ) {
			$integration_repository = Helper::get_integration_repository_by_id( $this->get_id(), $bundle_id );
			if ( ! empty( $integration_repository ) ) {
				$integration_repository = current( $integration_repository );
				$price                  = $integration_repository->price;
				$sale_price             = $price->get_price();
				$price                  = $price->get_price_html();
			}
		}

		ob_start();

		\AcademyPro\Helper::get_template(
			'course-bundle/enroll/price.php',
			apply_filters(
				'academy_pro/single/bundle_price_args', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				array(
					'enrolled'      => false,
					'is_paid'       => true,
					'regular_price' => $regular_price,
					'sale_price'    => $sale_price,
					'price'         => $price,
				),
				$bundle_id
			)
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		echo apply_filters( 'academy/templates/single_course/price_content_args', ob_get_clean(), $bundle_id );
	}

	public function single_enroll_content() {
		// return if the monetized engine is not storeengine
		if ( 'storeengine' !== \Academy\Helper::monetization_engine() ) {
			return;
		}

		$bundle_id                 = get_the_ID();
		$duration                  = \AcademyProCourseBundle\Helper::get_bundle_duration( $bundle_id );
		$total_lessons             = \AcademyProCourseBundle\Helper::get_bundle_lessons( $bundle_id );
		$total_enrolled            = $this->get_bundle_enrolled( $bundle_id );
		$max_students              = \AcademyProCourseBundle\Helper::get_max_students( $bundle_id );
		$total_enroll_count_status = \Academy\Helper::get_settings( 'is_enabled_course_single_enroll_count', true );
		$last_update               = get_the_modified_time( get_option( 'date_format' ), $bundle_id );

		ob_start();

		\AcademyPro\Helper::get_template(
			'course-bundle/enroll/content.php',
			apply_filters(
				'academy_pro/single/bundle_content_args', // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
				array(
					'duration'                  => $duration,
					'total_lessons'             => $total_lessons,
					'total_enroll_count_status' => $total_enroll_count_status,
					'total_enrolled'            => $total_enrolled,
					'max_students'              => $max_students,
					'last_update'               => $last_update,
				),
				$bundle_id
			)
		);

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		echo apply_filters( 'academy/templates/single_course/enroll_content', ob_get_clean(), $bundle_id );
	}

	public function single_bundle_enroll_content_form() {
		global $post;

		$engine = \Academy\Helper::get_settings( 'monetization_engine' );

		if ( 'alms_course_bundle' !== get_post_type( get_the_ID() ) || 'storeengine' !== $engine ) {
			return;
		}

		$integrations = $this->get_integration_repository( $post->ID );
		if ( empty( $integrations ) || ! count( $integrations ) ) {
			return;
		}

		$purchased_bundles = maybe_unserialize( get_user_meta( get_current_user_id(), '_academy_pro_purchased_course_bundles', true ) );
		$purchased_bundles = ! empty( $purchased_bundles ) ? $purchased_bundles : [];

		if ( in_array( $post->ID, $purchased_bundles ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
			Template::get_template( 'integrations/academy-lms/course-bundle-continue.php' );

			return;
		}

		Template::get_template( 'integrations/academy-lms/add-to-cart.php', [
			'integrations'      => $integrations,
			'integration_count' => count( $integrations ),
		] );
	}

	public function get_items( array $args = [] ): array {
		$args   = wp_parse_args( $args, [
			'search' => '',
		] );
		$search = $args['search'];

		$course_query = new \WP_Query(
			[
				'post_type'   => 'academy_courses',
				'post_status' => 'publish',
				's'           => $search,
				'per_page'    => 10,
				'meta_query'  => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					'key'     => 'academy_course_type',
					'value'   => 'paid',
					'compare' => '=',
				],
			]
		);

		$items = [];

		if ( $course_query->have_posts() ) {
			$items = array_map(
				function ( $post ) {
					return (object) [
						'value' => $post->ID,
						'label' => $post->post_title,
					];
				},
				$course_query->posts
			);
		}

		return $items;
	}

	protected function purchase_created( $integration, $order ) {
		$bundle_id         = $integration->get_integration_id();
		$purchased_bundles = $this->get_purchased_bundle_ids( $order->get_user_id() );

		if ( in_array( $bundle_id, $purchased_bundles ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
			return;
		}

		foreach ( $this->get_bundled_course_ids( $bundle_id ) as $course_id ) {
			\Academy\Helper::do_enroll( $course_id, $order->get_user_id() );
		}

		$purchased_bundles[] = $bundle_id;

		$this->save_purchased_bundle_ids( $order->get_user_id(), $purchased_bundles );
	}

	protected function purchase_removed( $integration, $order ) {
		global $wpdb;
		$bundles = $this->get_purchased_bundle_ids( $order->get_user_id() );
		if ( ! in_array( $integration->get_integration_id(), $bundles ) ) { // phpcs:ignore WordPress.PHP.StrictInArray.MissingTrueStrict
			// Early bail, if not purchased.
			return;
		}

		$course_ids             = $this->get_bundled_course_ids( $integration->get_integration_id() );
		$course_ids_placeholder = implode( ',', array_fill( 0, count( $course_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query(
			$wpdb->prepare(
				"
					UPDATE {$wpdb->posts}
					SET post_status='on-hold'
					WHERE post_author=%d
					  AND post_parent IN ($course_ids_placeholder)
					  AND post_type='academy_enrolled';
					",
				$order->get_user_id(),
				...$course_ids
			)
		);

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"
					SELECT ID FROM {$wpdb->posts}
					WHERE post_status='on-hold'
					  AND post_author=%d
					  AND post_parent IN ($course_ids_placeholder)
					  AND post_type='academy_enrolled';
					",
				$order->get_user_id(),
				...$course_ids
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( ! empty( $ids ) ) {
			array_map( 'clean_post_cache', $ids );
		}

		$this->save_purchased_bundle_ids(
			$order->get_user_id(),
			array_filter( $bundles, fn ( $bid ) => $bid !== $integration->get_integration_id() )
		);
	}

	private function get_bundle_enrolled( int $bundle_id ): int {
		global $wpdb;
		$enrolled = 0;

		$integration_repositories = Helper::get_integration_repository_by_id( $this->get_id(), $bundle_id );

		if ( empty( $integration_repositories ) ) {
			return $enrolled;
		}

		$price_ids = [];

		foreach ( $integration_repositories as $integration_repository ) {
			$price_ids[] = $integration_repository->price->get_id();
		}

		$price_ids_formatter = implode( ',', array_fill( 0, count( $price_ids ), '%d' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$enrolled_ids = $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT COUNT(*)
				FROM (
					SELECT DISTINCT opl.order_id
					FROM {$wpdb->prefix}storeengine_order_product_lookup AS opl
						INNER JOIN {$wpdb->prefix}storeengine_orders AS o ON opl.order_id = o.id
					WHERE opl.price_id IN ($price_ids_formatter)
					  AND o.status = 'completed'
				) AS subquery
				",
				...$price_ids
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		return (int) $enrolled_ids;
	}

	private function get_bundled_course_ids( int $bundle_id ): array {
		$courses = get_post_meta( $bundle_id, 'academy_course_bundle_courses_ids', true );

		if ( empty( $courses ) || ! is_array( $courses ) ) {
			$courses = [];
		}

		return wp_list_pluck( $courses, 'value' );
	}

	private function get_purchased_bundle_ids( int $user_id ): array {
		return Formatting::parse_ids( get_user_meta( $user_id, '_academy_pro_purchased_course_bundles', true ) );
	}

	private function save_purchased_bundle_ids( int $user_id, ?array $bundle_ids = null ): void {
		update_user_meta(
			$user_id,
			'_academy_pro_purchased_course_bundles',
			Formatting::parse_ids( $bundle_ids )
		);
	}
}
