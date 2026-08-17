<?php
/**
 * Notice template.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

/**
 * Notice vars.
 *
 * @var string $id
 * @var string $type
 * @var string $icon
 * @var string $title
 * @var string $message
 * @var array $buttons
 * @var bool $dismissible
 * @var bool $alt
 */
$allowed_tags                     = [ 'a', 'abbr', 'acronym', 'code', 'pre', 'em', 'strong', 'u', 'b', 'br' ];
$allowed_tags                     = array_fill_keys( $allowed_tags, [] );
$allowed_tags['a']                = array_fill_keys( [ 'href', 'title', 'target' ], true );
$allowed_tags['acronym']['title'] = true;
$allowed_tags['abbr']['title']    = true;
$dismissible                      = isset( $dismissible ) && $dismissible;
?>
<div id="<?php echo esc_attr( $id ?? '' ); ?>" class="storeengine-notice storeengine-notice--<?php echo empty( $type ) ? 'info' : esc_attr( $type ); ?><?php echo $dismissible ? ' is-dismissible' : ''; ?><?php echo isset( $alt ) && $alt ? ' storeengine-notice--alt' : ''; ?>" role="alert" aria-live="polite">
	<?php if ( ! empty( $title ) ) { ?>
		<h4 class="storeengine-notice--heading">
			<?php if ( ! empty( $icon ) ) { ?>
			<i class="storeengine-notice--icon storeengine-icon storeengine-icon--<?php echo esc_attr( $icon ); ?>" aria-hidden="true"></i>
			<?php } ?>
			<?php echo wp_kses( wptexturize( $title ), $allowed_tags ); ?>
		</h4>
	<?php } ?>
	<?php if ( $dismissible ) { ?>
		<button class="storeengine-notice--close" data-dismiss="alert" aria-label="<?php esc_attr_e( 'Dismiss this notice', 'storeengine' ); ?>">
			<span class="storeengine-icon storeengine-icon--close" aria-hidden="true"></span>
		</button>
	<?php } ?>
	<div class="storeengine-notice--content">
		<?php if ( ! empty( $message ) ) { ?>
		<div class="storeengine-notice--message"><?php echo wp_kses_post( wpautop( wptexturize( $message ) ) ); ?></div>
		<?php } ?>
		<?php if ( ! empty( $buttons ) ) { ?>
			<div class="storeengine-notice--control">
			<?php
				foreach ( $buttons as $button ) {
					$button = wp_parse_args( $button, [
						'link'    => '',
						'classes' => '',
						'label'   => '',
						'target'  => '',
						'icon'    => '',
						'attrs'   => [],
					] );

					if ( empty( $button['label'] ) ) {
						continue;
					}

					$attrs = '';

					if ( ! empty( $button['classes'] ) ) {
						$attrs .= ' class="' . esc_attr( $button['classes'] ) . '"';
					}

					if ( ! empty( $button['attrs'] ) ) {
						foreach ( $button['attrs'] as $key => $value ) {
							if ( is_numeric( $key ) ) {
								continue;
							}

							$attrs .= ' data-' . $key . '="' . esc_attr( $value ) . '"';
						}
					}

					$button['icon'] = $button['icon'] ? sprintf( '<span class="storeengine-icon--%s" aria-hidden="true"></span>', esc_attr( $button['icon'] ) ) : '';

					if ( $button['link'] ) {
						$attrs = ' href="' . esc_url( $button['link'] ) . '"' . $attrs;
						$attrs .= ' target="' . esc_attr( $button['target'] ) . '"';
						?>
						<a <?php echo trim( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes escaped above. ?>>
							<?php echo $button['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output escaped above. ?>
							<?php echo esc_html( $button['label'] ); ?>
						</a>
					<?php } else { ?>
						<button <?php echo trim( $attrs ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Attributes escaped above. ?>>
							<?php echo $button['icon']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Output escaped above. ?>
							<?php echo esc_html( $button['label'] ); ?>
						</button>
						<?php
					}
				}
			?>
			</div>
		<?php } ?>
	</div>
</div>
<?php
// End of file notice.php
