<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

	\ABlocks\Helper::get_template( 'email/template-header.php' );
?>
<div class="ablocks-container">
	<div class="ablocks-content"> 
		<div class="ablocks-wrapper">
			<h5 class="ablocks-main-heading"><?php esc_html_e( 'You have a new subscriber', 'ablocks' ); ?></h5>
			<div class="ablocks-entry-content">
				<p><strong><?php esc_html_e( 'You have a new subscriber from form submission!', 'ablocks' ); ?></strong></p>
				<p><strong><?php esc_html_e( 'Email', 'ablocks' ); ?>:</strong> <?php echo esc_html( $email ); ?></p>
				<p><?php esc_html_e( 'To manage your subscribers or contacts, log in to your WordPress admin dashboard.', 'ablocks' ); ?></p>	
			</div>
			<div class="ablocks-footer">
				&copy; <?php
						$url = wp_parse_url( get_bloginfo( 'url' ) );

						// Ensure 'host' exists and set default for 'port' if missing
						$host = isset( $url['host'] ) ? sanitize_text_field( $url['host'] ) : '';
						$port = isset( $url['port'] ) ? sanitize_text_field( (string) $url['port'] ) : '80';

						// Output the sanitized host and port
						echo esc_html( $host . ':' . $port );
				?>

				<?php // here is the footer	?>
			</div>
		</td>
	</div>
</div>
<?php
	\ABlocks\Helper::get_template( 'email/template-footer.php' );
