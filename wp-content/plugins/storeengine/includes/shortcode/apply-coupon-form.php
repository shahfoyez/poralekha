<?php

namespace StoreEngine\Shortcode;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Template;

class ApplyCouponForm {
	public function __construct() {
		add_shortcode( 'storeengine_apply_coupon_form', array( $this, 'render_apply_coupon_form' ) );
	}

	public function render_apply_coupon_form( array $attrs ) {
		if ( Formatting::string_to_bool( get_query_var( 'order_pay' ) ) ) {
			return '';
		}

		$attributes = shortcode_atts( [
			'placeholder'  => __( 'Coupon Code', 'storeengine' ),
			'button_label' => __( 'Apply', 'storeengine' ),
		], $attrs );

		ob_start();
		Template::get_template( 'shortcode/apply-coupon-form.php', $attributes );

		return ob_get_clean();
	}
}
