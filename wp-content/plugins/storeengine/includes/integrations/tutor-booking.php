<?php
/**
 * Tutor Booking Integration.
 *
 * @noinspection PhpUndefinedNamespaceInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 * @noinspection PhpUndefinedClassInspection
 */


namespace StoreEngine\Integrations;

use StoreEngine\Classes\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TutorBooking extends AbstractIntegration {
	const ID = 'storeengine/tutor-booking';

	public function setup(): void {
		$this->id          = static::ID;
		$this->label       = __( 'Academy Tutor Booking', 'storeengine' );
		$this->items_label = __( 'Booking Access', 'storeengine' );
		$this->logo        = STOREENGINE_ASSETS_URI . 'images/integrations/academy-lms.svg';
		$this->isEnabled   = defined( 'ACADEMY_VERSION' );
	}

	public function dispatch_hooks(): void {
		add_action( 'storeengine/integrations/created', [ $this, 'add_course_meta' ] );
		add_filter( 'academy_pro_booking/storeengine/get_product_price', [ $this, 'get_product_price' ], 10, 2 );
	}

	public function add_course_meta( Integration $integration ) {
		if ( $this->get_id() !== $integration->get_provider() ) {
			return;
		}

		$course_id = $integration->get_integration_id();
		$product   = get_post_meta( $course_id, '_academy_booking_product_id', true );
		if ( empty( $product ) ) {
			update_post_meta( $course_id, '_academy_booking_product_id', $integration->get_product_id() );
			update_post_meta( $course_id, '_academy_booking_type', 'paid' );
			update_post_meta( $integration->get_product_id(), '_academy_booking_id', $course_id );
		}
	}

	public function get_product_price( $args, $booking_id ) {
		$prices = $this->get_integration_repository( $booking_id );

		/** @noinspection SpellCheckingInspection */
		$schedule_time = get_user_meta( $args['user_id'], 'booking_schdule_time_' . $booking_id, true );
		if ( $schedule_time !== $args['booked_schedule_date_time'] ) {
			/** @noinspection SpellCheckingInspection */
			add_user_meta( $args['user_id'], 'booking_schdule_time_' . $booking_id, $args['booked_schedule_date_time'] );
		}

		foreach ( $prices as $price ) {
			return $price->price->get_id();
		}

		return false;
	}

	public function get_items( array $args = [] ): array {
		$args   = wp_parse_args( $args, [
			'search' => '',
		] );
		$search = $args['search'];

		$course_query = new \WP_Query(
			[
				'post_type'   => 'academy_booking',
				'post_status' => 'publish',
				's'           => $search,
				'per_page'    => 10,
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
		$user_id    = $order->get_customer_id();
		$booking_id = $integration->get_integration_id();
		$order_id   = $order->get_id();

		// Try to get the scheduled time from user meta first
		/** @noinspection SpellCheckingInspection */
		$schedule_time = get_user_meta( $user_id, 'booking_schdule_time_' . $booking_id, true );

		// Fallback to booked post meta if user meta is empty
		if ( empty( $schedule_time ) ) {
			$booked_id     = get_post_meta( $order_id, '_academy_order_for_booking_id_' . $booking_id, true );
			$schedule_time = $booked_id ? get_post_meta( $booked_id, '_academy_booked_schedule_time', true ) : '';
		}

		// Proceed only if we have a valid schedule time
		if ( ! empty( $schedule_time ) ) {
			$booked_id = \AcademyProTutorBooking\Helper::do_booked(
				$booking_id,
				$user_id,
				$schedule_time,
				$order_id
			);

			if ( $booked_id ) {
				// Remove user meta for this booking
				/** @noinspection SpellCheckingInspection */
				delete_user_meta( $user_id, 'booking_schdule_time_' . $booking_id, $schedule_time );

				// Clear post cache for the booked post
				clean_post_cache( $booked_id );
			}
		}
	}

	protected function purchase_removed( $integration, $order ) {
		global $wpdb;

		if ( ! \AcademyProTutorBooking\Helper::is_booked( $integration->get_integration_id(), $order->get_customer_id() ) ) {
			return;
		}

		$booked_id = get_post_meta( $order->get_id(), '_academy_order_for_booking_id_' . $integration->get_integration_id(), true );
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"
				UPDATE {$wpdb->posts}
				SET post_status='on-hold'
				WHERE post_author=%d
				  AND post_parent=%d
				  AND post_type='academy_booked'
				  AND ID=%d;
				",
				$order->get_customer_id(),
				$integration->get_integration_id(),
				$booked_id
			)
		);

		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"
				SELECT ID FROM {$wpdb->posts}
				WHERE post_status='on-hold'
					AND post_author=%d
					AND post_parent=%d
					AND post_type='academy_booked'
					AND ID=%d;
				",
				$order->get_customer_id(),
				$integration->get_integration_id(),
				$booked_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( ! empty( $ids ) ) {
			array_map( 'clean_post_cache', $ids );
		}
	}
}
