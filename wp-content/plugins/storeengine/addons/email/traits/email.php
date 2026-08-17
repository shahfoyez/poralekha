<?php

namespace StoreEngine\Addons\Email\Traits;

use Exception;
use StoreEngine\Addons\Email\HelperAddon;
use StoreEngine\Pelago\Emogrifier\CssInliner;
use StoreEngine\Pelago\Emogrifier\HtmlProcessor\CssToAttributeConverter;
use StoreEngine\Pelago\Emogrifier\HtmlProcessor\HtmlPruner;
use StoreEngine\Classes\Order;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

trait Email {

	protected string $email_name;
	private $settings;

	public function __construct( string $name ) {
		$this->email_name = $name;
		$this->settings   = HelperAddon::get_setting( $name, [] );
	}

	/**
	 * @param string|string[] $to Array or comma-separated list of email addresses to send message.
	 * @param string $subject Email subject.
	 * @param string $body Message contents.
	 * @param string|string[] $headers Optional. Additional headers.
	 * @param string|string[] $args Optional. Additional headers.
	 *
	 * @return bool|mixed
	 */
	public function mail_send( $to, string $subject, string $body, $headers, $args = [] ) {
		add_filter( 'wp_mail_from_name', [ $this, 'get_from_name' ] );
		add_filter( 'wp_mail_from', [ $this, 'get_from_address' ] );

		$arguments = apply_filters( 'storeengine/email/mail_send_arguments', [
			'headers'     => $headers,
			'body'        => $body,
			'to'          => $to,
			'subject'     => $subject,
			'attachments' => $args['attachments'] ?? [],
		], $this->email_name, $args );

		$is_send = wp_mail( $arguments['to'], wp_specialchars_decode( $arguments['subject'] ), $arguments['body'], $arguments['headers'], $arguments['attachments'] );

		remove_filter( 'wp_mail_from_name', [ $this, 'get_from_name' ] );
		remove_filter( 'wp_mail_from', [ $this, 'get_from_address' ] );

		return $is_send;
	}

	public function get_from_name( $name ) {
		$form_name = HelperAddon::get_setting( 'form_name' );
		if ( ! empty( $form_name ) ) {
			return sanitize_text_field( $form_name );
		}

		return $name;
	}

	public function get_from_address( $email_address ) {
		$form_email_address = HelperAddon::get_setting( 'email_address' );
		if ( ! empty( $form_email_address ) && is_email( $form_email_address ) ) {
			return sanitize_text_field( $form_email_address );
		}

		return $email_address;
	}

	public function get_order_item_template_old(): string {
		return '<ul><li data-list=bullet><strong>Product Name </strong>: {order_item_name}</li>{order_item_meta_html}<li data-list=bullet><strong>Product Quantity </strong>: {order_item_quantity}</li><li data-list=bullet><strong>Total Price </strong>: {order_item_line_total}</li></ul>';
	}

	public function get_order_item_template(): string {
		ob_start();
		?>
		<ul class="order-item-data">
			<li data-list="bullet" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
				<strong class="item-label"><?php esc_html_e( 'Product Name:', 'storeengine' ); ?></strong>
				<span class="item-data">{order_item_name}</span>
			</li>
			{order_item_meta_html}
			<li data-list="bullet" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
				<strong class="item-label"><?php esc_html_e( 'Product Quantity:', 'storeengine' ); ?></strong>
				<span class="item-data">{order_item_quantity}</span>
			</li>
			<li data-list="bullet" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
				<strong class="item-label"><?php esc_html_e( 'Total Price:', 'storeengine' ); ?></strong>
				<span class="item-data">{order_item_line_total}</span>
			</li>
		</ul>
		<?php
		return ob_get_clean();
	}

	private function get_settings( $action_name ) {
		if ( isset( $this->settings[ $action_name ] ) ) {
			return $this->settings[ $action_name ];
		}

		return false;
	}

	/**
	 * Append a Reply-To header pointing to the customer for admin-targeted
	 * order emails. Without this, when multiple customers place orders the
	 * site admin gets a stream of notifications all From the same site
	 * address — hitting Reply goes to the site itself, not the customer who
	 * actually placed the order, which is rarely useful.
	 *
	 * Filterable: `storeengine/email/admin_reply_to` receives the formed
	 * header string, the order, and the email_name slug so site owners can
	 * swap in their own routing logic (e.g. a shared support inbox).
	 *
	 * @param array $headers Existing wp_mail headers.
	 * @param Order $order
	 * @return array Headers with Reply-To appended (or unchanged if the
	 *               billing email is missing/invalid).
	 */
	protected function with_customer_reply_to( array $headers, Order $order ): array {
		$email = $order->get_billing_email();

		if ( ! $email || ! is_email( $email ) ) {
			return $headers;
		}

		$name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		$name = $name ?: $email;

		// Format per RFC 5322: `"Display Name" <addr@example.com>`. Quote the
		// name and escape any embedded quote so a hostile name field can't
		// break the header.
		$header = sprintf(
			'Reply-To: "%s" <%s>',
			str_replace( '"', '\\"', $name ),
			$email
		);

		$header = apply_filters( 'storeengine/email/admin_reply_to', $header, $order, $this->email_name );

		if ( $header ) {
			$headers[] = $header;
		}

		return $headers;
	}

