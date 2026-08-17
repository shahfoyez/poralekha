<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
global $ablocks_visibility_page_id;
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
<meta name="viewport" content="width=device-width,initial-scale=1.0" charset="<?php bloginfo( 'charset' ); ?>">
<?php if ( ! current_theme_supports( 'title-tag' ) ) : ?>
<title><?php echo wp_get_document_title(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></title>
<?php endif; ?>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
	<?php
		$post_data = get_post( $ablocks_visibility_page_id );
		echo apply_filters( 'the_content', $post_data->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		wp_footer();
	?>
</body>
</html>
