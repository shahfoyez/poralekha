<?php
/**
 * Standalone "content restricted" page.
 *
 * Rendered in place of the active theme's template so a restricted page shows
 * ONLY the restriction message + purchase options — no theme header, footer or
 * navigation. wp_head()/wp_footer() are kept so plugin/theme styles and the
 * direct-checkout scripts still load (the Purchase button needs them).
 *
 * @var array $args Restriction data (page_title, message, prices).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$storeengine_restriction = isset( $GLOBALS['storeengine_membership_restriction_data'] ) && is_array( $GLOBALS['storeengine_membership_restriction_data'] )
	? $GLOBALS['storeengine_membership_restriction_data']
	: [];

// The partial below reads from $args (shared template contract with restricted-template.php).
// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound -- Required contract variable consumed by the included restricted-template.php partial (shared across callers).
$args = [
	'page_title' => $storeengine_restriction['page_title'] ?? '',
	'message'    => $storeengine_restriction['message'] ?? '',
	'prices'     => $storeengine_restriction['prices'] ?? [],
];

$storeengine_partial = STOREENGINE_MEMBERSHIP_TEMPLATE_DIR . 'restricted-template.php';
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'storeengine-membership-restricted-page' ); ?>>
<?php
if ( function_exists( 'wp_body_open' ) ) {
	wp_body_open();
}

if ( file_exists( $storeengine_partial ) ) {
	include $storeengine_partial;
}

wp_footer();
?>
</body>
</html>