	private function get_the_email_body( $settings, $template_path ): array {
		$email_type = HelperAddon::get_setting( 'email_content_type' );
		$footer     = HelperAddon::get_setting( 'footer_text' );
		if ( 'plainText' === $email_type ) {
			$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
			$body    = $this->prepare_text_body( $settings['email_heading'], $settings['email_content'], $footer );
		} else {
			$headers = array( 'Content-Type: text/html; charset=UTF-8' );
			ob_start();
			Helper::get_template( $template_path, array(
				'heading' => $settings['email_heading'],
				'content' => $settings['email_content'],
				'footer'  => $footer,
			) );
			$body = ob_get_clean();
			$body = $this->style_inline( $body );
		}

		return array( $headers, $body );
	}

	protected function prepare_text_body( $email_heading, $email_content, $email_footer ): string {
		$allowed_tags = [ 'p', 'li' ];
		$body         = strip_tags( $email_heading, $allowed_tags ) . PHP_EOL;
		$body         .= strip_tags( $email_content, $allowed_tags ) . PHP_EOL;
		$body         .= strip_tags( $email_footer, $allowed_tags ) . PHP_EOL;
		$body         = preg_replace( '/<p[^>]*>(.*?)<\/p>/', '$1' . PHP_EOL, $body );
		$body         = preg_replace( '/<li[^>]*>(.*?)<\/li>/', '$1' . PHP_EOL, $body );

		return trim( html_entity_decode( $body ) );
	}

	protected function prepare_body_without_layout( $body ): string {
		if ( 'plainText' === HelperAddon::get_setting( 'email_content_type' ) ) {
			$body = $this->prepare_text_body( '', $body, '' );
		}

		return $body;
	}

	/**
	 * Apply inline styles to dynamic content.
	 *
	 * We only inline CSS for html emails, and to do so we use Emogrifier library (if supported).
	 *
	 * @param string|null $content Content that will receive inline styles.
	 *
	 * @return string
	 */
	protected function style_inline( $content ) {
		if ( 'plainText' === HelperAddon::get_setting( 'email_content_type' ) ) {
			return $content;
		}

		$css = PHP_EOL;
		$css .= $this->get_must_use_css_styles();
		$css .= PHP_EOL;

		ob_start();
		Helper::get_template( 'email/styles.php' );
		$css .= ob_get_clean();

		// @TODO compile email-template & css in same function to leverage wp-enqueue if DOM-Document ext not available.

		if ( class_exists( 'DOMDocument' ) ) {
			try {
				$css_inliner  = CssInliner::fromHtml( $content )->inlineCss( $css );
				$dom_document = $css_inliner->getDomDocument();
				HtmlPruner::fromDomDocument( $dom_document )->removeElementsWithDisplayNone();
				$content = CssToAttributeConverter::fromDomDocument( $dom_document )->convertCssToVisualAttributes()->render();
			} catch ( Exception $e ) {
				// CSS not applicable convert to text email.
				$content = nl2br( wp_strip_all_tags( $content ) );
			}
		} else {
			// CSS not applicable convert to text email.
			$content = nl2br( wp_strip_all_tags( $content ) );
		}

		return $content;
	}

	/**
	 * Returns CSS styles that should be included with all HTML e-mails, regardless of theme specific customizations.
	 *
	 * @return string
	 * @since 0.0.4
	 */
	protected function get_must_use_css_styles(): string {
		/**
		 * Temporary measure until e-mail clients more properly support the correct styles.
		 */
		$css = '.screen-reader-text {' . PHP_EOL;
		$css .= '	display: none !important;' . PHP_EOL;
		$css .= '	visibility: hidden !important;' . PHP_EOL;
		$css .= '	opacity: 0 !important;' . PHP_EOL;
		$css .= '	width: 0 !important;' . PHP_EOL;
		$css .= '	height: 0 !important;' . PHP_EOL;
		$css .= '}' . PHP_EOL;

		return $css;
	}

