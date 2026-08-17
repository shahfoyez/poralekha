<?php

namespace StoreEngine\Addons\Email\Ajax;

use StoreEngine\Classes\AbstractAjaxHandler;
use StoreEngine\Classes\Order;
use StoreEngine\Classes\Order\OrderItemProduct;
use StoreEngine\Classes\Refund;
use StoreEngine\Utils\Formatting;
use StoreEngine\Utils\Helper;
use StoreEngine\Addons\Email\Admin\Settings as EmailSettings;
use StoreEngine\Addons\Email\Traits\Email;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Admin extends AbstractAjaxHandler {

	use Email {
		__construct as private EmailInit;
	}

	/**
	 * WP Mail error.
	 *
	 * @var WP_Error|null
	 */
	protected ?WP_Error $mail_error = null;

	protected string $namespace = STOREENGINE_PLUGIN_SLUG . '_email';

	public function __construct() {
		$this->actions = [
			'preview_template' => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'preview_template' ],
				'fields'     => [
					'templateName'    => 'string',
					'templateSubName' => 'string',
				],
			],
			'test_email'       => [
				'capability' => 'manage_options',
				'callback'   => [ $this, 'test_email' ],
				'fields'     => [
					'templateName'    => 'string',
					'templateSubName' => 'string',
				],
			],
		];
	}

	public function preview_template( $payload ) {
		if ( empty( $payload['templateName'] ) ) {
			wp_send_json_error( esc_html__( 'Template name is required.', 'storeengine' ) );
		}

		$templateName     = $payload['templateName'];
		$templateSubName  = ! empty( $payload['templateSubName'] ) ? $payload['templateSubName'] : '';
		$settings         = EmailSettings::get_settings_saved_data();
		$templateSettings = $templateSubName ? $settings[ $templateName ][ $templateSubName ] : $settings[ $templateName ];
		$templateFile     = self::resolve_template_file( $templateName, $templateSubName );

		if ( empty( $templateFile ) ) {
			wp_send_json_error( esc_html__( 'Template file not exists.', 'storeengine' ) );
		}

		$this->EmailInit( $payload['templateName'] );

		ob_start();
		Helper::get_template( 'email/' . $templateFile, [
			'heading' => $templateSettings['email_heading'],
			'content' => $templateSettings['email_content'],
			'footer'  => $settings['footer_text'],
		] );
		$preview      = ob_get_clean();
		$replacements = $this->get_dummy_content();

		wp_send_json_success( $this->style_inline( str_replace( array_keys( $replacements ), array_values( $replacements ), $preview ) ) );
	}

	/**
	 * Resolve the email template file to render for preview / test mail.
	 *
	 * Only the original emails ship a dedicated `<name>-<sub>.php` file. Every
	 * email added later (password_reset, registration_welcome, subscription_*,
	 * affiliate_*, order_item_shipped, order_delivered, order_cancelled, …)
	 * renders through the shared generic shell — the same one their send path
	 * uses ({@see Helper::get_template( 'email/order-status-customer.php', … )}).
	 *
	 * Without this fallback the preview/test built `email/<name>-<sub>.php`
	 * unconditionally; locate_template() returned that non-existent default path
	 * and the include produced an empty document — the "preview not working"
	 * report.
	 *
	 * @param string $template_name
	 * @param string $template_sub_name
	 * @return string Template file name relative to `templates/email/`.
	 */
	protected static function resolve_template_file( string $template_name, string $template_sub_name = '' ): string {
		$template_file = Helper::get_email_template_name( $template_name, $template_sub_name );

		if ( ! file_exists( Helper::locate_template( 'email/' . $template_file ) ) ) {
			// Mirror the send path: emails without a dedicated file use the
			// generic customer shell (heading + content + footer).
			$template_file = 'order-status-customer.php';
		}

		return $template_file;
	}

	private function get_dummy_content(): array {
		return [
			'{site_title}'               => get_bloginfo( 'name', 'display' ),
			'{site_url}'                 => get_bloginfo( 'url', 'display' ),
			'{user_display_name}'        => 'John Doe',
			'{user_email}'               => 'john.doe@example.com',
			'{store_name}'               => 'Kodezen',
			'{user_first_name}'          => 'John',
			'{user_last_name}'           => 'Doe',
			'{user_password}'            => 'MYpass2119ab##%%',
			'{sign_in_url}'              => storeengine_login_url( Helper::get_dashboard_url() ),
			'{order_id}'                 => 100,
			'{order_created_date}'       => esc_html( gmdate( 'F j, Y' ) ),
			'{order_items}'              => $this->prepare_body_without_layout(
				implode( '', array_map( fn( $order_item ) => str_replace(
					[
						'{order_item_name}',
						'{order_item_meta_html}',
						'{order_item_quantity}',
						'{order_item_line_total}',
					],
					[
						esc_html( $order_item[0] ),
						'Cap' === $order_item[0] ? "<li data-list='bullet'><strong>Color </strong>: Blue</li><li data-list='bullet'><strong>Size </strong>: XL</li>" : null,
						esc_html( $order_item[1] ),
						wp_kses_post( Formatting::price( $order_item[2] ) ),
					],
					$this->get_order_item_template()
				), [ [ 'Album', 1, 20.0 ], [ 'Cap', 1, 12.0 ] ] ) )
			),
			'{order_payment_method}'     => 'Bank Transfer',
			'{order_totals}'             => $this->prepare_body_without_layout( implode( '', array_map( fn( array $total ) => "<p><strong>{$total['label']} </strong> {$total['value']}</p>", [
				[
					'label' => 'Subtotal:',
					'value' => Formatting::price( 32 ),
				],
				[
					'label' => 'Discount:',
					'value' => Formatting::price( - 2 ),
				],
				[
					'label' => 'Total:',
					'value' => Formatting::price( 30 ),
				],
				[
					'label' => 'Payment Method:',
					'value' => 'Bank Transfer',
				],
			] ) ) ),
			'{order_note}'               => 'This is a dummy order note for customer.',
			'{order_refunds}'            => $this->prepare_body_without_layout(
				'<ul>' . implode( '', array_map( function ( $refund ) {
					$refund_template = '<li data-list=bullet>{refund_name}: <strong>{refund_amount}</strong></li>';

					return str_replace(
						[ '{refund_name}', '{refund_amount}' ],
						[
							"Refund #$refund[0] - $refund[1] by $refund[2]",
							Formatting::price( $refund[3] ),
						],
						$refund_template
					);
				}, [ [ 10, esc_html( gmdate( 'F j, Y' ) ), wp_get_current_user()->display_name, 11.45 ] ] ) ) . '</ul>'
			),
			'{order_old_status}'         => 'Processing',
			'{order_new_status}'         => 'Completed',
			'{invoice_button}'           => '<a href="#" target="_blank" style="display: inline-block; padding: 12px 24px; background-color: #008DFF; color: #ffffff; text-decoration: none; border-radius: 3px;">View Invoice</a>',
			'{cart_items}'               => $this->prepare_body_without_layout(
				implode( '', array_map( fn( $order_item ) => str_replace(
					[
						'{order_item_name}',
						'{order_item_meta_html}',
						'{order_item_quantity}',
						'{order_item_line_total}',
					],
					[
						esc_html( $order_item[0] ),
						'Cap' === $order_item[0] ? "<li data-list='bullet'><strong>Color </strong>: Blue</li><li data-list='bullet'><strong>Size </strong>: XL</li>" : null,
						esc_html( $order_item[1] ),
						wp_kses_post( Formatting::price( $order_item[2] ) ),
					],
					$this->get_order_item_template()
				), [ [ 'Album', 1, 20.0 ], [ 'Cap', 1, 12.0 ] ] ) )
			),
			'{cart_totals}'              => $this->prepare_body_without_layout( implode( '', array_map( fn( array $total ) => "<p><strong>{$total['label']} </strong> {$total['value']}</p>", [
				[
					'label' => 'Subtotal:',
					'value' => Formatting::price( 32 ),
				],
				[
					'label' => 'Total:',
					'value' => Formatting::price( 30 ),
				],
			] ) ) ),
			'{order_billing_first_name}' => 'John',
			'{abandoned_cart_discount}'  => str_replace( [
				'{abandoned_cart_discount_amount}',
				'{abandoned_cart_discount_code}',
				'{abandoned_cart_discount_expired_in}',
			], [
				'15%',
				'ABCD1234',
				'1 day',
			], "<p>Get <strong>{abandoned_cart_discount_amount} off</strong> your purchase with this code:</p><h2>{abandoned_cart_discount_code}</h2><p>Hurry, this code expires in {abandoned_cart_discount_expired_in}!</p>" ),
			'{abandoned_cart_button}'    => '<a href="#" target="_blank" style="display: inline-block; padding: 12px 24px; background-color: #008DFF; color: #ffffff; text-decoration: none; border-radius: 3px;">Return to Checkout</a>',

			// Account / auth emails.
			'{user_login}'               => 'johndoe',
			'{customer_name}'            => 'John Doe',
			'{login_url}'                => storeengine_login_url( Helper::get_dashboard_url() ),
			'{reset_url}'                => home_url( '/wp-login.php?action=rp&key=example-key&login=johndoe' ),

			// Subscription / installment / license emails.
			'{days_left}'                => '3',
			'{expires_at}'               => esc_html( gmdate( 'F j, Y' ) ),
			'{pay_url}'                  => '#',
			'{retry_url}'                => '#',
			'{failed_order_url}'         => '#',
			'{retry_button}'             => '<a href="#" target="_blank" style="display: inline-block; padding: 12px 24px; background-color: #008DFF; color: #ffffff; text-decoration: none; border-radius: 3px;">Retry Payment</a>',
			'{product_name}'             => 'Album',
			'{license_key}'              => 'XXXX-XXXX-XXXX-XXXX',
			'{license_dashboard_url}'    => '#',

			// Affiliate emails.
			'{affiliate_name}'           => 'John Doe',
			'{referral_code}'            => 'REF-JOHN123',
			'{commission_amount}'        => wp_kses_post( Formatting::price( 15 ) ),
			'{payout_amount}'            => wp_kses_post( Formatting::price( 120 ) ),
			'{payout_method}'            => 'Bank Transfer',
			'{payout_transaction_id}'    => 'TXN-100200300',

			// Return / RMA emails.
			'{rma_number}'               => 'RMA-1024',
			'{refund_amount}'            => wp_kses_post( Formatting::price( 11.45 ) ),
			'{return_dashboard_url}'     => '#',
			'{return_shipping_address}'  => '123 Example Street, Demo City, 10001',

			// Shipment emails.
			'{shipped_item_name}'        => 'Album',
			'{shipment_status}'          => 'In Transit',
			'{shipment_courier}'         => 'Demo Courier',
			'{shipment_tracking_number}' => '1Z999AA10123456784',
			'{shipment_tracking_link}'   => '#',
			'{tracking_carrier}'         => 'Demo Courier',
			'{tracking_number}'          => '1Z999AA10123456784',
		];
	}

	public function test_email( $payload ) {
		if ( empty( $payload['templateName'] ) ) {
			wp_send_json_error( esc_html__( 'Template name is required.', 'storeengine' ) );
		}

		$settings         = EmailSettings::get_settings_saved_data();
		$templateSettings = ! empty( $payload['templateSubName'] ) ? $settings[ $payload['templateName'] ][ $payload['templateSubName'] ] : $settings[ $payload['templateName'] ];
		$templateFile     = self::resolve_template_file( $payload['templateName'], $payload['templateSubName'] ?? '' );

		if ( empty( $templateFile ) ) {
			wp_send_json_error( esc_html__( 'Template file not exists.', 'storeengine' ) );
		}

		if ( ! $templateSettings['is_enable'] ) {
			wp_send_json_error(
				sprintf(
				/* translators: %s. eMail template name. */
					esc_html__( '“%s” Mail is disabled. Please enable and try again.', 'storeengine' ),
					esc_html( $this->get_template_name( $payload['templateName'], $payload['templateSubName'] ?? '' ) )
				)
			);
		}

		$this->EmailInit( $payload['templateName'] );

		if ( 'plainText' === $settings['email_content_type'] ) {
			$headers = [ 'Content-Type: text/plain; charset=UTF-8' ];
			$body    = $this->prepare_text_body( $templateSettings['email_heading'], $templateSettings['email_content'], $settings['footer_text'] );
		} else {
			$headers = [ 'Content-Type: text/html; charset=UTF-8' ];
			ob_start();
			Helper::get_template( 'email/' . $templateFile, [
				'heading' => $templateSettings['email_heading'],
				'content' => $templateSettings['email_content'],
				'footer'  => $settings['footer_text'],
			] );
			$body = ob_get_clean();
		}

		$replacements = $this->get_dummy_content();
		$subject      = str_replace( array_keys( $replacements ), array_values( $replacements ), $templateSettings['email_subject'] );
		$body         = str_replace( array_keys( $replacements ), array_values( $replacements ), $body );

		if ( 'plainText' !== $settings['email_content_type'] ) {
			$body = $this->style_inline( $body );
		}

		add_action( 'wp_mail_failed', [ $this, 'catch_wp_mail_error' ] );

		$is_send = $this->mail_send( wp_get_current_user()->user_email, $subject, $body, $headers );

		remove_action( 'wp_mail_failed', [ $this, 'catch_wp_mail_error' ] );

		if ( $is_send ) {
			wp_send_json_success( esc_html__( 'Test email sent successfully.', 'storeengine' ) );
		} else {
			if ( $this->mail_error && is_wp_error( $this->mail_error ) ) {
				wp_send_json_error( sprintf(
				/* translators: %s. PHP Mailer Error Message. */
					esc_html__( 'Error sending test email. Error: %s', 'storeengine' ),
					esc_html( $this->mail_error->get_error_message() )
				) );
			}

			wp_send_json_error( esc_html__( 'Something went wrong. Unable to send test email.', 'storeengine' ) );
		}
	}

	public function catch_wp_mail_error( WP_Error $error ) {
		$this->mail_error = $error;
	}

	protected function get_template_name( string $templateName, string $templateSubName ) {
		$templateSlug = $templateName . '_' . $templateSubName;
		$names        = [
			'order_confirmation_admin'    => __( 'Order Confirmation Admin', 'storeengine' ),
			'order_confirmation_customer' => __( 'Order Confirmation Customer', 'storeengine' ),
			'order_invoice_customer'      => __( 'Order Invoice Customer', 'storeengine' ),
			'order_note_customer'         => __( 'Order Note Customer', 'storeengine' ),
			'order_refund_customer'       => __( 'Order Refund Customer', 'storeengine' ),
			'order_status_customer'       => __( 'Order Status Customer', 'storeengine' ),
			'new_user_notification'       => __( 'New User Notification', 'storeengine' ),
			'abandoned_cart_first'        => __( 'Abandoned Cart #1', 'storeengine' ),
			'abandoned_cart_second'       => __( 'Abandoned Cart #2', 'storeengine' ),
			'abandoned_cart_third'        => __( 'Abandoned Cart #3', 'storeengine' ),
		];

		return $names[ $templateSlug ] ?? ucwords( str_replace( [ '_', '-' ], ' ', $templateSlug ) );
	}
}
