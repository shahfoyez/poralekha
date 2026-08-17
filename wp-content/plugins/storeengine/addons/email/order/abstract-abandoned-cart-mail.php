<?php

namespace StoreEngine\Addons\Email\order;

use DateTime;
use StoreEngine\Addons\Email\HelperAddon;
use StoreEngine\Addons\Email\Traits\Email;
use StoreEngine\Classes\Cart;
use StoreEngine\Classes\Coupon;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Utils\TaxUtil;
use StoreEnginePro\Addons\AbandonedCart\Classes\AbandonedCart as AbandonedCartItem;
use StoreEnginePro\Addons\AbandonedCart\Settings as AbandonedCartSettings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AbstractAbandonedCartMail {
	use Email;

	private function get_abc_subject( AbandonedCartItem $abc, $email_subject = '' ) {
		$site_url  = get_bloginfo( 'url' );
		$site_name = get_bloginfo( 'name' );

		$replacements = [
			'{user_display_name}'   => esc_html( trim( $abc->get_first_name() . ' ' . $abc->get_last_name() ) ),
			'{customer_first_name}' => esc_html( $abc->get_first_name() ),
			'{customer_last_name}'  => esc_html( $abc->get_last_name() ),
			'{site_title}'          => esc_html( $site_name ),
			'{site_url}'            => esc_html( $site_url ),
		];

		$replacements = apply_filters( 'storeengine/email/' . $this->get_hook_name( 'subject-replacements' ), $replacements, $abc );

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $email_subject );
	}

	private function get_abc_email_body( AbandonedCartItem $abc, string $body, string $email_type ): string {
		$cart         = $abc->get_the_cart();
		$first_name   = esc_html( $abc->get_first_name() );
		$last_name    = esc_html( $abc->get_last_name() );
		$replacements = [
			'{user_display_name}'         => esc_html( trim( $abc->get_first_name() . ' ' . $abc->get_last_name() ) ),
			'{user_email}'                => $abc->get_email(),
			'{customer_first_name}'       => $first_name,
			'{customer_last_name}'        => $last_name,
			// Default templates ship with `{order_billing_first_name}` (carried
			// over from the order_confirmation / order_status templates that
			// share the same email_content field). Map them to the cart's
			// captured name fields so existing saved templates render properly
			// without forcing every admin to edit them.
			'{order_billing_first_name}'  => $first_name,
			'{order_billing_last_name}'   => $last_name,
			'{order_billing_email}'       => $abc->get_email(),
			'{order_billing_phone}'       => esc_html( (string) $abc->get_phone() ),
			'{cart_items}'                => $this->get_cart_items_data( $cart ),
			'{cart_totals}'               => $this->get_cart_totals_data( $cart ),
			'{abandoned_cart_discount}'   => '',
		];

		$cart_recovery_params = [ 'se-cart-recovery' => $abc->get_recovery_token() ];

		if (
			AbandonedCartSettings::init()->get_settings( 'enable_discount' ) &&
			$email_type === AbandonedCartSettings::init()->get_settings( 'offer_discount_on' )
		) {
			$coupon = $abc->get_coupon_id() ? new Coupon( $abc->get_coupon_id() ) : null;

			if ( ! $coupon || ! $coupon->get_id() ) {
				$code = $this->generate_random_code( 9 );
				$id   = wp_insert_post( [
					'post_type'   => 'storeengine_coupon',
					'post_status' => 'publish',
					'post_author' => 1,
					'post_title'  => $code,
				] );
				if ( $id && ! is_wp_error( $id ) ) {
					// Create coupon.
					$abc->set_coupon_id( $id );
					$abc->save();

					$coupon_type = 'percentage' === AbandonedCartSettings::init()->get_settings( 'discount_type' ) ? 'percentage' : 'fixedAmount';
					$time_type   = AbandonedCartSettings::init()->get_settings( 'expire_discount' ) ? 'set_time_limit' : 'forever_time';

					update_post_meta( $id, '_is_abandoned_cart_coupon', 1 );
					update_post_meta( $id, '_storeengine_coupon_name', $code );
					update_post_meta( $id, '_storeengine_coupon_type', $coupon_type );
					update_post_meta( $id, '_storeengine_coupon_amount', AbandonedCartSettings::init()->get_settings( 'discount_amount' ) );
					update_post_meta( $id, '_storeengine_coupon_time_type', $time_type );
					update_post_meta( $id, '_storeengine_coupon_customer_usage_limit', 1 );
					update_post_meta( $id, '_storeengine_coupon_usage_limit', 1 );
					update_post_meta( $id, '_storeengine_coupon_type_of_min_requirement', 'none' );
					update_post_meta( $id, '_storeengine_coupon_min_purchase_quantity', 0 );
					update_post_meta( $id, '_storeengine_coupon_min_purchase_amount', 0 );
					update_post_meta( $id, '_storeengine_coupon_who_can_use', 'allCustomer' );

					if ( AbandonedCartSettings::init()->get_settings( 'expire_discount' ) ) {
						$expire          = AbandonedCartSettings::init()->get_settings( 'discount_expire' );
						$start_date_time = [
							'date'     => gmdate( 'Y-m-d' ),
							'time'     => gmdate( 'H:i:s' ),
							'timezone' => '',
						];
						$end_date_time   = [
							'date'     => gmdate( 'Y-m-d', strtotime( '+' . $expire . ' days' ) ),
							'time'     => gmdate( 'H:i:s', strtotime( '+' . $expire . ' days' ) ),
							'timezone' => '',
						];

						update_post_meta( $id, '_storeengine_coupon_start_date_time', $start_date_time );
						update_post_meta( $id, '_storeengine_coupon_end_date_time', $end_date_time );
					}

					$coupon = new Coupon( $abc->get_coupon_id() );
				}
			}

			if ( $coupon && $coupon->get_id() ) {

				$cart_discount       = 'percentage' === $coupon->get_discount_type() ?
					"{$coupon->get_amount()}%" :
					"{$coupon->get_amount()}";
				$coupon_replacements = [
					'{abandoned_cart_discount_amount}' => $cart_discount,
					'{abandoned_cart_discount_code}'   => $coupon->get_code(),
				];

				$coupon_content = '<p>' . sprintf(
					// translators: %s. Amount/percentage (Coupon) off.
						__( 'Get %s your purchase with this code:', 'storeengine' ),
						// translators: %s. Coupon Amount/percentage.
						'<strong>' . sprintf( __( '%s off', 'storeengine' ), '{abandoned_cart_discount_amount}' ) . '</strong>'

					) . '</p>';
				$coupon_content .= '<h2>{abandoned_cart_discount_code}</h2>';
				if ( $coupon->get_date_expires() ) {
					$coupon_replacements['{abandoned_cart_discount_expired_in}'] = abs( $coupon->get_date_expires()->diff( new DateTime() )->days );
					// Content.
					$coupon_content .= '<p>' . sprintf(
						// translators: %s. Number of days coupon will expire.
							__( 'Hurry, this code expires in %s day!', 'storeengine' ),
							'{abandoned_cart_discount_expired_in}'
						) . '</p>';
				}

				$replacements['{abandoned_cart_discount}'] = str_replace(
					array_keys( $coupon_replacements ),
					array_values( $coupon_replacements ),
					$coupon_content
				);
				$cart_recovery_params['coupon_code']       = $coupon->get_code();
			}
		}

		$replacements['{abandoned_cart_button}'] = sprintf(
			'<a href="%s" target="_blank" style="%s">%s</a>',
			esc_url( add_query_arg( $cart_recovery_params, Helper::get_checkout_url() ) ),
			esc_attr( $this->btn_styles() ),
			esc_html__( 'Return to Checkout', 'storeengine' )
		);

		$replacements = apply_filters( 'storeengine/email/' . $this->get_hook_name( 'content-replacements' ), $replacements, $abc );

		$body = str_replace( array_keys( $replacements ), array_values( $replacements ), $body );

		return str_replace( '<blockquote></blockquote>', '', $body );
	}

	public function btn_styles(): string {
		return 'display:inline-block;padding:12px 24px;background-color:#008DFF;color:#fff;text-decoration:none;border-radius:3px;';
	}

	function generate_random_code( $size = 8 ): string {
		$alphabet        = 'MSOP0123456789ABCDEFGHNRVQUKYTJLZXIW';
		$alphabet_length = strlen( $alphabet );
		$code            = '';

		while ( strlen( $code ) < $size ) {
			$index = random_int( 0, $alphabet_length - 1 );
			$code  .= $alphabet[ $index ];
		}

		return $code;
	}

	public function send_email( AbandonedCartItem $abc, $email_type ) {
		if ( ! $abc->has_status( 'abandoned' ) ) {
			return false;
		}

		$settings = $this->get_settings( 'customer' );

		if ( ! is_array( $settings ) || empty( $settings['is_enable'] ) ) {
			Helper::log_warning( sprintf(
				'[abandoned-cart] %s skipped: customer.is_enable missing or false (abcId=%d). Check StoreEngine → Settings → Emails → %s.',
				$this->email_name,
				$abc->get_id(),
				$this->email_name
			) );
			return false;
		}

		if ( empty( $abc->get_email() ) ) {
			Helper::log_warning( sprintf(
				'[abandoned-cart] %s skipped: cart has no recipient email (abcId=%d).',
				$this->email_name,
				$abc->get_id()
			) );
			return false;
		}

		$subject = $this->get_abc_subject( $abc, $settings['email_subject'] );
		list( $headers, $body ) = $this->get_the_email_body( $settings, 'email/abandoned-cart-first-customer.php' );

		$body = $this->get_abc_email_body( $abc, $body, $email_type );

		// Send first, persist sent-state only on success — otherwise a failing
		// wp_mail() (SMTP down, invalid recipient, blocked by hook, etc.)
		// silently marks the cart "sent" and the shopper never gets the email
		// and never gets retried.
		$is_sent = $this->mail_send( $abc->get_email(), $subject, $body, $headers, [ 'abandoned_cart' => $abc->get_id() ] );

		if ( ! $is_sent ) {
			Helper::log_error( sprintf(
				'[abandoned-cart] wp_mail() returned false for %s (abcId=%d, to=%s). Check wp_mail filters / SMTP plugin / blocked recipient.',
				$this->email_name,
				$abc->get_id(),
				$abc->get_email()
			) );
			return false;
		}

		$abc->set_email_status( 'sent' );
		$abc->set_last_email_sent_at( current_time( 'mysql', 1 ) );
		$abc->set_email_sent_count( $abc->get_email_sent_count() + 1 );
		$abc->save();

		return true;
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
		}

		return array( $headers, $body );
	}

	public function mail_send( $to, string $subject, string $body, $headers, $args = [] ) {
		add_filter( 'wp_mail_from_name', [ $this, 'get_from_name' ] );
		add_filter( 'wp_mail_from', [ $this, 'get_from_address' ] );

		if ( 'plainText' !== HelperAddon::get_setting( 'email_content_type' ) ) {
			$body = $this->style_inline( $body );
		}

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

	public function get_cart_item_template(): string {
		ob_start();
		?>
		<ul class="cart-item-data">
			<li data-list="bullet" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
				<strong class="item-label"><?php esc_html_e( 'Product Name:', 'storeengine' ); ?></strong>
				<span class="item-data">{cart_item_name}</span>
			</li>
			<li data-list="bullet" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
				<strong class="item-label"><?php esc_html_e( 'Product Quantity:', 'storeengine' ); ?></strong>
				<span class="item-data">{cart_item_quantity}</span>
			</li>
			<li data-list="bullet" dir="<?php echo is_rtl() ? 'rtl' : 'ltr'; ?>">
				<strong class="item-label"><?php esc_html_e( 'Total Price:', 'storeengine' ); ?></strong>
				<span class="item-data">{cart_item_line_total}</span>
			</li>
		</ul>
		<?php
		return ob_get_clean();
	}

	private function get_cart_items_data( Cart $cart ): string {
		$cart_item_template = $this->get_cart_item_template();

		$items_data = '';
		foreach ( $cart->get_cart_items() as $cart_item ) {
			$replacements = [
				'{cart_item_name}'       => $cart_item->name,
				'{cart_item_quantity}'   => esc_html( $cart_item->quantity ),
				'{cart_item_line_total}' => wp_kses_post( Formatting::price( $cart_item->get_price() ) ),
			];

			$items_data .= str_replace( array_keys( $replacements ), array_values( $replacements ), $cart_item_template ) . PHP_EOL;
		}

		return $this->prepare_body_without_layout( $items_data );
	}

	private function get_cart_totals_data( Cart $cart ): string {
		$subtotal = "<p><strong>" . __( 'Subtotal:', 'storeengine' ) . "</strong> " . wp_kses_post( Formatting::price( $cart->get_subtotal() ) ) . "</p>";
		$total    = "<p><strong>" . __( 'Total:', 'storeengine' ) . "</strong> " . wp_kses_post( $cart->get_total() ) . "</p>";

		$shipping = [];
		if ( $cart->get_shipping_total() ) {
			foreach ( $cart->get_shipping_methods() as $shipping_method ) {
				// translators: %s. Shipping label.
				$shipping[] = "<p><strong>" . sprintf( __( 'Shipping (%s):', 'storeengine' ), $shipping_method->get_label() ) . "</strong> " . wp_kses_post( Formatting::price( $shipping_method->get_cost() ) ) . "</p>";
			}
		}

		$fees = [];
		foreach ( $cart->get_fees() as $fee ) {
			$fees[] = "<p><strong>" . Formatting::cart_totals_fee_label( $fee ) . "</strong> " . wp_kses_post( Formatting::price( $fee->amount ) ) . "</p>";
		}

		$taxes = [];
		if ( TaxUtil::is_tax_enabled() && ! $cart->display_prices_including_tax() ) {
			if ( 'itemized' === Helper::get_settings( 'tax_total_display' ) ) {
				foreach ( $cart->get_tax_totals() as $code => $tax ) { // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
					$taxes[] = "<p><strong>" . esc_html( $tax->label ) . ":</strong> " . wp_kses_post( $tax->formatted_amount ) . "</p>";
				}
			} else {
				$taxes[] = "<p><strong>" . esc_html__( 'Tax:', 'storeengine' ) . "</strong> " . wp_kses_post( Formatting::price( $cart->get_taxes_total() ) ) . "</p>";
			}
		}

		$body = [
			$subtotal,
			implode( PHP_EOL, $fees ),
			implode( PHP_EOL, $shipping ),
			implode( PHP_EOL, $taxes ),
			$total,
		];

		return $this->prepare_body_without_layout( implode( PHP_EOL, $body ) );
	}

}
