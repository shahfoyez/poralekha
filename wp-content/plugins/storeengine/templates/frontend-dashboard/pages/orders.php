<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use StoreEngine\Utils\Template;
?>
<div class="storeengine-frontend-dashboard--orders">
	<?php Template::get_template( 'frontend-dashboard/partials/orders.php' ); ?>
</div>
