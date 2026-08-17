<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

?>

<div class="storeengine-col-12 storeengine-col-md-9 storeengine-frontend-dashboard__content" id="storeengine-frontend-dashboard-content">
	<?php
	/**
	 * @hook -'storeengine/frontend/dashboard_content
	 */
	do_action( 'storeengine/frontend/dashboard_content' )
	?>
</div>
