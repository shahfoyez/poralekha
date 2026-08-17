<?php

namespace StoreEngine\Integrations;

if ( ! defined( 'ABSPATH' ) ) {
	die();
}

use StoreEngine\Addons\Subscription\Classes\Subscription;
use StoreEngine\Classes\Integration;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Template;
use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Membership\HelperAddon;

class AcademyLms extends AbstractIntegration {
	const ID = 'storeengine/academylms';

	public function setup(): void {
		$this->id          = static::ID;
		$this->label       = __( 'Academy LMS', 'storeengine' );
		$this->items_label = __( 'Course Access', 'storeengine' );
		$this->logo        = STOREENGINE_ASSETS_URI . 'images/integrations/academy-lms.svg';
		$this->isEnabled   = defined( 'ACADEMY_VERSION' );
	}

	public function dispatch_hooks(): void {
		add_action( 'storeengine/integrations/created', [ $this, 'add_course_meta' ] );
		add_filter( 'academy/templates/single_course/enroll_form', [ $this, 'add_to_cart_button' ], 10, 2 );
		add_filter( 'academy/single/enroll_content_args', [ $this, 'modify_enroll_form_content_args' ], 10, 2 );
		add_filter( 'academy/template/loop/price_args', [ $this, 'modify_loop_price_args' ], 11, 2 );

		if ( ! Helper::is_fse_theme() ) {
			add_filter( 'academy_pro/course_bundle/get_price_args', [ $this, 'modify_loop_price_args' ], 10, 2 );
		}

		add_filter( 'academy/template/loop/footer_form', [ $this, 'modify_footer_form_args' ], 10, 2 );
		add_filter( 'academy/shortcode/storeengine_enroll_form_prices_args', [ $this, 'get_academy_course_prices_args' ] );
	}

	public function add_course_meta( Integration $integration ) {
		if ( static::ID !== $integration->get_provider() ) {
			return;
		}

		update_post_meta( $integration->get_integration_id(), 'academy_course_type', 'paid' );
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
		global $wpdb;

		if ( ! class_exists( 'Academy' ) ) {
			return;
		}

		$is_enrolled = \Academy\Helper::is_enrolled( $integration->get_integration_id(), $order->get_customer_id(), 'any' );

		if ( ! $is_enrolled ) {
			\Academy\Helper::do_enroll( $integration->get_integration_id(), $order->get_customer_id(), $order->get_id() );

			return;
		}

		if ( 'completed' === $is_enrolled->enrolled_status ) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update( $wpdb->posts, [ 'post_status' => 'completed' ], [ 'ID' => $is_enrolled->ID ] );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery
		clean_post_cache( $is_enrolled->ID );
	}

