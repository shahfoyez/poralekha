<?php
/**
 * Dashboard top bar.
 *
 * @var string $page_title
 * @var string $path
 * @var string $sub_path
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

// Base URL paths
$dashboard_url    = '/store-dashboard/';
$current_url      = ( isset( $_SERVER['REQUEST_URI'] ) ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
$has_value_action = has_action( 'storeengine/templates/frontend-dashboard/topbar/breadcrumbs' );
?>

<?php
// Prominent page title shown in the shared header. On the dashboard root this is
// the page title; on any endpoint it is the current menu label. Filterable so a
// page can override it without hand-rolling its own heading.
$se_is_root     = ( $current_url === $dashboard_url );
$se_page_title  = $se_is_root ? get_the_title() : ( $page_title ?: get_the_title() );
$se_page_title  = apply_filters( 'storeengine/frontend-dashboard/page_title', $se_page_title, $path, $sub_path );
// Optional short description under the title (empty by default).
$se_page_desc   = apply_filters( 'storeengine/frontend-dashboard/page_description', '', $path, $sub_path );
?>
<div class="storeengine-topbar storeengine-topbar-tabs">
	<div class="storeengine-topbar__entry-left">
		<div id="storeengine-collapsible-menu-expand" class="storeengine-collapsible-menu storeengine-collapsible-menu--open" role="presentation">
			<span class="storeengine-icon storeengine-icon--arrow-right" aria-hidden="true" style="display:flex;font-size:16px"></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Expand Dashboard Menu Items', 'storeengine' ); ?></span>
		</div>
		<div class="storeengine-topbar__heading-group">
			<p class="storeengine-topbar-heading">
				<?php if ( $current_url !== $dashboard_url ) { ?>
				<a class="storeengine-topbar__breadcrumb-link" href="<?php echo esc_url( \StoreEngine\Utils\Helper::get_dashboard_url() ); ?>"><?php echo esc_html( get_the_title() ); ?></a>
				<?php } else { ?>
				<span class="storeengine-topbar-heading__subtitle"><?php echo esc_html( get_the_title() ); ?></span>
				<?php } ?>
				<?php
				if ( $current_url !== $dashboard_url ) :
					echo ' <i class="storeengine-icon storeengine-icon--arrow-right" aria-hidden="true"></i> ';
					printf( ! $has_value_action ? '%s' : '<span class="storeengine-topbar-heading__subtitle">%s</span>', esc_html( $page_title ) );
				endif;

				do_action( 'storeengine/templates/frontend-dashboard/topbar/breadcrumbs', $path, $sub_path );
				?>
			</p>
			<?php if ( $se_page_title ) : ?>
				<h1 class="storeengine-topbar__page-title"><?php echo esc_html( $se_page_title ); ?></h1>
			<?php endif; ?>
			<?php if ( $se_page_desc ) : ?>
				<p class="storeengine-topbar__page-description"><?php echo esc_html( $se_page_desc ); ?></p>
			<?php endif; ?>
			<?php
				do_action( 'storeengine/templates/frontend-dashboard/topbar/after_heading', $path, $sub_path )
			?>
		</div>
	</div>
	<div class="storeengine-topbar__entry-right">
		<?php do_action( 'storeengine/templates/frontend-dashboard/topbar/right_content', $path, $sub_path ); ?>
	</div>
</div>
