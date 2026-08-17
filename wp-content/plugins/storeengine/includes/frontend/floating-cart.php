<?php

namespace StoreEngine\Frontend;

use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FloatingCart {
	use Singleton;

	public function __construct() {
		add_action( 'wp_footer', [ $this, 'display_cart_button' ] );
	}

	public function display_cart_button() {
		if ( Helper::is_cart() || Helper::is_checkout() ) {
			return;
		}

		$count = storeengine_cart()->get_count();
		?>
		<button style="display: <?php echo esc_attr( 0 === $count ? 'none' : 'block' ); ?>" type="button" data-storeengine-mini-cart-open-drawer class="storeengine-floating-cart">
			<div class="storeengine-floating-cart-button">
				<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="currentColor" viewBox="0 0 16 16">
					<path d="M0 1.5A.5.5 0 0 1 .5 1H2a.5.5 0 0 1 .485.379L2.89 3H14.5a.5.5 0 0 1 .491.592l-1.5 8A.5.5 0 0 1 13 12H4a.5.5 0 0 1-.491-.408L2.01 3.607 1.61 2H.5a.5.5 0 0 1-.5-.5M3.102 4l1.313 7h8.17l1.313-7zM5 12a2 2 0 1 0 0 4 2 2 0 0 0 0-4m7 0a2 2 0 1 0 0 4 2 2 0 0 0 0-4m-7 1a1 1 0 1 1 0 2 1 1 0 0 1 0-2m7 0a1 1 0 1 1 0 2 1 1 0 0 1 0-2"/>
				</svg>
				<span class="storeengine-floating-cart-count storeengine-cart-count"><?php echo esc_html( $count ); ?></span>
			</div>
		</button>
		<div id="storeengine-mini-cart-drawer-root"></div>
		<?php
	}
}
