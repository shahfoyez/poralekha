<?php

namespace StoreEngine\Addons\Email\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

class Settings {

	public function __construct() {
		add_filter( 'storeengine/api/settings', array( $this, 'include_settings' ) );
	}

	public function include_settings( $settings ) {
		if ( ! isset( $settings->email ) ) {
			$settings->email = self::get_settings_saved_data();
		}

		return $settings;
	}

	public static function get_settings_saved_data() {
		$settings = get_option( 'storeengine_email_settings' );
		if ( $settings ) {
			return json_decode( $settings, true );
		}

		return [];
	}

	public static function get_settings_default_data() {
		return self::inject_content_tree_defaults( apply_filters( 'storeengine/email/settings_default_data', [
			'form_name'             => _x( 'StoreEngine', 'System Email Form Name', 'storeengine' ),
			'email_address'         => get_option( 'admin_email' ),
			'email_content_type'    => 'html',
			'header_image'          => '',
			// Plain HTML — the footer is edited with the rich-text editor, not the
			// block builder, so it must not carry Gutenberg block-delimiter
			// comments (`<!-- wp:… -->`). Those served no purpose in a sent email
			// and only round-tripped awkwardly through the editor.
			'footer_text'           => '<h2>Thank You</h2><p>StoreEngine</p>',
			// template
			'order_confirmation'    => [
				'admin'    => [
					'is_enable'     => true,
					// {order_id} keeps each notification's subject unique so
					// Gmail/Outlook don't thread multiple orders into one
					// conversation in the admin's inbox.
					'email_subject' => 'New order #{order_id} placed',
					'email_heading' => '{user_display_name} has placed a new order',
					'email_content' => '<p>You\'ve received the following order from {user_display_name}:</p><p><br></p><h2><strong><u>[Order #{order_id}]</u> ({order_created_date})</strong></h2><p><br></p><p>Order Items:</p><p>{order_items}</p><p>=============================================================</p><p>Order Totals:</p><p>{order_totals}</p>',
				],
				'customer' => [
					'is_enable'     => true,
					'email_subject' => 'Your order #{order_id} has been placed',
					'email_heading' => 'Thank you for your order',
					'email_content' => '<p>Hi {user_display_name},</p><p>Just to let you know — we\'ve received your order.</p><p><br></p><h2><strong><u>[Order #{order_id}]</u> ({order_created_date})</strong></h2><p><br></p><p>Order Items:</p><p>{order_items}</p><p>=============================================================</p><p>Order Totals:</p><p>{order_totals}</p><p><br></p><p>If you have questions or require more information, feel free to reach out.</p>',
				],
			],
			'order_status'          => [
				'customer' => [
					'is_enable'     => true,
					'email_subject' => 'Your order #{order_id} status has been changed',
					'email_heading' => 'Order #{order_id} status changed',
					'email_content' => '<p>Hi {user_display_name},</p><p>The following order(#{order_id}) status has been changed from <strong>{order_old_status}</strong> to <strong>{order_new_status}</strong>:</p><p><br></p><h2><strong><u>[Order #{order_id}]</u> ({order_created_date})</strong></h2><p><br></p><p>Order Items:</p><p>{order_items}</p><p>=============================================================</p><p>Order Totals:</p><p>{order_totals}</p><p><br></p><p>If you have questions or require more information, feel free to reach out.</p>',
				],
			],
			'order_note'            => [
				'customer' => [
					'is_enable'     => true,
					'email_subject' => 'Order Note - #{order_id}',
					'email_heading' => 'A note has been added to your order',
					'email_content' => '<p>Hi {user_display_name},</p><p>The following note has been added to your order:</p><blockquote>{order_note}</blockquote><p><br></p><h2><strong><u>[Order #{order_id}]</u> ({order_created_date})</strong></h2><p><br></p><p>Order Items:</p><p>{order_items}</p><p>=============================================================</p><p>Order Totals:</p><p>{order_totals}</p><p><br></p><p>If you have questions or require more information, feel free to reach out.</p>',
				],
			],
			'order_refund'          => [
				'customer' => [
					'is_enable'     => true,
					'email_subject' => 'Order Refunded: #{order_id}',
					'email_heading' => 'Order Refunded',
					'email_content' => '<p>Hi {user_display_name},</p><p>Your order(#{order_id}) has been refunded. There are more details below for your reference:</p><p><br></p><p>Order Items:</p><p>{order_items}</p><p>=============================================================</p><p>Order Totals:</p><p>{order_totals}</p><p><br></p><p>Refunds:</p><p>{order_refunds}</p><p><br></p><p>If you have questions or require more information, feel free to reach out.</p>',
				],
			],
			'new_user_notification' => [
				'customer' => [
					'is_enable'     => false,
					'email_subject' => '{store_name} — Welcome! Your account credentials',
					'email_heading' => 'Account created — here is your login details for {store_name}.',
					'email_content' => '<p>Hi <strong>{user_first_name} </strong>,<p>Your account has been created for faster checkout and order tracking.<p><strong>Email: </strong>{user_email}<p><strong>Password: </strong>{user_password}<p><strong>Login URL: </strong>{sign_in_url}<p><p><strong>Security note: </strong>This password is temporary. Please change it immediately after signing in. If you didn\'t create this account, contact us at <a href=mailto:support@store.example.com rel="noopener noreferrer" target=_blank>support@store.example.com </a>.<p>If you have questions or require more information, feel free to reach out.',
				],
			],
			'abandoned_cart_first'  => [
				'customer' => [
					'is_enable'     => true,
					'email_subject' => 'Your Cart Awaits – Complete Your Purchase!',
					'email_heading' => 'Your Cart Awaits – Complete Your Purchase!',
					// {order_billing_first_name} is supported here via an alias
					// in AbstractAbandonedCartMail::get_abc_email_body() — keeps
					// existing customised templates (saved against the original
					// default) rendering with the customer's first name.
					'email_content' => '<p>Hi {order_billing_first_name},</p><p>We noticed you left some items in your cart, and we want to make sure you don’t miss out on your favorites!</p><p><br></p><blockquote>{abandoned_cart_discount}</blockquote><p><br></p><p>{abandoned_cart_button}</p><p><br></p><p>Cart Items:</p><p>{cart_items}</p><p>=============================================================</p><p>Cart Totals:</p><p>{cart_totals}</p><p><br></p><p><br></p><p>If you have questions or require more information, feel free to reach out.</p>',
				],
			],
			'abandoned_cart_second' => [
				'customer' => [
					'is_enable'     => true,
					'email_subject' => 'Complete Your Purchase Before the Stock Out!',
					'email_heading' => 'Complete Your Purchase Before the Stock Out!',
					'email_content' => '<p>Hi {user_display_name},</p><p>We noticed you were just one step away from scoring some awesome products! We’ve saved your order, so all you need to do is finish checking out.</p><p><br></p><blockquote>{abandoned_cart_discount}</blockquote><p><br></p><p>{abandoned_cart_button}</p><p><br></p><p>Cart Items:</p><p>{cart_items}</p><p>=============================================================</p><p>Cart Totals:</p><p>{cart_totals}</p><p><br></p><p><br></p><p>If you have questions or require more information, feel free to reach out.</p>',
				],
			],
			'abandoned_cart_third'  => [
				'customer' => [
					'is_enable'     => true,
					'email_subject' => 'Did You Forget About Your Cart?',
					'email_heading' => 'Did You Forget About Your Cart?',
					'email_content' => '<p>Hi {user_display_name},</p><p>Looks like you left something behind… maybe you just got distracted by all the fun stuff we offer? No worries! Your cart is still here, waiting for you!</p><p><br></p><blockquote>{abandoned_cart_discount}</blockquote><p><br></p><p>{abandoned_cart_button}</p><p><br></p><p>Cart Items:</p><p>{cart_items}</p><p>=============================================================</p><p>Cart Totals:</p><p>{cart_totals}</p><p><br></p><p><br></p><p>If you have questions or require more information, feel free to reach out.</p>',
				],
			],
		] ) );
	}

