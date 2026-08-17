<?php
/**
 * Funnel Builder data-layer bootstrap.
 *
 * Funnels/steps live in custom tables (see Installer); the only WP-native
 * objects are the step *pages* (normal `page` posts built with aBlocks). We mark
 * each step page with meta so the frontend router and the editor can resolve a
 * page back to its funnel/step cheaply.
 *
 * @version 1.0.0
 */

namespace StoreEngine\Addons\FunnelBuilder;

use StoreEngine\Addons\FunnelBuilder\Classes\FunnelStep;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Database {

	const PAGE_META_FUNNEL = '_storeengine_funnel_id';
	const PAGE_META_STEP   = '_storeengine_funnel_step_id';

	public static function init() {
		$self = new self();
		add_action( 'init', [ $self, 'register_page_meta' ] );
		// Non-destructive: when a step's page is PERMANENTLY deleted, just detach
		// it (page_id -> null) so the step survives and the editor can offer to
		// recreate the page. Trashing is intentionally ignored (reversible) and
		// surfaced as an editor notice instead.
		add_action( 'before_delete_post', [ $self, 'detach_deleted_page' ] );
	}

	/**
	 * Detach a step from its page when the page is permanently deleted.
	 */
	public function detach_deleted_page( $post_id ) {
		$post_id = (int) $post_id;
		if ( ! $post_id ) {
			return;
		}
		$step_id = (int) get_post_meta( $post_id, self::PAGE_META_STEP, true );
		if ( ! $step_id ) {
			return;
		}
		$step = FunnelStep::find( $step_id );
		if ( $step && (int) $step->page_id === $post_id ) {
			$step->page_id = null;
			$step->save();
		}
	}

	public function register_page_meta() {
		register_post_meta( 'page', self::PAGE_META_FUNNEL, [
			'type'          => 'integer',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => static function () {
				return current_user_can( 'edit_pages' );
			},
		] );

		register_post_meta( 'page', self::PAGE_META_STEP, [
			'type'          => 'integer',
			'single'        => true,
			'show_in_rest'  => true,
			'auth_callback' => static function () {
				return current_user_can( 'edit_pages' );
			},
		] );
	}

	/**
	 * Create (or reuse) the WP page that backs a funnel step. Built as a normal
	 * page so it opens in the block editor with aBlocks available.
	 *
	 * @param string $blueprint Optional blueprint id the step was created from
	 *                          (sales, leadgen, webinar…). Only tailors the seeded
	 *                          hero copy — the layout is driven by $type.
	 */
	public static function create_step_page( int $funnel_id, string $title, string $type = 'landing', string $blueprint = '' ): int {
		$page_id = wp_insert_post( [
			'post_title'   => $title ?: __( 'Funnel Step', 'storeengine' ),
			'post_status'  => 'publish',
			'post_type'    => 'page',
			'post_content' => self::starter_content( $type, $blueprint ),
		] );

		if ( is_wp_error( $page_id ) || ! $page_id ) {
			return 0;
		}

		update_post_meta( $page_id, self::PAGE_META_FUNNEL, $funnel_id );
		// Full-width canvas template (same as the default store pages) so a funnel
		// checkout can show its form + order summary side by side.
		update_post_meta( $page_id, '_wp_page_template', 'storeengine-canvas.php' );

		return (int) $page_id;
	}

	/**
	 * A ready-to-edit starter design per step type. Every step is seeded with a
	 * complete, on-brand page — hero, supporting sections and the functional
	 * funnel blocks — using core Gutenberg blocks so it renders with just WordPress
	 * (aBlocks, when present, upgrades the funnel blocks to styled native ones).
	 *
	 * The merchant lands on a designed page they can tweak, instead of a blank
	 * canvas.
	 *
	 * @param string $type      Step type (landing, optin, checkout, upsell…).
	 * @param string $blueprint Blueprint id, used to pick the hero copy.
	 */
	protected static function starter_content( string $type, string $blueprint = '' ): string {
		$b    = '\StoreEngine\Blocks\Bridge';
		$hero = self::blueprint_hero( $blueprint );

		switch ( $type ) {
			case 'checkout':
				return self::join( [
					self::heading( __( 'Complete your order', 'storeengine' ), 1 ),
					self::para( __( 'You’re just one step away — enter your details below to finish up. It only takes a minute.', 'storeengine' ) ),
					self::spacer( 24 ),
					$b::block( 'storeengine/storeengine_funnel_checkout' ),
					self::spacer( 40 ),
					self::columns( [
						self::feature( '🔒', __( 'Secure checkout', 'storeengine' ), __( 'Your payment is encrypted and processed safely.', 'storeengine' ) ),
						self::feature( '↩️', __( 'Money-back guarantee', 'storeengine' ), __( 'Not happy? Get a full refund, no questions asked.', 'storeengine' ) ),
						self::feature( '💬', __( 'Friendly support', 'storeengine' ), __( 'Real people ready to help whenever you need it.', 'storeengine' ) ),
					] ),
				] );

			case 'upsell':
			case 'downsell':
				$is_down = 'downsell' === $type;
				$title   = $is_down
					? __( 'Hold on — here’s a smaller offer', 'storeengine' )
					: __( 'Wait! Your order isn’t complete', 'storeengine' );
				$intro   = $is_down
					? __( 'No problem if that wasn’t for you. Here’s a lighter one-time deal you can still add to your order.', 'storeengine' )
					: __( 'Add this exclusive one-time offer to your order now — you won’t see this price again.', 'storeengine' );

				return self::join( [
					self::section(
						self::join( [
							self::heading( $title, 1 ),
							self::lead( $intro ),
						] ),
						$is_down ? '#eff6ff' : '#fff7ed'
					),
					self::spacer( 32 ),
					$b::block( 'storeengine/storeengine_funnel_offer' ),
					self::spacer( 16 ),
					self::center( self::join( [
						$b::block( 'storeengine/storeengine_funnel_accept' ),
						$b::block( 'storeengine/storeengine_funnel_skip' ),
					] ) ),
					self::spacer( 24 ),
					self::para( __( 'This one-time offer is only available on this page — once you leave, it’s gone.', 'storeengine' ) ),
				] );

			case 'thankyou':
				return self::join( [
					self::section(
						self::join( [
							self::heading( __( 'Thank you for your order! 🎉', 'storeengine' ), 1 ),
							self::lead( __( 'Your order is confirmed. A receipt is on its way to your inbox.', 'storeengine' ) ),
						] ),
						'#f0fdf4'
					),
					self::spacer( 32 ),
					$b::block( 'storeengine/storeengine_thankyou_order_info' ),
					self::spacer( 40 ),
					self::heading( __( 'What happens next?', 'storeengine' ) ),
					self::bullet_list( [
						__( 'Check your email for the order confirmation and receipt.', 'storeengine' ),
						__( 'We’ll notify you as soon as your order is on its way.', 'storeengine' ),
						__( 'Need help? Just reply to your confirmation email.', 'storeengine' ),
					] ),
				] );

			case 'optin':
				return self::join( [
					self::section(
						self::join( [
							self::heading( $hero['headline'], 1 ),
							self::lead( $hero['sub'] ),
							self::bullet_list( [
								__( 'Instant access — no waiting around.', 'storeengine' ),
								__( 'Practical, no-fluff tips you can use today.', 'storeengine' ),
								__( 'Unsubscribe any time with one click.', 'storeengine' ),
							] ),
							self::spacer( 16 ),
							self::center( $b::block( 'storeengine/storeengine_funnel_next_step', [ 'label' => $hero['cta'] ] ) ),
						] ),
						'#f6f7fb'
					),
					self::spacer( 24 ),
					self::para( __( 'We respect your privacy. Your details are safe and never shared.', 'storeengine' ) ),
				] );

			case 'landing':
			default:
				return self::join( [
					self::section(
						self::join( [
							self::heading( $hero['headline'], 1 ),
							self::lead( $hero['sub'] ),
							self::spacer( 8 ),
							self::center( $b::block( 'storeengine/storeengine_funnel_next_step', [ 'label' => $hero['cta'] ] ) ),
						] ),
						'#f6f7fb'
					),
					self::spacer( 48 ),
					self::heading( __( 'Why you’ll love it', 'storeengine' ) ),
					self::spacer( 8 ),
					self::columns( [
						self::feature( '⚡', __( 'Fast results', 'storeengine' ), __( 'See the difference from day one — no steep learning curve.', 'storeengine' ) ),
						self::feature( '🛡️', __( 'Trusted & proven', 'storeengine' ), __( 'Loved by thousands of customers who keep coming back.', 'storeengine' ) ),
						self::feature( '💡', __( 'Simple to use', 'storeengine' ), __( 'Designed to just work, so you can focus on what matters.', 'storeengine' ) ),
					] ),
					self::spacer( 48 ),
					self::quote(
						__( 'This is exactly what I was looking for. I only wish I’d found it sooner!', 'storeengine' ),
						__( 'A happy customer', 'storeengine' )
					),
					self::spacer( 48 ),
					self::section(
						self::join( [
							self::heading( __( 'Ready to get started?', 'storeengine' ) ),
							self::para( __( 'Join today and see why so many people choose us.', 'storeengine' ) ),
							self::spacer( 8 ),
							self::center( $b::block( 'storeengine/storeengine_funnel_next_step', [ 'label' => $hero['cta'] ] ) ),
						] ),
						'#f6f7fb'
					),
				] );
		}
	}

	/**
	 * Hero copy (headline / sub-heading / CTA label) for the funnel's entry pages,
	 * tailored to the chosen blueprint so a "Webinar" funnel doesn't open with
	 * "Sales" copy. Falls back to neutral placeholder copy for unknown/blank ids.
	 *
	 * @return array{headline:string,sub:string,cta:string}
	 */
	protected static function blueprint_hero( string $blueprint ): array {
		$heroes = [
			'sales'           => [
				'headline' => __( 'A better way to get results', 'storeengine' ),
				'sub'      => __( 'Discover the product thousands of customers already love — and grab yours today at a special price.', 'storeengine' ),
				'cta'      => __( 'Get started', 'storeengine' ),
			],
			'simple'          => [
				'headline' => __( 'Get yours today', 'storeengine' ),
				'sub'      => __( 'A quick, no-hassle checkout — you’ll be done in under a minute.', 'storeengine' ),
				'cta'      => __( 'Buy now', 'storeengine' ),
			],
			'leadgen'         => [
				'headline' => __( 'Get the free guide', 'storeengine' ),
				'sub'      => __( 'Tell us where to send it and we’ll deliver it straight to your inbox — no cost, no catch.', 'storeengine' ),
				'cta'      => __( 'Get instant access', 'storeengine' ),
			],
			'lead-magnet'     => [
				'headline' => __( 'Grab your free download', 'storeengine' ),
				'sub'      => __( 'Enter your email on the next step and we’ll send your free resource right away.', 'storeengine' ),
				'cta'      => __( 'Send it to me', 'storeengine' ),
			],
			'webinar'         => [
				'headline' => __( 'Save your seat for the live class', 'storeengine' ),
				'sub'      => __( 'Join us live and learn the exact system we use — seats are limited, so register now.', 'storeengine' ),
				'cta'      => __( 'Reserve my seat', 'storeengine' ),
			],
			'tripwire'        => [
				'headline' => __( 'An offer you won’t want to miss', 'storeengine' ),
				'sub'      => __( 'Get started for a tiny price today and unlock the full experience.', 'storeengine' ),
				'cta'      => __( 'Claim this deal', 'storeengine' ),
			],
			'product-launch'  => [
				'headline' => __( 'Something new is here', 'storeengine' ),
				'sub'      => __( 'Be among the first to get it. Limited launch pricing won’t last long.', 'storeengine' ),
				'cta'      => __( 'Get early access', 'storeengine' ),
			],
		];

		return $heroes[ $blueprint ] ?? [
			'headline' => __( 'Your headline goes here', 'storeengine' ),
			'sub'      => __( 'Add a short, punchy subheading that tells visitors why they should keep reading.', 'storeengine' ),
			'cta'      => __( 'Continue', 'storeengine' ),
		];
	}

	/* ---------------------------------------------------------------------- *
	 * Core-block builders — tiny helpers that serialize valid block markup so
	 * the seeded pages open cleanly in the block editor (no "invalid content").
	 * ---------------------------------------------------------------------- */

	/** Join block strings with the blank line the serializer expects between them. */
	protected static function join( array $blocks ): string {
		return implode( "\n\n", array_filter( $blocks ) );
	}

	protected static function heading( string $text, int $level = 2 ): string {
		return sprintf(
			'<!-- wp:heading {"textAlign":"center","level":%1$d} -->' . "\n" .
			'<h%1$d class="wp-block-heading has-text-align-center">%2$s</h%1$d>' . "\n" .
			'<!-- /wp:heading -->',
			$level,
			esc_html( $text )
		);
	}

	/** Larger, centered intro paragraph. */
	protected static function lead( string $text ): string {
		return sprintf(
			'<!-- wp:paragraph {"align":"center","fontSize":"large"} -->' . "\n" .
			'<p class="has-text-align-center has-large-font-size">%s</p>' . "\n" .
			'<!-- /wp:paragraph -->',
			esc_html( $text )
		);
	}

	protected static function para( string $text ): string {
		return sprintf(
			'<!-- wp:paragraph {"align":"center"} -->' . "\n" .
			'<p class="has-text-align-center">%s</p>' . "\n" .
			'<!-- /wp:paragraph -->',
			esc_html( $text )
		);
	}

	protected static function spacer( int $height = 40 ): string {
		return sprintf(
			'<!-- wp:spacer {"height":"%1$dpx"} -->' . "\n" .
			'<div style="height:%1$dpx" aria-hidden="true" class="wp-block-spacer"></div>' . "\n" .
			'<!-- /wp:spacer -->',
			$height
		);
	}

	/** Constrained group section, optionally with a padded background color. */
	protected static function section( string $inner, string $bg = '' ): string {
		if ( $bg ) {
			return sprintf(
				'<!-- wp:group {"style":{"color":{"background":"%1$s"},"spacing":{"padding":{"top":"56px","bottom":"56px","left":"24px","right":"24px"}}},"layout":{"type":"constrained"}} -->' . "\n" .
				'<div class="wp-block-group has-background" style="background-color:%1$s;padding-top:56px;padding-right:24px;padding-bottom:56px;padding-left:24px">%2$s</div>' . "\n" .
				'<!-- /wp:group -->',
				esc_attr( $bg ),
				$inner
			);
		}

		return '<!-- wp:group {"layout":{"type":"constrained"}} -->' . "\n" .
			'<div class="wp-block-group">' . $inner . '</div>' . "\n" .
			'<!-- /wp:group -->';
	}

	/** Flex group that centers its inline children (used to center CTA buttons). */
	protected static function center( string $inner ): string {
		return '<!-- wp:group {"layout":{"type":"flex","justifyContent":"center","flexWrap":"wrap"}} -->' . "\n" .
			'<div class="wp-block-group">' . $inner . '</div>' . "\n" .
			'<!-- /wp:group -->';
	}

	/** Equal-width columns row. Each entry is the inner markup of one column. */
	protected static function columns( array $cols ): string {
		$inner = '';
		foreach ( $cols as $c ) {
			$inner .= '<!-- wp:column -->' . "\n" .
				'<div class="wp-block-column">' . $c . '</div>' . "\n" .
				'<!-- /wp:column -->' . "\n";
		}

		return '<!-- wp:columns {"align":"wide"} -->' . "\n" .
			'<div class="wp-block-columns alignwide">' . $inner . '</div>' . "\n" .
			'<!-- /wp:columns -->';
	}

	/** A single feature/benefit cell: an emoji, a bold title and a short blurb. */
	protected static function feature( string $emoji, string $title, string $text ): string {
		return self::join( [
			self::para( $emoji ),
			self::heading( $title, 3 ),
			self::para( $text ),
		] );
	}

	protected static function bullet_list( array $items ): string {
		$lis = '';
		foreach ( $items as $item ) {
			$lis .= '<!-- wp:list-item -->' . "\n" .
				'<li>' . esc_html( $item ) . '</li>' . "\n" .
				'<!-- /wp:list-item -->' . "\n";
		}

		return '<!-- wp:list -->' . "\n" .
			'<ul class="wp-block-list">' . $lis . '</ul>' . "\n" .
			'<!-- /wp:list -->';
	}

	protected static function quote( string $text, string $cite ): string {
		return '<!-- wp:quote {"className":"is-style-large"} -->' . "\n" .
			'<blockquote class="wp-block-quote is-style-large">' .
			'<!-- wp:paragraph {"align":"center"} -->' . "\n" .
			'<p class="has-text-align-center">' . esc_html( $text ) . '</p>' . "\n" .
			'<!-- /wp:paragraph --><cite>' . esc_html( $cite ) . '</cite></blockquote>' . "\n" .
			'<!-- /wp:quote -->';
	}
}
