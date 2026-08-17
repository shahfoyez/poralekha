<?php
/**
 * Funnel Builder frontend: step resolution, view tracking, navigation and the
 * shortcodes that make aBlocks-built step pages functional.
 *
 * Free behaviour: checkout steps reuse StoreEngine's checkout, accept/skip
 * buttons simply route to the next step. The Pro addon hooks the accept action
 * to perform the one-click charge before routing.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Addons\FunnelBuilder;

use StoreEngine\Addons\FunnelBuilder\Classes\Funnel;
use StoreEngine\Addons\FunnelBuilder\Classes\FunnelStep;
use StoreEngine\Addons\FunnelBuilder\Classes\FunnelStats;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Frontend {

	protected static ?FunnelStep $current_step = null;
	protected static bool $resolved = false;

	public static function init() {
		$self = new self();

		add_action( 'template_redirect', [ $self, 'track_view' ] );
		// Keep funnel step pages (esp. duplicate checkout) out of search indexes.
		add_filter( 'wp_robots', [ $self, 'noindex_step_pages' ] );

		add_shortcode( 'storeengine_funnel_checkout', [ $self, 'sc_checkout' ] );
		add_shortcode( 'storeengine_funnel_offer', [ $self, 'sc_offer' ] );
		add_shortcode( 'storeengine_funnel_accept', [ $self, 'sc_accept' ] );
		add_shortcode( 'storeengine_funnel_skip', [ $self, 'sc_skip' ] );
		// Generic step navigation (landing / opt-in / thank-you → next step, or a
		// specific target step). `[storeengine_funnel_next_step]` is an alias.
		add_shortcode( 'storeengine_funnel_next_step', [ $self, 'sc_next_step' ] );
		add_shortcode( 'storeengine_funnel_button', [ $self, 'sc_next_step' ] );
	}

	/**
	 * Resolve the funnel step backing the current request (a step page), if any.
	 */
	public static function current_step(): ?FunnelStep {
		if ( self::$resolved ) {
			return self::$current_step;
		}
		self::$resolved = true;

		$page_id = self::resolve_page_id();
		if ( $page_id ) {
			$step = FunnelStep::find_by_page( $page_id );
			if ( ! $step ) {
				// Fallback: the page carries the step id in post meta (robust for
				// editor / REST block-renderer contexts where find_by_page misses).
				$step_id = (int) get_post_meta( $page_id, '_storeengine_funnel_step_id', true );
				if ( $step_id ) {
					$step = FunnelStep::find( $step_id );
				}
			}
			self::$current_step = $step;
		}

		return self::$current_step;
	}

	/**
	 * Resolve the post id backing the current render — frontend singular page,
	 * block-editor ServerSideRender/REST, or the classic edit screen.
	 */
	protected static function resolve_page_id(): int {
		if ( is_singular( 'page' ) ) {
			return (int) get_queried_object_id();
		}
		// @phpcs:disable WordPress.Security.NonceVerification.Recommended
		if ( ! empty( $_REQUEST['post_id'] ) ) {
			return (int) $_REQUEST['post_id'];
		}
		if ( ! empty( $_GET['post'] ) ) {
			return (int) $_GET['post'];
		}
		// @phpcs:enable WordPress.Security.NonceVerification.Recommended
		$id = get_the_ID();

		return $id ? (int) $id : 0;
	}

	/**
	 * Are we rendering a block-editor preview? aBlocks dynamic blocks pass
	 * ?context=edit to the block-renderer; used to show sample output instead of
	 * a blank block when there's no live funnel/step context.
	 */
	protected static function is_preview(): bool {
		// @phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['context'] ) && 'edit' === sanitize_text_field( wp_unslash( $_GET['context'] ) );
	}

	/**
	 * A funnel button anchor (accept / skip / next-step).
	 */
	protected function button_html( string $modifier, string $label, string $url, int $step_id = 0 ): string {
		return sprintf(
			'<a class="storeengine-funnel-btn storeengine-funnel-btn--%s" data-step="%d" href="%s">%s</a>',
			esc_attr( $modifier ),
			$step_id,
			esc_url( $url ),
			esc_html( $label )
		);
	}

	public function track_view() {
		$step = self::current_step();
		if ( ! $step ) {
			return;
		}
		FunnelStats::record( $step->funnel_id, $step->id, 'view' );

		if ( 'checkout' === $step->type ) {
			// Seed the checkout step's assigned product into the cart so the funnel
			// checkout renders a real order summary + pay button even when the
			// visitor lands here directly (landing → checkout). No-op when the step
			// has no product (e.g. the global Store Checkout, which uses the live cart).
			self::ensure_checkout_product( $step );

			// With the cart still empty (no assigned product + nothing added), bounce
			// to the cart page — StoreEngine's core empty-cart guard only matches the
			// default checkout page id, so it never fires on a funnel checkout page.
			self::maybe_redirect_empty_checkout();

			// Hook the funnel checkout cart into abandonment tracking (no-op when the
			// abandoned-cart addon is inactive). Recovery returns to checkout, which
			// the Store Checkout override routes back through the funnel.
			do_action( 'storeengine_pro/abandoned_cart/track_cart' );
		}
	}

	/**
	 * Mirror StoreEngine's core empty-cart guard for funnel checkout step pages.
	 * Never fires while paying for an existing order, and never loops to itself.
	 */
	protected static function maybe_redirect_empty_checkout(): void {
		if ( ! is_singular( 'page' ) || headers_sent() ) {
			return;
		}
		// Paying for / receiving an existing order doesn't need a cart.
		if ( get_query_var( 'order_pay' ) || get_query_var( 'order-received' ) ) {
			return;
		}
		if ( ! apply_filters( 'storeengine/checkout_redirect_empty_cart', true ) ) {
			return;
		}

		$cart = \StoreEngine\Utils\Helper::cart();
		if ( ! $cart || ! $cart->is_cart_empty() ) {
			return;
		}

		$cart_url = \StoreEngine\Utils\Helper::get_cart_url();
		if ( ! $cart_url || untrailingslashit( $cart_url ) === untrailingslashit( (string) get_permalink() ) ) {
			return; // No cart page, or cart == checkout: don't loop.
		}

		wp_safe_redirect( $cart_url );
		exit;
	}

	/**
	 * Add a noindex robots directive to funnel step pages (they duplicate the
	 * checkout / are mid-funnel and shouldn't compete in search results).
	 */
	public function noindex_step_pages( $robots ) {
		if ( is_singular( 'page' ) && self::current_step() ) {
			$robots['noindex']  = true;
			$robots['nofollow'] = true;
			unset( $robots['index'], $robots['follow'] );
		}

		return $robots;
	}

	/**
	 * Ensure a checkout step's assigned product is present in the cart.
	 *
	 * Idempotent: when the configured product is already in the cart nothing
	 * happens, so refreshing the checkout keeps the quantity and any order bumps
	 * the shopper toggled. On first entry, `replace_cart` empties the cart first so
	 * the funnel represents only its own offer (CartFlows-style checkout step).
	 */
	protected static function ensure_checkout_product( FunnelStep $step ): void {
		$checkout = $step->settings['checkout'] ?? [];
		$price_id = (int) ( $checkout['price_id'] ?? 0 );
		if ( ! $price_id ) {
			return;
		}
		$quantity = max( 1, (int) ( $checkout['quantity'] ?? 1 ) );

		$cart = \StoreEngine\Utils\Helper::cart();
		if ( ! $cart ) {
			return;
		}

		foreach ( $cart->get_cart_items() as $item ) {
			if ( (int) $item->price_id === $price_id ) {
				return; // Already seeded — leave the cart (and any bumps) untouched.
			}
		}

		if ( ! empty( $checkout['replace_cart'] ) ) {
			foreach ( array_keys( $cart->get_cart_items() ) as $key ) {
				$cart->remove_cart_item( (string) $key );
			}
		}

		$result = $cart->add_product_to_cart( $price_id, $quantity );
		if ( ! is_wp_error( $result ) && method_exists( $cart, 'calculate_totals' ) ) {
			$cart->calculate_totals();
		}
	}

	/**
	 * The URL of the next step (or the configured fallback). Used by the
	 * accept/skip buttons and by Pro after a one-click charge.
	 */
	public static function next_step_url( FunnelStep $step ): string {
		// Advance to the next step whose page is live, skipping any step whose
		// backing page was trashed or deleted so the funnel never dead-ends on a
		// broken page.
		$next = $step->next();
		while ( $next ) {
			if ( $next->is_page_live() ) {
				return $next->get_url();
			}
			$next = $next->next();
		}

		// End of funnel — fall back to the configured redirect or the home url.
		$redirect = $step->settings['end_redirect'] ?? '';

		return $redirect ?: home_url( '/' );
	}

	/**
	 * Where a "No thanks" / decline should go.
	 *
	 * Offer steps (upsell/downsell) can point their decline at a specific step via
	 * the "When this offer is declined, go to" setting
	 * (`settings.routing.reject_step_id`), configured from the step's Settings
	 * drawer. When that target exists and its page is live we route there;
	 * otherwise (a non-offer step, an unset target, or a trashed/draft/deleted one)
	 * we fall back to the next step in sequence so a decline never dead-ends.
	 */
	public static function decline_url( FunnelStep $step ): string {
		if ( in_array( $step->type, [ 'upsell', 'downsell' ], true ) ) {
			$reject_id = (int) ( $step->settings['routing']['reject_step_id'] ?? 0 );
			if ( $reject_id ) {
				$target = FunnelStep::find( $reject_id );
				if ( $target && $target->is_page_live() ) {
					return $target->get_url();
				}
			}
		}

		return self::next_step_url( $step );
	}

	/**
	 * Checkout step — delegate to StoreEngine's checkout shortcode and stamp the
	 * funnel context so a completed order can be attributed (Hooks::track_purchase)
	 * and so Pro knows which funnel/step to upsell from.
	 */
	public function sc_checkout( $atts ): string {
		$step = self::current_step();
		if ( $step ) {
			$this->remember_context( $step );
		}

		if ( ! shortcode_exists( 'storeengine_checkout_form' ) ) {
			return '';
		}

		$atts = shortcode_atts( [ 'summary' => 'yes' ], $atts, 'storeengine_funnel_checkout' );
		$show_summary = 'no' !== strtolower( (string) $atts['summary'] );

		// Compose the full checkout: the form plus the order summary + totals, so a
		// funnel checkout page shows what's being bought and a working pay button
		// from a single block/shortcode (StoreEngine's default checkout splits these
		// across two columns/shortcodes). Wrapped in a dedicated class so the aBlocks
		// Funnel Checkout block's design controls have a stable hook to target.
		$form = do_shortcode( '[storeengine_checkout_form]' );

		$aside = '';
		if ( $show_summary ) {
			$summary = shortcode_exists( 'storeengine_order_summary' ) ? do_shortcode( '[storeengine_order_summary]' ) : '';
			$coupon  = shortcode_exists( 'storeengine_apply_coupon_form' ) ? do_shortcode( '[storeengine_apply_coupon_form]' ) : '';
			$totals  = shortcode_exists( 'storeengine_cart_sub_total_table' ) ? do_shortcode( '[storeengine_cart_sub_total_table]' ) : '';
			$aside   = $summary . $coupon . $totals;
		}

		return sprintf(
			'<div class="storeengine-funnel-checkout-shortcode">
				<div class="storeengine-funnel-checkout__main">%s</div>%s
			</div>',
			$form,
			$aside ? '<div class="storeengine-funnel-checkout__summary">' . $aside . '</div>' : ''
		);
	}

	/**
	 * Offer summary for an upsell/downsell step. Free renders a basic offer block
	 * from the step settings; Pro filters in the full one-click offer UI.
	 */
	public function sc_offer( $atts ): string {
		$step = self::current_step();
		if ( ! $step ) {
			if ( self::is_preview() ) {
				return '<div class="storeengine-funnel-offer-shortcode"><div class="storeengine-funnel-offer"><h3 class="storeengine-funnel-offer__title">'
					. esc_html__( 'Your offer appears here', 'storeengine' ) . '</h3>'
					. '<div class="storeengine-funnel-offer__desc">'
					. esc_html__( 'Shown on an upsell / downsell step using that step’s configured offer.', 'storeengine' )
					. '</div></div></div>';
			}
			return '';
		}

		// Block/shortcode atts can override the step's configured title/description.
		$atts  = shortcode_atts( [ 'title' => '', 'description' => '' ], $atts, 'storeengine_funnel_offer' );
		$offer = $step->settings['offer'] ?? [];
		$title = '' !== (string) $atts['title'] ? $atts['title'] : ( $offer['title'] ?? $step->name );
		$desc  = '' !== (string) $atts['description'] ? $atts['description'] : ( $offer['description'] ?? '' );

		$html  = '<div class="storeengine-funnel-offer">';
		$html .= '<h3 class="storeengine-funnel-offer__title">' . esc_html( $title ) . '</h3>';
		// Render the actual offered product (image + name) so the offer card shows
		// what's on offer, and so the aBlocks Funnel Offer block's product controls
		// have real markup to style. Uses the shared order-item classes for that.
		$html .= self::offer_product_html( (int) ( $offer['price_id'] ?? 0 ) );
		if ( $desc ) {
			$html .= '<div class="storeengine-funnel-offer__desc">' . wp_kses_post( wpautop( $desc ) ) . '</div>';
		}
		$html .= '</div>';

		/**
		 * Pro replaces/augments the offer markup with the live one-click offer
		 * (price + accept button bound to the saved payment token). Wrapped in a
		 * dedicated class so the aBlocks block's design controls can hook it.
		 */
		$html = apply_filters( 'storeengine/funnel-builder/offer_html', $html, $step );

		return '<div class="storeengine-funnel-offer-shortcode">' . $html . '</div>';
	}

	/**
	 * Product image + name markup for an offer step, resolved from the offered
	 * price id. Returns '' when no product is configured or it can't be loaded.
	 * Uses the shared order-item classes so the aBlocks Funnel Offer block's image
	 * and product-title controls apply to it.
	 */
	protected static function offer_product_html( int $price_id ): string {
		if ( ! $price_id || ! class_exists( '\StoreEngine\Utils\Helper' ) ) {
			return '';
		}

		try {
			$price = \StoreEngine\Utils\Helper::get_price( $price_id );
			if ( ! $price || ! method_exists( $price, 'get_product_id' ) ) {
				return '';
			}
			$product_id = (int) $price->get_product_id();
			if ( ! $product_id ) {
				return '';
			}

			$image = '';
			if ( function_exists( 'storeengine_product_image' ) ) {
				ob_start();
				storeengine_product_image( 'storeengine_thumbnail', $product_id, [ 'class' => 'storeengine-product__thumbnail-image' ] );
				$image = ob_get_clean();
			}

			$name = get_the_title( $product_id );
			$url  = get_permalink( $product_id );

			return '<div class="storeengine-order-item__content">'
				. ( $image ? '<div class="storeengine-order-item-entry-left">' . $image . '</div>' : '' )
				. '<div class="storeengine-order-item__title"><h6><a href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a></h6></div>'
				. '</div>';
		} catch ( \Throwable $e ) {
			return '';
		}
	}

	/**
	 * Accept button. Free routes to the next step; Pro intercepts the
	 * `accept_url` to charge the saved payment method first.
	 */
	public function sc_accept( $atts ): string {
		$step = self::current_step();
		$atts = shortcode_atts( [ 'label' => __( 'Yes, add this to my order', 'storeengine' ) ], $atts );

		if ( ! $step ) {
			return self::is_preview() ? $this->button_html( 'accept', $atts['label'], '#' ) : '';
		}

		$url = apply_filters( 'storeengine/funnel-builder/accept_url', self::next_step_url( $step ), $step );

		return $this->button_html( 'accept', $atts['label'], $url, (int) $step->id );
	}

	/**
	 * Skip/decline button — always routes to the next step.
	 */
	public function sc_skip( $atts ): string {
		$step = self::current_step();
		$atts = shortcode_atts( [ 'label' => __( 'No thanks', 'storeengine' ) ], $atts );

		if ( ! $step ) {
			return self::is_preview() ? $this->button_html( 'skip', $atts['label'], '#' ) : '';
		}

		return $this->button_html( 'skip', $atts['label'], self::decline_url( $step ), (int) $step->id );
	}

	/**
	 * Generic step-navigation button — advances the funnel from any step type
	 * (landing / opt-in / thank-you). Defaults to the next step in sequence; pass
	 * `step="<id>"` to jump to a specific step, or `url="…"` for a custom target.
	 *
	 * Usage: [storeengine_funnel_next_step label="Continue"]
	 *        [storeengine_funnel_next_step step="42" label="Get the deal"]
	 */
	public function sc_next_step( $atts ): string {
		$step = self::current_step();

		$atts = shortcode_atts( [
			'label' => __( 'Continue', 'storeengine' ),
			'step'  => 0,
			'url'   => '',
			'class' => '',
		], $atts, 'storeengine_funnel_next_step' );

		if ( ! $step ) {
			return self::is_preview() ? $this->button_html( 'next', $atts['label'], '#' ) : '';
		}

		// Resolve the destination: explicit url > specific step id > next step.
		$url = self::next_step_url( $step );
		if ( ! empty( $atts['step'] ) ) {
			$target = FunnelStep::find( (int) $atts['step'] );
			if ( $target && $target->page_id ) {
				$url = $target->get_url();
			}
		}
		if ( ! empty( $atts['url'] ) ) {
			$url = $atts['url'];
		}

		/**
		 * Lets integrations rewrite the navigation target (e.g. append tracking).
		 */
		$url = apply_filters( 'storeengine/funnel-builder/next_step_url', $url, $step, $atts );

		return sprintf(
			'<a class="storeengine-funnel-btn storeengine-funnel-btn--next %s" data-step="%d" href="%s">%s</a>',
			esc_attr( $atts['class'] ),
			(int) $step->id,
			esc_url( $url ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * Stash the active funnel/step on the StoreEngine cart/session so the order
	 * created at checkout carries the attribution meta. Uses order/session meta
	 * via the checkout hook downstream; here we keep it on a cookie as a
	 * lightweight, gateway-agnostic carrier.
	 */
	protected function remember_context( FunnelStep $step ): void {
		if ( headers_sent() ) {
			return;
		}
		$path = COOKIEPATH ? COOKIEPATH : '/';
		setcookie( 'storeengine_funnel_id', (string) $step->funnel_id, time() + HOUR_IN_SECONDS, $path, COOKIE_DOMAIN, is_ssl(), true );
		setcookie( 'storeengine_funnel_step_id', (string) $step->id, time() + HOUR_IN_SECONDS, $path, COOKIE_DOMAIN, is_ssl(), true );
	}
}
