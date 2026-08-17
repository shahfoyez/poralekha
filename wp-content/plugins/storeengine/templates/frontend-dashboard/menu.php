<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$current_endpoint = get_query_var( 'storeengine_dashboard_page' );

/**
 * @var array $menu_items
 */

$current_endpoint     = get_query_var( 'storeengine_dashboard_page' );
$current_sub_endpoint = get_query_var( 'storeengine_dashboard_sub_page' );
foreach ( $menu_items as $endpoint => $item ) :
	if ( ! $item['public'] ) {
		continue;
	}

	// Items routable for logged-out users but not meant to live in the
	// sidebar — e.g. `forgot-password`, reached from the login form and the
	// reset email, never from nav.
	if ( ! empty( $item['hide_from_nav'] ) ) {
		continue;
	}

	// Separator entries render as a non-clickable label that visually breaks up
	// the menu (used by addons like multi-vendor and by the auto-injected
	// group headers from Helper::apply_frontend_dashboard_menu_groups).
	if ( ! empty( $item['is_separator'] ) ) {
		?>
		<li class="storeengine-dashboard-menu__separator">
			<span class="storeengine-dashboard-menu__separator-label"><?php echo esc_html( $item['label'] ); ?></span>
		</li>
		<?php
		continue;
	}

	$is_current = $current_endpoint === $endpoint || '' === $current_endpoint && 'index' === $endpoint;
	$classnames = 'storeengine-dashboard-menu__item storeengine-dashboard-menu__item-' . $endpoint;
	if ( $is_current ) {
		$classnames .= ' storeengine-dashboard-menu__item-current';
	}

	?>
	<li class="<?php echo esc_attr( $classnames ); ?>">
		<a class="storeengine-dashboard-menu__item-link" href="<?php echo esc_url( $item['permalink'] ?? storeengine_get_dashboard_endpoint_url( $endpoint ) ); ?>">
			<i class="<?php echo esc_attr( $item['icon'] ); ?> storeengine-flex" aria-hidden="true"></i>
			<span class="storeengine-dashboard-menu__item-label"><?php echo esc_html( $item['label'] ); ?></span>
		</a>
		<?php if ( ! empty( $item['children'] ) ) {
			$children = array_filter( $item['children'], fn( $c )=> $c['public'] ?? false );
			if ( empty( $children ) ) {
				continue;
			}

			uasort( $children, fn( $a, $b ) => $a['priority'] <=> $b['priority'] );
			?>
		<ul class="storeengine-dashboard-menu--children storeengine-frontend-dashboard__menu--children">
			<?php foreach ( $children as $sub_endpoint => $child ) {
				$sub_classnames = 'storeengine-dashboard-menu__item storeengine-dashboard-menu__sub-item-' . $endpoint . '--' . $sub_endpoint;
				if ( $is_current && $current_sub_endpoint === $sub_endpoint ) {
					$sub_classnames .= ' storeengine-dashboard-menu__sub-item-current';
					$sub_classnames .= ' storeengine-dashboard-menu__item-current';
				}
				?>
				<li class="<?php echo esc_attr( $sub_classnames ); ?>">
					<a class="storeengine-dashboard-menu__item-link" href="<?php echo esc_url( $item['permalink'] ?? storeengine_get_dashboard_endpoint_url( $endpoint, $sub_endpoint ) ); ?>">
						<?php if ( ! empty( $child['icon'] ) ) { ?>
						<i class="<?php echo esc_attr( $child['icon'] ); ?> storeengine-flex" aria-hidden="true"></i>
						<?php } ?>
						<span class="storeengine-dashboard-menu__item-label"><?php echo esc_html( $child['label'] ); ?></span>
					</a>
				</li>
			<?php } ?>
		</ul>
		<?php } ?>
	</li>
<?php endforeach; ?>