	/**
	 * Seed an empty `email_content_tree` default wherever an `email_content`
	 * default exists. Keeps the block-editor tree key present through the
	 * deep-merge in {@see self::save_settings()} and returned by
	 * {@see HelperAddon::get_setting()}. Mirrors the fields whitelist injection
	 * in the AJAX handler.
	 *
	 * @param array $data Default settings data.
	 * @return array
	 */
	protected static function inject_content_tree_defaults( array $data ): array {
		foreach ( $data as $key => $value ) {
			if ( is_array( $value ) ) {
				$data[ $key ] = self::inject_content_tree_defaults( $value );
			} elseif ( 'email_content' === $key && ! isset( $data['email_content_tree'] ) ) {
				$data['email_content_tree'] = '';
			}
		}

		return $data;
	}

	public static function save_settings( $form_data = false ) {
		$default_data  = self::get_settings_default_data();
		$saved_data    = self::get_settings_saved_data();

		// Deep merge instead of shallow wp_parse_args(): each email key
		// (e.g. abandoned_cart_first) holds a nested structure like
		// [ 'customer' => [ 'is_enable' => true, 'email_subject' => '...', ... ] ].
		// Shallow merge meant a saved row missing one nested key (most
		// commonly `is_enable` when the option was written by an older
		// plugin version that didn't include that email yet) was returned
		// AS-IS to HelperAddon::get_setting(), and downstream `send_email()`
		// short-circuited because `! empty($settings['is_enable'])` was false.
		$settings_data = self::deep_merge_defaults( $default_data, $saved_data );

		if ( $form_data ) {
			$settings_data = self::deep_merge_defaults( $settings_data, $form_data );
		}

		$settings_data = self::migrate_threading_safe_subjects( $settings_data );

		update_option( 'storeengine_email_settings', wp_json_encode( $settings_data ), false );
	}

