<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

\ABlocks\Helper::get_template( 'email/template-header.php' );
?>
<div class="ablocks-container">
	<div class="ablocks-content">
		<div class="ablocks-wrapper">

			<div class="ablocks-entry-content">
				<p><?php echo wp_kses_post( $message ); ?></p>
			</div>

			<div class="ablocks-footer">
				&copy;
				<?php
				$site_url = wp_parse_url( get_bloginfo( 'url' ) );

				if ( ! empty( $site_url['host'] ) ) {
					echo esc_html( $site_url['host'] );

					if ( ! empty( $site_url['port'] ) ) {
						echo ':' . esc_html( $site_url['port'] );
					}
				}
				?>
			</div>

		</div>
	</div>
</div>
<?php
\ABlocks\Helper::get_template( 'email/template-footer.php' );
