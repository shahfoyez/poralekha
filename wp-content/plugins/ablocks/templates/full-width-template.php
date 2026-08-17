<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
/*
 * Template Name: aBlocks Full Width
 * Description: A full-width template with no sidebar.
 */

get_header();
?>

<div class="ablocks-content-area ablocks-content-area--full-width">
	<?php
	while ( have_posts() ) :
		the_post();
		the_content();
	endwhile;
	?>
</div>
<?php
get_footer();
