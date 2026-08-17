<?php
namespace ABlocks\Admin\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Blocks {
	public static function get_saved_data() {
		$settings = get_option( ABLOCKS_BLOCKS_VISIBILITY_SETTINGS_NAME );
		if ( $settings ) {
			return json_decode( $settings, true );
		}
		return [];
	}
	public static function get_default_data() {
		return apply_filters('ablocks/admin/settings/blocks_default_data', [
			// Core Blocks
			'progress-tracker' => true,
			'accordion' => true,
			'button' => true,
			'dual-button' => true,
			'notice' => true,
			'container' => true,
			'countdown' => true,
			'counter' => true,
			'divider' => true,
			'heading' => true,
			'icon' => true,
			'image' => true,
			'image-scroll' => true,
			'lists' => true,
			'paragraph' => true,
			'player' => true,
			'spacer' => true,
			'star-ratings' => true,
			'video' => true,
			'tabs' => true,
			'toggle' => true,
			'loop-builder' => true,
			'loop-template' => true,
			'loop-load-more' => true,
			'news-ticker' => true,
			'image-hotspot' => true,
			'form-builder' => true,
			'form-multi-step' => true,
			'toggle-child' => true,
			'menu' => true,
			'coupon' => true,
			'modal' => true,
			'content-timeline' => true,
			'map' => true,
			'table-of-content' => true,
			'table' => true,
			'carousel' => true,
			'image-comparison' => true,
			'flip-box' => true,
			'filterable-cards' => true,
			'filterable-cards-item' => true,
			'stripe-button' => true,
			'paypal-button' => true,
			'social-shares' => true,
			'search' => true,
			'svg-draw' => true,
			'info-box' => true,
			'price-menu' => true,
			'savings-calculator' => true,
			'frontend-dashboard' => true,
			'lottie-animation' => true,
			'marquee' => true,
			'marquee-child' => true,
			'logout' => true,
			'qr-code' => true,
			'chart' => true,
			'text-path' => true,
			'advance-lists' => true,
			'stacked-cards' => true,
			'group-image-effect' => true,
			'taxonomy-listing' => true,
			'dynamic-text' => true,
			'featured-image' => true,
			'scroll-to-top' => true,
			'breadcrumb' => true,
			'interactive-circle-infographic' => true,
			'code-highlighter' => true,
			// Academy LMS Blocks
			'academy-courses' => true,
			'academy-container' => true,
			'academy-course-search' => true,
			'academy-enroll-form' => true,
			'academy-instructor-registration-form' => true,
			'academy-course-media' => true,
			'academy-login-form' => true,
			'academy-password-reset-form' => true,
			'academy-pdf' => true,
			'academy-student-registration-form' => true,
			'academy-certificate' => true,
			'academy-course-curriculums' => true,
			'academy-course-reviews' => true,
			'academy-review-form' => true,
			'academy-review-list' => true,
			'academy-addition-info' => true,
			'academy-course-instructor' => true,
			'academy-course-description' => true,
			'academy-enroll-content' => true,

			// StoreEngine Blocks
			'storeengine-products' => true,
			'storeengine-cart-list' => true,
			'storeengine-login-form' => true,
			'storeengine-coupon-form' => true,
			'storeengine-product-filter' => true,
			'storeengine-checkout-form' => true,
			'storeengine-continue-button' => true,
			'storeengine-checkout-button' => true,
			'storeengine-cart-sub-table' => true,
			'storeengine-cart-button' => true,
			'storeengine-order-info' => true,
			'storeengine-billing-info' => true,
			'storeengine-shipping-info' => true,
			'storeengine-order-details' => true,
			'storeengine-funnel-checkout' => true,
			'storeengine-funnel-offer' => true,
			'storeengine-funnel-accept' => true,
			'storeengine-funnel-decline' => true,
			'storeengine-funnel-continue' => true,
			'storeengine-mini-cart' => true,
			'storeengine-cart-notice' => true,
			'storeengine-product-gallery' => true,
			'storeengine-product-summary' => true,
			'storeengine-product-description' => true,
			'storeengine-product-review' => true,

			// Easy Content Manager Blocks
			'ecm-bookmark-button' => true,
			'ecm-bookmark-badge' => true,
			'ecm-bookmark-list' => true,
			'ecm-bookmark-count' => true,
			'ecm-reaction-list' => true,
			'ecm-reaction' => true,
			'ecm-upvote' => true,
			'ecm-upvote-count' => true,
			'ecm-upvote-list' => true,
			'ecm-claim-button' => true,
			'ecm-claim-badge' => true,
			'ecm-claim-list' => true,
			'ecm-claim-count' => true,
		]);
	}

	public static function save_settings( $form_data = false ) {
		$default_data = self::get_default_data();
		$saved_data = self::get_saved_data();
		$settings_data = wp_parse_args( $saved_data, $default_data );
		if ( $form_data ) {
			$settings_data = wp_parse_args( $form_data, $settings_data );
		}
		// if settings already saved, then update it
		if ( count( $saved_data ) ) {
			return update_option( ABLOCKS_BLOCKS_VISIBILITY_SETTINGS_NAME, wp_json_encode( $settings_data ) );
		}
		return add_option( ABLOCKS_BLOCKS_VISIBILITY_SETTINGS_NAME, wp_json_encode( $settings_data ) );
	}
}
