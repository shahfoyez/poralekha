<?php
/**
 * EU Compliance addon.
 *
 * German/EU checkout legal compliance: the "Button-Lösung" (customizable order
 * button text), VAT transparency (net + per-rate VAT + gross), a consent
 * checkbox builder with GDPR consent logging + newsletter→GemCRM sync, and
 * AGB/Widerrufsbelehrung PDF email attachments.
 *
 * Opt-in per store: the addon only runs when active, and consent checkboxes ship
 * disabled so nothing changes until configured under Settings → Compliance.
 */

namespace StoreEngine\Addons\EuCompliance;

use StoreEngine\Classes\AbstractAddon;
use StoreEngine\Traits\Singleton;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\Formatting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EuCompliance extends AbstractAddon {
	use Singleton;

	protected string $addon_name = 'eu-compliance';

	const DB_VERSION_OPTION = 'storeengine_compliance_db_version';
	const DB_VERSION        = '1.0.0';

	public function define_constants() {
		define( 'STOREENGINE_EU_COMPLIANCE_VERSION', '1.0.0' );
	}

	/**
	 * Registered only while the addon is active (AbstractAddon::run() gates this).
	 */
	public function init_addon() {
		// Settings persistence (defaults + save whitelist) + seed keys for the admin app.
		add_filter( 'storeengine/admin/settings_default_data', [ $this, 'register_setting_defaults' ] );
		add_filter( 'storeengine/ajax/settings_fields', [ $this, 'register_setting_fields' ] );
		add_filter( 'storeengine/api/settings', [ $this, 'ensure_settings_defaults' ] );

		// Consent log table creation is handled centrally by the Addons schema
		// manager via get_db_version() + install_tables() (runs on init:4).

		// Feature 1 — customizable place-order button text.
		add_filter( 'storeengine/checkout/place_order_button_text', [ $this, 'filter_button_text' ] );

		// Feature 3 — consent checkboxes: render (PHP), expose (React snapshot), validate, log, sync.
		add_action( 'storeengine/checkout_page/before_submit', [ $this, 'render_consent_checkboxes' ] );
		add_filter( 'storeengine/checkout/consent_checkboxes', [ $this, 'snapshot_consent_checkboxes' ] );
		add_filter( 'storeengine/checkout/validate', [ $this, 'validate_consent' ], 10, 2 );
		add_action( 'storeengine/checkout/after_place_order', [ $this, 'log_consent' ], 20, 2 );

		// Feature 4 — attach AGB / Widerrufsbelehrung PDFs to the confirmation email.
		add_filter( 'storeengine/email/mail_send_arguments', [ $this, 'attach_legal_documents' ], 20, 3 );

		// GDPR — contribute the consent log to StoreEngine's privacy exporters/erasers
		// (only registered while this addon, and the gdpr addon, are both active).
		add_filter( 'storeengine/privacy/exporters', [ $this, 'register_privacy_exporter' ] );
		add_filter( 'storeengine/privacy/erasers', [ $this, 'register_privacy_eraser' ] );

		// Feature 2 — expose net / per-rate VAT / gross to the checkout snapshot (React)
		// and render the same breakdown on the PHP checkout/cart totals.
		add_filter( 'storeengine/api/checkout/totals', [ $this, 'add_vat_breakdown' ], 10, 2 );
		add_action( 'storeengine/cart/cart_totals_before_order_total', [ $this, 'render_vat_breakdown_rows' ] );

		// Feature 5 — Grundpreis (PAngV base price per unit) shown next to the
		// selling price on product archive + single product (via price meta).
		// Product meta is registered by the addon (only when active) on
		// rest_api_init so the product editor can save it via REST.
		add_action( 'rest_api_init', [ $this, 'register_grundpreis_meta' ] );
		add_filter( 'storeengine/get_formatted_price_meta', [ $this, 'append_grundpreis_meta' ], 10, 2 );
	}

	/**
	 * Build the consent log table when the store activates the addon.
	 */
	public function addon_activation_hook() {
		$this->maybe_create_table();
	}

	public function get_db_version(): string {
		return self::DB_VERSION;
	}

	public function install_tables(): void {
		$this->maybe_create_table();
	}

	/* ---------------------------------------------------------------------
	 * Settings
	 * ------------------------------------------------------------------- */

	public function register_setting_defaults( array $settings ): array {
		return array_merge( $settings, [
			'place_order_button_text'       => '',
			'compliance_vat_breakdown'      => true,
			'compliance_consent_checkboxes' => $this->default_checkboxes(),
			'compliance_legal_docs'         => [
				'agb_page'      => 0,
				'widerruf_page' => 0,
				'attach_to'     => [ 'order_confirmation' ],
			],
		] );
	}

	/**
	 * Seed compliance keys into the settings object returned to the admin React app,
	 * so the Compliance tab's fields render even before the store's first save (the
	 * global settings object only contains previously-saved keys).
	 *
	 * @param object $settings Settings object (clone of the global).
	 * @return object
	 */
	public function ensure_settings_defaults( $settings ) {
		if ( ! is_object( $settings ) ) {
			return $settings;
		}
		if ( ! isset( $settings->place_order_button_text ) ) {
			$settings->place_order_button_text = '';
		}
		if ( ! isset( $settings->compliance_vat_breakdown ) ) {
			$settings->compliance_vat_breakdown = true;
		}
		if ( ! isset( $settings->compliance_legal_docs ) ) {
			$settings->compliance_legal_docs = [
				'agb_page'      => 0,
				'widerruf_page' => 0,
				'attach_to'     => [ 'order_confirmation' ],
			];
		}
		if ( ! isset( $settings->compliance_consent_checkboxes ) ) {
			$settings->compliance_consent_checkboxes = $this->default_checkboxes();
		}

		// Normalise types before handing to the React form. The generic 'array'
		// settings passthrough stores nested booleans as the strings "true"/"false"
		// (and ids as numeric strings); without this, SwitchControl reads "false"
		// as truthy and toggles appear not to persist after save.
		$settings->compliance_vat_breakdown      = Formatting::string_to_bool( $settings->compliance_vat_breakdown );
		$settings->compliance_consent_checkboxes = $this->normalize_checkbox_defs( $settings->compliance_consent_checkboxes );
		$settings->compliance_legal_docs         = $this->normalize_legal_docs( $settings->compliance_legal_docs );

		return $settings;
	}

	/**
	 * Coerce stored checkbox definitions to proper types (bool/int), tolerating
	 * stdClass items and the string booleans produced by the settings passthrough.
	 *
	 * @param mixed $defs Raw stored definitions.
	 * @return array<int, array<string, mixed>>
	 */
	private function normalize_checkbox_defs( $defs ): array {
		if ( is_object( $defs ) ) {
			$defs = (array) $defs;
		}
		if ( ! is_array( $defs ) ) {
			return [];
		}

		$out = [];
		foreach ( $defs as $def ) {
			$def = (array) $def;
			if ( empty( $def['key'] ) ) {
				continue;
			}
			$role  = $def['role'] ?? 'none';
			$out[] = [
				'key'      => sanitize_key( $def['key'] ),
				'label'    => (string) ( $def['label'] ?? '' ),
				'required' => Formatting::string_to_bool( $def['required'] ?? false ),
				'role'     => in_array( $role, [ 'none', 'newsletter' ], true ) ? $role : 'none',
				'page'     => absint( $def['page'] ?? 0 ),
				'enabled'  => Formatting::string_to_bool( $def['enabled'] ?? false ),
				'order'    => absint( $def['order'] ?? 0 ),
			];
		}

		return $out;
	}

	/**
	 * Coerce stored legal-docs config to proper types.
	 *
	 * @param mixed $docs Raw stored config.
	 * @return array<string, mixed>
	 */
	private function normalize_legal_docs( $docs ): array {
		$docs = (array) $docs;
		$attach_to = $docs['attach_to'] ?? [ 'order_confirmation' ];

		return [
			'agb_page'      => absint( $docs['agb_page'] ?? 0 ),
			'widerruf_page' => absint( $docs['widerruf_page'] ?? 0 ),
			'attach_to'     => array_values( array_filter( array_map( 'sanitize_key', (array) $attach_to ) ) ),
		];
	}

	public function register_setting_fields( array $fields ): array {
		return array_merge( $fields, [
			'place_order_button_text'       => 'string',
			'compliance_vat_breakdown'      => 'boolean',
			// Stored verbatim (passthrough) so the builder's nested shape is preserved.
			'compliance_consent_checkboxes' => 'array',
			'compliance_legal_docs'         => 'array',
		] );
	}

	/**
	 * Default checkbox builder definitions. All disabled by default so enabling
	 * the German wording is an explicit store decision (no behaviour change OOTB).
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function default_checkboxes(): array {
		return [
			[
				'key'      => 'agb',
				'label'    => __( 'I accept the Terms and Conditions (AGB).', 'storeengine' ),
				'required' => true,
				'role'     => 'none',
				'page'     => 0,
				'enabled'  => false,
				'order'    => 1,
			],
			[
				'key'      => 'widerruf',
				'label'    => __( 'I have read the Revocation Policy (Widerrufsbelehrung).', 'storeengine' ),
				'required' => true,
				'role'     => 'none',
				'page'     => 0,
				'enabled'  => false,
				'order'    => 2,
			],
			[
				'key'      => 'newsletter',
				'label'    => __( 'Subscribe me to the newsletter.', 'storeengine' ),
				'required' => false,
				'role'     => 'newsletter',
				'page'     => 0,
				'enabled'  => false,
				'order'    => 3,
			],
		];
	}

	/**
	 * Enabled, normalised, order-sorted checkbox definitions.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	public function get_enabled_checkboxes(): array {
		$defs    = $this->normalize_checkbox_defs( Helper::get_settings( 'compliance_consent_checkboxes', $this->default_checkboxes() ) );
		$enabled = array_values( array_filter( $defs, static fn( $d ) => $d['enabled'] ) );

		usort( $enabled, static fn( $a, $b ) => $a['order'] <=> $b['order'] );

		return $enabled;
	}

	/* ---------------------------------------------------------------------
	 * Feature 1 — button text
	 * ------------------------------------------------------------------- */

	public function filter_button_text( $label ) {
		$custom = trim( (string) Helper::get_settings( 'place_order_button_text', '' ) );

		return '' !== $custom ? $custom : $label;
	}

	/* ---------------------------------------------------------------------
	 * Feature 3 — consent checkboxes
	 * ------------------------------------------------------------------- */

	public function render_consent_checkboxes() {
		$checkboxes = $this->get_enabled_checkboxes();
		if ( empty( $checkboxes ) ) {
			return;
		}

		// Each consent item is a <label> (inline by default). Force a stacked
		// block/flex layout so checkboxes don't run together on one line. Static,
		// trusted CSS — printed once per request (wp_kses strips inline styles
		// from the markup below, so it can't live on the elements themselves).
		static $printed_style = false;
		if ( ! $printed_style ) {
			$printed_style = true;
			echo '<style>.storeengine-consent{display:flex;flex-direction:column;gap:8px;margin:12px 0}.storeengine-consent__item{display:flex;align-items:flex-start;gap:8px}.storeengine-consent__item input[type="checkbox"]{margin-top:3px;flex:0 0 auto}.storeengine-consent__required{color:#d63638;text-decoration:none}</style>';
		}

		$allowed = [
			'div'    => [ 'class' => true ],
			'label'  => [ 'class' => true ],
			'span'   => [ 'class' => true ],
			'abbr'   => [
				'class' => true,
				'title' => true,
			],
			'a'      => [
				'href'   => true,
				'target' => true,
				'rel'    => true,
			],
			'input'  => [
				'type'     => true,
				'name'     => true,
				'value'    => true,
				'required' => true,
			],
			'strong' => [],
			'em'     => [],
			'br'     => [],
		];

		$html = '<div class="storeengine-consent">';
		foreach ( $checkboxes as $box ) {
			$label_html = wp_kses_post( $box['label'] );

			if ( $box['page'] ) {
				$url = get_permalink( $box['page'] );
				if ( $url ) {
					$label_html .= ' <a href="' . esc_url( $url ) . '" target="_blank" rel="noopener noreferrer">' .
						esc_html__( 'View', 'storeengine' ) . '</a>';
				}
			}

			$required_mark = $box['required']
				? ' <abbr class="storeengine-consent__required" title="' . esc_attr__( 'Required', 'storeengine' ) . '">*</abbr>'
				: '';

			$html .= sprintf(
				'<label class="storeengine-consent__item"><input type="checkbox" name="storeengine_consent[%1$s]" value="1" class="storeengine-input-check"%2$s> <span>%3$s</span>%4$s</label>',
				esc_attr( $box['key'] ),
				$box['required'] ? ' required' : '',
				$label_html,
				$required_mark
			);
		}
		$html .= '</div>';

		echo wp_kses( $html, $allowed );
	}

	/**
	 * JSON-friendly enabled checkbox list for the React Instant Checkout snapshot.
	 *
	 * @param mixed $checkboxes Incoming filter value (unused).
	 * @return array<int, array<string, mixed>>
	 */
	public function snapshot_consent_checkboxes( $checkboxes ) {
		$out = is_array( $checkboxes ) ? $checkboxes : [];
		foreach ( $this->get_enabled_checkboxes() as $box ) {
			$out[] = [
				'key'      => $box['key'],
				'label'    => wp_kses_post( $box['label'] ),
				'required' => (bool) $box['required'],
				'page_url' => $box['page'] ? (string) get_permalink( $box['page'] ) : '',
			];
		}

		return $out;
	}

	/**
	 * Server-side gate: reject the order if any mandatory consent is missing.
	 *
	 * @param mixed $result Previous validation result (WP_Error or null).
	 * @param array $data   Checkout payload.
	 * @return mixed WP_Error when a required consent is missing, otherwise $result.
	 */
	public function validate_consent( $result, array $data ) {
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$submitted = $this->read_submitted_consent( $data );
		$missing   = [];
		foreach ( $this->get_enabled_checkboxes() as $box ) {
			if ( $box['required'] && empty( $submitted[ $box['key'] ] ) ) {
				$missing[] = 'storeengine_consent_' . $box['key'];
			}
		}

		if ( ! empty( $missing ) ) {
			return new \WP_Error(
				'storeengine_consent_required',
				__( 'Please accept the required terms before placing your order.', 'storeengine' ),
				[
					'status' => 422,
					'fields' => $missing,
				]
			);
		}

		return $result;
	}

	/**
	 * Persist a consent log row per enabled checkbox (GDPR burden-of-proof) and
	 * mirror a compact summary onto the order. Syncs newsletter opt-ins to GemCRM.
	 *
	 * @param \StoreEngine\Classes\Order $order   Placed order.
	 * @param array                      $payload Checkout payload.
	 */
	public function log_consent( $order, array $payload ) {
		$checkboxes = $this->get_enabled_checkboxes();
		if ( empty( $checkboxes ) ) {
			return;
		}

		$submitted = $this->read_submitted_consent( $payload );
		$email     = $order->get_order_email() ?: $order->get_billing_email();
		$user_id   = (int) $order->get_customer_id();
		$ip        = (string) $order->get_ip_address();
		$agent     = (string) $order->get_user_agent();
		$source    = 'checkout:' . absint( Helper::get_settings( 'checkout_page', 0 ) );
		$summary   = [];

		foreach ( $checkboxes as $box ) {
			$given = $box['required'] ? true : ! empty( $submitted[ $box['key'] ] );

			$this->insert_consent_log( [
				'order_id'      => $order->get_id(),
				'user_id'       => $user_id ?: null,
				'email'         => $email,
				'consent_key'   => $box['key'],
				'consent_label' => $box['label'],
				'consent_given' => $given ? 1 : 0,
				'source'        => $source,
				'ip_address'    => $ip,
				'user_agent'    => $agent,
			] );

			$summary[ $box['key'] ] = [
				'label' => $box['label'],
				'given' => $given,
			];

			if ( $given && 'newsletter' === $box['role'] ) {
				$this->sync_newsletter_to_gemcrm( $order );
			}
		}

		$order->update_meta_data( '_storeengine_consent', $summary );
		$order->save_meta_data();
	}

	/**
	 * Read submitted consent values from the payload, falling back to the request.
	 * Nonce is verified upstream by the checkout flow before validate()/place_order().
	 *
	 * @param array $data Checkout payload.
	 * @return array<string, bool>
	 */
	private function read_submitted_consent( array $data ): array {
		$raw = [];
		if ( isset( $data['storeengine_consent'] ) && is_array( $data['storeengine_consent'] ) ) {
			$raw = $data['storeengine_consent'];
		} elseif ( isset( $_POST['storeengine_consent'] ) && is_array( $_POST['storeengine_consent'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw = wp_unslash( $_POST['storeengine_consent'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		} else {
			// The traditional checkout serializes bracket-notation checkbox names as
			// flat JSON keys (e.g. "storeengine_consent[agb]" => "1") because
			// Object.fromEntries(new FormData()) does not expand bracket notation.
			// Parse those flat keys so REST-submitted consent values are recognized.
			$prefix     = 'storeengine_consent[';
			$prefix_len = strlen( $prefix );
			foreach ( $data as $key => $value ) {
				if ( 0 === strpos( $key, $prefix ) && ']' === substr( $key, -1 ) ) {
					$raw[ substr( $key, $prefix_len, -1 ) ] = $value;
				}
			}
		}

		$out = [];
		foreach ( (array) $raw as $key => $value ) {
			$out[ sanitize_key( $key ) ] = ! empty( $value );
		}

		return $out;
	}

	private function sync_newsletter_to_gemcrm( $order ) {
		if ( ! class_exists( '\GemCrm\Database\Models\Contact' ) ) {
			return;
		}

		try {
			\GemCrm\Database\Models\Contact::create( [
				'email'      => $order->get_order_email() ?: $order->get_billing_email(),
				'first_name' => (string) $order->get_billing_first_name(),
				'last_name'  => (string) $order->get_billing_last_name(),
				'phone'      => (string) $order->get_billing_phone(),
				'status'     => 'subscribed',
				'type'       => 'lead',
			] );
		} catch ( \Throwable $e ) {
			// Contact already exists or GemCRM rejected it — non-fatal for checkout.
			Helper::log_error( $e );
		}
	}

	/* ---------------------------------------------------------------------
	 * Feature 4 — AGB / Widerrufsbelehrung PDF attachments
	 * ------------------------------------------------------------------- */

	public function attach_legal_documents( array $mail_arguments, string $email_name, array $args ): array {
		$config = Helper::get_settings( 'compliance_legal_docs', [] );
		if ( ! is_array( $config ) ) {
			return $mail_arguments;
		}

		$attach_to = isset( $config['attach_to'] ) && is_array( $config['attach_to'] )
			? $config['attach_to']
			: [ 'order_confirmation' ];
		if ( ! in_array( $email_name, $attach_to, true ) ) {
			return $mail_arguments;
		}

		foreach ( [ 'agb_page', 'widerruf_page' ] as $key ) {
			$page_id = absint( $config[ $key ] ?? 0 );
			if ( ! $page_id ) {
				continue;
			}
			$file = $this->render_page_to_pdf( $page_id );
			if ( $file ) {
				$mail_arguments['attachments'][] = $file;
			}
		}

		return $mail_arguments;
	}

	/**
	 * Render a WordPress page to a temporary PDF.
	 *
	 * PDF rendering — including the Unicode font set required for German text — is
	 * owned by the Invoice addon (fonts live in uploads/invoice/fonts). We reuse its
	 * generator so legal documents render identically to invoices. When the Invoice
	 * addon is inactive there is no font infrastructure, so we no-op gracefully.
	 *
	 * @param int $page_id Page ID.
	 * @return string|null Absolute file path, or null when unavailable.
	 */
	private function render_page_to_pdf( int $page_id ): ?string {
		$page = get_post( $page_id );
		if ( ! $page || 'trash' === $page->post_status ) {
			return null;
		}

		if ( ! class_exists( '\StoreEngine\Addons\Invoice\PDF\Generator' ) ) {
			return null;
		}

		$title   = get_the_title( $page );
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Applying the WordPress core "the_content" filter so the page renders identically to the front end.
		$content = apply_filters( 'the_content', $page->post_content );
		$html    = '<h1>' . esc_html( $title ) . '</h1>' . $content;

		try {
			$file      = trailingslashit( get_temp_dir() ) . 'storeengine-legal-' . $page_id . '-' . wp_generate_password( 8, false ) . '.pdf';
			$generator = new \StoreEngine\Addons\Invoice\PDF\Generator( $html, '' );
			$generator->save( $file );

			// Best-effort cleanup after the request completes.
			add_action( 'shutdown', static function () use ( $file ) {
				if ( file_exists( $file ) ) {
					wp_delete_file( $file );
				}
			} );

			return $file;
		} catch ( \Throwable $e ) {
			Helper::log_error( $e );

			return null;
		}
	}

	/* ---------------------------------------------------------------------
	 * Feature 2 — VAT breakdown
	 * ------------------------------------------------------------------- */

	/**
	 * Add net / per-rate VAT / gross to the checkout totals payload consumed by
	 * the React Instant Checkout. The PHP checkout renders the same data server-side.
	 *
	 * @param array                     $totals Existing totals payload.
	 * @param \StoreEngine\Classes\Cart $cart   Current cart.
	 * @return array
	 */
	public function add_vat_breakdown( array $totals, $cart ): array {
		if ( ! Helper::get_settings( 'compliance_vat_breakdown', true ) ) {
			return $totals;
		}
		if ( ! $cart ) {
			return $totals;
		}

		$tax_lines = [];
		foreach ( (array) $cart->get_tax_totals() as $code => $tax ) {
			$label       = is_object( $tax ) ? ( $tax->label ?? $code ) : ( $tax['label'] ?? $code );
			$amount      = is_object( $tax ) ? ( $tax->amount ?? 0 ) : ( $tax['amount'] ?? 0 );
			$tax_lines[] = [
				'code'   => (string) $code,
				'label'  => (string) $label,
				'amount' => (float) $amount,
			];
		}

		$tax_total             = (float) $cart->get_taxes_total( true, false );
		$gross                 = (float) $cart->get_total( 'edit' );
		$totals['tax_lines']   = $tax_lines;
		$totals['tax_total']   = $tax_total;
		$totals['net_total']   = (float) ( $gross - $tax_total );
		$totals['gross_total'] = $gross;

		return $totals;
	}

	/**
	 * Render an EU-compliant net / VAT breakdown on the PHP cart + checkout totals.
	 * The explicit "Net total" row is always added (never shown otherwise); per-rate
	 * VAT rows are added only when core hasn't already itemized them (i.e. when the
	 * tax display mode isn't "exclusive"), so nothing is duplicated.
	 */
	public function render_vat_breakdown_rows() {
		if ( ! Helper::get_settings( 'compliance_vat_breakdown', true ) ) {
			return;
		}

		$cart = Helper::cart();
		if ( ! $cart ) {
			return;
		}

		$tax_total = (float) $cart->get_taxes_total( true, false );
		if ( $tax_total <= 0 ) {
			return;
		}

		$net = (float) $cart->get_total( 'edit' ) - $tax_total;
		printf(
			'<tr class="order-net-total"><th scope="row">%1$s</th><td>%2$s</td></tr>',
			esc_html__( 'Net total (excl. VAT)', 'storeengine' ),
			wp_kses_post( Formatting::price( $net ) )
		);

		if ( 'excl' !== Helper::get_settings( 'tax_display_cart', 'excl' ) ) {
			foreach ( (array) $cart->get_tax_totals() as $tax ) {
				$label  = is_object( $tax ) ? ( $tax->label ?? '' ) : '';
				$amount = is_object( $tax ) ? ( $tax->amount ?? 0 ) : 0;
				printf(
					'<tr class="order-vat"><th scope="row">%1$s</th><td>%2$s</td></tr>',
					esc_html( $label ),
					wp_kses_post( Formatting::price( $amount ) )
				);
			}
		}
	}

	/* ---------------------------------------------------------------------
	 * Consent log table
	 * ------------------------------------------------------------------- */

	public static function table_name(): string {
		global $wpdb;

		return $wpdb->prefix . 'storeengine_consent_logs';
	}

	/* ---------------------------------------------------------------------
	 * GDPR — consent log exporter / eraser (via the gdpr addon's filters)
	 * ------------------------------------------------------------------- */

	/**
	 * Register the consent-log exporter on StoreEngine's privacy exporter list.
	 *
	 * @param array $exporters
	 * @return array
	 */
	public function register_privacy_exporter( array $exporters ): array {
		$exporters['storeengine-consent-logs'] = [
			'exporter_friendly_name' => __( 'StoreEngine Consent Logs', 'storeengine' ),
			'callback'               => [ $this, 'export_consent_logs' ],
		];

		return $exporters;
	}

	/**
	 * Register the consent-log eraser on StoreEngine's privacy eraser list.
	 *
	 * @param array $erasers
	 * @return array
	 */
	public function register_privacy_eraser( array $erasers ): array {
		$erasers['storeengine-consent-logs'] = [
			'eraser_friendly_name' => __( 'StoreEngine Consent Logs', 'storeengine' ),
			'callback'             => [ $this, 'erase_consent_logs' ],
		];

		return $erasers;
	}

	/**
	 * Export consent log entries recorded for the given email.
	 *
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public function export_consent_logs( string $email, int $page = 1 ): array {
		global $wpdb;
		$per_page = 50;
		$offset   = max( 0, ( $page - 1 ) * $per_page );
		$table    = self::table_name();

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, consent_label, consent_given, ip_address, created_at_gmt FROM {$table} WHERE email = %s ORDER BY id DESC LIMIT %d OFFSET %d", // phpcs:ignore
				$email,
				$per_page,
				$offset
			)
		);

		$data = [];
		foreach ( (array) $rows as $row ) {
			$data[] = [
				'group_id'    => 'storeengine_consent_logs',
				'group_label' => __( 'Consent Logs', 'storeengine' ),
				'item_id'     => 'consent-log-' . $row->id,
				'data'        => [
					[ 'name' => __( 'Consent', 'storeengine' ), 'value' => wp_strip_all_tags( (string) $row->consent_label ) ],
					[ 'name' => __( 'Given', 'storeengine' ), 'value' => $row->consent_given ? __( 'Yes', 'storeengine' ) : __( 'No', 'storeengine' ) ],
					[ 'name' => __( 'IP Address', 'storeengine' ), 'value' => (string) $row->ip_address ],
					[ 'name' => __( 'Recorded At', 'storeengine' ), 'value' => (string) $row->created_at_gmt ],
				],
			];
		}

		return [ 'data' => $data, 'done' => count( (array) $rows ) < $per_page ];
	}

	/**
	 * Anonymize the PII on consent log rows while retaining the consent record
	 * itself (kept as legal burden-of-proof of consent).
	 *
	 * @param string $email
	 * @param int    $page
	 * @return array
	 */
	public function erase_consent_logs( string $email, int $page = 1 ): array {
		if ( $page > 1 ) {
			return [ 'items_removed' => false, 'items_retained' => false, 'messages' => [], 'done' => true ];
		}

		global $wpdb;
		$table = self::table_name();
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET email = NULL, ip_address = NULL, user_agent = NULL, user_id = NULL WHERE email = %s", // phpcs:ignore
				$email
			)
		);

		$messages = $updated ? [ __( 'Consent log entries have been anonymized (the consent record is retained as proof of consent).', 'storeengine' ) ] : [];

		return [
			'items_removed'  => false,
			'items_retained' => (bool) $updated,
			'messages'       => $messages,
			'done'           => true,
		];
	}

	public function maybe_create_table() {
		if ( get_option( self::DB_VERSION_OPTION ) === self::DB_VERSION ) {
			return;
		}

		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table           = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id BIGINT UNSIGNED DEFAULT NULL,
			user_id BIGINT UNSIGNED DEFAULT NULL,
			email VARCHAR(255) DEFAULT NULL,
			consent_key VARCHAR(191) NOT NULL,
			consent_label TEXT NOT NULL,
			consent_given TINYINT(1) NOT NULL DEFAULT 0,
			source VARCHAR(191) DEFAULT NULL,
			ip_address VARCHAR(100) DEFAULT NULL,
			user_agent TEXT DEFAULT NULL,
			created_at_gmt DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY email (email),
			KEY order_id (order_id)
		) {$charset_collate};";

		dbDelta( $sql );
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * @param array<string, mixed> $row Consent log row.
	 */
	private function insert_consent_log( array $row ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->insert(
			self::table_name(),
			[
				'order_id'       => $row['order_id'] ? absint( $row['order_id'] ) : null,
				'user_id'        => $row['user_id'] ? absint( $row['user_id'] ) : null,
				'email'          => $row['email'] ? sanitize_email( $row['email'] ) : null,
				'consent_key'    => sanitize_key( $row['consent_key'] ),
				'consent_label'  => wp_strip_all_tags( (string) $row['consent_label'] ),
				'consent_given'  => $row['consent_given'] ? 1 : 0,
				'source'         => sanitize_text_field( (string) $row['source'] ),
				'ip_address'     => sanitize_text_field( (string) $row['ip_address'] ),
				'user_agent'     => sanitize_text_field( (string) $row['user_agent'] ),
				'created_at_gmt' => current_time( 'mysql', true ),
			],
			[ '%d', '%d', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' ]
		);
	}

	/* ---------------------------------------------------------------------
	 * Feature 5 — Grundpreis (PAngV base price per unit)
	 * ------------------------------------------------------------------- */

	/**
	 * Register the Grundpreis product meta for REST. Lives in the addon (not
	 * core) so the fields only exist while the addon is active — mirroring core
	 * register_product_meta(), which also runs on rest_api_init.
	 */
	public function register_grundpreis_meta(): void {
		$meta = [
			'_storeengine_product_grundpreis_enabled'      => 'boolean',
			'_storeengine_product_grundpreis_content'      => 'string',
			'_storeengine_product_grundpreis_content_unit' => 'string',
			'_storeengine_product_grundpreis_base'         => 'string',
			'_storeengine_product_grundpreis_base_unit'    => 'string',
		];

		foreach ( $meta as $key => $type ) {
			register_meta( 'post', $key, [
				'object_subtype' => Helper::PRODUCT_POST_TYPE,
				'type'           => $type,
				'single'         => true,
				'show_in_rest'   => true,
			] );
		}
	}

	/**
	 * Unit conversion factors to a canonical unit per measurement family.
	 * content and base units must share a family for a Grundpreis to apply.
	 *
	 * @return array<string, array{family:string, factor:float, label:string}>
	 */
	private function grundpreis_units(): array {
		return [
			// Weight — canonical: gram.
			'mg'    => [ 'family' => 'weight', 'factor' => 0.001, 'label' => 'mg' ],
			'g'     => [ 'family' => 'weight', 'factor' => 1.0,   'label' => 'g' ],
			'kg'    => [ 'family' => 'weight', 'factor' => 1000.0, 'label' => 'kg' ],
			// Volume — canonical: millilitre.
			'ml'    => [ 'family' => 'volume', 'factor' => 1.0,    'label' => 'ml' ],
			'cl'    => [ 'family' => 'volume', 'factor' => 10.0,   'label' => 'cl' ],
			'l'     => [ 'family' => 'volume', 'factor' => 1000.0, 'label' => 'l' ],
			// Length — canonical: metre.
			'mm'    => [ 'family' => 'length', 'factor' => 0.001, 'label' => 'mm' ],
			'cm'    => [ 'family' => 'length', 'factor' => 0.01,  'label' => 'cm' ],
			'm'     => [ 'family' => 'length', 'factor' => 1.0,   'label' => 'm' ],
			// Area — canonical: square metre.
			'cm2'   => [ 'family' => 'area',   'factor' => 0.0001, 'label' => 'cm²' ],
			'm2'    => [ 'family' => 'area',   'factor' => 1.0,    'label' => 'm²' ],
			// Count.
			'piece' => [ 'family' => 'count',  'factor' => 1.0, 'label' => __( 'piece', 'storeengine' ) ],
		];
	}

	/**
	 * Append the Grundpreis line to a product price's formatted meta, so it
	 * renders next to the price on archive + single-product (PAngV).
	 *
	 * @param array                     $meta  Existing price meta (key => html).
	 * @param \StoreEngine\Classes\Price $price Price object.
	 * @return array
	 */
	public function append_grundpreis_meta( array $meta, $price ): array {
		$html = $this->grundpreis_html( $price );
		if ( null !== $html ) {
			$meta['grundpreis'] = $html;
		}

		return $meta;
	}

	/**
	 * Compute the Grundpreis string for a price, e.g. "6,00 € / 1 kg".
	 *
	 * @param \StoreEngine\Classes\Price $price Price object.
	 * @return string|null Null when not configured/applicable.
	 */
	public function grundpreis_html( $price ): ?string {
		if ( ! is_object( $price ) || ! method_exists( $price, 'get_product_id' ) ) {
			return null;
		}

		$product_id = (int) $price->get_product_id();
		if ( ! $product_id ) {
			return null;
		}

		if ( ! Formatting::string_to_bool( get_post_meta( $product_id, '_storeengine_product_grundpreis_enabled', true ) ) ) {
			return null;
		}

		$content      = (float) get_post_meta( $product_id, '_storeengine_product_grundpreis_content', true );
		$content_unit = (string) get_post_meta( $product_id, '_storeengine_product_grundpreis_content_unit', true );
		$base         = (float) get_post_meta( $product_id, '_storeengine_product_grundpreis_base', true );
		$base_unit    = (string) get_post_meta( $product_id, '_storeengine_product_grundpreis_base_unit', true );

		if ( $content <= 0 || $base <= 0 || '' === $content_unit || '' === $base_unit ) {
			return null;
		}

		$units = $this->grundpreis_units();
		if ( ! isset( $units[ $content_unit ], $units[ $base_unit ] ) ) {
			return null;
		}
		if ( $units[ $content_unit ]['family'] !== $units[ $base_unit ]['family'] ) {
			return null;
		}

		// Express the package content in the chosen base unit.
		$content_in_base = ( $content * $units[ $content_unit ]['factor'] ) / $units[ $base_unit ]['factor'];
		if ( $content_in_base <= 0 ) {
			return null;
		}

		$per_base = ( (float) $price->get_price() / $content_in_base ) * $base;

		return sprintf(
			'%1$s / %2$s %3$s',
			Formatting::price( $per_base ),
			Formatting::format_decimal( $base, false, true ),
			esc_html( $units[ $base_unit ]['label'] )
		);
	}
}