	private function get_order_items_data( Order $order ): string {
		$order_item_template = $this->get_order_item_template();

		$items_data = '';
		foreach ( $order->get_items() as $order_item ) {
			$formatted_item_meta = array_map( fn( $metadata ) => "<li data-list='bullet'><strong>{$metadata['display_key']} </strong>: {$metadata['display_value']}</li>", $order_item->get_formatted_metadata() );

			if ( method_exists( $order_item, 'get_price_name' ) && is_callable( [ $order_item, 'get_price_name' ] ) ) {
				// translators: %1$s: Order item (product) name, %2$s: Price name.
				$item_name = sprintf( _x( '%1$s (%2$s)', 'Order item name with price name', 'storeengine' ), esc_html( $order_item->get_name() ), esc_html( $order_item->get_price_name() ) );
			} else {
				$item_name = $order_item->get_name();
			}

			$replacements = [
				'{order_item_name}'       => $item_name,
				'{order_item_meta_html}'  => implode( '', $formatted_item_meta ),
				'{order_item_quantity}'   => esc_html( $order_item->get_quantity() ),
				'{order_item_line_total}' => wp_kses_post( Formatting::price( $order_item->get_subtotal() ) ),
			];

			$items_data .= str_replace( array_keys( $replacements ), array_values( $replacements ), $order_item_template ) . PHP_EOL;
		}

		return $this->prepare_body_without_layout( $items_data );
	}

	private function get_order_totals_data( Order $order ): string {
		$totals_data = array_map( fn( array $total ) => "<p><strong>{$total['label']}</strong> {$total['value']}</p>", $order->get_order_item_totals() );

		return $this->prepare_body_without_layout( implode( PHP_EOL, $totals_data ) );
	}

	private function get_order_email_body( Order $order, string $body ): string {
		$customer     = $order->get_customer();
		$replacements = [
			'{user_display_name}'        => $customer ? $customer->get_display_name() : null,
			'{user_email}'               => $customer ? $customer->get_email() : null,
			'{order_billing_first_name}' => esc_html( $order->get_billing_first_name() ),
			'{order_billing_last_name}'  => esc_html( $order->get_billing_last_name() ),
			'{order_billing_email}'      => esc_html( $order->get_billing_email() ),
			'{order_id}'                 => esc_html( $order->get_id() ),
			'{order_created_date}'       => $order->get_order_placed_date() ? $order->get_order_placed_date()->date( 'F j, Y' ) : $order->get_date_created_gmt()->date( 'F j, Y' ),
			'{order_payment_method}'     => esc_html( $order->get_payment_method_title() ),
			'{order_items}'              => $this->get_order_items_data( $order ),
			'{order_totals}'             => $this->get_order_totals_data( $order ),
			// Subjects already resolved {site_title} / {site_url} via
			// get_email_subject(); body templates need the same support so
			// the subscription cancelled / trial-ending-soon templates (which
			// sign off with "Thanks for being a customer at {site_title}.")
			// don't leak the raw placeholder.
			'{site_title}'               => esc_html( get_bloginfo( 'name' ) ),
			'{site_url}'                 => esc_html( get_bloginfo( 'url' ) ),
		];

		$replacements = apply_filters( 'storeengine/email/' . $this->get_hook_name( 'content-replacements' ), $replacements, $order );

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $body );
	}

	private function get_email_subject( Order $order, $email_subject = '' ) {
		$customer  = $order->get_customer();
		$site_url  = get_bloginfo( 'url' );
		$site_name = get_bloginfo( 'name' );

		$replacements = [
			'{user_display_name}'        => esc_html( $customer ? $customer->get_display_name() : '' ),
			'{order_billing_first_name}' => esc_html( $order->get_billing_first_name() ),
			'{order_billing_last_name}'  => esc_html( $order->get_billing_last_name() ),
			'{site_title}'               => esc_html( $site_name ),
			'{site_url}'                 => esc_html( $site_url ),
			'{order_id}'                 => esc_html( $order->get_id() ),
		];

		$replacements = apply_filters( 'storeengine/email/' . $this->get_hook_name( 'subject-replacements' ), $replacements, $order );

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $email_subject );
	}

	protected function get_hook_name( string $suffix = null ): string {
//		$name = str_replace( [ __NAMESPACE__ . '\\', 'StoreEngine' ], '', get_class( $this ) );
//		if ( $name ) {
//			preg_match_all('/([A-Z][a-z]+|[A-Z]+(?![a-z]))/', $name, $matches);
//
//			if ( ! empty( $matches[0] ) ) {
//				$name = implode( '-', $matches[0] );
//
//			}
//		}
//
//		return strtolower( $name );

		if ( ! $suffix ) {
			return $this->email_name;
		}

		return $this->email_name . '/' . $suffix;
	}
}
