<?php

namespace StoreEngine\Addons\Membership\Shortcodes;

use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Template;

class Pricing {

	public function __construct() {
		add_shortcode( 'storeengine_membership_pricing', [ $this, 'render_pricing' ] );
		add_action( 'init', [ $this, 'register_shortcode_block' ], 20 );
	}

	/**
	 * Expose the membership pricing table as a configurable block via the
	 * StoreEngine shortcode → block bridge. No-op when the bridge isn't present;
	 * the shortcode still works everywhere.
	 */
	public function register_shortcode_block() {
		if ( ! function_exists( 'storeengine_register_shortcode_block' ) ) {
			return;
		}

		storeengine_register_shortcode_block( [
			'tag'         => 'storeengine_membership_pricing',
			'owner'       => 'storeengine',
			'title'       => __( 'Membership Pricing', 'storeengine' ),
			'category'    => __( 'StoreEngine', 'storeengine' ),
			'description' => __( 'The membership plans pricing table.', 'storeengine' ),
			'icon'        => 'money-alt',
			'keywords'    => [ 'membership', 'pricing', 'plans', 'subscription' ],
			'attributes'  => [
				[
					'name'     => 'orderby',
					'label'    => __( 'Order', 'storeengine' ),
					'type'     => 'select',
					'default'  => 'ASC',
					'sanitize' => 'key',
					'options'  => [
						[ 'label' => __( 'Ascending', 'storeengine' ), 'value' => 'ASC' ],
						[ 'label' => __( 'Descending', 'storeengine' ), 'value' => 'DESC' ],
					],
				],
				[
					'name'     => 'no_pricing_text',
					'label'    => __( 'Empty-state text', 'storeengine' ),
					'type'     => 'text',
					'default'  => __( 'No pricing available!', 'storeengine' ),
					'group'    => __( 'Advanced', 'storeengine' ),
					'sanitize' => 'text',
				],
			],
		] );
	}

	public function render_pricing( $atts ): string {
		$atts         = shortcode_atts( [
			'no_pricing_text' => __( 'No pricing available!', 'storeengine' ),
			'orderby'         => 'ASC',
		], $atts, 'storeengine_membership_pricing' );
		$integrations = Helper::get_integration_repository_by_provider( 'storeengine/membership-addon', [ 'orderby' => $atts['orderby'] ] );

		ob_start();
		?>
		<div class="storeengine-membership-pricing storeengine-row storeengine-mt-5">
			<?php if ( empty( $integrations ) ) { ?>
				<p><?php echo esc_html( $atts['no_pricing_text'] ); ?></p>
			<?php } else {
				foreach ( $integrations as $integration ) {
					$features = get_post_meta( $integration->integration->get_integration_id(), '_storeengine_membership_features', true );
					Template::get_template( 'membership/pricing-card.php', [
						'integration' => $integration,
						'features'    => is_array( $features ) ? $features : [],
					] );
				}
			}
			?>
		</div>
		<?php

		// @TODO maybe just remove the content from buffer, as hook handling the cleanups anyway.
		/** @see Hooks::remove_empty_spaces() */

		$output = Helper::remove_line_break( ob_get_clean() );

		return Helper::remove_tag_space( $output );
	}

}
