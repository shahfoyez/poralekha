<?php
/**
 * Notice template.
 *
 * @var string $notice_key
 * @var string $classes
 * @var array $notice
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>
	<div id="storeengine-admin-notice-<?php echo esc_attr( $notice['key'] ); ?>" class="<?php echo esc_attr( $classes ); ?>" role="alert">
		<?php if ( $notice['icon'] ) { ?>
			<span class="storeengine-admin-notice__icon" aria-hidden="true">
				<?php echo \StoreEngine\Admin\Notices::get_svg_icon( $notice['icon'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Controlled inline SVG built from a static whitelist. ?>
			</span>
		<?php } ?>

		<div class="storeengine-admin-notice__body">
			<?php if ( $notice['title'] ) { ?>
				<h3 class="storeengine-admin-notice__title"><?php echo esc_html( $notice['title'] ); ?></h3>
			<?php } ?>
			<div class="storeengine-admin-notice__content"><?php echo wp_kses_post( $notice['message'] ); ?></div>
		</div>

		<?php if ( $notice['has_buttons'] ) { ?>
			<div class="storeengine-admin-notice__actions">
				<?php if ( $notice['button_text'] && $notice['button_action'] ) { ?>
					<a class="<?php echo esc_attr( $notice['button_class'] ?: 'storeengine-btn storeengine-btn--md storeengine-btn--preset-blue' ); ?>" href="<?php echo esc_url( $notice['button_action'] ); ?>"<?php echo ! empty( $notice['button_target'] ) ? ' target="' . esc_attr( $notice['button_target'] ) . '"' : '' ?>>
						<?php echo esc_html( $notice['button_text'] ); ?>
					</a>
				<?php } ?>
				<?php if ( $notice['dismissible'] ) { ?>
					<button class="storeengine-admin-notice-close notice-dismiss" data-notice="<?php echo esc_attr( $notice['key'] ); ?>" aria-label="<?php esc_attr_e( 'Dismiss this notice', 'storeengine' ); ?>">
						<?php echo \StoreEngine\Admin\Notices::get_svg_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Controlled inline SVG built from a static whitelist. ?>
					</button>
				<?php } ?>
			</div>
		<?php } ?>
	</div>
<?php
