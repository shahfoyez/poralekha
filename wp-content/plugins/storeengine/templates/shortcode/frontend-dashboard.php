<?php

use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

?>
<div class="storeengine-frontend-dashboard">
	<div class="storeengine-container">
		<div class="storeengine-row">
			<?php Helper::get_template( 'frontend-dashboard/sidebar.php' ); ?>
			<?php Helper::get_template( 'frontend-dashboard/content.php' ); ?>
		</div>
	</div>
</div>
