<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
?>
<div class="storeengine-col-12 storeengine-col-md-3 storeengine-frontend-dashboard__sidebar" id="storeengine-frontend-dashboard-sidebar">
	<div class="storeengine-frontend-dashboard__user">
		<div class="storeengine-frontend-user-title" id="user-avatar">
			<?php
			$current_user            = wp_get_current_user();
			$storeengine_first       = trim( $current_user->first_name );
			$storeengine_last        = trim( $current_user->last_name );
			$storeengine_display_label = ( $storeengine_first || $storeengine_last )
				? trim( $storeengine_first . ' ' . $storeengine_last )
				: ( $current_user->user_login ?: $current_user->user_email );
			?>
			<?php echo get_avatar( get_current_user_id(), 96, '', $storeengine_display_label, [ 'loading' => 'lazy', 'class' => 'storeengine-frontend-user-title__avatar' ] ); ?>
			<span class="storeengine-dashboard-menu__item-label"><?php echo esc_html( $storeengine_display_label ); ?></span>
		</div>
		<button id="storeengine-dashboard-menu-toggle" class="storeengine-d-block storeengine-d-md-none storeengine-btn storeengine-btn--xs storeengine-btn--preset-transparent storeengine-btn--border-dark-gray storeengine-ml-auto" aria-controls="storeengine-dashboard-menu" aria-label="<?php esc_attr_e( 'Toggle Dashboard Menu', 'storeengine' ); ?>">
			<span class="storeengine-icon storeengine-icon--arrow-down" aria-hidden="true"></span>
		</button>
		<div id="storeengine-collapsible-menu-compact" class="storeengine-d-none storeengine-d-md-flex storeengine-collapsible-menu storeengine-collapsible-menu--close storeengine-ml-auto" role="presentation">
			<span class="storeengine-icon storeengine-icon--arrow-left" aria-hidden="true" style="display:flex;font-size:16px"></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Compact Dashboard Menu Items', 'storeengine' ); ?></span>
		</div>
	</div>
	<ul class="storeengine-dashboard-menu storeengine-frontend-dashboard__menu" id="storeengine-dashboard-menu" role="menu" aria-label="<?php esc_attr_e( 'Dashboard Menu', 'storeengine' );?>">
		<?php
		/**
		 * @hook - storeengine_frontend_dashboard_menu
		 */
		do_action( 'storeengine/frontend/dashboard_menu' )
		?>
	</ul>
</div>
