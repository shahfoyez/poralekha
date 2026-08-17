<?php

namespace StoreEngine\Shortcode;

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class FrontendDashboard {
	public function __construct() {
		add_shortcode( 'storeengine_dashboard', array( $this, 'frontend_dashboard' ) );
	}
	public function frontend_dashboard() {
		ob_start();

		do_action( 'storeengine/shortcode/before_storeengine_dashboard' );

		if ( ! is_user_logged_in() ) {
			// Some endpoints (e.g. `forgot-password`) are intentionally
			// reachable by logged-out visitors and need their own template
			// to render — falling back to the login form here would
			// effectively hijack those URLs.
			//
			// sanitize_key() before splicing into the action name: even
			// though the array lookup already constrains $endpoint to known
			// menu keys, feeding an unsanitized query var into do_action()'s
			// hook name is fragile (a weird query string could produce a
			// hook name with quotes/whitespace). Same defensive treatment
			// the standard router applies in functions.php:794.
			$endpoint = sanitize_key( (string) get_query_var( 'storeengine_dashboard_page' ) );
			$items    = Helper::get_frontend_dashboard_menu_items();
			if ( $endpoint && ! empty( $items[ $endpoint ]['guest_accessible'] ) ) {
				do_action( 'storeengine/frontend/dashboard_' . $endpoint . '_endpoint', get_query_var( 'storeengine_dashboard_sub_page' ) );
			} else {
				echo do_shortcode( '[storeengine_login_form]' );
			}
		} else {
			Helper::get_template( 'shortcode/frontend-dashboard.php' );
		}

		do_action( 'storeengine/shortcode/after_storeengine_dashboard' );

		return apply_filters( 'storeengine/templates/shortcode/dashboard', Helper::remove_tag_space( Helper::remove_line_break( ob_get_clean() ) ) );
	}
}
