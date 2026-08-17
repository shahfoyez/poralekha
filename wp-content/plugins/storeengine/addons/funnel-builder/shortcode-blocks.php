<?php
/**
 * Registers the funnel shortcodes with the aBlocks shortcode → block bridge, so
 * they appear as configurable blocks in the editor (recommended) while the
 * shortcodes remain the default, builder-agnostic way to render them.
 *
 * No-op when the bridge isn't present (aBlocks inactive) — the shortcodes still
 * work everywhere.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Addons\FunnelBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ShortcodeBlocks {

	public static function init() {
		// After the shortcodes themselves are added, and only if the bridge exists.
		add_action( 'init', [ __CLASS__, 'register' ], 20 );
	}

	public static function register() {
		if ( ! function_exists( 'storeengine_register_shortcode_block' ) ) {
			return;
		}

		$owner    = 'storeengine';
		$category = __( 'StoreEngine Funnel', 'storeengine' );

		storeengine_register_shortcode_block( [
			'tag'         => 'storeengine_funnel_checkout',
			'owner'       => $owner,
			'title'       => __( 'Funnel Checkout', 'storeengine' ),
			'category'    => $category,
			'description' => __( 'The funnel checkout — form, order summary and totals for this step.', 'storeengine' ),
			'icon'        => 'cart',
			'keywords'    => [ 'checkout', 'funnel', 'payment' ],
			'ablocks_block' => 'ablocks/storeengine-funnel-checkout',
			'attributes'  => [
				[
					'name'    => 'summary',
					'label'   => __( 'Order summary', 'storeengine' ),
					'type'    => 'select',
					'default' => 'yes',
					'group'   => __( 'Layout', 'storeengine' ),
					'sanitize' => 'key',
					'options' => [
						[ 'label' => __( 'Show', 'storeengine' ), 'value' => 'yes' ],
						[ 'label' => __( 'Hide', 'storeengine' ), 'value' => 'no' ],
					],
				],
			],
		] );

		storeengine_register_shortcode_block( [
			'tag'         => 'storeengine_funnel_offer',
			'owner'       => $owner,
			'title'       => __( 'Funnel Offer', 'storeengine' ),
			'category'    => $category,
			'description' => __( 'The upsell / downsell offer for this step (product + one-click buttons).', 'storeengine' ),
			'icon'        => 'megaphone',
			'keywords'    => [ 'offer', 'upsell', 'downsell', 'funnel' ],
			'ablocks_block' => 'ablocks/storeengine-funnel-offer',
			'ablocks_map'   => [ 'title' => 'offerTitle', 'description' => 'offerDescription' ],
			'attributes'  => [
				[
					'name'        => 'title',
					'label'       => __( 'Offer title', 'storeengine' ),
					'type'        => 'text',
					'default'     => '',
					'placeholder' => __( 'Uses the step’s configured title', 'storeengine' ),
					'sanitize'    => 'text',
				],
				[
					'name'        => 'description',
					'label'       => __( 'Offer description', 'storeengine' ),
					'type'        => 'textarea',
					'default'     => '',
					'placeholder' => __( 'Uses the step’s configured description', 'storeengine' ),
					'sanitize'    => 'text',
				],
			],
		] );

		storeengine_register_shortcode_block( [
			'tag'         => 'storeengine_funnel_accept',
			'owner'       => $owner,
			'title'       => __( 'Funnel Accept Button', 'storeengine' ),
			'category'    => $category,
			'description' => __( 'The “accept / add to my order” button on an offer step.', 'storeengine' ),
			'icon'        => 'yes',
			'keywords'    => [ 'accept', 'button', 'funnel', 'upsell' ],
			'ablocks_block' => 'ablocks/storeengine-funnel-accept',
			'ablocks_map'   => [ 'label' => 'label' ],
			'attributes'  => [
				[
					'name'        => 'label',
					'label'       => __( 'Button label', 'storeengine' ),
					'type'        => 'text',
					'default'     => '',
					'placeholder' => __( 'Yes, add this to my order', 'storeengine' ),
					'sanitize'    => 'text',
				],
			],
		] );

		storeengine_register_shortcode_block( [
			'tag'         => 'storeengine_funnel_skip',
			'owner'       => $owner,
			'title'       => __( 'Funnel Decline Button', 'storeengine' ),
			'category'    => $category,
			'description' => __( 'The “no thanks / decline” button on an offer step.', 'storeengine' ),
			'icon'        => 'no',
			'keywords'    => [ 'decline', 'skip', 'button', 'funnel' ],
			'ablocks_block' => 'ablocks/storeengine-funnel-decline',
			'ablocks_map'   => [ 'label' => 'label' ],
			'attributes'  => [
				[
					'name'        => 'label',
					'label'       => __( 'Button label', 'storeengine' ),
					'type'        => 'text',
					'default'     => '',
					'placeholder' => __( 'No thanks', 'storeengine' ),
					'sanitize'    => 'text',
				],
			],
		] );

		storeengine_register_shortcode_block( [
			'tag'         => 'storeengine_funnel_next_step',
			'owner'       => $owner,
			'title'       => __( 'Funnel Continue Button', 'storeengine' ),
			'category'    => $category,
			'description' => __( 'Advances the funnel to the next step (or a specific step / URL).', 'storeengine' ),
			'icon'        => 'arrow-right-alt',
			'keywords'    => [ 'continue', 'next', 'button', 'funnel' ],
			'ablocks_block' => 'ablocks/storeengine-funnel-continue',
			'ablocks_map'   => [ 'label' => 'label' ],
			'attributes'  => [
				[
					'name'        => 'label',
					'label'       => __( 'Button label', 'storeengine' ),
					'type'        => 'text',
					'default'     => __( 'Continue', 'storeengine' ),
					'sanitize'    => 'text',
				],
				[
					'name'        => 'step',
					'label'       => __( 'Go to step ID', 'storeengine' ),
					'type'        => 'number',
					'default'     => 0,
					'help'        => __( 'Leave 0 to go to the next step in sequence.', 'storeengine' ),
					'group'       => __( 'Target', 'storeengine' ),
					'sanitize'    => 'int',
				],
				[
					'name'        => 'url',
					'label'       => __( 'Custom URL', 'storeengine' ),
					'type'        => 'text',
					'default'     => '',
					'help'        => __( 'Overrides the step target.', 'storeengine' ),
					'group'       => __( 'Target', 'storeengine' ),
					'sanitize'    => 'url',
				],
				[
					'name'     => 'class',
					'label'    => __( 'CSS class', 'storeengine' ),
					'type'     => 'text',
					'default'  => '',
					'group'    => __( 'Advanced', 'storeengine' ),
					'sanitize' => 'text',
				],
			],
		] );
	}
}
