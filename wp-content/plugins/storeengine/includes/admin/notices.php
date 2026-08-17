<?php
/**
 * Admin notice handler.
 */

namespace StoreEngine\Admin;

use StoreEngine\ActionQueue;
use StoreEngine\Installer;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\CheckoutFields;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\ShippingUtils;
use StoreEngine\Utils\TaxUtil;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Notices {
	use Singleton;

	const TYPE_INFO = 'info';

	const TYPE_SUCCESS = 'success';

	const TYPE_WARNING = 'warning';

	const TYPE_ERROR = 'error';

	private static array $notices = [];

	private static string $action = 'storeengine/hide-notice';

	private static string $option_name = 'storeengine_notices';

	protected function __construct() {

		add_action( 'init', [ $this, 'dispatch_notices' ] );
		add_filter( 'storeengine/backend_scripts_data', [ $this, 'add_notice_data' ] );
		add_action( 'admin_notices', [ $this, 'render_notices' ] );

		// Handle dismissal requests.
		add_action( 'admin_init', [ $this, 'handle_admin_request' ], 20 );
		add_action( 'wp_ajax_' . self::$action, [ $this, 'handle_admin_request' ] );
	}

	public function dispatch_notices() {
		Notices::show_get_pro_nag();

		if ( empty( get_option( 'permalink_structure' ) ) ) {
			$this->add_permalink_notice();
		}


		if ( ! Installer::is_new_install() ) {
			self::add_ablocks_notice();
		}

		if ( ! self::is_uploads_directory_protected() ) {
			self::add_unprotected_uploads_directory_notice();
		}

		$core_pages    = [
			'shop_page',
			'cart_page',
			'checkout_page',
			'thankyou_page',
			'dashboard_page',
		];
		$contents      = Helper::get_store_page_contents();
		$missing_pages = [];

		foreach ( $core_pages as $page ) {
			if ( ! Helper::get_settings( $page ) || ! get_post( Helper::get_settings( $page ) ) ) {
				$missing_pages[ $page ] = $contents[ $page ]['title'];
			}
		}

		// Auto-create any missing core pages (e.g. when the setup wizard was
		// skipped/cancelled) so the store works without the merchant having to
		// click "Generate Missing Pages" by hand.
		$missing_pages = $this->maybe_autogenerate_core_pages( $missing_pages );

		if ( ! empty( $missing_pages ) ) {
			self::add_notice( 'missing_core_pages', [
				'type'          => self::TYPE_ERROR,
				'icon'          => 'info',
				'alt'           => false,
				'large'         => false,
				'classes'       => '',
				'title'         => '',
				'message'       => sprintf(
				// translators: %s. Missing pages.
					__( '<h3>Some core pages are missing.</h3><p>One or more required pages were not found: <code>%s</code>.</p>', 'storeengine' ),
					implode( ', ', $missing_pages )
				),
				'button_text'   => __( 'Generate Missing Pages', 'storeengine' ),
				'button_action' => admin_url( 'admin.php?page=storeengine-tools&path=pages&regenerate=1' ),
				'dismissible'   => false,
			] );
		}

		self::add_shipping_address_fields_notice();
		self::add_postcode_required_notice();
		self::add_unconfigured_gateways_notice();
		self::add_catalog_mode_notice();
	}

	/**
	 * Explain why prices / add-to-cart may be missing when Catalog Mode is on.
	 *
	 * Catalog Mode intentionally hides prices and/or the add-to-cart button, so a
	 * merchant who doesn't know it's enabled has no way to understand why their
	 * store looks "broken". Show a persistent (non-dismissible) info notice while
	 * it's active, with a shortcut to its settings — it disappears the moment the
	 * mode is switched off.
	 */
	public static function add_catalog_mode_notice() {
		if ( self::has_notice( 'catalog_mode_active' ) ) {
			return;
		}

		// Catalog Mode only actually runs while its addon is active. The
		// `enabled` setting persists after the addon is deactivated, so gate on
		// the addon too — otherwise the notice lingers even though nothing is
		// being hidden on the storefront.
		if ( ! Helper::get_addon_active_status( 'catalog-mode' ) ) {
			return;
		}

		$catalog = (array) Helper::get_settings( 'catalog_mode' );

		if ( empty( $catalog['enabled'] ) ) {
			return;
		}

		// Describe what's actually hidden so the message matches the config.
		$hidden = [];
		if ( ! empty( $catalog['disable_price'] ) ) {
			$hidden[] = __( 'prices', 'storeengine' );
		}
		if ( ! empty( $catalog['disable_cart_checkout'] ) || 'all' === ( $catalog['hide_add_to_cart_in'] ?? '' ) ) {
			$hidden[] = __( 'the add-to-cart button', 'storeengine' );
		}
		$hidden_text = empty( $hidden )
			? __( 'some storefront elements', 'storeengine' )
			: implode( __( ' and ', 'storeengine' ), $hidden );

		self::add_notice( 'catalog_mode_active', [
			'type'          => self::TYPE_INFO,
			'dismissible'   => false,
			'message'       => sprintf(
				/* translators: %s: what catalog mode is hiding, e.g. "prices and the add-to-cart button". */
				__( '<h3>Catalog Mode is on.</h3><p>Your store is running as a catalog, so %s are hidden by design. If that isn\'t what you expected, turn Catalog Mode off or adjust it in its settings.</p>', 'storeengine' ),
				$hidden_text
			),
			'button_text'   => __( 'Catalog Mode settings', 'storeengine' ),
			'button_action' => admin_url( 'admin.php?page=storeengine-settings&path=catalog-mode' ),
		] );
	}

	/**
	 * Prompt the merchant to finish connecting the payment methods they enabled.
	 *
	 * A payment gateway can be switched on (e.g. from the setup wizard's Payments
	 * step) before its API credentials are entered — in that state it can't take
	 * a single payment. Surface a dismissible notice listing every enabled-but-
	 * unconfigured gateway with a shortcut to the Payments settings. The notice
	 * self-clears the moment each gateway reports it no longer `needs_setup()`.
	 */
	public static function add_unconfigured_gateways_notice() {
		if ( self::has_notice( 'payment_gateways_need_setup' ) ) {
			return;
		}

		$gateways = Helper::get_payment_gateways()->payment_gateways();

		if ( empty( $gateways ) ) {
			return;
		}

		$pending = [];
		foreach ( $gateways as $gateway ) {
			if ( method_exists( $gateway, 'needs_setup' ) && $gateway->needs_setup() ) {
				$pending[] = '<strong>' . esc_html( $gateway->get_method_title() ) . '</strong>';
			}
		}

		if ( empty( $pending ) ) {
			return;
		}

		$names = implode( ', ', $pending );

		self::add_notice( 'payment_gateways_need_setup', [
			'type'          => self::TYPE_WARNING,
			'dismissible'   => true,
			'message'       => sprintf(
				/* translators: %s: comma-separated payment method names, already wrapped in <strong> tags. */
				_n(
					'<h3>Connect your payment method to start selling.</h3><p>You enabled %s during setup, but it still needs its API credentials before it can accept payments.</p>',
					'<h3>Connect your payment methods to start selling.</h3><p>You enabled %s during setup, but they still need their API credentials before they can accept payments.</p>',
					count( $pending ),
					'storeengine'
				),
				$names
			),
			'button_text'   => __( 'Configure payments', 'storeengine' ),
			'button_action' => admin_url( 'admin.php?page=storeengine-settings&path=payment-method' ),
		] );
	}

	/**
	 * Make sure the store's core pages always exist.
	 *
	 * A fresh install offers the setup wizard, whose "Setup Pages" step creates
	 * these pages (optionally with a slug prefix or mapped to existing
	 * store pages). If the admin skips or cancels the wizard, the store is
	 * left without a Cart, Checkout, etc. and nothing works — surfaced only as a
	 * "Some core pages are missing" notice they have to act on manually.
	 *
	 * Instead, silently create any missing core pages with their default slugs
	 * the moment we notice they're gone. This runs everywhere EXCEPT the wizard
	 * screen itself, which still owns first-run creation so we never pre-empt its
	 * prefix / page-mapping choices. {@see Helper::create_page()} is
	 * idempotent (reuses existing/trashed pages, skips ones already configured),
	 * so this only fills the gaps.
	 *
	 * @param array $missing Missing pages as setting-key => title.
	 *
	 * @return array The pages still missing after the attempt (usually empty).
	 */
	private function maybe_autogenerate_core_pages( array $missing ): array {
		if ( empty( $missing ) ) {
			return $missing;
		}

		// Only a store manager on a normal admin page should trigger creation.
		// Skip AJAX / REST / cron, and the setup wizard (it creates them itself).
		$on_setup_screen = isset( $_GET['page'] ) && Setup::PAGE_ID === sanitize_key( wp_unslash( $_GET['page'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if (
			! is_admin() ||
			wp_doing_ajax() ||
			( defined( 'REST_REQUEST' ) && REST_REQUEST ) ||
			$on_setup_screen ||
			! current_user_can( 'manage_options' )
		) {
			return $missing;
		}

		// Don't hammer create-page queries every request if creation keeps
		// failing; retry at most hourly. A successful run resolves the missing
		// state so the lock never matters.
		if ( get_transient( 'storeengine_autogenerate_core_pages_lock' ) ) {
			return $missing;
		}
		set_transient( 'storeengine_autogenerate_core_pages_lock', 'yes', HOUR_IN_SECONDS );

		// Create any missing pages with default (no-prefix) slugs and refresh
		// the in-memory settings so the re-check below sees the new IDs.
		Helper::create_initial_pages();
		Settings::load_settings();

		$contents      = Helper::get_store_page_contents();
		$still_missing = [];
		foreach ( array_keys( $missing ) as $page ) {
			if ( ! Helper::get_settings( $page ) || ! get_post( Helper::get_settings( $page ) ) ) {
				$still_missing[ $page ] = $contents[ $page ]['title'];
			}
		}

		// Everything created — clear the lock so a future deletion is handled
		// promptly instead of waiting out the hour.
		if ( empty( $still_missing ) ) {
			delete_transient( 'storeengine_autogenerate_core_pages_lock' );
		}

		return $still_missing;
	}

	/**
	 * Warn the admin when shipping and/or tax is active but the Postcode field
	 * customers rely on to trigger those calculations isn't collected.
	 *
	 * Both shipping-zone matching ({@see ShippingUtils}) and tax-rate matching
	 * ({@see \StoreEngine\Classes\Tax}) look the rate up by the destination
	 * postcode. If the relevant Postcode checkout field is disabled — or merely
	 * optional, so a shopper can leave it blank — postcode-based rates cannot be
	 * matched and the order silently gets the wrong (or zero) shipping/tax.
	 *
	 * Shipping's own postcode is auto-promoted to required whenever the field is
	 * enabled and shipping is live (see CheckoutFields::all()), so the real gaps
	 * this catches are: (a) the field turned off entirely, and (b) billing-based
	 * tax, where the billing Postcode has no such promotion and can be left
	 * optional.
	 */
	public static function add_postcode_required_notice() {
		if ( self::has_notice( 'postcode_field_not_collected' ) ) {
			return;
		}

		$shipping_live = ShippingUtils::is_shipping_enabled() && ShippingUtils::get_shipping_methods_count( true ) > 0;
		$tax_enabled   = TaxUtil::is_tax_enabled();

		// Nothing depends on the postcode unless shipping or tax is in play.
		if ( ! $shipping_live && ! $tax_enabled ) {
			return;
		}

		// Map each postcode field that actually feeds a calculation to the
		// feature(s) that need it. Shipping always resolves against the shipping
		// address; tax follows the "Calculate tax based on" setting (a `base`
		// address uses the store's own postcode, so nothing to collect there).
		$needed = [];
		if ( $shipping_live ) {
			$needed['shipping_post_code'][] = __( 'shipping', 'storeengine' );
		}
		if ( $tax_enabled ) {
			$tax_based_on = TaxUtil::tax_based_on();
			if ( 'billing' === $tax_based_on ) {
				$needed['billing_post_code'][] = __( 'tax', 'storeengine' );
			} elseif ( 'shipping' === $tax_based_on ) {
				$needed['shipping_post_code'][] = __( 'tax', 'storeengine' );
			}
		}

		// Collect the fields that aren't reliably captured — disabled outright or
		// enabled-but-optional. Uses resolved values so shipping's auto-promotion
		// isn't reported as a false positive.
		$problems = [];
		foreach ( $needed as $field_id => $features ) {
			$row      = CheckoutFields::get( $field_id );
			$enabled  = $row ? ! empty( $row['enabled'] ) : true;
			$required = $row ? ! empty( $row['required'] ) : true;

			if ( $enabled && $required ) {
				continue;
			}

			$label = 'billing_post_code' === $field_id
				? __( 'Billing Postcode', 'storeengine' )
				: __( 'Shipping Postcode', 'storeengine' );

			// Join the affected features ("shipping", "tax") into a readable phrase.
			// There are at most two, so a simple " and " join is enough.
			$feature_text = implode( __( ' and ', 'storeengine' ), $features );

			$problems[] = sprintf(
				/* translators: 1: checkout field label, 2: affected features e.g. "shipping and tax" */
				__( '<strong>%1$s</strong> (used for %2$s)', 'storeengine' ),
				$label,
				$feature_text
			);
		}

		if ( empty( $problems ) ) {
			return;
		}

		self::add_notice( 'postcode_field_not_collected', [
			'type'          => self::TYPE_WARNING,
			'dismissible'   => true,
			'message'       => sprintf(
				/* translators: %s: list of postcode fields that are not required, already wrapped in <li> items. */
				__( '<h3>Shipping &amp; tax may be calculated incorrectly.</h3><p>StoreEngine matches shipping and tax rates by postcode/ZIP, but the field customers need isn\'t being collected as required:</p><ul style="list-style:disc;margin-left:20px;">%s</ul><p>Enable it and mark it <strong>required</strong> under Checkout Fields so rates can always be matched.</p>', 'storeengine' ),
				'<li>' . implode( '</li><li>', $problems ) . '</li>'
			),
			'button_text'   => __( 'Review Checkout Fields', 'storeengine' ),
			'button_action' => admin_url( 'admin.php?page=storeengine-settings&path=checkout-fields' ),
		] );
	}

	/**
	 * Warn the admin when shipping is active but customers can't enter the
	 * address details needed to calculate it.
	 *
	 * Shipping zone matching and rate calculation rely on a "full" shipping
	 * address. When BOTH the State / Region and Postal Code checkout fields are
	 * disabled, the only location detail left is the City — so an empty City
	 * (or a store that expected to collect a ZIP) silently yields zero shipping
	 * options with no feedback to the shopper. Surface that misconfiguration so
	 * it isn't discovered the hard way at checkout.
	 */
	public static function add_shipping_address_fields_notice() {
		if ( self::has_notice( 'shipping_address_fields_disabled' ) ) {
			return;
		}

		// Only relevant when shipping is actually in use (enabled + at least one live method).
		if ( ! ShippingUtils::is_shipping_enabled() || ShippingUtils::get_shipping_methods_count( true ) < 1 ) {
			return;
		}

		$state = CheckoutFields::get( 'shipping_state' );
		$post  = CheckoutFields::get( 'shipping_post_code' );

		// Unsaved fields default to enabled, so a null row is treated as "available".
		$state_enabled = $state ? ! empty( $state['enabled'] ) : true;
		$post_enabled  = $post ? ! empty( $post['enabled'] ) : true;

		// At least one of State / Postal Code is available — nothing to warn about.
		if ( $state_enabled || $post_enabled ) {
			return;
		}

		self::add_notice( 'shipping_address_fields_disabled', [
			'type'          => self::TYPE_WARNING,
			'dismissible'   => true,
			'message'       => __( '<h3>Shipping may not work at checkout.</h3><p>You have shipping methods enabled, but both the <strong>State / Region</strong> and <strong>Postal Code</strong> checkout fields are turned off. StoreEngine uses these to match shipping zones and calculate shipping costs — with both disabled, customers may see no shipping options. Enable at least one of them under Checkout Fields.</p>', 'storeengine' ),
			'button_text'   => __( 'Enable Address Fields', 'storeengine' ),
			'button_action' => admin_url( 'admin.php?page=storeengine-settings&path=checkout-fields' ),
		] );
	}

	private function respond_error( $message, $code = 403 ) {
		if ( ! wp_doing_ajax() ) {
			wp_die( esc_html( $message ), esc_html__( 'Action failed.', 'storeengine' ), [ 'response' => $code ] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}

		wp_send_json_error( $message, $code );
	}

	private function respond_success( $message = null ) {
		if ( ! wp_doing_ajax() ) {
			wp_safe_redirect( remove_query_arg( [ 'action', 'security', 'notice' ] ) );
			die();
		}

		wp_send_json_success( $message );
	}

	public function handle_admin_request() {
		if ( isset( $_REQUEST['action'], $_REQUEST['security'] ) && ! empty( $_REQUEST['notice'] ) && self::$action === wp_unslash( $_REQUEST['action'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_REQUEST['security'] ) ), 'storeengine_nonce' ) ) {
				$this->respond_error( __( 'Action failed. Please refresh the page and retry.', 'storeengine' ) );
			}

			$notice     = sanitize_text_field( wp_unslash( $_REQUEST['notice'] ) );
			$capability = apply_filters( 'storeengine/admin_notice/dismissal_capability', 'manage_options', $notice );

			if ( ! current_user_can( $capability ) ) {
				$this->respond_error( __( "You don't have permission to do this.", 'storeengine' ), 403 );
			}

			if ( ! self::has_notice( $notice ) ) {
				$this->respond_success(); // Return the user. Notice may have already been removed (different tab).
			}

			self::remove_notice( $notice );

			$this->respond_success();
		}

	}

	public function add_notice_data( array $data ): array {
		return array_merge( $data, [ 'admin_notices' => array_values( self::get_notices() ) ] );
	}

	public function render_notices() {
		global $pagenow;

		$notices = self::get_notices();

		if ( ! empty( $notices ) ) {
			$has_dismissible = false;
			foreach ( $notices as $notice ) {
				$classes = array_merge(
					[
						'storeengine-admin-notice',
						'storeengine-admin-notice-' . $notice['type'],
						'notice',
						str_replace( [ '.' ], '-', $pagenow ),
						'storeengine-admin-notice--' . $notice['key'],

					],
					array_filter( $notice['classes'] )
				);

				switch ( $notice['type'] ) {
					case 'error':
						$classes[] = 'notice-error';
						break;
					case 'warning':
						$classes[] = 'notice-warning';
						break;
					case 'success':
						$classes[] = 'notice-success';
						break;
					case 'info':
					default:
						$classes[] = 'notice-info';
						break;
				}

				if ( $notice['large'] ) {
					$classes[] = 'notice-large';
				}

				if ( $notice['alt'] ) {
					$classes[] = 'notice-alt';
				}

				if ( $notice['dismissible'] ) {
					$classes[] = 'is-dismissible';
					if ( ! $has_dismissible ) {
						$has_dismissible = true;
					}
				}

				$classes = implode( ' ', $classes );

				include __DIR__ . '/notice-html.php';
			}

			// Always enqueue — the footer script wires dismissal AND collapses
			// 3+ notices behind a grouping "bell". ( $has_dismissible retained
			// for clarity; grouping needs to run regardless. )
			add_action( 'admin_footer', [ $this, 'add_notice_script' ], 100 );
			unset( $has_dismissible );
		}
	}

	/**
	 * Inline SVG for the small icon set used by admin notices and the grouping
	 * bell. This replaces the icon-font glyphs so the always-loaded admin path
	 * no longer needs the full icon-font stylesheet + font files.
	 *
	 * The returned <svg> keeps the `storeengine-icon` class and is sized in `em`
	 * with `currentColor`, so the existing size/color CSS keeps working. Unknown
	 * names fall back to "info". The markup is built from a static whitelist
	 * (only the resolved name is interpolated, and it is escaped), so callers can
	 * echo the return value directly.
	 *
	 * @param string $name Icon name (info|success|warning|error|close|notification).
	 * @return string Inline SVG markup.
	 */
	public static function get_svg_icon( string $name ): string {
		$paths = [
			'info'         => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>',
			'success'      => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
			'warning'      => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
			'error'        => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
			'close'        => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
			'notification' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
		];

		// Legacy icon-font names that map onto one of the SVGs above.
		$aliases = [
			'check-circle'       => 'success',
			'notify-success'     => 'success',
			'notify-warning'     => 'warning',
			'notify-error'       => 'error',
			'notify-information' => 'info',
			'info-circle'        => 'info',
			'info-primary'       => 'info',
		];

		if ( isset( $aliases[ $name ] ) ) {
			$name = $aliases[ $name ];
		}

		if ( ! isset( $paths[ $name ] ) ) {
			$name = 'info';
		}

		return sprintf(
			'<svg class="storeengine-icon storeengine-icon--%1$s" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">%2$s</svg>',
			esc_attr( $name ),
			$paths[ $name ]
		);
	}

	public function add_notice_script() {
		$labels = [
			'one'      => __( '1 thing needs your attention', 'storeengine' ),
			// translators: %d is the number of admin notices needing attention.
			'many'     => __( '%d things need your attention', 'storeengine' ),
			'expand'   => __( 'Expand', 'storeengine' ),
			'collapse' => __( 'Collapse', 'storeengine' ),
		];
		?>
		<script>
			( ( $ ) => {
				const L = <?php echo wp_json_encode( $labels ); ?>;
				const label = ( n ) => n === 1 ? L.one : L.many.replace( '%d', n );

				// --- Dismiss ---
				$( document ).on( 'click', '.storeengine-admin-notice-close.notice-dismiss', function ( event ) {
					event.preventDefault();
					const notice = $( this ).data( 'notice' );
					const $card = $( this ).closest( '.storeengine-admin-notice' );
					$.post( '<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>', {
						notice: notice,
						action: '<?php echo esc_js( self::$action ); ?>',
						security: '<?php echo esc_js( wp_create_nonce( 'storeengine_nonce' ) ); ?>',
					}, function () {
						$card.slideUp( 150, function () {
							$card.remove();
							refreshGroup();
						} );
					} );
				} );

				// Keep the group's count/label in sync after a dismissal.
				function refreshGroup() {
					const $g = $( '.storeengine-notice-group' );
					if ( ! $g.length ) { return; }
					const n = $g.find( '.storeengine-admin-notice' ).length;
					if ( n === 0 ) { $g.remove(); return; }
					$g.find( '.storeengine-notice-group__count' ).text( n );
					$g.find( '.storeengine-notice-group__label' ).text( label( n ) );
				}

				// --- Group 3+ notices behind a bell ---
				$( function () {
					// Only the classic, top-of-page notices — never the ones the
					// React app renders inside its own root.
					const $notices = $( '.storeengine-admin-notice' ).filter( function () {
						return ! $( this ).closest( '#storeengine-admin, #storeenginewrap, #storeengine_setup_screen_wrap, .storeengine-notice-group' ).length;
					} );
					if ( $notices.length < 3 ) { return; }

					const count = $notices.length;
					const $group = $( '<div class="storeengine-notice-group"></div>' );
					const $head = $(
						'<button type="button" class="storeengine-notice-group__head" aria-expanded="false">' +
							'<span class="storeengine-notice-group__bell">' + <?php echo wp_json_encode( self::get_svg_icon( 'notification' ) ); ?> +
								'<span class="storeengine-notice-group__count">' + count + '</span></span>' +
							'<b class="storeengine-notice-group__label"></b>' +
							'<span class="storeengine-notice-group__chev">' + L.expand + '</span>' +
						'</button>'
					);
					const $list = $( '<div class="storeengine-notice-group__list" hidden></div>' );

					$head.find( '.storeengine-notice-group__label' ).text( label( count ) );
					$notices.first().before( $group );
					$group.append( $head ).append( $list );
					$notices.each( function () { $list.append( this ); } );

					$head.on( 'click', function () {
						const open = $group.hasClass( 'is-open' );
						$group.toggleClass( 'is-open', ! open );
						$list.prop( 'hidden', open );
						$head.attr( 'aria-expanded', ( ! open ).toString() );
						$head.find( '.storeengine-notice-group__chev' ).text( open ? L.expand : L.collapse );
					} );
				} );
			} )( jQuery );
		</script>
		<?php
	}

	/**
	 * Add notice.
	 *
	 * Notices are frequently registered while addons boot on `plugins_loaded`,
	 * before WordPress has loaded textdomains. Resolving a translatable string
	 * that early triggers WP 6.7+'s `_load_textdomain_just_in_time` _doing_it_wrong
	 * warning. To register a notice safely from that window, pass a {@see \Closure}
	 * for any translatable field (`message`, `title`, `button_text`, …); the
	 * closure is resolved on `init`, after textdomains are available. Plain-string
	 * callers keep working unchanged — and are still deferred to `init` so the
	 * registration order is consistent.
	 *
	 * @param string $notice_name
	 * @param array $args {
	 *
	 * @type string $type Notice type.
	 * @type string $icon Icon.
	 * @type bool $alt Render alt style (wp notice-alt class)
	 * @type string|string[] $classes Extra class.
	 * @type string|\Closure $title Optional Title.
	 * @type string|\Closure $message The Message.
	 * @type string|\Closure $button_text Extra button text/label
	 * @type string $button_action Extra button action url
	 * @type bool $dismissible Show dismissible button.
	 * }
	 *
	 * @return void
	 */
	public static function add_notice( string $notice_name, array $args ) {
		// Defer registration until `init` when called too early, so any deferred
		// (Closure) translatable fields resolve after textdomains are loaded.
		// Priority 5 keeps notices in place before dispatch_notices() (prio 10)
		// and admin_notices rendering.
		if ( ! did_action( 'init' ) ) {
			add_action( 'init', static function () use ( $notice_name, $args ) {
				self::register_notice( $notice_name, $args );
			}, 5 );

			return;
		}

		self::register_notice( $notice_name, $args );
	}

	private static function register_notice( string $notice_name, array $args ) {
		// Resolve any deferred (Closure) values now that textdomains are available.
		foreach ( $args as $key => $value ) {
			if ( $value instanceof \Closure ) {
				$args[ $key ] = $value();
			}
		}

		$args = wp_parse_args( $args, [
			'type'          => self::TYPE_INFO,
			'icon'          => 'info',
			'alt'           => false,
			'large'         => false,
			'classes'       => '',
			'title'         => '',
			'message'       => '',
			'button_text'   => '',
			'button_action' => '',
			'button_class'  => '',
			'button_target' => '_self',
			'dismissible'   => false,
		] );

		// This is final.
		$args['key'] = $notice_name;

		$types = [ self::TYPE_INFO, self::TYPE_SUCCESS, self::TYPE_WARNING, self::TYPE_ERROR ];

		if ( ! in_array( $args['type'], $types, true ) ) {
			$args['type'] = 'info';
		}

		$args['alt']           = (bool) $args['alt'];
		$args['large']         = (bool) $args['large'];
		$args['dismissible']   = (bool) $args['dismissible'];
		$args['classes']       = $args['classes'] && ! is_array( $args['classes'] ) ? explode( ' ', $args['classes'] ) : [];
		$args['title']         = esc_html( $args['title'] );
		$args['message']       = wp_kses_post( wpautop( $args['message'] ) );
		$args['button_text']   = esc_html( $args['button_text'] );
		$args['button_action'] = sanitize_url( $args['button_action'] );
		$args['has_buttons']   = $args['dismissible'] || ( $args['button_text'] && $args['button_action'] );

		self::$notices[ $notice_name ] = $args;
	}

	public static function remove_notice( string $notice_name ) {
		$notice = self::$notices[ $notice_name ];

		unset( self::$notices[ $notice_name ] );

		if ( 'uploads_directory_is_unprotected' === $notice_name ) {
			// this will hide the notice for next 12 hours.
			set_transient( '_storeengine_upload_directory_status', 'protected', DAY_IN_SECONDS );
		}

		if ( $notice['dismissible'] ) {
			// Hide for the current user only.
			update_user_meta( get_current_user_id(), 'dismissed_' . $notice_name . '_notice', 'yes' );
		}

		do_action( 'storeengine/admin_notice/hide_' . $notice_name . '_notice' );
	}

	protected static function is_user_dismissed( string $notice_name ): bool {
		return Formatting::string_to_bool( get_user_meta( get_current_user_id(), 'dismissed_' . $notice_name . '_notice', true ) );
	}

	protected static function remove_user_dismissal( string $notice_name ) {
		delete_user_meta( get_current_user_id(), 'dismissed_' . $notice_name . '_notice' );
	}

	protected function maybe_inside_dashboard(): bool {
		return isset( $_REQUEST['page'] ) && str_starts_with( wp_unslash( $_REQUEST['page'] ), 'storeengine-' ) || wp_is_serving_rest_request(); // phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
	}

	public static function get_notices(): array {
		return array_filter( self::$notices, fn( $notice ) => ! self::is_user_dismissed( $notice ), ARRAY_FILTER_USE_KEY );
	}

	public static function remove_all_notices() {
		self::$notices = [];
	}

	public static function has_notice( string $notice_name ): bool {
		return isset( self::$notices[ $notice_name ] );
	}

	public function add_permalink_notice() {
		if ( self::has_notice( 'update_permalink_settings' ) ) {
			return;
		}

		self::add_notice( 'update_permalink_settings', [
			'type'          => 'warning',
			'message'       => __( 'Your permalink settings is set to <code>plain</code>. Please update your permalink settings. StoreEngine works better with search engine friendly permalink.', 'storeengine' ),
			'button_text'   => __( 'Update Permalink', 'storeengine' ),
			'button_action' => admin_url( 'options-permalink.php' ),
		] );
	}

	/**
	 * Check if uploads directory is protected.
	 *
	 * @return bool
	 */
	protected static function is_uploads_directory_protected(): bool {
		$status = get_transient( '_storeengine_upload_directory_status' );

		// Check for cache.
		if ( false !== $status ) {
			return 'protected' === $status;
		}

		// retry creating index & .htaccess files.
		Installer::create_base_secure_directory();

		// Get only data from the uploads directory.
		$uploads = wp_get_upload_dir();

		// Check for the "uploads/storeengine_uploads" directory.
		$response         = wp_safe_remote_get( esc_url_raw( $uploads['baseurl'] . '/storeengine_uploads/' ), [ 'redirection' => 0 ] );
		$response_code    = intval( wp_remote_retrieve_response_code( $response ) );
		$response_content = wp_remote_retrieve_body( $response );

		// Check if returns 200 with empty content in case can open an index.html file,
		// and check for non-200 codes in case the directory is protected.
		$is_protected = ( 200 === $response_code && empty( $response_content ) ) || ( 200 !== $response_code );

		set_transient(
			'_storeengine_upload_directory_status',
			$is_protected ? 'protected' : 'unprotected',
			DAY_IN_SECONDS
		);

		return $is_protected;
	}

	public function add_unprotected_uploads_directory_notice() {
		if ( self::has_notice( 'uploads_directory_is_unprotected' ) ) {
			return;
		}

		$uploads = wp_get_upload_dir();

		/** @noinspection HtmlUnknownTarget */
		self::add_notice( 'uploads_directory_is_unprotected', [
			'type'        => 'error',
			'dismissible' => true,
			'message'     => sprintf(
			/* translators: 1: uploads directory URL 2: documentation URL */
				__( 'Your store\'s uploads directory is <a href="%1$s" target="_blank" rel="noopener">browsable via the web</a>. We strongly recommend <a href="%2$s" target="_blank" rel="noopener">configuring your web server to prevent directory indexing</a>.', 'storeengine' ),
				esc_url( $uploads['baseurl'] . '/storeengine_uploads' ),
				'https://storeengine.pro/docs/prevent-public-access-to-storeengine-uploads/'
			),
		] );
	}

	protected function maybe_show_ablocks_notice(): bool {
		if (
			isset( $_REQUEST['action'], $_REQUEST['plugin'] ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'install-plugin' === sanitize_text_field( wp_unslash( $_REQUEST['action'] ) ) && // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			'ablocks' === sanitize_text_field( wp_unslash( $_REQUEST['plugin'] ) ) // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		) {
			return false;
		}

		return ! self::has_notice( 'install_ablocks' ) && ! Helper::is_plugin_active( 'ablocks/ablocks.php' ) && ! self::is_user_dismissed( 'install_ablocks' );
	}

	public function add_ablocks_notice() {
		if ( ! $this->maybe_show_ablocks_notice() ) {
			return;
		}

		if ( ! Helper::is_plugin_installed( 'ablocks/ablocks.php' ) ) {
			$type        = __( 'install & activate', 'storeengine' );
			$button      = __( 'Install & Activate aBlocks', 'storeengine' );
			$install_url = Helper::get_plugin_install_url( 'ablocks' );
		} else {
			$type        = __( 'activate', 'storeengine' );
			$button      = __( 'Activate aBlocks', 'storeengine' );
			$install_url = Helper::get_plugin_activation_url( 'ablocks/ablocks.php' );
		}

		/** @noinspection HtmlUnknownTarget */
		self::add_notice( 'install_ablocks', [
			'type'          => 'info',
			'dismissible'   => true,
			'message'       => sprintf(
			/* translators: %1$s: install or install & activate %2$s: aBlocks WordPress plugin repository URL. */
				__( '<b>StoreEngine</b> offers 20+ Gutenberg Blocks powered by <a href="%2$s" target="_blank" rel="noopener">aBlocks</a>. Please %1$s <a href="%2$s" target="_blank" rel="noopener">aBlocks</a> to use them.', 'storeengine' ),
				$type,
				'https://wordpress.org/plugins/ablocks/'
			),
			'button_text'   => $button,
			'button_action' => $install_url,
			'button_target' => $this->maybe_inside_dashboard() ? '_blank' : '',
		] );
	}

	public static function show_get_pro_nag() {
		if ( self::has_notice( 'se-get-pro-nag' ) || defined( 'STOREENGINE_PRO_VERSION' ) ) {
			return;
		}

		// Give the store a few days to settle in before nagging about Pro.
		// `storeengine_first_install_time` is stored via Helper::get_time(), so
		// comparing against the same clock keeps the 3-day window accurate.
		$installed_at = (int) get_option( 'storeengine_first_install_time', 0 );
		if ( ! $installed_at || ( Helper::get_time() - $installed_at ) < ( 3 * DAY_IN_SECONDS ) ) {
			return;
		}

		$link = add_query_arg(
			[
				'utm_source'   => 'storeengine-plugin',
				'utm_medium'   => 'admin-notice',
				'utm_campaign' => 'upgrade-to-pro',
				'utm_content'  => 'get-pro-notice',
				'utm_term'     => 'free-user',
				'locale'       => get_locale(),
			],
			'https://storeengine.pro/pricing'
		);

		self::add_notice( 'se-get-pro-nag', [
			'type'          => self::TYPE_INFO,
			'large'         => true,
			'dismissible'   => true,
			'title'         => '',
			'message'       => '<h3>'.__( 'Unlock more with StoreEngine Pro 🚀', 'storeengine' ) .'</h3><p>'.__( 'Upgrade to <strong>StoreEngine Pro</strong> to unlock powerful features and get priority support—so you can build faster, customize more, and grow without limitations.', 'storeengine' ) . '</p>',
			'button_text'   => __( 'Get Pro', 'storeengine' ),
			'button_action' => $link,
			'button_target' => '_blank',
		] );
	}

	public static function remove_get_pro_nag() {
		self::remove_notice( 'se-get-pro-nag' );
	}
}
