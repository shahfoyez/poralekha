<?php

namespace StoreEngine;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Shortcode {

	public static function init() {
		$self = new self();
		$self->dispatch_shortcode();
		$self->dispatch_shortcode_blocks();
	}

	/**
	 * Boot the shortcode → block bridge: the generic `storeengine/shortcode` block
	 * that renders any registered shortcode with real editor controls (works with
	 * only StoreEngine active), and register the core StoreEngine shortcodes as
	 * block descriptors. Referencing the Bridge class autoloads its file, which
	 * also defines the global storeengine_register_shortcode_block() API.
	 */
	public function dispatch_shortcode_blocks() {
		Blocks\Bridge::init();
		Blocks\CoreShortcodes::register();
		// Always-on "Restrict This Block" panel — shows the membership access
		// controls when the addon is active, or an activation promo when it isn't.
		Blocks\BlockVisibility::init();
	}

	public function dispatch_shortcode() {
		new Shortcode\Products();
		new Shortcode\ProductsSidebar();
		new Shortcode\ProductsArchive();
		new Shortcode\Login();
		new Shortcode\FrontendDashboard();
		new Shortcode\ArchiveHeaderFilter();
		new Shortcode\SingleProduct();
		new Shortcode\ProceedToCheckout();
		new Shortcode\ContinueShopping();
		new Shortcode\CartListTable();
		new Shortcode\CartSubTotalTable();
		new Shortcode\ApplyCouponForm();
		new Shortcode\CheckoutForm();
		new Shortcode\OrderSummary();
		new Shortcode\ThankyouOrderInfo();
		new Shortcode\ThankyouPaymentInstructions();
		new Shortcode\OrderDetails();
		new Shortcode\OrderDownloads();
		new Shortcode\OrderBillingAddress();
		new Shortcode\OrderShippingAddress();
		new Shortcode\ProductDescription();
		new Shortcode\AddToCart();
		new Shortcode\MiniCart();
		new Shortcode\SingleProductCartNotice();
		new Shortcode\SingleProductGallery();
		new Shortcode\SingleProductSummary();
		new Shortcode\SingleProductDescription();
		new Shortcode\SingleProductComments();
		new Shortcode\SingleProductReviews();
		new Shortcode\SingleProductFaq();
	}
}
