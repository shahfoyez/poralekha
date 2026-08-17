<?php
/**
 * Edit Account dispatcher.
 *
 * Account is now a parent menu item with three children: account (default),
 * notifications, privacy. The active sub-page is read from the rewrite-rule
 * query var `storeengine_dashboard_sub_page`. Each branch loads its own
 * template AND fires a `storeengine/frontend_dashboard/account_section_{sub}`
 * action so addons can register additional sub-tabs without core edits.
 *
 * @var \StoreEngine\Classes\Customer $customer
 */

use StoreEngine\Utils\Template;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$storeengine_sub = (string) get_query_var( 'storeengine_dashboard_sub_page' );
$storeengine_sub = $storeengine_sub !== '' ? $storeengine_sub : 'account';

switch ( $storeengine_sub ) {
	case 'notifications':
		Template::get_template( 'frontend-dashboard/pages/account/notifications.php', [ 'customer' => $customer ] );
		break;
	case 'privacy':
		Template::get_template( 'frontend-dashboard/pages/account/privacy.php', [ 'customer' => $customer ] );
		break;
	case 'account':
	default:
		Template::get_template( 'frontend-dashboard/pages/account/details.php', [ 'customer' => $customer ] );
		break;
}

/**
 * Fires after a sub-tab renders so addons can append additional content
 * without editing core templates. Slug-suffixed: e.g.
 * `storeengine/frontend_dashboard/account_section_notifications`.
 */
do_action( 'storeengine/frontend_dashboard/account_section_' . $storeengine_sub, $customer );
