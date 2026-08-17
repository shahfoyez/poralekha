<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

	\ABlocks\Helper::get_template( 'email/template-header.php' );
?>
<div class="container">
	<div class="content"> 
		<div class="wrapper">
			<h5 class="main-heading">Thank you for subscribing with us!</h5>
			<div class="entry-content">
				<p>Thank you for verifying your email address.</p>
				<p><a href="<?php echo esc_url( home_url() ); ?>">Go to home page</a></p>
				<p>Your email has been successfully verified.If you have any questions, feel free to reach out to our support team.</p>
				<p>Best regards,<br><?php echo esc_attr( get_bloginfo( 'name' ) ); ?></p>
			</div>
			<div class="footer">
				&copy; <?php
					$url = wp_parse_url( get_bloginfo( 'url' ) );
					$host = isset( $url['host'] ) ? sanitize_text_field( $url['host'] ) : '';
					$port = isset( $url['port'] ) ? sanitize_text_field( (string) $url['port'] ) : '80';
					echo esc_html( $host . ':' . $port );
				?>
				<?php // echo wp_kses_post( $footer );
					// phpcs::ignore Squiz.PHP.CommentedOutCode.Found
				?>
			</div>
		</td>
	</div>
</div>
<?php
	\ABlocks\Helper::get_template( 'email/template-footer.php' );