	protected function purchase_removed( $integration, $order ) {
		global $wpdb;

		if ( ! \Academy\Helper::is_enrolled( $integration->get_integration_id(), $order->get_customer_id() ) ) {
			return;
		}

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->update(
			$wpdb->posts,
			[ 'post_status' => 'on-hold' ],
			[
				'post_author' => $order->get_customer_id(),
				'post_parent' => $integration->get_integration_id(),
				'post_type'   => 'academy_enrolled',
			],
			[ '%s' ],
			[ '%d', '%d', '%s' ]
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery

		$enrolled = \Academy\Helper::is_enrolled( $integration->get_integration_id(), $order->get_customer_id() );

		if ( $enrolled ) {
			clean_post_cache( $enrolled->ID );
		}
	}

	public function add_to_cart_button( $html, $course_id ) {
		$course_type = \Academy\Helper::get_course_type( $course_id );
		if ( 'free' === $course_type || 'public' === $course_type ) {
			return $html;
		}

		$integrations = $this->get_integration_repository( $course_id );
		$user_id      = get_current_user_id();
		if ( empty( $integrations ) || ! count( $integrations ) || \Academy\Helper::is_enrolled( $course_id, $user_id, 'on-hold' ) ) {
			return $html;
		}

		ob_start();

		// Render cart.
		Template::get_template( 'integrations/academy-lms/add-to-cart.php', [
			'integrations'      => $integrations,
			'integration_count' => count( $integrations ),
		] );

		return ob_get_clean() . $html;
	}

	public function get_price_html( $course_id ): string {
		$integrations = $this->get_integration_repository( $course_id );

		if ( count( $integrations ) > 1 ) {
			return '<span class="academy-course-price"><span>' . __( 'Paid', 'storeengine' ) . '</span></span>';
		}

		return '<span class="academy-course-price"><span>' . current( $integrations )->price->get_price_html() . '</span></span>';
	}

	public function modify_enroll_form_content_args( $args, $course_id ) {
		$course_type = \Academy\Helper::get_course_type( $course_id );
		if ( 'free' === $course_type || $args['is_public'] || \Academy\Helper::is_enrolled( $course_id, get_current_user_id() ) ) {
			return $args;
		}

		$integration = $this->get_integration_repository( $course_id );

		if ( empty( $integration ) ) {
			return $args;
		}

		$prices_markup   = $this->get_price_html( $course_id );
		$args['is_paid'] = true;
		$args['price']   = '<div class="academy-course-type">' . $prices_markup . '</div>';

		return $args;
	}

	public function modify_loop_price_args( array $args, $course_id ): array {
		if ( 'alms_course_bundle' === get_post_type( $course_id ) ) {
			return $this->modify_bundle_loop_price_args( $args, $course_id );
		}

		if ( \Academy\Helper::is_enrolled( $course_id, get_current_user_id() ) ) {
			return array_merge( $args, [
				'course_type' => 'free',
				'is_paid'     => false,
			] );
		}

		$integrations = $this->get_integration_repository( $course_id );
		if ( Helper::get_addon_active_status( 'membership' ) && empty( $integrations ) ) {
			[ $integrations ] = $this->get_course_integrations( $course_id );

			if ( count( $integrations ) > 0 ) {
				$is_purchased = MembershipAddon::is_purchased_membership( $integrations );
				if ( $is_purchased ) {
					return $args;
				}

				return array_merge(
					$args,
					[
						'is_paid'     => true,
						'course_type' => 'paid',
						'price'       => '<div class="academy-course-type">' . esc_html__( 'Membership', 'storeengine' ) . '</div>',
					]
				);
			}
		}

		if ( 'public' === $args['course_type'] || 'free' === $args['course_type'] || empty( $integrations ) ) {
			return $args;
		}

		return array_merge(
			$args,
			[
				'is_paid'     => true,
				'course_type' => 'paid',
				'price'       => '<div class="academy-course-type">' . $this->get_price_html( $course_id ) . '</div>',
			]
		);
	}

	public function modify_bundle_loop_price_args( array $args, $course_id ): array {
		if ( \Academy\Helper::is_enrolled( $course_id, get_current_user_id() ) ) {
			return array_merge( $args, [
				'course_type' => 'free',
				'is_paid'     => false,
			] );
		}

		$integrations = Helper::get_integration_repository_by_id( 'storeengine/course-bundle', $course_id );
		if ( Helper::get_addon_active_status( 'membership' ) && empty( $integrations ) ) {
			[ $integrations ] = $this->get_course_bundle_integrations( $course_id );

			if ( count( $integrations ) > 0 ) {
				$is_purchased = MembershipAddon::is_purchased_membership( $integrations );
				if ( $is_purchased ) {
					return $args;
				}

				return array_merge(
					$args,
					[
						'is_paid'     => true,
						'course_type' => 'paid',
						'price'       => '<div class="academy-course-type">' . esc_html__( 'Membership', 'storeengine' ) . '</div>',
					]
				);
			}
		}

		if ( 'public' === $args['course_type'] || 'free' === $args['course_type'] || empty( $integrations ) ) {
			return $args;
		}

		return array_merge(
			$args,
			[
				'is_paid'     => true,
				'course_type' => 'paid',
				'price'       => '<div class="academy-course-type">' . $this->get_price_html( $course_id ) . '</div>',
			]
		);
	}

	protected function get_course_bundle_integrations( $course_id ): array {
		$all_plans = HelperAddon::get_all_plans( $course_id );

		$integrations = [];
		foreach ( $all_plans as $plan_id => $plan ) {
			$integrations = array_merge( $integrations, Helper::get_integration_repository_by_id( 'storeengine/course-bundle', $plan_id ) );
		}

		$is_available_membership = MembershipAddon::is_available_membership( $integrations );

		return [ $integrations, $is_available_membership ];
	}

	public function modify_footer_form_args( $args, $course_id ) {
		$post_type = get_post_type( $course_id );
		if ( 'alms_course_bundle' === $post_type ) {
			$integrations = Helper::get_integration_repository_by_id( 'storeengine/course-bundle', $course_id );
		} else {
			$integrations = $this->get_integration_repository( $course_id );
		}

		if ( Helper::get_addon_active_status( 'membership' ) && empty( $integrations ) ) {
			if ( 'alms_course_bundle' === $post_type ) {
				[ $integrations ] = $this->get_course_bundle_integrations( $course_id );
			} else {
				[ $integrations ] = $this->get_course_integrations( $course_id );
			}

			if ( empty( $integrations ) || 1 < count( $integrations ) ) {
				return $args;
			}

			if ( count( $integrations ) > 0 ) {
				$is_purchased = MembershipAddon::is_purchased_membership( $integrations );
				if ( $is_purchased ) {
					return $args;
				}

				return array_merge( $args, [
					'is_storeengine_product' => true,
					'price_qtn'              => count( $integrations ),
					'integration'            => $integrations,
				] );
			}
		}

		if ( 'free' === $args['course_type'] || 'public' === $args['course_type'] ) {
			return $args;
		}

		if ( empty( $integrations ) || count( $integrations ) > 1 ) {
			return $args;
		}

		$args['is_storeengine_product'] = true;
		$args['price_qtn']              = count( $integrations );
		$args['integration']            = $integrations;

		return $args;
	}

	public function get_academy_course_prices_args( $course_id ) {
		$integrations = $this->get_integration_repository( (int) $course_id );
		$args         = [
			'price' => '',
			'link'  => '',
		];

		if ( empty( $integrations ) ) {
			return $args;
		}

		if ( count( $integrations ) > 1 ) {
			$prices = array_map(
				fn( $integration ) => intval( $integration->price->data['price'] ?? 0 ),
				$integrations
			);

			$args['price'] = min( $prices ) . ' - ' . max( $prices );

			return $args;
		}

		$args['price'] = current( $integrations )->price->get_price_html();

		return $args;
	}

	protected function get_course_integrations( $course_id ): array {
		$all_plans = HelperAddon::get_all_plans( $course_id );

		$integrations = [];
		foreach ( $all_plans as $plan_id => $plan ) {
			$integrations = array_merge( $integrations, $this->get_integration_repository( $plan_id ) );
		}

		$is_available_membership = MembershipAddon::is_available_membership( $integrations );

		return [ $integrations, $is_available_membership ];
	}

	protected function is_only_membership( int $course_id ): bool {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return ! $wpdb->get_var(
			$wpdb->prepare(
				"
				SELECT id
				FROM {$wpdb->prefix}storeengine_integrations
				WHERE
					provider = %s
				  	AND integration_id = %d;
				",
				static::ID,
				$course_id
			)
		);
		// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	}
}