	/**
	 * Recursive defaults-aware merge. Each key in $override replaces the
	 * value in $base; when both sides are associative arrays we recurse so
	 * nested defaults survive a partial save (e.g. saved `email_subject`
	 * overrides default while a missing nested `is_enable` inherits the
	 * default `true`).
	 */
	protected static function deep_merge_defaults( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			if ( is_array( $value ) && isset( $base[ $key ] ) && is_array( $base[ $key ] ) ) {
				$base[ $key ] = self::deep_merge_defaults( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}

		return $base;
	}

	/**
	 * One-shot upgrade for sites that activated the email addon before
	 * subjects included {order_id}. Without a unique token, Gmail/Outlook
	 * thread every "New order" notification into one conversation in the
	 * admin's inbox.
	 *
	 * We only overwrite if the saved subject *exactly* matches the historic
	 * default — that's the strong signal the admin never customized it.
	 * Anything else is left alone. Idempotent: once flipped to the new
	 * value, future runs no longer match the old default and bail out.
	 *
	 * @param array $settings_data
	 * @return array
	 */
	protected static function migrate_threading_safe_subjects( array $settings_data ): array {
		$migrations = [
			[ 'order_confirmation', 'admin', 'A new order placed', 'New order #{order_id} placed' ],
			[ 'order_confirmation', 'customer', 'Your order has been placed', 'Your order #{order_id} has been placed' ],
			[ 'order_status', 'customer', 'Your order status has been changed', 'Your order #{order_id} status has been changed' ],
		];

		foreach ( $migrations as [ $template, $channel, $old, $new ] ) {
			if ( isset( $settings_data[ $template ][ $channel ]['email_subject'] )
				&& $old === $settings_data[ $template ][ $channel ]['email_subject'] ) {
				$settings_data[ $template ][ $channel ]['email_subject'] = $new;
			}
		}

		return $settings_data;
	}
}

