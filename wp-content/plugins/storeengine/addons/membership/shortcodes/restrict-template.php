<?php

namespace StoreEngine\Addons\Membership\Shortcodes;

use StoreEngine\Utils\Helper;

class RestrictTemplate {

	public function __construct() {
		add_shortcode('storeengine_membership_restricted_template', [ $this, 'render' ]);
	}

	public function render() {
		ob_start();

		global $storeengine_membership_restriction_data;

		// Use your existing helper class
		Helper::get_template(
			'restricted-template.php',
			$storeengine_membership_restriction_data,
			STOREENGINE_MEMBERSHIP_TEMPLATE_DIR,
			STOREENGINE_MEMBERSHIP_TEMPLATE_DIR
		);

		return ob_get_clean();
	}

}
