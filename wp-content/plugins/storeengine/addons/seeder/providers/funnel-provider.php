<?php
/**
 * Seeds a complete, ready-to-demo sales funnel:
 *
 *   Landing → Checkout → One-time Upsell → Thank you
 *
 * The checkout step is pre-assigned a product (so it renders a real order
 * summary + pay button), the upsell step carries a configured offer whose
 * rejection routes straight to the thank-you page, and every step gets a
 * published page built from the funnel shortcodes. Depends on the products
 * provider for the price ids to sell.
 *
 * @package StoreEngine\Addons\Seeder\Providers
 */

namespace StoreEngine\Addons\Seeder\Providers;

use StoreEngine\Addons\Seeder\Classes\AbstractSeederProvider;
use StoreEngine\Addons\Seeder\Classes\SeederContext;
use StoreEngine\Addons\FunnelBuilder\Classes\Funnel;
use StoreEngine\Addons\FunnelBuilder\Classes\FunnelStep;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FunnelProvider extends AbstractSeederProvider {

	public function get_key(): string {
		return 'funnels';
	}

	public function get_label(): string {
		return 'Sales Funnels';
	}

	/**
	 * Needs sellable price ids for the checkout + upsell steps.
	 *
	 * @return string[]
	 */
	public function get_dependencies(): array {
		return [ 'products' ];
	}

	public function get_default_count(): int {
		return 1;
	}

	public function seed( SeederContext $context, int $count ): void {
		if ( ! class_exists( Funnel::class ) || ! class_exists( FunnelStep::class ) ) {
			// Funnel Builder addon is off — nothing to seed.
			return;
		}

		$price_ids = $context->ids( 'products', 'price' );
		if ( empty( $price_ids ) ) {
			$price_ids = $this->existing_price_ids();
		}
		if ( empty( $price_ids ) ) {
			// No sellable products anywhere — a funnel checkout would be empty.
			return;
		}

		$checkout_price = (int) $price_ids[0];
		$offer_price    = (int) ( $price_ids[1] ?? $price_ids[0] );

		for ( $i = 0; $i < $count; $i++ ) {
			try {
				$this->build_funnel( $context, $checkout_price, $offer_price, $i );
			} catch ( \Throwable $e ) {
				// Skip a bad funnel, keep going — the manager logs the failure.
				continue;
			}
		}
	}

	/**
	 * Build one example funnel (funnel row + 4 step pages + 4 step rows).
	 */
	private function build_funnel( SeederContext $context, int $checkout_price, int $offer_price, int $index ): void {
		$suffix = $index > 0 ? ' ' . ( $index + 1 ) : '';

		$funnel               = new Funnel();
		$funnel->name         = 'Example Sales Funnel' . $suffix;
		$funnel->status       = 'publish';
		$funnel->type         = 'sales';
		$funnel->trigger_type = 'manual';
		$funnel_id            = $funnel->save();

		if ( ! $funnel_id ) {
			return;
		}
		$context->record( 'funnel', $funnel_id );

		// Create the four steps in order. Each returns [ step_id, page_id ] so the
		// upsell can point its rejection at the thank-you step.
		$landing  = $this->create_step( $context, $funnel_id, 'landing', 'Landing', 0, [], $this->landing_content() );
		$checkout = $this->create_step( $context, $funnel_id, 'checkout', 'Checkout', 1, [
			'checkout' => [ 'price_id' => $checkout_price, 'quantity' => 1, 'replace_cart' => true ],
		], $this->checkout_content() );
		$thankyou = $this->create_step( $context, $funnel_id, 'thankyou', 'Thank You', 3, [], $this->thankyou_content() );
		$upsell   = $this->create_step( $context, $funnel_id, 'upsell', 'One-time Upsell', 2, [
			'offer'   => [
				'price_id'     => $offer_price,
				'title'        => 'Wait — add this at a one-time discount!',
				'description'  => 'Customers who bought this also grabbed this deal. Add it to your order with one click — no need to re-enter payment details.',
				'accept_label' => 'Yes, add it to my order',
				'skip_label'   => 'No thanks, I’ll pass',
			],
			// Demonstrates the reject routing: declining the upsell jumps straight
			// to the thank-you step instead of the next step in sequence.
			'routing' => [ 'reject_step_id' => $thankyou['step_id'] ],
		], $this->upsell_content() );
	}

	/**
	 * Create a step page + step row, wiring the page meta both ways.
	 *
	 * @return array{step_id:int,page_id:int}
	 */
	private function create_step( SeederContext $context, int $funnel_id, string $type, string $name, int $order, array $settings, string $content ): array {
		$page_id = wp_insert_post( [
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => 'Example Funnel — ' . $name,
			'post_content' => $content,
		], true );

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return [ 'step_id' => 0, 'page_id' => 0 ];
		}
		$context->record( 'page', (int) $page_id );
		// Full-width canvas template so the funnel checkout renders two columns.
		update_post_meta( $page_id, '_wp_page_template', 'storeengine-canvas.php' );

		$step             = new FunnelStep();
		$step->funnel_id  = $funnel_id;
		$step->page_id    = (int) $page_id;
		$step->type       = $type;
		$step->name       = $name;
		$step->step_order = $order;
		$step->settings   = $settings;
		$step_id          = $step->save();

		update_post_meta( $page_id, '_storeengine_funnel_id', $funnel_id );
		update_post_meta( $page_id, '_storeengine_funnel_step_id', $step_id );

		return [ 'step_id' => (int) $step_id, 'page_id' => (int) $page_id ];
	}

	/**
	 * Newest published one-time prices to fall back on when no products were
	 * seeded this run.
	 *
	 * @return int[]
	 */
	private function existing_price_ids(): array {
		global $wpdb;

		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			"SELECT price.id
			FROM {$wpdb->prefix}storeengine_product_price price
			INNER JOIN {$wpdb->prefix}posts product ON price.product_id = product.ID
			WHERE product.post_type = 'storeengine_product'
				AND product.post_status = 'publish'
				AND price.price_type = 'onetime'
				AND price.settings IS NOT NULL
			ORDER BY price.id DESC
			LIMIT 2"
		);
		// phpcs:enable

		return array_map( 'intval', (array) $ids );
	}

	/* -------------------------------------------------------------- */
	/* Step page content (funnel shortcodes wrapped in core blocks)    */
	/* -------------------------------------------------------------- */

	private function landing_content(): string {
		return '<!-- wp:heading {"textAlign":"center","level":1} -->
<h1 class="wp-block-heading has-text-align-center">Upgrade Your Everyday Carry</h1>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Premium gear, one simple checkout, and an exclusive bundle waiting on the next page.</p>
<!-- /wp:paragraph -->

' . \StoreEngine\Blocks\Bridge::block( 'storeengine/storeengine_funnel_next_step', [ 'label' => 'Get Started →' ] );
	}

	private function checkout_content(): string {
		return '<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Complete Your Order</h2>
<!-- /wp:heading -->

' . $this->block_or_shortcode( 'storeengine-funnel-checkout', 'storeengine/storeengine_funnel_checkout' );
	}

	private function upsell_content(): string {
		return '<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Wait! A one-time exclusive offer</h2>
<!-- /wp:heading -->

' . $this->block_or_shortcode( 'storeengine-funnel-offer', 'storeengine/storeengine_funnel_offer' );
	}

	/**
	 * Emit the recommended aBlocks funnel block when it's registered (so seeded
	 * demos showcase the block editing experience), falling back to the core
	 * `storeengine/shortcode` block otherwise. Both render identically on the
	 * front end — the block just wraps the shortcode — so behaviour is the same.
	 *
	 * @param string $block_slug   aBlocks block slug (without the `ablocks/` prefix).
	 * @param string $shortcode_id Bridge descriptor id (owner/tag) for the fallback.
	 */
	private function block_or_shortcode( string $block_slug, string $shortcode_id ): string {
		if (
			class_exists( '\WP_Block_Type_Registry' ) &&
			\WP_Block_Type_Registry::get_instance()->is_registered( 'ablocks/' . $block_slug )
		) {
			return '<!-- wp:ablocks/' . $block_slug . ' {"block_id":"' . $this->block_id() . '"} /-->';
		}

		return \StoreEngine\Blocks\Bridge::block( $shortcode_id );
	}

	/**
	 * Short unique id for an aBlocks block instance (used for its CSS scope).
	 */
	private function block_id(): string {
		return 'se' . substr( md5( uniqid( 'se_funnel', true ) ), 0, 8 );
	}

	private function thankyou_content(): string {
		return '<!-- wp:heading {"textAlign":"center"} -->
<h2 class="wp-block-heading has-text-align-center">Thank you for your order! 🎉</h2>
<!-- /wp:heading -->

<!-- wp:paragraph {"align":"center"} -->
<p class="has-text-align-center">Your order is confirmed and a receipt is on its way to your inbox.</p>
<!-- /wp:paragraph -->

' . \StoreEngine\Blocks\Bridge::block( 'storeengine/storeengine_thankyou_order_info' );
	}
}
