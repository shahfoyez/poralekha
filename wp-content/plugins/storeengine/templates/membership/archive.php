<?php

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

storeengine_get_header();

global $storeengine_membership_restriction_data;

?>
<div class="storeengine-membership-archive">
	<h1 class="storeengine-membership-protected-page-title"><?php the_archive_title(); ?></h1>
	<div class="storeengine-membership-protected-page-container">
		<?php
		Helper::get_template(
			'restricted-template.php',
			$storeengine_membership_restriction_data,
			STOREENGINE_MEMBERSHIP_TEMPLATE_DIR,
			STOREENGINE_MEMBERSHIP_TEMPLATE_DIR
		);
		?>
	</div>
</div>
<?php
storeengine_get_footer();
